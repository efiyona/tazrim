<?php
declare(strict_types=1);

/**
 * שרת נתונים פנימי עבור סוכן ה-AI של פאנל הניהול.
 *
 * לא endpoint חשוף — נקרא מ-stream_message.php כפונקציה כאשר הבינה מחזירה [[DATA_QUERY]].
 * כל הקריאות כאן הן קריאה בלבד (SELECT / SHOW COLUMNS).
 */

require_once __DIR__ . '/../services/agent_schema.php';

if (!function_exists('admin_ai_agent_data_error')) {
    function admin_ai_agent_data_error(string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'error' => $message], $extra);
    }
}

if (!function_exists('admin_ai_agent_data_ok')) {
    function admin_ai_agent_data_ok(array $data): array
    {
        return ['ok' => true] + $data;
    }
}

if (!function_exists('admin_ai_agent_data_allowed_column')) {
    function admin_ai_agent_data_allowed_column(string $table, string $col): bool
    {
        if (in_array($col, admin_ai_agent_global_blocked_fields(), true)) {
            return false;
        }
        $cfg = admin_ai_agent_get_table_config($table);
        if (!$cfg) {
            return false;
        }
        return isset($cfg['fields'][$col]);
    }
}

if (!function_exists('admin_ai_agent_data_filter_columns')) {
    function admin_ai_agent_data_filter_columns(string $table, array $columns): array
    {
        $out = [];
        foreach ($columns as $c) {
            $c = (string) $c;
            if ($c === '' || $c === '*') {
                continue;
            }
            if (admin_ai_agent_data_allowed_column($table, $c)) {
                $out[] = $c;
            }
        }
        return $out;
    }
}

if (!function_exists('admin_ai_agent_data_safe_select_list')) {
    function admin_ai_agent_data_safe_select_list(string $table, array $columns = []): string
    {
        $filtered = admin_ai_agent_data_filter_columns($table, $columns);
        if (empty($filtered)) {
            $cfg = admin_ai_agent_get_table_config($table);
            $all = $cfg ? array_keys($cfg['fields']) : [];
            $filtered = array_values(array_diff($all, admin_ai_agent_global_blocked_fields()));
        }
        if (empty($filtered)) {
            return '*';
        }
        return '`' . implode('`, `', $filtered) . '`';
    }
}

if (!function_exists('admin_ai_agent_data_build_where')) {
    /**
     * בונה WHERE מ-array של ['column' => value] או ['column' => ['op' => '=', 'value' => v]].
     * מחזיר [$sql, $types, $values, $error|null].
     */
    function admin_ai_agent_data_build_where(string $table, array $where): array
    {
        if (empty($where)) {
            return ['', '', [], null];
        }
        $parts = [];
        $types = '';
        $values = [];
        foreach ($where as $col => $v) {
            $col = (string) $col;
            if (!admin_ai_agent_data_allowed_column($table, $col)) {
                return ['', '', [], 'invalid_where_column:' . $col];
            }
            $op = '=';
            $val = $v;
            if (is_array($v) && array_key_exists('op', $v)) {
                $op = strtoupper((string) $v['op']);
                $val = $v['value'] ?? null;
            }
            $allowedOps = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IS NULL', 'IS NOT NULL', 'IN'];
            if (!in_array($op, $allowedOps, true)) {
                return ['', '', [], 'invalid_op:' . $op];
            }
            if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $parts[] = "`{$col}` {$op}";
                continue;
            }
            if ($op === 'IN') {
                if (!is_array($val) || empty($val)) {
                    return ['', '', [], 'in_requires_array'];
                }
                $placeholders = [];
                foreach ($val as $item) {
                    $placeholders[] = '?';
                    $types .= is_int($item) ? 'i' : 's';
                    $values[] = $item;
                }
                $parts[] = "`{$col}` IN (" . implode(',', $placeholders) . ')';
                continue;
            }
            $parts[] = "`{$col}` {$op} ?";
            $types .= is_int($val) ? 'i' : 's';
            $values[] = $val;
        }
        return [' WHERE ' . implode(' AND ', $parts), $types, $values, null];
    }
}

if (!function_exists('admin_ai_agent_data_fetch_all')) {
    function admin_ai_agent_data_fetch_all(mysqli $conn, string $sql, string $types, array $values): array
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['error' => 'prepare_failed: ' . $conn->error, 'rows' => []];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['error' => 'execute_failed: ' . $err, 'rows' => []];
        }
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return ['error' => null, 'rows' => $rows];
    }
}

if (!function_exists('admin_ai_agent_data_decrypt_rows')) {
    function admin_ai_agent_data_decrypt_rows(string $table, array $rows): array
    {
        $map = admin_ai_agent_encrypt_map();
        if (!isset($map[$table])) {
            return $rows;
        }
        $cols = $map[$table];
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($cols as $c) {
                if (array_key_exists($c, $row) && function_exists('decryptBalance')) {
                    $row[$c] = decryptBalance($row[$c]);
                }
            }
        }
        unset($row);
        return $rows;
    }
}

if (!function_exists('admin_ai_chat_agent_query')) {
    /**
     * נקודת כניסה ראשית. מקבל בקשה מהבינה, מחזיר תוצאה.
     *
     * $request['action'] in: list | get | count | search | describe | query
     */
    function admin_ai_chat_agent_query(mysqli $conn, array $request): array
    {
        $action = strtolower((string) ($request['action'] ?? ''));
        $table = (string) ($request['table'] ?? '');

        if ($action !== 'query' && !admin_ai_agent_can_read($table)) {
            return admin_ai_agent_data_error('table_not_readable', ['table' => $table]);
        }

        switch ($action) {
            case 'list':
                return admin_ai_agent_data_list($conn, $table, $request);
            case 'get':
                return admin_ai_agent_data_get($conn, $table, $request);
            case 'count':
                return admin_ai_agent_data_count($conn, $table, $request);
            case 'search':
                return admin_ai_agent_data_search($conn, $table, $request);
            case 'describe':
                return admin_ai_agent_data_describe($table);
            case 'query':
                return admin_ai_agent_data_raw_query($conn, $request);
            default:
                return admin_ai_agent_data_error('unknown_action', ['action' => $action]);
        }
    }
}

if (!function_exists('admin_ai_agent_data_list')) {
    function admin_ai_agent_data_list(mysqli $conn, string $table, array $req): array
    {
        $columns = isset($req['columns']) && is_array($req['columns']) ? $req['columns'] : [];
        $where = isset($req['where']) && is_array($req['where']) ? $req['where'] : [];
        $orderBy = (string) ($req['order_by'] ?? 'id');
        $orderDir = strtoupper((string) ($req['order_dir'] ?? 'DESC'));
        $limit = max(1, min(100, (int) ($req['limit'] ?? 20)));
        $offset = max(0, (int) ($req['offset'] ?? 0));

        if (!admin_ai_agent_data_allowed_column($table, $orderBy)) {
            $orderBy = 'id';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $cols = admin_ai_agent_data_safe_select_list($table, $columns);
        [$whereSql, $types, $values, $err] = admin_ai_agent_data_build_where($table, $where);
        if ($err) {
            return admin_ai_agent_data_error($err);
        }

        $sql = "SELECT {$cols} FROM `{$table}`{$whereSql} ORDER BY `{$orderBy}` {$orderDir} LIMIT {$limit} OFFSET {$offset}";
        $res = admin_ai_agent_data_fetch_all($conn, $sql, $types, $values);
        if ($res['error']) {
            return admin_ai_agent_data_error($res['error']);
        }
        $rows = admin_ai_agent_data_decrypt_rows($table, $res['rows']);
        $rows = admin_ai_agent_sanitize_rows_for_output($rows);

        return admin_ai_agent_data_ok([
            'table' => $table,
            'count' => count($rows),
            'rows' => $rows,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}

if (!function_exists('admin_ai_agent_data_get')) {
    function admin_ai_agent_data_get(mysqli $conn, string $table, array $req): array
    {
        $id = (int) ($req['id'] ?? 0);
        if ($id <= 0) {
            return admin_ai_agent_data_error('missing_id');
        }
        $columns = isset($req['columns']) && is_array($req['columns']) ? $req['columns'] : [];
        $cols = admin_ai_agent_data_safe_select_list($table, $columns);
        $sql = "SELECT {$cols} FROM `{$table}` WHERE `id` = ? LIMIT 1";
        $res = admin_ai_agent_data_fetch_all($conn, $sql, 'i', [$id]);
        if ($res['error']) {
            return admin_ai_agent_data_error($res['error']);
        }
        if (empty($res['rows'])) {
            return admin_ai_agent_data_ok(['table' => $table, 'row' => null, 'found' => false]);
        }
        $rows = admin_ai_agent_data_decrypt_rows($table, $res['rows']);
        $rows = admin_ai_agent_sanitize_rows_for_output($rows);

        return admin_ai_agent_data_ok([
            'table' => $table,
            'row' => $rows[0] ?? null,
            'found' => true,
        ]);
    }
}

if (!function_exists('admin_ai_agent_data_count')) {
    function admin_ai_agent_data_count(mysqli $conn, string $table, array $req): array
    {
        $where = isset($req['where']) && is_array($req['where']) ? $req['where'] : [];
        [$whereSql, $types, $values, $err] = admin_ai_agent_data_build_where($table, $where);
        if ($err) {
            return admin_ai_agent_data_error($err);
        }
        $sql = "SELECT COUNT(*) AS c FROM `{$table}`{$whereSql}";
        $res = admin_ai_agent_data_fetch_all($conn, $sql, $types, $values);
        if ($res['error']) {
            return admin_ai_agent_data_error($res['error']);
        }
        $c = (int) ($res['rows'][0]['c'] ?? 0);
        return admin_ai_agent_data_ok(['table' => $table, 'count' => $c]);
    }
}

if (!function_exists('admin_ai_agent_data_search')) {
    function admin_ai_agent_data_search(mysqli $conn, string $table, array $req): array
    {
        $term = trim((string) ($req['search'] ?? ''));
        if ($term === '') {
            return admin_ai_agent_data_error('missing_search');
        }
        $searchCols = isset($req['columns']) && is_array($req['columns']) ? $req['columns'] : [];
        $searchCols = admin_ai_agent_data_filter_columns($table, $searchCols);
        if (empty($searchCols)) {
            $cfg = admin_ai_agent_get_table_config($table);
            $searchCols = [];
            foreach (($cfg['fields'] ?? []) as $fname => $fdef) {
                $t = (string) ($fdef['type'] ?? '');
                if (in_array($t, ['string', 'text'], true) && !in_array($fname, admin_ai_agent_global_blocked_fields(), true)) {
                    $searchCols[] = $fname;
                }
            }
            $searchCols = array_slice($searchCols, 0, 6);
        }
        if (empty($searchCols)) {
            return admin_ai_agent_data_error('no_searchable_columns');
        }

        $limit = max(1, min(100, (int) ($req['limit'] ?? 20)));
        $parts = [];
        $types = '';
        $values = [];
        $like = '%' . $term . '%';
        foreach ($searchCols as $c) {
            $parts[] = "`{$c}` LIKE ?";
            $types .= 's';
            $values[] = $like;
        }
        $whereSql = ' WHERE ' . implode(' OR ', $parts);
        $cols = admin_ai_agent_data_safe_select_list($table, $req['return_columns'] ?? []);
        $sql = "SELECT {$cols} FROM `{$table}`{$whereSql} ORDER BY `id` DESC LIMIT {$limit}";
        $res = admin_ai_agent_data_fetch_all($conn, $sql, $types, $values);
        if ($res['error']) {
            return admin_ai_agent_data_error($res['error']);
        }
        $rows = admin_ai_agent_data_decrypt_rows($table, $res['rows']);
        $rows = admin_ai_agent_sanitize_rows_for_output($rows);

        return admin_ai_agent_data_ok([
            'table' => $table,
            'search' => $term,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    }
}

if (!function_exists('admin_ai_agent_data_describe')) {
    function admin_ai_agent_data_describe(string $table): array
    {
        $cfg = admin_ai_agent_get_table_config($table);
        if (!$cfg) {
            return admin_ai_agent_data_error('unknown_table', ['table' => $table]);
        }
        $fields = [];
        foreach (($cfg['fields'] ?? []) as $name => $def) {
            if (in_array($name, admin_ai_agent_global_blocked_fields(), true)) {
                continue;
            }
            $fields[$name] = [
                'type' => $def['type'] ?? 'string',
                'desc' => $def['desc'] ?? '',
                'readonly' => !empty($def['readonly']),
                'enum' => $def['enum'] ?? null,
            ];
        }
        return admin_ai_agent_data_ok([
            'table' => $table,
            'description' => $cfg['description'] ?? '',
            'can_read' => !empty($cfg['read']),
            'can_write' => !empty($cfg['write']),
            'dangerous' => !empty($cfg['dangerous']),
            'fields' => $fields,
        ]);
    }
}

if (!function_exists('admin_ai_agent_data_raw_query')) {
    /**
     * שאילתת SELECT גולמית. חסום INSERT/UPDATE/DELETE ו-DDL.
     * נועד לשאלות מורכבות שלא ניתן לבטא ב-list/search.
     */
    function admin_ai_agent_data_raw_query(mysqli $conn, array $req): array
    {
        $sql = trim((string) ($req['sql'] ?? ''));
        if ($sql === '') {
            return admin_ai_agent_data_error('missing_sql');
        }
        if (!preg_match('/^\s*SELECT\s/i', $sql)) {
            return admin_ai_agent_data_error('only_select_allowed');
        }
        $forbidden = ['insert ', 'update ', 'delete ', 'drop ', 'alter ', 'truncate ', 'replace ', 'grant ', 'create ', ';--', '/*'];
        $lower = strtolower($sql);
        foreach ($forbidden as $bad) {
            if (strpos($lower, $bad) !== false) {
                return admin_ai_agent_data_error('forbidden_token', ['token' => trim($bad)]);
            }
        }
        if (substr_count(trim(rtrim($sql, ';')), ';') > 0) {
            return admin_ai_agent_data_error('multiple_statements_not_allowed');
        }
        $params = isset($req['params']) && is_array($req['params']) ? array_values($req['params']) : [];
        if (!empty($params) && substr_count($sql, '?') !== count($params)) {
            return admin_ai_agent_data_error('params_count_mismatch');
        }
        if (!preg_match('/\blimit\s+\d+/i', $sql)) {
            $sql .= ' LIMIT 100';
        }
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : 's';
        }
        $res = admin_ai_agent_data_fetch_all($conn, $sql, $types, $params);
        if ($res['error']) {
            return admin_ai_agent_data_error($res['error']);
        }
        $rows = admin_ai_agent_sanitize_rows_for_output($res['rows']);
        return admin_ai_agent_data_ok([
            'count' => count($rows),
            'rows' => $rows,
            'sql_executed' => true,
        ]);
    }
}

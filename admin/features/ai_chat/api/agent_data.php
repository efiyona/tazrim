<?php
declare(strict_types=1);

/**
 * שרת נתונים פנימי עבור סוכן ה-AI של פאנל הניהול.
 *
 * לא endpoint חשוף — נקרא מ-stream_message.php כפונקציה כאשר הבינה מחזירה [[DATA_QUERY]].
 * כל הקריאות כאן הן קריאה בלבד (SELECT/SHOW/DESCRIBE/EXPLAIN).
 */

require_once __DIR__ . '/../services/agent_schema.php';
require_once __DIR__ . '/../services/agent_project_files.php';

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

if (!function_exists('admin_ai_agent_data_where_sql_ops')) {
    /** @return list<string> */
    function admin_ai_agent_data_where_sql_ops(): array
    {
        return ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IS NULL', 'IS NOT NULL', 'IN', 'BETWEEN'];
    }
}

if (!function_exists('admin_ai_agent_data_is_stray_range_operator_json_key')) {
    /** מפתח JSON כמו "<=" או ">=" שלא עמודה — טעות נפוצה של מודלים לטווח תאריכים. */
    function admin_ai_agent_data_is_stray_range_operator_json_key(string $key): bool
    {
        $k = strtoupper(trim($key));

        return $k === '<=' || $k === '>=';
    }
}

if (!function_exists('admin_ai_agent_data_where_key_looks_like_operator')) {
    function admin_ai_agent_data_where_key_looks_like_operator(string $key): bool
    {
        if (admin_ai_agent_data_is_stray_range_operator_json_key($key)) {
            return false;
        }
        $k = strtoupper(trim($key));

        return in_array($k, ['=', '!=', '<', '>', 'LIKE'], true);
    }
}

if (!function_exists('admin_ai_agent_data_bind_type_char')) {
    function admin_ai_agent_data_bind_type_char(mixed $v): string
    {
        return is_int($v) ? 'i' : 's';
    }
}

if (!function_exists('admin_ai_agent_data_parse_scalar_where_to_op_val')) {
    /**
     * ממפה ערך בודד של where (לעמודה ידועה) ל-op + ערך/ערכים.
     *
     * @return array{ok:true, op:string, val:mixed}|array{ok:false, error:string}
     */
    function admin_ai_agent_data_parse_scalar_where_to_op_val(mixed $v): array
    {
        if (is_array($v) && array_key_exists('op', $v)) {
            $op = strtoupper(trim((string) $v['op']));
            $val = $v['value'] ?? null;

            return ['ok' => true, 'op' => $op, 'val' => $val];
        }
        // קיצור נפוץ מהמודלים: ["<=", "2026-03-31"] במקום {"op":"<=","value":"..."}
        if (is_array($v) && !array_key_exists('op', $v) && count($v) === 2
            && is_string($v[0]) && !is_array($v[1])) {
            $maybeOp = strtoupper(trim($v[0]));
            if (in_array($maybeOp, ['=', '!=', '<', '>', '<=', '>=', 'LIKE'], true)) {
                return ['ok' => true, 'op' => $maybeOp, 'val' => $v[1]];
            }
        }
        if (is_array($v) && !array_key_exists('op', $v)) {
            return ['ok' => false, 'error' => 'where_value_array_requires_op_or_two_tuple'];
        }

        return ['ok' => true, 'op' => '=', 'val' => $v];
    }
}

if (!function_exists('admin_ai_agent_data_flatten_where_clauses')) {
    /**
     * הופך where לאיחוד של תנאים (עמודה, אופרטור, ערך) — תומך בכמה תנאים על אותה עמודה.
     *
     * צורות נתמכות:
     * - אובייקט מפתח=>ערך (legacy): {"user_id":2,"transaction_date":{"op":">=","value":"..."}}
     * - מערך תנאים: [{"column":"transaction_date","op":">=","value":"..."}, ...]
     *
     * @return array{ok:true, clauses:list<array{col:string, op:string, val:mixed}>}|array{ok:false, error:string}
     */
    function admin_ai_agent_data_flatten_where_clauses(string $table, array $where): array
    {
        $clauses = [];
        if ($where === []) {
            return ['ok' => true, 'clauses' => []];
        }

        if (function_exists('array_is_list') ? array_is_list($where) : array_keys($where) === range(0, count($where) - 1)) {
            foreach ($where as $item) {
                if (!is_array($item)) {
                    return ['ok' => false, 'error' => 'where_list_item_not_object'];
                }
                $col = trim((string) ($item['column'] ?? ''));
                if ($col === '') {
                    return ['ok' => false, 'error' => 'where_list_missing_column'];
                }
                if (!admin_ai_agent_data_allowed_column($table, $col)) {
                    return ['ok' => false, 'error' => 'invalid_where_column:' . $col];
                }
                $op = strtoupper(trim((string) ($item['op'] ?? '=')));
                $val = $item['value'] ?? null;
                $clauses[] = ['col' => $col, 'op' => $op, 'val' => $val];
            }

            return ['ok' => true, 'clauses' => $clauses];
        }

        $lastInequalityColumn = null;
        foreach ($where as $col => $v) {
            $col = (string) $col;
            if (admin_ai_agent_data_is_stray_range_operator_json_key($col)) {
                if ($lastInequalityColumn === null || $lastInequalityColumn === '') {
                    return ['ok' => false, 'error' => 'invalid_where_operator_as_key:' . $col];
                }
                if (!is_scalar($v) && $v !== null) {
                    return ['ok' => false, 'error' => 'invalid_where_range_operator_value'];
                }
                $op = strtoupper(trim($col));
                $clauses[] = ['col' => $lastInequalityColumn, 'op' => $op, 'val' => $v];

                continue;
            }
            if (admin_ai_agent_data_where_key_looks_like_operator($col)) {
                return ['ok' => false, 'error' => 'invalid_where_operator_as_key:' . $col];
            }
            if (!admin_ai_agent_data_allowed_column($table, $col)) {
                return ['ok' => false, 'error' => 'invalid_where_column:' . $col];
            }
            $parsed = admin_ai_agent_data_parse_scalar_where_to_op_val($v);
            if (!$parsed['ok']) {
                return ['ok' => false, 'error' => (string) $parsed['error']];
            }
            $clauses[] = ['col' => $col, 'op' => $parsed['op'], 'val' => $parsed['val']];
            if (in_array($parsed['op'], ['>', '>=', '<', '<='], true)) {
                $lastInequalityColumn = $col;
            } else {
                // אחרי = / BETWEEN / IN וכו' — לא מצמידים מפתח "<=" שגוי לעמודה הקודמת בטעות
                $lastInequalityColumn = null;
            }
        }

        return ['ok' => true, 'clauses' => $clauses];
    }
}

if (!function_exists('admin_ai_agent_data_build_where')) {
    /**
     * בונה WHERE מתנאים מנורמלים (AND).
     * מחזיר [$sql, $types, $values, $error|null].
     */
    function admin_ai_agent_data_build_where(string $table, array $where): array
    {
        $flat = admin_ai_agent_data_flatten_where_clauses($table, $where);
        if (!$flat['ok']) {
            return ['', '', [], (string) $flat['error']];
        }
        $normClauses = $flat['clauses'];
        if ($normClauses === []) {
            return ['', '', [], null];
        }
        $allowedOps = admin_ai_agent_data_where_sql_ops();
        $parts = [];
        $types = '';
        $values = [];
        foreach ($normClauses as $row) {
            $col = (string) $row['col'];
            $op = strtoupper((string) $row['op']);
            $val = $row['val'];
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
                    $types .= admin_ai_agent_data_bind_type_char($item);
                    $values[] = $item;
                }
                $parts[] = "`{$col}` IN (" . implode(',', $placeholders) . ')';
                continue;
            }
            if ($op === 'BETWEEN') {
                if (!is_array($val) || count($val) !== 2) {
                    return ['', '', [], 'between_requires_two_values'];
                }
                $parts[] = "`{$col}` BETWEEN ? AND ?";
                $types .= admin_ai_agent_data_bind_type_char($val[0]) . admin_ai_agent_data_bind_type_char($val[1]);
                $values[] = $val[0];
                $values[] = $val[1];
                continue;
            }
            $parts[] = "`{$col}` {$op} ?";
            $types .= admin_ai_agent_data_bind_type_char($val);
            $values[] = $val;
        }

        return [' WHERE ' . implode(' AND ', $parts), $types, $values, null];
    }
}

if (!function_exists('admin_ai_agent_data_query_error_coach_appendix')) {
    /**
     * טקסט קצר למודל כש-DATA_QUERY נכשל בגלל where — מפחית סבבי ניסוי שגויים.
     */
    function admin_ai_agent_data_query_error_coach_appendix(array $queryResult): string
    {
        if (($queryResult['ok'] ?? false) === true) {
            return '';
        }
        $err = (string) ($queryResult['error'] ?? '');
        if ($err === '' || !preg_match('/invalid_where|where_list_|where_value|between_requires|in_requires_array|^invalid_op:/u', $err)) {
            if ($err === 'only_read_queries_allowed') {
                return "\n\n[מערכת — השאילתה נדחתה כי מותרות רק שאילתות קריאה]\n"
                    . "- מותר: `SELECT`, `SHOW`, `DESCRIBE`/`DESC`, `EXPLAIN`.\n"
                    . "- אסור: `INSERT/UPDATE/DELETE/ALTER/DROP/CREATE/TRUNCATE` בתוך `DATA_QUERY`.\n"
                    . "- כדי לקבל שמות טבלאות במסד, השתמש ב:\n"
                    . "  `{\"action\":\"query\",\"sql\":\"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME\"}`\n"
                    . "- כדי לקבל שדות של טבלה ספציפית, השתמש ב:\n"
                    . "  `{\"action\":\"query\",\"sql\":\"SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION\",\"params\":[\"transactions\"]}`";
            }
            if (str_starts_with($err, 'forbidden_token')) {
                return "\n\n[מערכת — זוהה טוקן חסום ב-SQL]\n"
                    . "- הרץ משפט יחיד וללא הערות inline (`--`/`/* */`).\n"
                    . "- ב-`DATA_QUERY` מותרות רק שאילתות קריאה; שינויים במסד יש לבצע דרך `[[ACTION]]` מסוג מתאים.";
            }

            return '';
        }

        return "\n\n[מערכת — פורמט where לטווחים ולתאריכים]\n"
            . "- טווח על עמודה אחת: `{\"transaction_date\":{\"op\":\"BETWEEN\",\"value\":[\"2026-03-01\",\"2026-03-31\"]}}` בתוך `where`.\n"
            . "- או `where` כמערך תנאים (כמה פעמים אותה עמודה): `[{\"column\":\"user_id\",\"value\":2},{\"column\":\"transaction_date\",\"op\":\">=\",\"value\":\"2026-03-01\"},{\"column\":\"transaction_date\",\"op\":\"<=\",\"value\":\"2026-03-31\"}]`.\n"
            . "- קיצור לתנאי בודד: `\"transaction_date\": [\">=\", \"2026-03-01\"]` — זהה ל-`{\"op\":\">=\",\"value\":\"...\"}`.\n"
            . "- אין להשתמש במפתחות כמו `\"<=\"` בשורש ה-where; לקצה שני השתמש ב-BETWEEN או במערך תנאים.";
    }
}

if (!function_exists('admin_ai_agent_data_fetch_all')) {
    function admin_ai_agent_data_fetch_all(mysqli $conn, string $sql, string $types, array $values): array
    {
        // ב-PHP 8.1+ ברירת המחדל של mysqli_report היא לזרוק mysqli_sql_exception במקום להחזיר false.
        // עוטפים בתחרת try/catch כדי למנוע fatal מיידי כששדה/טבלה לא קיימים במסד (schema drift).
        try {
            $stmt = @$conn->prepare($sql);
        } catch (\Throwable $e) {
            return ['error' => 'prepare_failed: ' . $e->getMessage(), 'rows' => [], 'sql' => $sql];
        }
        if (!$stmt) {
            return ['error' => 'prepare_failed: ' . $conn->error, 'rows' => [], 'sql' => $sql];
        }
        try {
            if ($types !== '') {
                $stmt->bind_param($types, ...$values);
            }
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                return ['error' => 'execute_failed: ' . $err, 'rows' => [], 'sql' => $sql];
            }
            $res = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return ['error' => null, 'rows' => $rows];
        } catch (\Throwable $e) {
            try { $stmt->close(); } catch (\Throwable $ie) { /* noop */ }
            return ['error' => 'execute_failed: ' . $e->getMessage(), 'rows' => [], 'sql' => $sql];
        }
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
     * $request['action'] in: list | get | count | search | describe | query | file_read
     */
    function admin_ai_chat_agent_query(mysqli $conn, array $request): array
    {
        $action = strtolower((string) ($request['action'] ?? ''));
        $table = (string) ($request['table'] ?? '');

        if ($action === 'file_read') {
            $path = (string) ($request['path'] ?? '');
            return admin_ai_agent_project_file_read($path);
        }

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

        // חיפוש עם כמה מילים (כל הטבלאות): לכל מילה — OR על עמודות הטקסט; בין מילים — AND.
        // כך «שם פרטי משפחה» שמפוצל לעמודות, כתובת עם רווחים, או כמה טוקנים — מתנהגים עקבית בלי פלסטר לפי טבלה.
        $searchMultiWordMax = 8;
        if (preg_match('/\s/u', $term)) {
            $rawWords = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
            $words = [];
            foreach ($rawWords as $w) {
                $w = trim((string) $w);
                if ($w !== '') {
                    $words[] = $w;
                }
            }
            $words = array_slice($words, 0, $searchMultiWordMax);
            if (count($words) >= 2) {
                $groupSqls = [];
                $typesMw = '';
                $valuesMw = [];
                foreach ($words as $w) {
                    $likeW = '%' . $w . '%';
                    $wordParts = [];
                    foreach ($searchCols as $c) {
                        $wordParts[] = "`{$c}` LIKE ?";
                        $typesMw .= 's';
                        $valuesMw[] = $likeW;
                    }
                    $groupSqls[] = '(' . implode(' OR ', $wordParts) . ')';
                }
                $whereSqlMw = ' WHERE ' . implode(' AND ', $groupSqls);
                $colsMw = admin_ai_agent_data_safe_select_list($table, $req['return_columns'] ?? []);
                $sqlMw = "SELECT {$colsMw} FROM `{$table}`{$whereSqlMw} ORDER BY `id` DESC LIMIT {$limit}";
                $resMw = admin_ai_agent_data_fetch_all($conn, $sqlMw, $typesMw, $valuesMw);
                if ($resMw['error']) {
                    return admin_ai_agent_data_error($resMw['error']);
                }
                $rowsMw = admin_ai_agent_data_decrypt_rows($table, $resMw['rows']);
                $rowsMw = admin_ai_agent_sanitize_rows_for_output($rowsMw);

                return admin_ai_agent_data_ok([
                    'table' => $table,
                    'search' => $term,
                    'search_mode' => 'multi_word_and',
                    'count' => count($rowsMw),
                    'rows' => $rowsMw,
                ]);
            }
        }

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
                'encrypted' => !empty($def['encrypted']),
                'nullable' => !empty($def['nullable']),
                'enum' => $def['enum'] ?? null,
            ];
        }
        return admin_ai_agent_data_ok([
            'table' => $table,
            'source' => 'INFORMATION_SCHEMA (live)',
            'can_read' => !empty($cfg['read']),
            'can_write' => !empty($cfg['write']),
            'fields' => $fields,
        ]);
    }
}

if (!function_exists('admin_ai_agent_data_raw_query')) {
    /**
     * שאילתת קריאה גולמית. חסום INSERT/UPDATE/DELETE ו-DDL.
     * נועד לשאלות מורכבות שלא ניתן לבטא ב-list/search.
     */
    function admin_ai_agent_data_raw_query(mysqli $conn, array $req): array
    {
        $sql = trim((string) ($req['sql'] ?? ''));
        if ($sql === '') {
            return admin_ai_agent_data_error('missing_sql');
        }
        if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $sql)) {
            return admin_ai_agent_data_error('only_read_queries_allowed');
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
        // רק SELECT מאפשר הוספת LIMIT אוטומטית בצורה עקבית בכל דיאלקט.
        if (preg_match('/^\s*SELECT\b/i', $sql) && !preg_match('/\blimit\s+\d+/i', $sql)) {
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

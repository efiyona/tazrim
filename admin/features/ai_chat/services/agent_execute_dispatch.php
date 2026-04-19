<?php
declare(strict_types=1);

/**
 * ליבת ביצוע פעולות agent (CRUD + sql) — משותף ל-agent_execute.php.
 * מחזיר תשובת JSON כמערך + קוד HTTP, בלי exit.
 */

require_once __DIR__ . '/../api/agent_data.php';

if (!function_exists('admin_ai_agent_exec_log')) {
    function admin_ai_agent_exec_log(mysqli $conn, int $homeId, int $userId, string $text): void
    {
        try {
            $trimmed = function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : substr($text, 0, 500);
            $escaped = mysqli_real_escape_string($conn, $trimmed);
            @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$escaped}')");
        } catch (\Throwable $e) {
        }
    }
}

if (!function_exists('admin_ai_agent_exec_analyze_sql')) {
    /**
     * @return array{ok:true,sql:string,kind:string,verb:string,original:string}|array{ok:false,error:string}
     */
    function admin_ai_agent_exec_analyze_sql(string $sql): array
    {
        $original = $sql;
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'error' => 'empty_sql'];
        }
        $sql = rtrim($sql, ';');
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'error' => 'empty_sql'];
        }

        $sqlForCheck = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "''", $sql);
        $sqlForCheck = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', (string) $sqlForCheck);
        $sqlForCheck = preg_replace('/`[^`]*`/', '``', (string) $sqlForCheck);
        $sqlForCheck = preg_replace('/\/\*.*?\*\//s', '', (string) $sqlForCheck);
        $sqlForCheck = preg_replace('/--[^\n]*/', '', (string) $sqlForCheck);
        $sqlForCheck = preg_replace('/#[^\n]*/', '', (string) $sqlForCheck);
        if (strpos((string) $sqlForCheck, ';') !== false) {
            return ['ok' => false, 'error' => 'multiple_statements_not_allowed'];
        }

        $upper = strtoupper(ltrim((string) $sqlForCheck));

        $forbiddenPatterns = [
            '/\bDROP\s+DATABASE\b/' => 'drop_database_forbidden',
            '/\bDROP\s+SCHEMA\b/' => 'drop_schema_forbidden',
            '/\bCREATE\s+DATABASE\b/' => 'create_database_forbidden',
            '/\bCREATE\s+SCHEMA\b/' => 'create_schema_forbidden',
            '/\bCREATE\s+USER\b/' => 'user_mgmt_forbidden',
            '/\bDROP\s+USER\b/' => 'user_mgmt_forbidden',
            '/\bGRANT\b/' => 'grant_forbidden',
            '/\bREVOKE\b/' => 'revoke_forbidden',
            '/\bSET\s+PASSWORD\b/' => 'set_password_forbidden',
            '/\bALTER\s+USER\b/' => 'alter_user_forbidden',
            '/\bLOAD\s+DATA\b/' => 'load_data_forbidden',
            '/\bINTO\s+OUTFILE\b/' => 'into_outfile_forbidden',
            '/\bINTO\s+DUMPFILE\b/' => 'into_dumpfile_forbidden',
            '/\bSLEEP\s*\(/' => 'sleep_forbidden',
            '/\bBENCHMARK\s*\(/' => 'benchmark_forbidden',
        ];
        foreach ($forbiddenPatterns as $pattern => $code) {
            if (preg_match($pattern, $upper)) {
                return ['ok' => false, 'error' => $code];
            }
        }

        $first = preg_match('/^[A-Z]+/', $upper, $m) ? $m[0] : '';
        $kind = 'other';
        $dml = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'];
        $ddl = ['CREATE', 'ALTER', 'DROP', 'RENAME', 'TRUNCATE'];
        if (in_array($first, $dml, true)) {
            $kind = 'dml';
        } elseif (in_array($first, $ddl, true)) {
            $kind = 'ddl';
        } elseif ($first === 'SELECT' || $first === 'SHOW' || $first === 'DESCRIBE' || $first === 'DESC' || $first === 'EXPLAIN') {
            $kind = 'read';
        } elseif ($first === 'SET') {
            return ['ok' => false, 'error' => 'set_statement_not_allowed'];
        } else {
            return ['ok' => false, 'error' => 'unsupported_statement:' . $first];
        }

        return [
            'ok' => true,
            'sql' => $sql,
            'kind' => $kind,
            'verb' => $first,
            'original' => $original,
        ];
    }
}

if (!function_exists('admin_ai_agent_dispatch_execute_payload')) {
    /**
     * @param array<string, mixed> $payload כמו agent_execute: action, table, chat_id, proposed_at, id?, data?, sql?, kind?
     * @return array{http:int, payload:array<string, mixed>}
     */
    function admin_ai_agent_dispatch_execute_payload(
        mysqli $conn,
        int $homeId,
        int $userId,
        int $chatId,
        array $payload
    ): array {
        $action = strtolower((string) ($payload['action'] ?? ''));
        $table = (string) ($payload['table'] ?? '');
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $rowId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $rawSql = (string) ($payload['sql'] ?? '');
        $proposedAtMs = isset($payload['proposed_at']) ? (int) $payload['proposed_at'] : 0;

        if (!in_array($action, ['create', 'update', 'delete', 'sql', 'push_broadcast'], true)) {
            return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'invalid_action', 'action' => $action]];
        }

        $ACTION_PROPOSAL_TTL_MS = 5 * 60 * 1000;
        $CLOCK_SKEW_TOLERANCE_MS = 2 * 60 * 1000;
        $nowMs = (int) round(microtime(true) * 1000);
        if ($proposedAtMs <= 0) {
            admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent ACTION NO_TIMESTAMP action=' . $action . ' chat=' . $chatId . ' — stale client cache?');
        } else {
            $ageMs = $nowMs - $proposedAtMs;
            if ($ageMs > $ACTION_PROPOSAL_TTL_MS) {
                $ageSec = (int) round($ageMs / 1000);
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent ACTION EXPIRED action=' . $action . ' age_sec=' . $ageSec . ' chat=' . $chatId);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'תוקף ההצעה פג (מעל 5 דקות). בקש מהסוכן להציע את הפעולה מחדש.',
                        'reason' => 'proposal_expired',
                        'age_seconds' => $ageSec,
                    ],
                ];
            }
            if ($ageMs < -$CLOCK_SKEW_TOLERANCE_MS) {
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'חותמת זמן לא תקינה בהצעה. רענן את הדף ונסה שוב.',
                        'reason' => 'future_proposal_timestamp',
                    ],
                ];
            }
        }

        if ($action === 'push_broadcast') {
            if (!function_exists('tazrim_admin_push_broadcast_execute')) {
                require_once dirname(__DIR__, 3) . '/includes/helpers.php';
            }
            $title = trim((string) ($payload['title'] ?? ''));
            $bodyText = trim((string) ($payload['body'] ?? ''));
            $link = trim((string) ($payload['link'] ?? '/'));
            if ($link === '') {
                $link = '/';
            }
            $target = strtolower((string) ($payload['target'] ?? 'all'));
            if ($target !== 'all' && $target !== 'homes') {
                return [
                    'http' => 400,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'יעד שידור לא תקין (צפוי all או homes).',
                        'action' => 'push_broadcast',
                    ],
                ];
            }
            $delivery = strtolower((string) ($payload['delivery'] ?? 'push'));
            if (!in_array($delivery, ['push', 'bell', 'both'], true)) {
                return [
                    'http' => 400,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'סוג משלוח לא תקין (צפוי push, bell או both).',
                        'action' => 'push_broadcast',
                    ],
                ];
            }
            $homeIds = [];
            if (isset($payload['home_ids']) && is_array($payload['home_ids'])) {
                foreach ($payload['home_ids'] as $rid) {
                    $n = (int) $rid;
                    if ($n > 0) {
                        $homeIds[] = $n;
                    }
                }
            }

            admin_ai_agent_exec_log(
                $conn,
                $homeId,
                $userId,
                'Admin AI Agent PUSH_BROADCAST target=' . $target . ' delivery=' . $delivery . ' homes=' . count($homeIds) . ' chat=' . $chatId
            );

            $exec = tazrim_admin_push_broadcast_execute($conn, [
                'title' => $title,
                'body' => $bodyText,
                'link' => $link,
                'target' => $target,
                'delivery' => $delivery,
                'home_ids' => $homeIds,
            ]);

            if (!$exec['ok']) {
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'message' => $exec['message'],
                        'action' => 'push_broadcast',
                    ],
                ];
            }

            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'push_broadcast',
                    'message' => $exec['message'],
                    'homes_count' => (int) ($exec['homes_count'] ?? 0),
                ],
            ];
        }

        if ($action === 'sql') {
            if ($rawSql === '') {
                return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'missing_sql']];
            }
            $analyzed = admin_ai_agent_exec_analyze_sql($rawSql);
            if (!$analyzed['ok']) {
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL REJECTED: ' . $analyzed['error'] . ' | ' . substr($rawSql, 0, 300) . ' | chat=' . $chatId);

                return [
                    'http' => 400,
                    'payload' => ['status' => 'error', 'message' => 'sql_rejected', 'reason' => $analyzed['error']],
                ];
            }

            $safeSql = $analyzed['sql'];
            $verb = $analyzed['verb'];
            $kind = $analyzed['kind'];

            admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL ATTEMPT verb=' . $verb . ' kind=' . $kind . ' chat=' . $chatId . ' sql=' . substr($safeSql, 0, 400));

            $startedAt = microtime(true);
            $queryResult = false;
            $caughtErr = '';
            try {
                $queryResult = @mysqli_query($conn, $safeSql);
            } catch (\Throwable $e) {
                $caughtErr = $e->getMessage();
                $queryResult = false;
            }
            $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($queryResult === false) {
                $err = $caughtErr !== '' ? $caughtErr : (string) mysqli_error($conn);
                if ($err === '') {
                    $err = 'שגיאה בלתי-ידועה במסד הנתונים';
                }
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL FAILED verb=' . $verb . ' err=' . substr($err, 0, 200) . ' chat=' . $chatId);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'ביצוע ה-SQL נכשל: ' . $err,
                        'detail' => $err,
                        'verb' => $verb,
                        'kind' => $kind,
                    ],
                ];
            }

            $affected = mysqli_affected_rows($conn);
            $insertId = mysqli_insert_id($conn);

            $rowsReturned = null;
            if ($queryResult instanceof mysqli_result) {
                $rowsReturned = mysqli_num_rows($queryResult);
                mysqli_free_result($queryResult);
            }

            admin_ai_agent_exec_log(
                $conn,
                $homeId,
                $userId,
                'Admin AI Agent SQL OK verb=' . $verb . ' kind=' . $kind . ' affected=' . $affected . ' rows=' . ((string) $rowsReturned) . ' ms=' . $elapsedMs . ' chat=' . $chatId
            );

            $msgParts = ['SQL הורץ בהצלחה (' . $verb . ')'];
            if ($kind === 'ddl') {
                $msgParts[] = '— שינוי מבנה נרשם';
            } elseif ($affected >= 0 && in_array($verb, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) {
                $msgParts[] = '— שורות מושפעות: ' . $affected;
            }
            if ($rowsReturned !== null) {
                $msgParts[] = '— שורות שהוחזרו: ' . $rowsReturned;
            }

            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'sql',
                    'verb' => $verb,
                    'kind' => $kind,
                    'affected' => (int) $affected,
                    'insert_id' => $insertId > 0 ? (int) $insertId : null,
                    'rows_returned' => $rowsReturned,
                    'elapsed_ms' => $elapsedMs,
                    'message' => implode(' ', $msgParts),
                ],
            ];
        }

        if (!admin_ai_agent_can_write($table)) {
            return ['http' => 403, 'payload' => ['status' => 'error', 'message' => 'table_not_writable', 'table' => $table]];
        }

        $cfg = admin_ai_agent_get_table_config($table);
        $fieldDefs = $cfg['fields'] ?? [];

        $sanitizedData = [];
        foreach ($data as $col => $val) {
            $col = (string) $col;
            if (!admin_ai_agent_is_field_writable($table, $col)) {
                return [
                    'http' => 403,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'field_not_writable',
                        'table' => $table,
                        'field' => $col,
                    ],
                ];
            }
            $def = $fieldDefs[$col] ?? [];
            $type = (string) ($def['type'] ?? 'string');
            if (isset($def['enum']) && is_array($def['enum']) && $val !== null && !in_array((string) $val, $def['enum'], true)) {
                return [
                    'http' => 400,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'invalid_enum_value',
                        'field' => $col,
                        'allowed' => $def['enum'],
                    ],
                ];
            }
            if ($type === 'bool' && $val !== null) {
                $val = ((int) (bool) $val);
            }
            if ($type === 'int' && $val !== null && $val !== '') {
                $val = (int) $val;
            }
            $sanitizedData[$col] = $val;
        }

        $sanitizedData = admin_ai_agent_encrypt_write_payload($table, $sanitizedData);

        switch ($action) {
            case 'create':
                if (empty($sanitizedData)) {
                    return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'empty_data']];
                }
                $cols = array_keys($sanitizedData);
                $placeholders = array_fill(0, count($cols), '?');
                $sqlIns = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $conn->prepare($sqlIns);
                if (!$stmt) {
                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error]];
                }
                $types = '';
                $values = [];
                foreach ($sanitizedData as $c => $v) {
                    $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
                    $values[] = $v;
                }
                $stmt->bind_param($types, ...$values);
                if (!$stmt->execute()) {
                    $err = $stmt->error;
                    $stmt->close();

                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'execute_failed', 'detail' => $err]];
                }
                $insertId = $stmt->insert_id;
                $stmt->close();
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent CREATE ' . $table . ' id=' . $insertId . ' chat=' . $chatId);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'success',
                        'action' => 'create',
                        'table' => $table,
                        'id' => (int) $insertId,
                        'affected' => 1,
                        'message' => 'הרשומה נוצרה בהצלחה',
                    ],
                ];

            case 'update':
                if ($rowId <= 0) {
                    return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'missing_id']];
                }
                if (empty($sanitizedData)) {
                    return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'empty_data']];
                }
                $setParts = [];
                $types = '';
                $values = [];
                foreach ($sanitizedData as $c => $v) {
                    $setParts[] = "`{$c}` = ?";
                    $types .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
                    $values[] = $v;
                }
                $types .= 'i';
                $values[] = $rowId;
                $sqlUp = 'UPDATE `' . $table . '` SET ' . implode(', ', $setParts) . ' WHERE `id` = ?';
                $stmt = $conn->prepare($sqlUp);
                if (!$stmt) {
                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error]];
                }
                $stmt->bind_param($types, ...$values);
                if (!$stmt->execute()) {
                    $err = $stmt->error;
                    $stmt->close();

                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'execute_failed', 'detail' => $err]];
                }
                $affected = $stmt->affected_rows;
                $stmt->close();
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent UPDATE ' . $table . ' id=' . $rowId . ' fields=' . implode(',', array_keys($sanitizedData)) . ' chat=' . $chatId);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'success',
                        'action' => 'update',
                        'table' => $table,
                        'id' => $rowId,
                        'affected' => (int) $affected,
                        'message' => $affected > 0 ? 'הרשומה עודכנה בהצלחה' : 'לא בוצע שינוי (ייתכן שהערכים זהים)',
                    ],
                ];

            case 'delete':
                if ($rowId <= 0) {
                    return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'missing_id']];
                }
                $sqlDel = 'DELETE FROM `' . $table . '` WHERE `id` = ?';
                $stmt = $conn->prepare($sqlDel);
                if (!$stmt) {
                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error]];
                }
                $stmt->bind_param('i', $rowId);
                if (!$stmt->execute()) {
                    $err = $stmt->error;
                    $stmt->close();

                    return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'execute_failed', 'detail' => $err]];
                }
                $affected = $stmt->affected_rows;
                $stmt->close();
                admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent DELETE ' . $table . ' id=' . $rowId . ' chat=' . $chatId);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'success',
                        'action' => 'delete',
                        'table' => $table,
                        'id' => $rowId,
                        'affected' => (int) $affected,
                        'message' => $affected > 0 ? 'הרשומה נמחקה בהצלחה' : 'לא נמצאה רשומה למחיקה',
                    ],
                ];
        }

        return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'invalid_action', 'action' => $action]];
    }
}

if (!function_exists('admin_ai_agent_exec_persist_chat_execution')) {
    /**
     * שומר EXECUTION_RESULT בהיסטוריית הצ'אט (זהה ל-agent_execute — ללא exit).
     *
     * @param array<string, mixed> $ctx chat_id, action, table, id, sql
     * @param bool                   $persistErrors כשאמת — תיעוד גם כשלונות בצ'אט
     */
    function admin_ai_agent_exec_persist_chat_execution(mysqli $conn, int $chatId, array $ctx, array $result, bool $persistErrors = false): void
    {
        if ($chatId <= 0) {
            return;
        }
        $st = (string) ($result['status'] ?? '');
        if ($st !== 'success' && !$persistErrors) {
            return;
        }
        require_once __DIR__ . '/chat_repository.php';
        $payload = [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'action' => (string) ($result['action'] ?? ($ctx['action'] ?? '')),
            'table' => (string) ($result['table'] ?? ($ctx['table'] ?? '')),
            'id' => $result['id'] ?? ($ctx['id'] ?? null),
            'affected' => $result['affected'] ?? null,
            'message' => (string) ($result['message'] ?? ''),
        ];
        if ($st === 'error' && ($payload['message'] ?? '') === '' && !empty($result['detail'])) {
            $payload['message'] = (string) $result['detail'];
        }
        if (($payload['action'] ?? '') === 'sql') {
            $payload['verb'] = (string) ($result['verb'] ?? '');
            $payload['kind'] = (string) ($result['kind'] ?? '');
            if (!empty($ctx['sql'])) {
                $sqlSnippet = (string) $ctx['sql'];
                if (function_exists('mb_strlen') && mb_strlen($sqlSnippet, 'UTF-8') > 300) {
                    $sqlSnippet = mb_substr($sqlSnippet, 0, 300, 'UTF-8') . '…';
                }
                $payload['sql'] = $sqlSnippet;
            }
        }
        if (($payload['action'] ?? '') === 'push_broadcast' && isset($result['homes_count'])) {
            $payload['homes_count'] = (int) $result['homes_count'];
        }
        $summary = '[[EXECUTION_RESULT]]' . json_encode($payload, JSON_UNESCAPED_UNICODE) . '[[/EXECUTION_RESULT]]';
        @admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $summary, 'agent-execute');
        @admin_ai_chat_repo_touch($conn, $chatId, '{}');
    }
}

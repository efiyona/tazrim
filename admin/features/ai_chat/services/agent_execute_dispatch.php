<?php
declare(strict_types=1);

/**
 * ליבת ביצוע פעולות agent (CRUD + sql) — משותף ל-agent_execute.php.
 * מחזיר תשובת JSON כמערך + קוד HTTP, בלי exit.
 */

require_once __DIR__ . '/../api/agent_data.php';
require_once __DIR__ . '/agent_project_files.php';
require_once __DIR__ . '/agent_git_ops.php';
require_once __DIR__ . '/agent_sql_change_report.php';

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

if (!function_exists('tazrim_admin_agent_sync_ledger_from_transactions')) {
    /**
     * מסנכרן bank_balance_ledger_cached אחרי שינוי ב-transactions (סוכן אדמין).
     */
    function tazrim_admin_agent_sync_ledger_from_transactions(mysqli $conn, string $table, ?array $row, ?int $insertId, ?array $insertData = null): void
    {
        if ($table !== 'transactions') {
            return;
        }
        if (!defined('ROOT_PATH')) {
            return;
        }
        if (!function_exists('tazrim_recompute_home_ledger_cached_from_db')) {
            require_once ROOT_PATH . '/app/functions/home_bank_balance.php';
        }
        $hid = 0;
        if ($row !== null && isset($row['home_id'])) {
            $hid = (int) $row['home_id'];
        }
        if ($hid <= 0 && $insertData !== null && isset($insertData['home_id'])) {
            $hid = (int) $insertData['home_id'];
        }
        if ($hid <= 0 && $insertId !== null && $insertId > 0) {
            $q = mysqli_query($conn, 'SELECT home_id FROM transactions WHERE id = ' . (int) $insertId . ' LIMIT 1');
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                $hid = (int) ($r['home_id'] ?? 0);
            }
        }
        if ($hid > 0) {
            tazrim_recompute_home_ledger_cached_from_db($conn, $hid);
        }
    }
}

if (!function_exists('admin_ai_agent_dispatch_fetch_row_snapshot')) {
    /**
     * שורה אחת לפני עדכון/מחיקה — אותו נתיב כמו enrich (get + פענוח/סינון פלט).
     *
     * @return array<string, mixed>|null
     */
    function admin_ai_agent_dispatch_fetch_row_snapshot(mysqli $conn, string $table, int $id): ?array
    {
        if ($id <= 0 || $table === '') {
            return null;
        }
        $q = admin_ai_chat_agent_query($conn, ['action' => 'get', 'table' => $table, 'id' => $id]);
        if (!empty($q['ok']) && !empty($q['found']) && isset($q['row']) && is_array($q['row'])) {
            return $q['row'];
        }

        return null;
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
        $restoreDeletedRow = $action === 'create' && !empty($payload['restore_deleted_row']);
        $rowId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $rawSql = (string) ($payload['sql'] ?? '');
        $path = (string) ($payload['path'] ?? '');
        $proposedAtMs = isset($payload['proposed_at']) ? (int) $payload['proposed_at'] : 0;

        if (!in_array($action, ['create', 'update', 'delete', 'sql', 'push_broadcast', 'send_mail', 'file_patch', 'file_write', 'file_delete', 'export_sql_changes'], true)) {
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

        if ($action === 'send_mail') {
            require_once __DIR__ . '/agent_send_mail.php';
            $out = admin_ai_agent_send_mail_execute($conn, $homeId, $userId, $chatId, $payload);
            if (empty($out['ok'])) {
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'action' => 'send_mail',
                        'message' => (string) ($out['message'] ?? 'send_failed'),
                        'detail' => (string) ($out['detail'] ?? ''),
                    ],
                ];
            }

            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'send_mail',
                    'message' => (string) ($out['message'] ?? 'נשלח'),
                    'recipients' => (int) ($out['recipients'] ?? 0),
                ],
            ];
        }

        if ($action === 'export_sql_changes') {
            $exp = admin_ai_agent_sql_change_export_for_chat($conn, $chatId, $userId);
            if (empty($exp['ok'])) {
                return [
                    'http' => 400,
                    'payload' => [
                        'status' => 'error',
                        'action' => 'export_sql_changes',
                        'message' => (string) ($exp['message'] ?? 'export_failed'),
                    ],
                ];
            }
            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'export_sql_changes',
                    'count' => (int) ($exp['count'] ?? 0),
                    'sql_script' => (string) ($exp['sql_script'] ?? ''),
                    'message' => (string) ($exp['message'] ?? 'export_ok'),
                ],
            ];
        }

        if ($action === 'file_patch') {
            $path = (string) ($payload['path'] ?? '');
            $searchBlock = (string) ($payload['search_block'] ?? '');
            $replaceBlock = (string) ($payload['replace_block'] ?? '');
            $op = admin_ai_agent_project_file_patch($path, $searchBlock, $replaceBlock);
            if (empty($op['ok'])) {
                $errCode = (string) ($op['error'] ?? 'file_patch_failed');
                $hint = '';
                if ($errCode === 'search_block_not_found') {
                    $hint = 'לא נמצאה התאמה ל-search_block בקובץ. בצע file_read מחדש והשתמש בבלוק מדויק מהקובץ (עדיף כמה שורות עם הקשר).';
                } elseif ($errCode === 'ambiguous_match_found_make_search_block_larger') {
                    $hint = 'נמצאו כמה התאמות. הרחב את search_block עם יותר הקשר עד שהוא ייחודי.';
                }
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'action' => 'file_patch',
                        'message' => $errCode,
                        'detail' => (string) ($op['detail'] ?? ''),
                        'hint' => $hint,
                        'path' => (string) ($op['path'] ?? $path),
                    ],
                ];
            }
            $matchedBlock = (string) ($op['matched_block'] ?? $searchBlock);
            $undoPayload = [
                'action' => 'file_patch',
                'path' => (string) ($op['path'] ?? $path),
                'search_block' => $replaceBlock,
                'replace_block' => $matchedBlock,
                'description' => 'ביטול patch קובץ',
            ];
            $git = admin_ai_agent_git_after_file_change(admin_ai_agent_project_root_path(), (string) ($op['path'] ?? $path), $chatId, 'file_patch');
            admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent FILE_PATCH ' . (string) ($op['path'] ?? $path) . ' chat=' . $chatId);
            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'file_patch',
                    'path' => (string) ($op['path'] ?? $path),
                    'message' => 'קובץ עודכן בהצלחה',
                    'patch_mode' => (string) ($op['mode'] ?? 'exact'),
                    'syntax_check' => $op['syntax_check'] ?? ['ok' => true, 'skipped' => true],
                    'undo_payload' => $undoPayload,
                    'git' => $git,
                ],
            ];
        }

        if ($action === 'file_write') {
            $path = (string) ($payload['path'] ?? '');
            $content = (string) ($payload['content'] ?? '');
            $op = admin_ai_agent_project_file_write($path, $content);
            if (empty($op['ok'])) {
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'action' => 'file_write',
                        'message' => (string) ($op['error'] ?? 'file_write_failed'),
                        'detail' => (string) ($op['detail'] ?? ''),
                        'path' => (string) ($op['path'] ?? $path),
                    ],
                ];
            }
            $hadBefore = isset($op['before_content']) && is_string($op['before_content']);
            $undoPayload = $hadBefore
                ? [
                    'action' => 'file_write',
                    'path' => (string) ($op['path'] ?? $path),
                    'content' => (string) $op['before_content'],
                    'description' => 'שחזור קובץ לגרסה קודמת',
                ]
                : [
                    'action' => 'file_delete',
                    'path' => (string) ($op['path'] ?? $path),
                    'description' => 'מחיקת קובץ שנוצר',
                ];
            $git = admin_ai_agent_git_after_file_change(admin_ai_agent_project_root_path(), (string) ($op['path'] ?? $path), $chatId, 'file_write');
            admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent FILE_WRITE ' . (string) ($op['path'] ?? $path) . ' chat=' . $chatId);
            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'file_write',
                    'path' => (string) ($op['path'] ?? $path),
                    'message' => 'קובץ נשמר בהצלחה',
                    'syntax_check' => $op['syntax_check'] ?? ['ok' => true, 'skipped' => true],
                    'undo_payload' => $undoPayload,
                    'git' => $git,
                ],
            ];
        }

        if ($action === 'file_delete') {
            $path = (string) ($payload['path'] ?? '');
            $op = admin_ai_agent_project_file_delete($path);
            if (empty($op['ok'])) {
                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'error',
                        'action' => 'file_delete',
                        'message' => (string) ($op['error'] ?? 'file_delete_failed'),
                        'path' => (string) ($op['path'] ?? $path),
                    ],
                ];
            }
            $undoPayload = [
                'action' => 'file_write',
                'path' => (string) ($op['path'] ?? $path),
                'content' => (string) ($op['before_content'] ?? ''),
                'description' => 'שחזור קובץ שנמחק',
            ];
            $git = admin_ai_agent_git_after_file_change(admin_ai_agent_project_root_path(), (string) ($op['path'] ?? $path), $chatId, 'file_delete');
            admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent FILE_DELETE ' . (string) ($op['path'] ?? $path) . ' chat=' . $chatId);
            return [
                'http' => 200,
                'payload' => [
                    'status' => 'success',
                    'action' => 'file_delete',
                    'path' => (string) ($op['path'] ?? $path),
                    'message' => 'קובץ נמחק בהצלחה',
                    'undo_payload' => $undoPayload,
                    'git' => $git,
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
            admin_ai_agent_sql_change_append($conn, $chatId, $safeSql, $kind);

            if ($kind === 'dml' && stripos($safeSql, 'transactions') !== false) {
                if (!defined('ROOT_PATH')) {
                    // skip
                } else {
                    if (!function_exists('tazrim_recompute_home_ledger_cached_from_db_all_homes')) {
                        require_once ROOT_PATH . '/app/functions/home_bank_balance.php';
                    }
                    tazrim_recompute_home_ledger_cached_from_db_all_homes($conn);
                }
            }

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
        $blockedFlip = array_flip(admin_ai_agent_global_blocked_fields());

        $sanitizedData = [];
        foreach ($data as $col => $val) {
            $col = (string) $col;
            if (isset($blockedFlip[$col])) {
                return [
                    'http' => 403,
                    'payload' => [
                        'status' => 'error',
                        'message' => 'field_blocked',
                        'table' => $table,
                        'field' => $col,
                    ],
                ];
            }
            if ($restoreDeletedRow) {
                if (!isset($fieldDefs[$col])) {
                    return [
                        'http' => 400,
                        'payload' => [
                            'status' => 'error',
                            'message' => 'unknown_field',
                            'table' => $table,
                            'field' => $col,
                        ],
                    ];
                }
            } elseif (!admin_ai_agent_is_field_writable($table, $col)) {
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
                $sqlAudit = '/* CREATE via agent */ INSERT INTO `' . $table . '` (...) VALUES (...)';
                admin_ai_agent_sql_change_append($conn, $chatId, $sqlAudit, 'dml');

                tazrim_admin_agent_sync_ledger_from_transactions($conn, $table, null, (int) $insertId, $sanitizedData);

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
                $beforeRow = admin_ai_agent_dispatch_fetch_row_snapshot($conn, $table, $rowId);
                if ($beforeRow === null) {
                    return [
                        'http' => 404,
                        'payload' => [
                            'status' => 'error',
                            'message' => 'row_not_found',
                            'table' => $table,
                            'id' => $rowId,
                        ],
                    ];
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
                $sqlAudit = '/* UPDATE via agent */ UPDATE `' . $table . '` SET ... WHERE id=' . $rowId;
                admin_ai_agent_sql_change_append($conn, $chatId, $sqlAudit, 'dml');

                $undoData = [];
                foreach (array_keys($sanitizedData) as $colName) {
                    if (array_key_exists($colName, $beforeRow)) {
                        $undoData[$colName] = $beforeRow[$colName];
                    }
                }
                $undoPayload = [
                    'action' => 'update',
                    'table' => $table,
                    'id' => $rowId,
                    'description' => 'ביטול שינויים — שחזור ערכים קודמים',
                    'data' => $undoData,
                ];

                tazrim_admin_agent_sync_ledger_from_transactions($conn, $table, $beforeRow, null, null);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'success',
                        'action' => 'update',
                        'table' => $table,
                        'id' => $rowId,
                        'affected' => (int) $affected,
                        'message' => $affected > 0 ? 'הרשומה עודכנה בהצלחה' : 'לא בוצע שינוי (ייתכן שהערכים זהים)',
                        'undo_payload' => $undoPayload,
                    ],
                ];

            case 'delete':
                if ($rowId <= 0) {
                    return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'missing_id']];
                }
                $beforeRow = admin_ai_agent_dispatch_fetch_row_snapshot($conn, $table, $rowId);
                if ($beforeRow === null) {
                    return [
                        'http' => 404,
                        'payload' => [
                            'status' => 'error',
                            'message' => 'row_not_found',
                            'table' => $table,
                            'id' => $rowId,
                        ],
                    ];
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
                $sqlAudit = '/* DELETE via agent */ DELETE FROM `' . $table . '` WHERE id=' . $rowId;
                admin_ai_agent_sql_change_append($conn, $chatId, $sqlAudit, 'dml');

                $restoreData = [];
                foreach ($beforeRow as $colName => $val) {
                    $cn = (string) $colName;
                    if (isset($blockedFlip[$cn]) || !isset($fieldDefs[$cn])) {
                        continue;
                    }
                    $restoreData[$cn] = $val;
                }
                $undoPayload = [
                    'action' => 'create',
                    'table' => $table,
                    'description' => 'שחזור רשומה שנמחקה (ביטול מחיקה)',
                    'restore_deleted_row' => true,
                    'data' => $restoreData,
                ];

                tazrim_admin_agent_sync_ledger_from_transactions($conn, $table, $beforeRow, null, null);

                return [
                    'http' => 200,
                    'payload' => [
                        'status' => 'success',
                        'action' => 'delete',
                        'table' => $table,
                        'id' => $rowId,
                        'affected' => (int) $affected,
                        'message' => $affected > 0 ? 'הרשומה נמחקה בהצלחה' : 'לא נמצאה רשומה למחיקה',
                        'undo_payload' => $undoPayload,
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
        if (($payload['action'] ?? '') === 'send_mail' && isset($result['recipients'])) {
            $payload['recipients'] = (int) $result['recipients'];
        }
        if (in_array((string) ($payload['action'] ?? ''), ['file_patch', 'file_write', 'file_delete'], true)) {
            $payload['path'] = (string) ($result['path'] ?? '');
            if (isset($result['patch_mode'])) {
                $payload['patch_mode'] = (string) $result['patch_mode'];
            }
            if (isset($result['syntax_check'])) {
                $payload['syntax_check'] = $result['syntax_check'];
            }
            if (isset($result['git'])) {
                $payload['git'] = $result['git'];
            }
        }
        if (($payload['action'] ?? '') === 'export_sql_changes') {
            $payload['count'] = (int) ($result['count'] ?? 0);
            $payload['sql_script'] = (string) ($result['sql_script'] ?? '');
        }
        if (isset($result['undo_payload']) && is_array($result['undo_payload'])) {
            $payload['undo_payload'] = $result['undo_payload'];
        }
        $summary = '[[EXECUTION_RESULT]]' . json_encode($payload, JSON_UNESCAPED_UNICODE) . '[[/EXECUTION_RESULT]]';
        @admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $summary, 'agent-execute');
        @admin_ai_chat_repo_touch($conn, $chatId, '{}');
    }
}

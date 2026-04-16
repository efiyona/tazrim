<?php
declare(strict_types=1);

/**
 * Endpoint ביצוע פעולות על ידי סוכן ה-AI בפאנל ניהול.
 *
 * פעולות נתמכות:
 *  - create/update/delete — CRUD מבוקר על טבלאות ב-whitelist עם typed fields
 *  - sql — SQL גולמי שהמנהל סיפק (DML או DDL) — עוקף את ה-whitelist
 *
 * אבטחה:
 *  1. api_token מוזרק לקליינט רק ל-program_admin דרך bootstrap.php
 *  2. הטוקן מאומת כאן מול טבלת users + role=program_admin
 *  3. Session חייב להיות אקטיבי + program_admin (בדיקה דו-שכבתית)
 *  4. ל-CRUD: Whitelist טבלאות + Blacklist שדות (דרך agent_schema.php)
 *  5. הצפנה אוטומטית לשדות רגישים (homes.initial_balance)
 *  6. ל-sql: וולידציה של משפט יחיד, חסימת DROP DATABASE/SCHEMA, חסימת GRANT/REVOKE, לוג מלא
 *  7. logging של כל פעולה ל-ai_api_logs
 */

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../services/agent_schema.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * חיסכון במצב גלובלי עבור שמירת תוצאת הביצוע בהיסטוריית הצ'אט בעת exit.
 */
$GLOBALS['admin_ai_agent_exec_chat_context'] = null;

function admin_ai_agent_exec_persist_result(mysqli $conn, array $result): void
{
    $ctx = $GLOBALS['admin_ai_agent_exec_chat_context'] ?? null;
    if (!is_array($ctx) || empty($ctx['chat_id'])) {
        return;
    }
    $chatId = (int) $ctx['chat_id'];
    if ($chatId <= 0) {
        return;
    }
    require_once __DIR__ . '/../services/chat_repository.php';
    $payload = [
        'status' => (string) ($result['status'] ?? 'unknown'),
        'action' => (string) ($result['action'] ?? ($ctx['action'] ?? '')),
        'table' => (string) ($result['table'] ?? ($ctx['table'] ?? '')),
        'id' => $result['id'] ?? ($ctx['id'] ?? null),
        'affected' => $result['affected'] ?? null,
        'message' => (string) ($result['message'] ?? ''),
    ];
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
    $summary = '[[EXECUTION_RESULT]]' . json_encode($payload, JSON_UNESCAPED_UNICODE) . '[[/EXECUTION_RESULT]]';
    @admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $summary, 'agent-execute');
    @admin_ai_chat_repo_touch($conn, $chatId, '{}');
}

function admin_ai_agent_exec_respond(array $payload, int $status = 200): void
{
    global $conn;
    if (($payload['status'] ?? '') === 'success' && $conn instanceof mysqli) {
        admin_ai_agent_exec_persist_result($conn, $payload);
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_ai_agent_exec_log(mysqli $conn, int $homeId, int $userId, string $text): void
{
    $trimmed = function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : substr($text, 0, 500);
    $escaped = mysqli_real_escape_string($conn, $trimmed);
    @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$escaped}')");
}

/**
 * מנתח משפט SQL שהמנהל ביקש לבצע ומחזיר מערך עם סוג, פעולה עיקרית ומטא-נתונים
 * או שגיאת בטיחות אם המשפט אסור.
 */
function admin_ai_agent_exec_analyze_sql(string $sql): array
{
    $original = $sql;
    $sql = trim($sql);
    if ($sql === '') {
        return ['ok' => false, 'error' => 'empty_sql'];
    }
    // הסר ; נגרר
    $sql = rtrim($sql, ';');
    $sql = trim($sql);
    if ($sql === '') {
        return ['ok' => false, 'error' => 'empty_sql'];
    }

    // זהה מספר משפטים: מצא ; שאינם בתוך מרכאות
    // במקום ניתוח מלא — בדיקה פשוטה של ; שנשאר אחרי trim
    $sqlForCheck = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "''", $sql);
    $sqlForCheck = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', (string) $sqlForCheck);
    $sqlForCheck = preg_replace('/`[^`]*`/', '``', (string) $sqlForCheck);
    // הסר תגובות
    $sqlForCheck = preg_replace('/\/\*.*?\*\//s', '', (string) $sqlForCheck);
    $sqlForCheck = preg_replace('/--[^\n]*/', '', (string) $sqlForCheck);
    $sqlForCheck = preg_replace('/#[^\n]*/', '', (string) $sqlForCheck);
    if (strpos((string) $sqlForCheck, ';') !== false) {
        return ['ok' => false, 'error' => 'multiple_statements_not_allowed'];
    }

    $upper = strtoupper($sqlForCheck);
    $upper = ltrim($upper);

    // חסימות קשות — פעולות שאין להן מקום דרך הסוכן
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

    // זהה סוג + רמת סיכון
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
        // SET session variables — מותר אך לא מועיל דרך זה
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

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'invalid_json'], 400);
}

$apiToken = (string) ($payload['api_token'] ?? '');
if ($apiToken === '' || strlen($apiToken) < 20) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'missing_api_token'], 401);
}

$tokenStmt = $conn->prepare('SELECT id, role FROM users WHERE api_token = ? LIMIT 1');
if (!$tokenStmt) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'db_prepare_failed'], 500);
}
$tokenStmt->bind_param('s', $apiToken);
$tokenStmt->execute();
$tokenResult = $tokenStmt->get_result();
$tokenUser = $tokenResult ? $tokenResult->fetch_assoc() : null;
$tokenStmt->close();

if (!$tokenUser || (string) $tokenUser['role'] !== 'program_admin') {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'invalid_token'], 403);
}

$tokenUserId = (int) $tokenUser['id'];
if ($tokenUserId !== $userId) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'token_user_mismatch'], 403);
}

$action = strtolower((string) ($payload['action'] ?? ''));
$table = (string) ($payload['table'] ?? '');
$chatId = (int) ($payload['chat_id'] ?? 0);
$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
$rowId = isset($payload['id']) ? (int) $payload['id'] : 0;
$rawSql = (string) ($payload['sql'] ?? '');

$GLOBALS['admin_ai_agent_exec_chat_context'] = [
    'chat_id' => $chatId,
    'action' => $action,
    'table' => $table,
    'id' => $rowId,
    'sql' => $rawSql,
];

if (!in_array($action, ['create', 'update', 'delete', 'sql'], true)) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'invalid_action', 'action' => $action], 400);
}

// ===== SQL גולמי (DML/DDL) =====
if ($action === 'sql') {
    if ($rawSql === '') {
        admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'missing_sql'], 400);
    }
    $analyzed = admin_ai_agent_exec_analyze_sql($rawSql);
    if (!$analyzed['ok']) {
        admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL REJECTED: ' . $analyzed['error'] . ' | ' . substr($rawSql, 0, 300) . ' | chat=' . $chatId);
        admin_ai_agent_exec_respond([
            'status' => 'error',
            'message' => 'sql_rejected',
            'reason' => $analyzed['error'],
        ], 400);
    }

    $safeSql = $analyzed['sql'];
    $verb = $analyzed['verb'];
    $kind = $analyzed['kind'];

    // רישום מקדים לפני ביצוע — מבטיח audit trail גם אם הביצוע יקרוס
    admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL ATTEMPT verb=' . $verb . ' kind=' . $kind . ' chat=' . $chatId . ' sql=' . substr($safeSql, 0, 400));

    $startedAt = microtime(true);
    $queryResult = @mysqli_query($conn, $safeSql);
    $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

    if ($queryResult === false) {
        $err = mysqli_error($conn);
        admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent SQL FAILED verb=' . $verb . ' err=' . substr($err, 0, 200) . ' chat=' . $chatId);
        admin_ai_agent_exec_respond([
            'status' => 'error',
            'message' => 'sql_execute_failed',
            'detail' => $err,
            'verb' => $verb,
            'kind' => $kind,
        ], 500);
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

    admin_ai_agent_exec_respond([
        'status' => 'success',
        'action' => 'sql',
        'verb' => $verb,
        'kind' => $kind,
        'affected' => (int) $affected,
        'insert_id' => $insertId > 0 ? (int) $insertId : null,
        'rows_returned' => $rowsReturned,
        'elapsed_ms' => $elapsedMs,
        'message' => implode(' ', $msgParts),
    ]);
}

// ===== CRUD מבוקר (create/update/delete) =====
if (!admin_ai_agent_can_write($table)) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'table_not_writable', 'table' => $table], 403);
}

$cfg = admin_ai_agent_get_table_config($table);
$fieldDefs = $cfg['fields'] ?? [];

$sanitizedData = [];
foreach ($data as $col => $val) {
    $col = (string) $col;
    if (!admin_ai_agent_is_field_writable($table, $col)) {
        admin_ai_agent_exec_respond([
            'status' => 'error',
            'message' => 'field_not_writable',
            'table' => $table,
            'field' => $col,
        ], 403);
    }
    $def = $fieldDefs[$col] ?? [];
    $type = (string) ($def['type'] ?? 'string');
    if (isset($def['enum']) && is_array($def['enum']) && $val !== null && !in_array((string) $val, $def['enum'], true)) {
        admin_ai_agent_exec_respond([
            'status' => 'error',
            'message' => 'invalid_enum_value',
            'field' => $col,
            'allowed' => $def['enum'],
        ], 400);
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
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'empty_data'], 400);
        }
        $cols = array_keys($sanitizedData);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error], 500);
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
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'execute_failed', 'detail' => $err], 500);
        }
        $insertId = $stmt->insert_id;
        $stmt->close();
        admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent CREATE ' . $table . ' id=' . $insertId . ' chat=' . $chatId);
        admin_ai_agent_exec_respond([
            'status' => 'success',
            'action' => 'create',
            'table' => $table,
            'id' => (int) $insertId,
            'affected' => 1,
            'message' => 'הרשומה נוצרה בהצלחה',
        ]);
        break;

    case 'update':
        if ($rowId <= 0) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'missing_id'], 400);
        }
        if (empty($sanitizedData)) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'empty_data'], 400);
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
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $setParts) . ' WHERE `id` = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error], 500);
        }
        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'execute_failed', 'detail' => $err], 500);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent UPDATE ' . $table . ' id=' . $rowId . ' fields=' . implode(',', array_keys($sanitizedData)) . ' chat=' . $chatId);
        admin_ai_agent_exec_respond([
            'status' => 'success',
            'action' => 'update',
            'table' => $table,
            'id' => $rowId,
            'affected' => (int) $affected,
            'message' => $affected > 0 ? 'הרשומה עודכנה בהצלחה' : 'לא בוצע שינוי (ייתכן שהערכים זהים)',
        ]);
        break;

    case 'delete':
        if ($rowId <= 0) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'missing_id'], 400);
        }
        $sql = 'DELETE FROM `' . $table . '` WHERE `id` = ?';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'prepare_failed', 'detail' => $conn->error], 500);
        }
        $stmt->bind_param('i', $rowId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'execute_failed', 'detail' => $err], 500);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        admin_ai_agent_exec_log($conn, $homeId, $userId, 'Admin AI Agent DELETE ' . $table . ' id=' . $rowId . ' chat=' . $chatId);
        admin_ai_agent_exec_respond([
            'status' => 'success',
            'action' => 'delete',
            'table' => $table,
            'id' => $rowId,
            'affected' => (int) $affected,
            'message' => $affected > 0 ? 'הרשומה נמחקה בהצלחה' : 'לא נמצאה רשומה למחיקה',
        ]);
        break;
}

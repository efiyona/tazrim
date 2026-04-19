<?php
declare(strict_types=1);

/**
 * Endpoint ביצוע פעולות על ידי סוכן ה-AI בפאנל ניהול.
 *
 * פעולות נתמכות:
 *  - create/update/delete — CRUD מבוקר על טבלאות ב-whitelist עם typed fields
 *  - sql — SQL גולמי שהמנהל סיפק (DML או DDL) — עוקף את ה-whitelist
 *  - push_broadcast — שידור Push/פעמון כמו בעמוד push_broadcast (title, body, target, delivery, link, home_ids)
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

// הגנה: תמיד נחזיר JSON נקי — מונע 'network_error' בלקוח
// עקב warnings/notices שנשפכו ל-output ושברו את הפענוח.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
ob_start();

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../services/agent_schema.php';
require_once __DIR__ . '/../services/agent_execute_dispatch.php';

header('Content-Type: application/json; charset=utf-8');

// מסגרת בטיחות — מוודאת שבכל מקרה לצד הלקוח תגיע תשובת JSON תקינה
register_shutdown_function(function () {
    $err = error_get_last();
    if (is_array($err) && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        echo json_encode([
            'status' => 'error',
            'message' => 'שגיאת שרת בלתי-צפויה',
            'detail' => 'fatal: ' . (string) ($err['message'] ?? 'unknown'),
        ], JSON_UNESCAPED_UNICODE);
    }
});

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
    admin_ai_agent_exec_persist_chat_execution($conn, $chatId, $ctx, $result, false);
}

function admin_ai_agent_exec_respond(array $payload, int $status = 200): void
{
    global $conn;
    if (($payload['status'] ?? '') === 'success' && $conn instanceof mysqli) {
        try { admin_ai_agent_exec_persist_result($conn, $payload); } catch (\Throwable $e) { /* noop */ }
    }
    // נקה כל output מקדים (warnings/notices) לפני פליטת JSON
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_ai_agent_exec_log(mysqli $conn, int $homeId, int $userId, string $text): void
{
    try {
        $trimmed = function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : substr($text, 0, 500);
        $escaped = mysqli_real_escape_string($conn, $trimmed);
        @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$escaped}')");
    } catch (\Throwable $e) {
        // לוג בלבד — לא לשבור את ה-response גם אם הטבלה חסרה/אין הרשאה
    }
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
$path = (string) ($payload['path'] ?? '');
// חותמת זמן ההצעה (ב-ms) שנשלחת מהלקוח לאכיפת TTL של 5 דקות
$proposedAtMs = isset($payload['proposed_at']) ? (int) $payload['proposed_at'] : 0;

$GLOBALS['admin_ai_agent_exec_chat_context'] = [
    'chat_id' => $chatId,
    'action' => $action,
    'table' => $table,
    'id' => $rowId,
    'sql' => $rawSql,
    'path' => $path,
];

if (!in_array($action, ['create', 'update', 'delete', 'sql', 'push_broadcast', 'send_mail', 'file_patch', 'file_write', 'file_delete', 'export_sql_changes'], true)) {
    admin_ai_agent_exec_respond(['status' => 'error', 'message' => 'invalid_action', 'action' => $action], 400);
}

$dispatchOut = admin_ai_agent_dispatch_execute_payload($conn, $homeId, $userId, $chatId, $payload);
admin_ai_agent_exec_respond($dispatchOut['payload'], $dispatchOut['http']);

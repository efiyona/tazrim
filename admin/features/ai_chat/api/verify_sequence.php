<?php
declare(strict_types=1);

/**
 * אימות מצב DB אחרי רצף — השוואת שדות לערכים צפויים (כמו DATA_QUERY get).
 * אותה אבטחה כמו agent_execute (טוקן + program_admin).
 */

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/agent_data.php';
require_once __DIR__ . '/../services/stream_history.php';

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function () {
    $err = error_get_last();
    if (is_array($err) && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'שגיאת שרת בלתי-צפויה',
            'detail' => 'fatal: ' . (string) ($err['message'] ?? 'unknown'),
        ], JSON_UNESCAPED_UNICODE);
    }
});

/**
 * השוואה רופפת לערכי DB מול JSON (מחרוזות/מספרים).
 */
function admin_ai_verify_values_match(mixed $actual, mixed $expected): bool
{
    if ($actual === $expected) {
        return true;
    }
    if ($actual === null && ($expected === '' || $expected === null)) {
        return true;
    }
    if (is_numeric($actual) && is_numeric($expected)) {
        return (string) (float) $actual === (string) (float) $expected;
    }

    return trim((string) $actual) === trim((string) $expected);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(['status' => 'error', 'message' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiToken = (string) ($payload['api_token'] ?? '');
if ($apiToken === '' || strlen($apiToken) < 20) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'missing_api_token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenStmt = $conn->prepare('SELECT id, role FROM users WHERE api_token = ? LIMIT 1');
if (!$tokenStmt) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(['status' => 'error', 'message' => 'db_prepare_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
$tokenStmt->bind_param('s', $apiToken);
$tokenStmt->execute();
$tokenResult = $tokenStmt->get_result();
$tokenUser = $tokenResult ? $tokenResult->fetch_assoc() : null;
$tokenStmt->close();

if (!$tokenUser || (string) $tokenUser['role'] !== 'program_admin') {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'invalid_token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenUserId = (int) $tokenUser['id'];
if ($tokenUserId !== $userId) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'token_user_mismatch'], JSON_UNESCAPED_UNICODE);
    exit;
}

$verification = $payload['verification'] ?? null;
$vr = admin_ai_chat_validate_sequence_verification($verification);
if (!$vr['ok']) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(['status' => 'error', 'message' => 'bad_verification', 'detail' => $vr['error'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($verification === null || $verification === []) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(['status' => 'success', 'ok' => true, 'checks' => [], 'message' => 'אין בדיקות'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = array_is_list($verification) ? $verification : [$verification];
$results = [];
$allOk = true;

foreach ($items as $check) {
    if (!is_array($check)) {
        continue;
    }
    $table = trim((string) ($check['table'] ?? ''));
    $id = (int) ($check['id'] ?? 0);
    $expect = $check['expect'] ?? null;
    if ($table === '' || $id <= 0 || !is_array($expect)) {
        $allOk = false;
        $results[] = [
            'ok' => false,
            'table' => $table,
            'id' => $id,
            'error' => 'bad_check',
        ];
        continue;
    }

    $q = admin_ai_chat_agent_query($conn, ['action' => 'get', 'table' => $table, 'id' => $id]);
    if (empty($q['ok']) || empty($q['found']) || !isset($q['row']) || !is_array($q['row'])) {
        $allOk = false;
        $results[] = [
            'ok' => false,
            'table' => $table,
            'id' => $id,
            'error' => 'row_not_found',
        ];
        continue;
    }
    $row = $q['row'];
    $mismatches = [];
    foreach ($expect as $col => $expectedVal) {
        $col = (string) $col;
        if ($col === '' || !array_key_exists($col, $row)) {
            $allOk = false;
            $mismatches[$col] = ['expected' => $expectedVal, 'actual' => null, 'reason' => 'missing_column'];
            continue;
        }
        $actualVal = $row[$col];
        if (!admin_ai_verify_values_match($actualVal, $expectedVal)) {
            $allOk = false;
            $mismatches[$col] = ['expected' => $expectedVal, 'actual' => $actualVal];
        }
    }
    $results[] = [
        'ok' => $mismatches === [],
        'table' => $table,
        'id' => $id,
        'mismatches' => $mismatches,
    ];
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}
echo json_encode([
    'status' => 'success',
    'ok' => $allOk,
    'checks' => $results,
    'message' => $allOk ? 'כל הבדיקות עברו' : 'נמצאו סטיות מהצפוי',
], JSON_UNESCAPED_UNICODE);

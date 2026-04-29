<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/user_gemini_key.php';
require_once ROOT_PATH . '/app/functions/app_session_csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'code' => 'method']);
    exit;
}

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'code' => 'auth']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    echo json_encode(['status' => 'error', 'code' => 'bad_json']);
    exit;
}

$csrf = isset($body['csrf_token']) ? (string) $body['csrf_token'] : '';
if (!tazrim_app_csrf_validate($csrf)) {
    echo json_encode(['status' => 'error', 'code' => 'csrf']);
    exit;
}

$key = isset($body['api_key']) ? (string) $body['api_key'] : '';
$userId = (int) $_SESSION['id'];

$result = tazrim_user_save_gemini_key($conn, $userId, $key);
if (!$result['ok']) {
    echo json_encode([
        'status' => 'error',
        'code' => $result['code'],
        'message' => $result['message'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$parts = tazrim_user_get_gemini_key_mask_parts($conn, $userId);

echo json_encode([
    'status' => 'ok',
    'code' => 'saved',
    'mask' => $parts['mask'],
    'key_count' => $parts['key_count'] ?? 1,
    'keys' => $parts['keys'] ?? [],
    'message' => 'המפתח נשמר בהצלחה.',
], JSON_UNESCAPED_UNICODE);

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

$userId = (int) $_SESSION['id'];

$keyIdMaybe = isset($body['key_id']) ? (int) $body['key_id'] : 0;
$rowId = $keyIdMaybe > 0 ? $keyIdMaybe : null;

$ok = tazrim_user_delete_gemini_key($conn, $userId, $rowId);
$stillThere = function_exists('tazrim_user_get_gemini_key_mask_parts')
    ? tazrim_user_get_gemini_key_mask_parts($conn, $userId)
    : ['configured' => false];

echo json_encode([
    'status' => $ok ? 'ok' : 'error',
    'configured' => !empty($stillThere['configured']),
    'key_count' => (int) ($stillThere['key_count'] ?? 0),
    'mask' => $stillThere['mask'] ?? '',
], JSON_UNESCAPED_UNICODE);

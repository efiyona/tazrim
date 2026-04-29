<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/user_gemini_key.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'code' => 'auth', 'configured' => false]);
    exit;
}

$userId = (int) $_SESSION['id'];
$parts = tazrim_user_get_gemini_key_mask_parts($conn, $userId);

echo json_encode([
    'status' => 'ok',
    'configured' => $parts['configured'],
    'mask' => $parts['mask'],
    'key_count' => $parts['key_count'] ?? 0,
    'keys' => $parts['keys'] ?? [],
], JSON_UNESCAPED_UNICODE);

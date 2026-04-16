<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

$chatId = isset($_GET['chat_id']) ? (int) $_GET['chat_id'] : 0;
if ($chatId <= 0) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'chat_id_required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$chat = admin_ai_chat_repo_get($conn, $chatId, $userId);
if (!$chat) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$messages = admin_ai_chat_repo_get_messages($conn, $chatId, $userId, 200);
echo json_encode([
    'status' => 'success',
    'chat' => $chat,
    'messages' => $messages,
], JSON_UNESCAPED_UNICODE);

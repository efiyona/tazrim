<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
$chatId = (int) ($payload['chat_id'] ?? 0);

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

$stmt = $conn->prepare('DELETE FROM admin_ai_chat_messages WHERE chat_id = ?');
$stmt->bind_param('i', $chatId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM admin_ai_chats WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $chatId, $userId);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);

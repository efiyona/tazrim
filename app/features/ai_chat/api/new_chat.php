<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    $payload = [];
}

$scopeSnapshot = '{}';

$chatId = ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
$chat = ai_chat_repo_get($conn, $chatId, $userId);

echo json_encode(['status' => 'success', 'chat' => $chat], JSON_UNESCAPED_UNICODE);

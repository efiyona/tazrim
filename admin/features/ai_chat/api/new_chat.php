<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    $payload = [];
}

$scope = $payload['scope'] ?? ['topic' => 'system'];
if (!is_array($scope)) {
    $scope = ['topic' => 'system'];
}
$t = (string) ($scope['topic'] ?? 'system');
$scope = ['topic' => $t === 'financial' ? 'financial' : 'system'];
$scopeSnapshot = json_encode($scope, JSON_UNESCAPED_UNICODE);
if ($scopeSnapshot === false) {
    $scopeSnapshot = '{"topic":"system"}';
}

$chatId = admin_ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
$chat = admin_ai_chat_repo_get($conn, $chatId, $userId);

echo json_encode(['status' => 'success', 'chat' => $chat], JSON_UNESCAPED_UNICODE);

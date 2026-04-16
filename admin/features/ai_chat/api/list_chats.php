<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

$items = admin_ai_chat_repo_list($conn, $userId, 80);
echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);

<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');

mysqli_begin_transaction($conn);
try {
    $stmt = $conn->prepare('DELETE m FROM admin_ai_chat_messages m INNER JOIN admin_ai_chats c ON c.id = m.chat_id WHERE c.user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $deletedMessages = (int) $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM admin_ai_chats WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $deletedChats = (int) $stmt->affected_rows;
    $stmt->close();

    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'deleted_chats' => $deletedChats,
        'deleted_messages' => $deletedMessages,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'delete_all_failed'], JSON_UNESCAPED_UNICODE);
}

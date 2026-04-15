<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (!function_exists('ai_chat_repo_list')) {
    function ai_chat_repo_list(mysqli $conn, int $userId, int $limit = 50): array
    {
        $sql = "SELECT c.id, c.title, c.scope_snapshot, c.updated_at
                FROM ai_chats c
                WHERE c.user_id = ?
                ORDER BY c.updated_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('ai_chat_repo_get')) {
    function ai_chat_repo_get(mysqli $conn, int $chatId, int $userId): ?array
    {
        $sql = "SELECT id, user_id, title, scope_snapshot, created_at, updated_at
                FROM ai_chats
                WHERE id = ? AND user_id = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $chatId, $userId);
        $stmt->execute();
        $chat = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $chat;
    }
}

if (!function_exists('ai_chat_repo_create')) {
    function ai_chat_repo_create(mysqli $conn, int $userId, string $scopeSnapshot, string $title = 'שיחה חדשה'): int
    {
        $sql = "INSERT INTO ai_chats (user_id, title, scope_snapshot, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $userId, $title, $scopeSnapshot);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('ai_chat_repo_touch')) {
    function ai_chat_repo_touch(mysqli $conn, int $chatId, string $scopeSnapshot): void
    {
        $sql = "UPDATE ai_chats SET scope_snapshot = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $scopeSnapshot, $chatId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ai_chat_repo_add_message')) {
    function ai_chat_repo_add_message(mysqli $conn, int $chatId, string $role, string $content, ?string $model = null): int
    {
        $sql = "INSERT INTO ai_chat_messages (chat_id, role, content, model, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isss', $chatId, $role, $content, $model);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('ai_chat_repo_get_messages')) {
    function ai_chat_repo_get_messages(mysqli $conn, int $chatId, int $userId, int $limit = 120): array
    {
        $sql = "SELECT m.id, m.role, m.content, m.model, m.created_at
                FROM ai_chat_messages m
                INNER JOIN ai_chats c ON c.id = m.chat_id
                WHERE m.chat_id = ? AND c.user_id = ?
                ORDER BY m.id ASC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $chatId, $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('ai_chat_repo_update_title_if_default')) {
    function ai_chat_repo_update_title_if_default(mysqli $conn, int $chatId, string $firstMessage): void
    {
        $clean = preg_replace('/\s+/u', ' ', trim($firstMessage));
        if ($clean === '') {
            return;
        }
        $title = mb_substr($clean, 0, 70, 'UTF-8');
        $sql = "UPDATE ai_chats SET title = ? WHERE id = ? AND title = 'שיחה חדשה'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $title, $chatId);
        $stmt->execute();
        $stmt->close();
    }
}

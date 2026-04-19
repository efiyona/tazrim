<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// ================================================================
//  DB Keep-Alive  (קריטי — Gemini יכול לקחת 40 שנ' ומעלה, וחיבור ה-MySQL
//  של Hostinger מתנתק אחרי ~60 שנ' idle. בלי המנגנון הזה נקבל fatal:
//  "MySQL server has gone away" כשננסה לשמור את התגובה אחרי קריאת המודל.)
// ================================================================

if (!function_exists('admin_ai_chat_db_ensure_alive')) {
    /**
     * מוודא ש-$conn חי. אם לא — סוגר את הישן ומתחבר מחדש עם אותם
     * credentials. מעדכן גם את $conn המקומי (by-ref) וגם את $GLOBALS['conn'].
     *
     * מחזיר true אם לאחר הפעולה יש חיבור חי.
     */
    function admin_ai_chat_db_ensure_alive(?mysqli &$conn): bool
    {
        // 1. ping — אם מצליח, הכל טוב
        try {
            if ($conn instanceof mysqli && @$conn->ping()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ping יכול לזרוק mysqli_sql_exception תחת mysqli_report strict
        }

        // 2. לסגור את החיבור הישן (אם קיים)
        try {
            if ($conn instanceof mysqli) {
                @$conn->close();
            }
        } catch (\Throwable $e) { /* noop */ }

        // 3. להתחבר מחדש
        if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
            return false;
        }
        try {
            $new = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (@$new->connect_error) {
                return false;
            }
            @$new->set_charset('utf8mb4');
            @$new->query("SET SESSION wait_timeout = 600");
            $conn = $new;
            $GLOBALS['conn'] = $new;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_ai_chat_db_extend_session_timeout')) {
    /**
     * מרחיב את wait_timeout של ה-session הנוכחי כדי להפחית סיכוי לניתוק
     * בזמן קריאת Gemini ארוכה. קריאה אחת בתחילת הבקשה מספיקה.
     */
    function admin_ai_chat_db_extend_session_timeout(?mysqli $conn): void
    {
        if (!($conn instanceof mysqli)) return;
        try {
            @$conn->query("SET SESSION wait_timeout = 600");
            @$conn->query("SET SESSION interactive_timeout = 600");
        } catch (\Throwable $e) { /* noop */ }
    }
}

// ================================================================
//  Repository functions — כולן מקבלות $conn by-reference וכולן קוראות
//  ל-ensure_alive לפני כל פעולה, כדי לשרוד חיבור שמת.
// ================================================================

if (!function_exists('admin_ai_chat_repo_list')) {
    function admin_ai_chat_repo_list(mysqli &$conn, int $userId, int $limit = 50): array
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "SELECT c.id, c.title, c.scope_snapshot, c.updated_at
                FROM admin_ai_chats c
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

if (!function_exists('admin_ai_chat_repo_get')) {
    function admin_ai_chat_repo_get(mysqli &$conn, int $chatId, int $userId): ?array
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "SELECT id, user_id, title, scope_snapshot, created_at, updated_at
                FROM admin_ai_chats
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

if (!function_exists('admin_ai_chat_repo_create')) {
    function admin_ai_chat_repo_create(mysqli &$conn, int $userId, string $scopeSnapshot, string $title = 'שיחה חדשה'): int
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "INSERT INTO admin_ai_chats (user_id, title, scope_snapshot, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $userId, $title, $scopeSnapshot);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('admin_ai_chat_repo_touch')) {
    function admin_ai_chat_repo_touch(mysqli &$conn, int $chatId, string $scopeSnapshot): void
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "UPDATE admin_ai_chats SET scope_snapshot = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $scopeSnapshot, $chatId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('admin_ai_chat_repo_add_message')) {
    function admin_ai_chat_repo_add_message(mysqli &$conn, int $chatId, string $role, string $content, ?string $model = null): int
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "INSERT INTO admin_ai_chat_messages (chat_id, role, content, model, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isss', $chatId, $role, $content, $model);
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('admin_ai_chat_repo_get_messages')) {
    function admin_ai_chat_repo_get_messages(mysqli &$conn, int $chatId, int $userId, int $limit = 120): array
    {
        admin_ai_chat_db_ensure_alive($conn);
        $sql = "SELECT m.id, m.role, m.content, m.model, m.created_at
                FROM admin_ai_chat_messages m
                INNER JOIN admin_ai_chats c ON c.id = m.chat_id
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

if (!function_exists('admin_ai_chat_repo_update_title_if_default')) {
    function admin_ai_chat_repo_update_title_if_default(mysqli &$conn, int $chatId, string $firstMessage): void
    {
        admin_ai_chat_db_ensure_alive($conn);
        $clean = preg_replace('/\s+/u', ' ', trim($firstMessage));
        if ($clean === '') {
            return;
        }
        $title = mb_substr($clean, 0, 70, 'UTF-8');
        $sql = "UPDATE admin_ai_chats SET title = ? WHERE id = ? AND title = 'שיחה חדשה'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $title, $chatId);
        $stmt->execute();
        $stmt->close();
    }
}

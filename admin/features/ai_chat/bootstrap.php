<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__, 3) . '/path.php';
}

if (!function_exists('selectOne')) {
    require_once ROOT_PATH . '/app/database/db.php';
}

if (!defined('ADMIN_AI_CHAT_ASSISTANT_NAME')) {
    define('ADMIN_AI_CHAT_ASSISTANT_NAME', 'תזרי מנהל');
}

if (!function_exists('admin_ai_chat_user_initials')) {
    function admin_ai_chat_user_initials(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $firstName = trim((string) ($_SESSION['first_name'] ?? ''));
        $lastName = trim((string) ($_SESSION['last_name'] ?? ''));
        $firstInitial = $firstName !== '' ? mb_substr($firstName, 0, 1, 'UTF-8') : 'מ';
        $lastInitial = $lastName !== '' ? mb_substr($lastName, 0, 1, 'UTF-8') : 'נ';

        return $firstInitial . $lastInitial;
    }
}

if (!function_exists('admin_ai_chat_get_context')) {
    function admin_ai_chat_get_context(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $homeId = (int) ($_SESSION['home_id'] ?? 0);
        return [
            'ok' => true,
            'user_id' => $userId,
            'home_id' => $homeId,
            'base_url' => defined('BASE_URL') ? BASE_URL : '/',
        ];
    }
}

if (!function_exists('admin_ai_chat_render_launcher_button')) {
    function admin_ai_chat_render_launcher_button(): void
    {
        $an = ADMIN_AI_CHAT_ASSISTANT_NAME;
        $inlineStyle = 'position:fixed;bottom:24px;left:24px;z-index:1000;'
            . 'background:linear-gradient(135deg,#6c5ce7 0%,#5b52d6 100%);'
            . 'border:0;border-radius:999px;cursor:pointer;'
            . 'width:52px;height:52px;display:inline-flex;align-items:center;justify-content:center;'
            . 'box-shadow:0 4px 16px rgba(91,82,214,0.35),0 2px 4px rgba(0,0,0,0.1);'
            . 'color:#fff;font-size:1.25rem;transition:transform .2s ease,box-shadow .2s ease;';
        echo '<button type="button" id="adminAiChatLauncher" class="admin-ai-chat-launcher" style="' . $inlineStyle . '" title="צ\'אט עם ' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '" aria-label="פתיחת צ\'אט עם ' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>';
        echo '</button>';
    }
}

if (!function_exists('admin_ai_chat_render_modal')) {
    function admin_ai_chat_render_modal(): void
    {
        $an = ADMIN_AI_CHAT_ASSISTANT_NAME;
        $userInitials = admin_ai_chat_user_initials();
        echo '<div id="adminAiChatModal" class="admin-ai-chat-modal" aria-hidden="true" data-admin-ai-chat-assistant="' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '" data-admin-ai-chat-user-initials="' . htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') . '">';
        echo '  <div class="admin-ai-chat-backdrop" data-admin-ai-chat-close="1"></div>';
        echo '  <section class="admin-ai-chat-panel" role="dialog" aria-modal="true" aria-labelledby="adminAiChatTitle">';
        echo '    <header class="admin-ai-chat-header">';
        echo '      <div class="admin-ai-chat-header-info">';
        echo '        <div class="admin-ai-chat-avatar" aria-hidden="true"><i class="fa-solid fa-robot"></i></div>';
        echo '        <div class="admin-ai-chat-header-text">';
        echo '          <div id="adminAiChatTitle" class="admin-ai-chat-agent-name">' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '          <div class="admin-ai-chat-agent-status"><span class="admin-ai-chat-status-dot" aria-hidden="true"></span><span>מחובר · מקוון</span></div>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="admin-ai-chat-header-actions">';
        echo '        <button type="button" class="admin-ai-chat-history-toggle" id="adminAiChatHistoryToggle" aria-label="פתיחת רשימת שיחות"><i class="fa-solid fa-list"></i></button>';
        echo '        <button type="button" class="admin-ai-chat-close" id="adminAiChatClose" aria-label="סגור"><i class="fa-solid fa-xmark"></i></button>';
        echo '      </div>';
        echo '    </header>';
        echo '    <div class="admin-ai-chat-layout">';
        echo '      <button type="button" class="admin-ai-chat-history-overlay" id="adminAiChatHistoryOverlay" aria-label="סגירת רשימת שיחות"></button>';
        echo '      <aside class="admin-ai-chat-history">';
        echo '        <div class="admin-ai-chat-history-head">';
        echo '          <button type="button" class="admin-ai-chat-history-close" id="adminAiChatHistoryClose" aria-label="סגירת רשימת שיחות"><i class="fa-solid fa-chevron-right"></i></button>';
        echo '          <button type="button" class="admin-ai-chat-new-btn" id="adminAiChatNewBtn"><i class="fa-solid fa-plus"></i> שיחה חדשה</button>';
        echo '        </div>';
        echo '        <div class="admin-ai-chat-history-list" id="adminAiChatHistoryList" aria-label="היסטוריית שיחות"></div>';
        echo '        <div class="admin-ai-chat-history-foot">';
        echo '          <button type="button" class="admin-ai-chat-danger-btn" id="adminAiChatDeleteAllBtn"><i class="fa-regular fa-trash-can"></i> מחיקת כל השיחות</button>';
        echo '        </div>';
        echo '      </aside>';
        echo '      <main class="admin-ai-chat-main">';
        echo '        <div class="admin-ai-chat-chat-title admin-ai-chat-chat-title--hidden" id="adminAiChatCurrentTitle" aria-live="polite"></div>';
        echo '        <div class="admin-ai-chat-messages" id="adminAiChatMessages" aria-live="polite"></div>';
        echo '        <div class="admin-ai-chat-composer" id="adminAiChatComposer">';
        echo '          <form id="adminAiChatForm" class="admin-ai-chat-form">';
        echo '            <input type="text" id="adminAiChatInput" class="admin-ai-chat-input" maxlength="1500" autocomplete="off" placeholder="מה תרצו לשאול?" enterkeyhint="send">';
        echo '            <button type="button" class="admin-ai-chat-stop" id="adminAiChatStopBtn" hidden aria-label="עצירת שליחה והפסקת חשיבה"><i class="fa-solid fa-stop" aria-hidden="true"></i></button>';
        echo '            <button type="submit" class="admin-ai-chat-send" id="adminAiChatSendBtn" aria-label="שליחת הודעה"><i class="fa-solid fa-paper-plane"></i></button>';
        echo '          </form>';
        echo '        </div>';
        echo '      </main>';
        echo '    </div>';
        echo '  </section>';
        echo '</div>';
    }
}

if (!function_exists('admin_ai_chat_get_api_token')) {
    /**
     * שולף את api_token של המנהל הנוכחי מטבלת users.
     * מוחזר רק ל-program_admin. אחרת מחרוזת ריקה.
     */
    function admin_ai_chat_get_api_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if ($role !== 'program_admin') {
            return '';
        }
        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            return '';
        }
        global $conn;
        if (!($conn instanceof mysqli)) {
            return '';
        }
        $stmt = $conn->prepare('SELECT api_token FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $token = (string) ($row['api_token'] ?? '');
        return $token;
    }
}

if (!function_exists('admin_ai_chat_get_schema_json')) {
    function admin_ai_chat_get_schema_json(): string
    {
        require_once __DIR__ . '/services/agent_schema.php';
        return json_encode(admin_ai_agent_build_schema_for_js(), JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('admin_ai_chat_asset_version')) {
    /**
     * מחזיר חתימת גרסה לקובץ סטטי לצורך cache-busting אוטומטי בלייב.
     * מתבסס על filemtime כך שכל עדכון קובץ מאלץ טעינה מחדש בדפדפן.
     */
    function admin_ai_chat_asset_version(string $relativePath): string
    {
        $full = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        $mtime = @filemtime($full);
        return (string) ($mtime > 0 ? $mtime : time());
    }
}

if (!function_exists('admin_ai_chat_render_assets')) {
    function admin_ai_chat_render_assets(): void
    {
        $token = admin_ai_chat_get_api_token();
        $schemaJson = admin_ai_chat_get_schema_json();
        $cssVer = admin_ai_chat_asset_version('admin/features/ai_chat/assets/admin-ai-chat.css');
        $jsVer = admin_ai_chat_asset_version('admin/features/ai_chat/assets/admin-ai-chat.js');
        echo '<script>window.ADMIN_AI_CHAT_BASE_URL = ' . json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_ASSISTANT_NAME = ' . json_encode(ADMIN_AI_CHAT_ASSISTANT_NAME, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_API_TOKEN = ' . json_encode($token, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_SCHEMA = ' . $schemaJson . ';</script>';
        echo '<script>window.ADMIN_AI_CHAT_USER_INITIALS = ' . json_encode(admin_ai_chat_user_initials(), JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<script>if(typeof window.ADMIN_AI_PAGE_ENTITY==="undefined"){window.ADMIN_AI_PAGE_ENTITY=null;}</script>';
        echo '<link rel="stylesheet" href="' . BASE_URL . 'admin/features/ai_chat/assets/admin-ai-chat.css?v=' . $cssVer . '">';
        echo '<script src="' . BASE_URL . 'admin/features/ai_chat/assets/admin-ai-chat.js?v=' . $jsVer . '" defer></script>';
    }
}

if (!function_exists('admin_ai_chat_render_lazy_loader')) {
    function admin_ai_chat_render_lazy_loader(): void
    {
        $token = admin_ai_chat_get_api_token();
        $schemaJson = admin_ai_chat_get_schema_json();
        $loaderVer = admin_ai_chat_asset_version('admin/features/ai_chat/assets/admin-ai-chat-loader.js');
        $cssVer = admin_ai_chat_asset_version('admin/features/ai_chat/assets/admin-ai-chat.css');
        $jsVer = admin_ai_chat_asset_version('admin/features/ai_chat/assets/admin-ai-chat.js');
        echo '<script>window.ADMIN_AI_CHAT_BASE_URL = ' . json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_ASSISTANT_NAME = ' . json_encode(ADMIN_AI_CHAT_ASSISTANT_NAME, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_API_TOKEN = ' . json_encode($token, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.ADMIN_AI_CHAT_SCHEMA = ' . $schemaJson . ';';
        echo 'window.ADMIN_AI_CHAT_ASSET_VER = ' . json_encode(['css' => $cssVer, 'js' => $jsVer], JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<script>window.ADMIN_AI_CHAT_USER_INITIALS = ' . json_encode(admin_ai_chat_user_initials(), JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<script>if(typeof window.ADMIN_AI_PAGE_ENTITY==="undefined"){window.ADMIN_AI_PAGE_ENTITY=null;}</script>';
        echo '<script src="' . BASE_URL . 'admin/features/ai_chat/assets/admin-ai-chat-loader.js?v=' . $loaderVer . '" defer></script>';
    }
}

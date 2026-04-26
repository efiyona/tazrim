<?php
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__, 3) . '/path.php';
}

if (!function_exists('selectOne')) {
    require_once ROOT_PATH . '/app/database/db.php';
}

if (!defined('AI_CHAT_ASSISTANT_NAME')) {
    define('AI_CHAT_ASSISTANT_NAME', 'התזרים החכם');
}

if (!function_exists('ai_chat_user_initials')) {
    function ai_chat_user_initials(): string
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

if (!function_exists('ai_chat_get_context')) {
    function ai_chat_get_context(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $homeId = (int) ($_SESSION['home_id'] ?? 0);
        if (!function_exists('selectOne')) {
            require_once ROOT_PATH . '/app/database/db.php';
        }
        if (file_exists(ROOT_PATH . '/app/functions/email_verification_runtime.php')) {
            if (!function_exists('tazrim_user_email_is_unverified')) {
                require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
            }
            if (function_exists('tazrim_email_verified_column_exists') && tazrim_email_verified_column_exists() && function_exists('selectOne')) {
                $u = selectOne('users', ['id' => $userId]);
                if (!$u) {
                    return ['ok' => false, 'error' => 'unauthorized'];
                }
                if (tazrim_user_email_is_unverified($u)) {
                    return ['ok' => false, 'error' => 'email_verification_required'];
                }
            }
        }
        return [
            'ok' => true,
            'user_id' => $userId,
            'home_id' => $homeId,
            'base_url' => defined('BASE_URL') ? BASE_URL : '/',
        ];
    }
}

if (!function_exists('ai_chat_assets_version_token')) {
    /** ערך גרסה לשבירת מטמון (חותמת שינוי קבצי JS/CSS) */
    function ai_chat_assets_version_token(): string
    {
        static $v = null;
        if ($v !== null) {
            return $v;
        }
        $js = __DIR__ . '/assets/ai-chat.js';
        $css = __DIR__ . '/assets/ai-chat.css';
        $t = max((int) @filemtime($js), (int) @filemtime($css));
        $v = $t > 0 ? (string) $t : '';

        return $v;
    }
}

if (!function_exists('ai_chat_assets_cache_query')) {
    /** פרמטר query (?v=…) לשבירת מטמון CDN/דפדפן */
    function ai_chat_assets_cache_query(): string
    {
        $tok = ai_chat_assets_version_token();

        return $tok !== '' ? '?v=' . rawurlencode($tok) : '';
    }
}

if (!function_exists('ai_chat_render_launcher_button')) {
    function ai_chat_render_launcher_button(): void
    {
        $an = AI_CHAT_ASSISTANT_NAME;
        echo '<button type="button" id="aiChatLauncher" class="icon-btn ai-chat-launcher" title="צ\'אט עם ' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '" aria-label="פתיחת צ\'אט עם ' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>';
        echo '</button>';
    }
}

if (!function_exists('ai_chat_render_modal')) {
    function ai_chat_render_modal(): void
    {
        $an = AI_CHAT_ASSISTANT_NAME;
        $userInitials = ai_chat_user_initials();
        echo '<div id="aiChatModal" class="ai-chat-modal" aria-hidden="true" data-ai-chat-assistant="' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '" data-ai-chat-user-initials="' . htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') . '">';
        echo '  <div class="ai-chat-backdrop" data-ai-chat-close="1"></div>';
        echo '  <section class="ai-chat-panel" role="dialog" aria-modal="true" aria-labelledby="aiChatTitle">';
        echo '    <header class="ai-chat-header">';
        echo '      <div class="ai-chat-header-info">';
        echo '        <div class="ai-chat-avatar" aria-hidden="true"><i class="fa-solid fa-robot"></i></div>';
        echo '        <div class="ai-chat-header-text">';
        echo '          <div id="aiChatTitle" class="ai-chat-agent-name">' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '          <div class="ai-chat-agent-status"><span class="ai-chat-status-dot" aria-hidden="true"></span><span>מחובר · מקוון</span></div>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div class="ai-chat-header-actions">';
        echo '        <button type="button" class="ai-chat-history-toggle" id="aiChatHistoryToggle" aria-label="פתיחת רשימת שיחות"><i class="fa-solid fa-list"></i></button>';
        echo '        <button type="button" class="ai-chat-close" id="aiChatClose" aria-label="סגור"><i class="fa-solid fa-xmark"></i></button>';
        echo '      </div>';
        echo '    </header>';
        echo '    <div class="ai-chat-layout">';
        echo '      <button type="button" class="ai-chat-history-overlay" id="aiChatHistoryOverlay" aria-label="סגירת רשימת שיחות"></button>';
        echo '      <aside class="ai-chat-history">';
        echo '        <div class="ai-chat-history-head">';
        echo '          <button type="button" class="ai-chat-history-close" id="aiChatHistoryClose" aria-label="סגירת רשימת שיחות"><i class="fa-solid fa-chevron-right"></i></button>';
        echo '          <button type="button" class="ai-chat-new-btn" id="aiChatNewBtn"><i class="fa-solid fa-plus"></i> שיחה חדשה</button>';
        echo '        </div>';
        echo '        <div class="ai-chat-history-list" id="aiChatHistoryList" aria-label="היסטוריית שיחות"></div>';
        echo '        <div class="ai-chat-history-foot">';
        echo '          <button type="button" class="ai-chat-danger-btn" id="aiChatDeleteAllBtn"><i class="fa-regular fa-trash-can"></i> מחיקת כל השיחות</button>';
        echo '        </div>';
        echo '      </aside>';
        echo '      <main class="ai-chat-main">';
        echo '        <div class="ai-chat-chat-title ai-chat-chat-title--hidden" id="aiChatCurrentTitle" aria-live="polite"></div>';
        echo '        <div class="ai-chat-messages" id="aiChatMessages" aria-live="polite"><div class="ai-chat-empty">שאלו כאן — המערכת תנתב את השאלה אוטומטית.</div></div>';
        echo '        <div class="ai-chat-composer" id="aiChatComposer">';
        echo '          <form id="aiChatForm" class="ai-chat-form">';
        echo '            <input type="text" id="aiChatInput" class="ai-chat-input" maxlength="1500" autocomplete="off" placeholder="מה תרצו לשאול?" enterkeyhint="send">';
        echo '            <button type="submit" class="ai-chat-send" id="aiChatSendBtn" aria-label="שליחת הודעה"><i class="fa-solid fa-paper-plane"></i></button>';
        echo '          </form>';
        echo '        </div>';
        echo '      </main>';
        echo '    </div>';
        echo '  </section>';
        echo '</div>';
    }
}

if (!function_exists('ai_chat_render_assets')) {
    function ai_chat_render_assets(): void
    {
        require_once __DIR__ . '/services/allowed_chat_pages.php';
        $pageAllow = json_encode(ai_chat_allowed_page_paths(), JSON_UNESCAPED_UNICODE);
        echo '<script>window.AI_CHAT_BASE_URL = ' . json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_ASSISTANT_NAME = ' . json_encode(AI_CHAT_ASSISTANT_NAME, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_USER_INITIALS = ' . json_encode(ai_chat_user_initials(), JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_ASSETS_VER=' . json_encode(ai_chat_assets_version_token(), JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_PAGE_ALLOWLIST=' . $pageAllow . ';</script>';
        $vq = ai_chat_assets_cache_query();
        echo '<link rel="stylesheet" href="' . BASE_URL . 'app/features/ai_chat/assets/ai-chat.css' . $vq . '">';
        echo '<script src="' . BASE_URL . 'app/features/ai_chat/assets/ai-chat.js' . $vq . '" defer></script>';
    }
}

/** טעינה עצלה: רק כפתור פתיחה + סקריפט טעינה — CSS/JS/מודאל בלחיצה ראשונה */
if (!function_exists('ai_chat_render_lazy_loader')) {
    function ai_chat_render_lazy_loader(): void
    {
        require_once __DIR__ . '/services/allowed_chat_pages.php';
        $pageAllowLazy = json_encode(ai_chat_allowed_page_paths(), JSON_UNESCAPED_UNICODE);
        echo '<script>window.AI_CHAT_BASE_URL = ' . json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_ASSISTANT_NAME = ' . json_encode(AI_CHAT_ASSISTANT_NAME, JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_USER_INITIALS = ' . json_encode(ai_chat_user_initials(), JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_ASSETS_VER=' . json_encode(ai_chat_assets_version_token(), JSON_UNESCAPED_UNICODE) . ';';
        echo 'window.AI_CHAT_PAGE_ALLOWLIST=' . $pageAllowLazy . ';</script>';
        $lv = (int) @filemtime(__DIR__ . '/assets/ai-chat-loader.js');
        $lq = $lv > 0 ? '?v=' . rawurlencode((string) $lv) : '';
        echo '<script src="' . BASE_URL . 'app/features/ai_chat/assets/ai-chat-loader.js' . $lq . '" defer></script>';
    }
}

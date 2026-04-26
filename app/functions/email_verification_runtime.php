<?php
/**
 * אימות מייל: בדיקות, חסימת UI ו-API, ו-allowlist.
 * דורש ש-db.php (selectOne) כבר נטען.
 */

if (!function_exists('tazrim_user_email_is_verified')) {
    function tazrim_user_email_is_verified(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (!array_key_exists('email_verified_at', $user)) {
            // עמודה עדיין לא נפרסה — לא חוסמים
            return true;
        }
        $v = $user['email_verified_at'] ?? null;

        return $v !== null && $v !== '' && $v !== '0000-00-00 00:00:00';
    }
}

if (!function_exists('tazrim_user_email_is_unverified')) {
    function tazrim_user_email_is_unverified(?array $user): bool
    {
        if (!$user) {
            return true;
        }
        if (!array_key_exists('email_verified_at', $user)) {
            return false;
        }

        return !tazrim_user_email_is_verified($user);
    }
}

if (!function_exists('tazrim_email_verified_column_exists')) {
    function tazrim_email_verified_column_exists(): bool
    {
        global $conn;
        if (!$conn) {
            return false;
        }
        static $yes = null;
        if ($yes !== null) {
            return $yes;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'email_verified_at'");
        $yes = (bool) ($r && mysqli_num_rows($r) > 0);

        return $yes;
    }
}

if (!function_exists('tazrim_email_ui_exempt_page')) {
    /**
     * דפים בלי באנר אימות: onboarding לפני קטגוריות (בברירת מחדל: welcome)
     */
    function tazrim_email_ui_exempt_page(string $basename): bool
    {
        $exempt = [
            'welcome.php',
            'accept_tos.php',
            'user_profile.php',
            'manage_home.php',
        ];
        if (in_array($basename, $exempt, true)) {
            return true;
        }
        if ($basename === 'setup_welcome.php' || $basename === 'logout.php') {
            return true;
        }
        if ($basename === 'login.php' || $basename === 'register.php' || $basename === 'forgot.php') {
            return true;
        }

        return false;
    }
}

if (!function_exists('tazrim_session_refresh_email_status')) {
    function tazrim_session_refresh_email_status(int $userId, ?array $userRow = null): void
    {
        if (!tazrim_email_verified_column_exists()) {
            return;
        }
        if ($userRow === null) {
            if (!function_exists('selectOne')) {
                return;
            }
            $userRow = selectOne('users', ['id' => $userId]);
        }
        if ($userRow) {
            $_SESSION['user_email'] = (string) ($userRow['email'] ?? $_SESSION['user_email'] ?? '');
            if (tazrim_user_email_is_verified($userRow)) {
                $_SESSION['email_verified_at'] = (string) ($userRow['email_verified_at'] ?? '');
            } else {
                $_SESSION['email_verified_at'] = null;
            }
        }
    }
}

if (!function_exists('tazrim_session_mark_email_verified')) {
    function tazrim_session_mark_email_verified(string $atDatetime): void
    {
        $_SESSION['email_verified_at'] = $atDatetime;
    }
}

if (!function_exists('tazrim_should_block_ui_for_email')) {
    function tazrim_should_block_ui_for_email(string $currentPageBasename): bool
    {
        if (tazrim_email_ui_exempt_page($currentPageBasename)) {
            return false;
        }
        if (!tazrim_email_verified_column_exists() || !isset($_SESSION['id']) || (int) $_SESSION['id'] <= 0) {
            return false;
        }
        if (!function_exists('selectOne')) {
            return false;
        }
        $u = selectOne('users', ['id' => (int) $_SESSION['id']]);
        if (!$u) {
            return false;
        }
        tazrim_session_refresh_email_status((int) $_SESSION['id'], $u);

        return tazrim_user_email_is_unverified($u);
    }
}

if (!function_exists('tazrim_ajax_basename_email_whitelist')) {
    function tazrim_ajax_basename_email_whitelist(): array
    {
        $base = [
            'email_verification.php',
            'process_tos.php',
            'setup_welcome.php',
            'setup_shopping_categories.php',
        ];
        $settings = [
            'update_profile.php',
            'profile_password_reset.php',
            'save_notification_preferences.php',
            'delete_account.php',
            'get_ios_tazrim_panel.php',
            'delete_api_token.php',
            'generate_api_token.php',
            'save_subscription.php',
            'delete_subscription.php',
            'update_home.php',
            'reset_home_balance.php',
            'delete_recurring.php',
            'fetch_manage_home_recurring.php',
            'save_recurring.php',
            'fetch_manage_home_categories.php',
            'save_category.php',
            'fetch_manage_home_shopping_stores.php',
            'save_shopping_store.php',
            'delete_category.php',
            'delete_shopping_store.php',
            'fetch_notifications.php',
            'mark_notifications_read.php',
            'submit_feedback.php',
        ];

        return array_merge($base, $settings);
    }
}

if (!function_exists('tazrim_ajax_exit_if_email_unverified_for_session')) {
    function tazrim_ajax_exit_if_email_unverified_for_session(): void
    {
        if (empty($_SERVER['SCRIPT_NAME']) || strpos((string) $_SERVER['SCRIPT_NAME'], '/app/ajax/') === false) {
            return;
        }
        if (!tazrim_email_verified_column_exists() || !isset($_SESSION['id']) || (int) $_SESSION['id'] <= 0) {
            return;
        }
        $base = basename((string) $_SERVER['SCRIPT_NAME']);
        if (in_array($base, tazrim_ajax_basename_email_whitelist(), true)) {
            return;
        }
        if (!function_exists('selectOne')) {
            return;
        }
        $u = selectOne('users', ['id' => (int) $_SESSION['id']]);
        if ($u && tazrim_user_email_is_unverified($u)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'code' => 'email_verification_required',
                'message' => 'יש לאמת את כתובת המייל לפני המשך השימוש במערכת.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('tazrim_api_v1_json_exit_if_email_unverified')) {
    function tazrim_api_v1_json_exit_if_email_unverified($user): void
    {
        if (!$user || !tazrim_email_verified_column_exists()) {
            return;
        }
        if (tazrim_user_email_is_unverified($user)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'code' => 'email_verification_required',
                'message' => 'יש לאמת כתובת מייל לפני שימוש באפליקציה. התחברו מהאתר (או האפליקציה) והשלימו אימות.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

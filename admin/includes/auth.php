<?php

/**
 * טוקן CSRF לפעולות POST באדמין.
 */
function tazrim_admin_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function tazrim_admin_csrf_validate(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($token === null || $token === '' || empty($_SESSION['admin_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['admin_csrf_token'], $token);
}

/**
 * טוען משתמש מהמסד ומאמת program_admin.
 */
function tazrim_admin_current_user_row(): ?array
{
    global $conn;
    if (!isset($_SESSION['id'])) {
        return null;
    }
    $uid = (int) $_SESSION['id'];
    if ($uid <= 0) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, role, first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: null;
}

function tazrim_admin_is_program_admin(): bool
{
    $user = tazrim_admin_current_user_row();
    return $user && isset($user['role']) && $user['role'] === 'program_admin';
}

/**
 * מסנכרן את תפקיד הסשן לאחר אימות מהמסד.
 */
function tazrim_admin_sync_session_role(array $user): void
{
    if (isset($user['role'])) {
        $_SESSION['role'] = $user['role'];
    }
}

/**
 * חובה: משתמש מחובר + program_admin. אחרת הפניה.
 */
function tazrim_admin_require(): void
{
    if (!isset($_SESSION['id'])) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit;
    }
    $user = tazrim_admin_current_user_row();
    if (!$user || ($user['role'] ?? '') !== 'program_admin') {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
    tazrim_admin_sync_session_role($user);
}

/**
 * כמו tazrim_admin_require אבל ל-endpoints של JSON (AJAX).
 */
function tazrim_admin_require_json(): void
{
    if (!isset($_SESSION['id'])) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'נדרשת התחברות.'], 401);
    }
    $user = tazrim_admin_current_user_row();
    if (!$user || ($user['role'] ?? '') !== 'program_admin') {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'אין הרשאת מנהל מערכת.'], 403);
    }
    tazrim_admin_sync_session_role($user);
}

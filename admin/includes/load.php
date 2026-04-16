<?php
/**
 * טעינה משותפת: path, DB, סשן (כולל remember_token), auth, helpers.
 * לא מבצע הפניות — רק מכין את הסשן.
 */
require_once dirname(__DIR__, 2) . '/path.php';
require_once ROOT_PATH . '/app/database/db.php';

if (!isset($_SESSION['id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $user = selectOne('users', ['remember_token' => $token]);
    if ($user) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['home_id'] = $user['home_id'];
        $_SESSION['role'] = $user['role'];
    } else {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/crud.php';

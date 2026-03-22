<?php
// 1. התחלת סשן במידה ולא קיים
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. בדיקה אם המשתמש מחובר (דרך סשן או דרך קוקי)
if (!isset($_SESSION['id'])) {
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $user = selectOne('users', ['remember_token' => $token]);
        
        if ($user) {
            $_SESSION['id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['home_id'] = $user['home_id'];
            $_SESSION['role'] = $user['role'];
        } else {
            setcookie('remember_token', '', time() - 3600, "/");
            header('location: ' . BASE_URL . 'pages/login.php');
            exit();
        }
    } else {
        header('location: ' . BASE_URL . 'pages/login.php');
        exit();
    }
}

if (isset($_SESSION['home_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // רשימת דפים שמהם לא נבצע הפניה (כדי למנוע לולאה אינסופית)
    $excluded_pages = ['welcome.php', 'logout.php', 'setup_welcome.php'];

    if (!in_array($current_page, $excluded_pages)) {
        // בדיקה אם קיימות קטגוריות לבית הזה
        $check_cats = selectOne('categories', ['home_id' => $_SESSION['home_id'], 'is_active' => 1]);
        
        if (!$check_cats) {
            header('location: ' . BASE_URL . 'pages/welcome.php');
            exit();
        }
    }
}
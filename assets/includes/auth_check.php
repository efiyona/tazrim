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
            $_SESSION['last_name'] = $user['last_name'];
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

// 3. הגדרת דפים מותרים שלא דורשים בדיקת קטגוריות או אישור תקנון
$current_page = basename($_SERVER['PHP_SELF']);
$excluded_pages = ['welcome.php', 'logout.php', 'setup_welcome.php', 'accept_tos.php']; // הוספנו את accept_tos.php

if (!in_array($current_page, $excluded_pages)) {
    
    // === 4. בדיקת אישור תקנון (TOS) מול הגרסה הנוכחית ===
    if (!isset($_SESSION['tos_version']) || $_SESSION['tos_version'] !== CURRENT_TOS_VERSION) {
        $user_id_for_tos = $_SESSION['id'];
        
        // שליפת האישור האחרון מהמסד
        $tos_query = "SELECT tos_version FROM tos_agreements WHERE user_id = $user_id_for_tos ORDER BY accepted_at DESC LIMIT 1";
        $tos_result = mysqli_query($conn, $tos_query);
        $tos_data = mysqli_fetch_assoc($tos_result);
        
        $latest_version = $tos_data ? $tos_data['tos_version'] : null;
        
        if ($latest_version === CURRENT_TOS_VERSION) {
            // המשתמש אישר בעבר, נשמור בסשן כדי לחסוך קריאות למסד
            $_SESSION['tos_version'] = CURRENT_TOS_VERSION;
        } else {
            // לא אישר את הגרסה העדכנית - מעבירים לדף אישור (בתוך התיקייה pages)
            header("Location: " . BASE_URL . "pages/accept_tos.php");
            exit();
        }
    }

    // === 5. בדיקה אם קיימות קטגוריות לבית הזה ===
    if (isset($_SESSION['home_id'])) {
        $check_cats = selectOne('categories', ['home_id' => $_SESSION['home_id'], 'is_active' => 1]);
        
        if (!$check_cats) {
            header('location: ' . BASE_URL . 'pages/welcome.php');
            exit();
        }
    }
}
?>
<?php
// logout.php (נמצא בתיקיית tazrim/ המרכזית)
require('path.php'); 
include(ROOT_PATH . "/app/database/db.php");

session_start();

// 1. מחיקת הטוקן ממסד הנתונים (אבטחה מקסימלית)
if (isset($_SESSION['id'])) {
    update('users', $_SESSION['id'], ['remember_token' => NULL]);
}

// 2. מחיקת העוגייה מהדפדפן (הגדרת התוקף לעבר)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/");
}

// 3. הריסת הסשן
session_unset();
session_destroy();

// הפניה לדף הלוגין
header('location: ' . BASE_URL . 'pages/login.php');
exit();
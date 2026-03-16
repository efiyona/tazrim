<?php
// בדיקה אם הסשן כבר התחיל, אם לא - מתחילים אותו
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// בדיקה האם המשתנה id קיים בסשן
if (!isset($_SESSION['id'])) {
    // המשתמש לא מחובר! שליחה לדף התחברות
    // שים לב: אנחנו משתמשים ב-BASE_URL שהגדרנו ב-path.php
    header('location: ' . BASE_URL . 'pages/login.php');
    exit();
}

// אם הגענו לכאן, המשתמש מחובר והדף ימשיך להיטען כרגיל
?>
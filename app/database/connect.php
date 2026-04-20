<?php
    // 127.0.0.1 במקום localhost — תואם CLI ו-Mac (מניעת כשל socket ב-mysqli)
    $dbHost = (defined('DB_HOST') && (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1'))
        ? '127.0.0.1'
        : DB_HOST;
    $conn = new mysqli($dbHost, DB_USER, DB_PASS, DB_NAME);

    if($conn->connect_error){
        die('Data Base Connection error: ' . $conn->connect_error);
    }

    date_default_timezone_set('Asia/Jerusalem');
    $today_il = date('Y-m-d');

    // תקנון: ראו app/functions/tos_runtime.php + טבלת tos_terms (ניהול באדמין)
?>
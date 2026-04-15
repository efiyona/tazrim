<?php
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if($conn->connect_error){
        die('Data Base Connection error: ' . $conn->connect_error);
    }

    date_default_timezone_set('Asia/Jerusalem');
    $today_il = date('Y-m-d');

    // תקנון: ראו app/functions/tos_runtime.php + טבלת tos_terms (ניהול באדמין)
?>
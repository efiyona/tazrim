<?php
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if($conn->connect_error){
        die('Data Base Connection error: ' . $conn->connect_error);
    }

    date_default_timezone_set('Asia/Jerusalem');
    $today_il = date('Y-m-d');

    // הגדרות תקנון ומדיניות פרטיות (TOS)
    define('CURRENT_TOS_VERSION', '2.0');
    define('TOS_LAST_UPDATED', 'אפריל 2026');
?>
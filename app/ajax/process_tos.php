<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // וידוא שהמשתמש מחובר
    if (!isset($_SESSION['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
        exit();
    }

    $user_id = (int)$_SESSION['id'];
    $version = mysqli_real_escape_string($conn, CURRENT_TOS_VERSION);
    $ip_address = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
    $user_agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

    // הזרקת התיעוד לטבלת ההיסטוריה (Audit Trail)
    $query = "INSERT INTO tos_agreements (user_id, tos_version, ip_address, user_agent) 
              VALUES ($user_id, '$version', '$ip_address', '$user_agent')";
              
    if (mysqli_query($conn, $query)) {
        // חשוב מאוד: עדכון הסשן הנוכחי כדי שהמשתמש יפסיק להיזרק לעמוד הזה
        $_SESSION['tos_version'] = CURRENT_TOS_VERSION;
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת האישור במסד הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'גישה לא מורשית.']);
}
?>
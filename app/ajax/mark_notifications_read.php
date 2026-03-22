<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

$home_id = $_SESSION['home_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if ($home_id && $user_id) {
    /**
     * לוגיקה חכמה: הזרקת רשומת קריאה לכל ההתראות שטרם נקראו ע"י המשתמש.
     * אנחנו משתמשים ב-INSERT IGNORE למקרה שהמשתמש פתח את התפריט פעמיים מהר.
     */
    $sql = "INSERT IGNORE INTO notification_reads (user_id, notification_id)
            SELECT $user_id, n.id
            FROM notifications n
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = $user_id
            WHERE nr.id IS NULL -- רק כאלו שאין להן רשומת קריאה עדיין
            AND (n.home_id = $home_id OR n.home_id = 0) 
            AND (n.user_id = $user_id OR n.user_id IS NULL)";
            
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
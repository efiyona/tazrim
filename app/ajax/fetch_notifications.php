<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

$home_id = $_SESSION['home_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if (!$home_id || !$user_id) {
    echo json_encode(['status' => 'error']);
    exit();
}

/**
 * השאילתה המעודכנת:
 * אנחנו מחפשים התראות שאין להן התאמה בטבלת הקריאות (nr.id IS NULL).
 * זה אומר שהמשתמש הנוכחי עוד לא פתח את הפעמון כשהן היו קיימות.
 */
$query = "SELECT n.* FROM notifications n
          LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = $user_id
          WHERE nr.id IS NULL -- מציג רק התראות שלא נקראו
          AND (n.home_id = $home_id OR n.home_id = 0) 
          AND (n.user_id = $user_id OR n.user_id IS NULL) 
          ORDER BY n.created_at DESC LIMIT 15";

$result = mysqli_query($conn, $query);
$notifications = [];

while ($row = mysqli_fetch_assoc($result)) {
    // מאחר והשאילתה מסננת מראש רק unread, אנחנו יודעים ש-is_read תמיד 0
    $row['is_read'] = 0; 
    
    $time_ago = strtotime($row['created_at']);
    $row['time_formatted'] = date('d/m H:i', $time_ago);
    
    $notifications[] = $row;
}

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications,
    'unread_count' => count($notifications) // הספירה היא פשוט כמות המערך
]);
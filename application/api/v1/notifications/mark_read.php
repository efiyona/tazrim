<?php
/**
 * סימון כל ההתראות הלא-נקראות כנקראו — תואם ל־app/ajax/mark_notifications_read.php (עם טוקן API).
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }

    $user_id = (int) ($user['id'] ?? 0);
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($user_id <= 0 || $home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא משתמש או בית.']);
        exit();
    }

    $sql = "INSERT IGNORE INTO notification_reads (user_id, notification_id)
            SELECT $user_id, n.id
            FROM notifications n
            LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = $user_id
            WHERE nr.id IS NULL
            AND (n.home_id = $home_id OR n.home_id = 0)
            AND (n.user_id = $user_id OR n.user_id IS NULL)";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'data' => []]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'עדכון נכשל']);
    }
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

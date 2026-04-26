<?php
/**
 * עדכון העדפת התראה בודדת — מקביל ל־save_notification_preferences.php (טוקן API)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
    tazrim_api_v1_json_exit_if_email_unverified($user);

    $user_id = (int) ($user['id'] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא תקין.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $pref = $body['pref'] ?? '';
    $value = isset($body['value']) ? (int) $body['value'] : null;
    if ($value !== 0 && $value !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים לא תקינים.']);
        exit();
    }

    $col = null;
    switch ($pref) {
        case 'home_transactions':
            $col = 'notify_home_transactions';
            break;
        case 'budget':
            $col = 'notify_budget';
            break;
        case 'system':
            $col = 'notify_system';
            break;
    }
    if ($col === null) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים לא תקינים.']);
        exit();
    }

    $seed = $conn->prepare(
        'INSERT INTO user_notification_preferences (user_id, notify_home_transactions, notify_budget, notify_system)
         VALUES (?, 1, 1, 1)
         ON DUPLICATE KEY UPDATE user_id = user_id'
    );
    if (!$seed) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
        exit();
    }
    $seed->bind_param('i', $user_id);
    $seed->execute();
    $seed->close();

    $sql = "UPDATE user_notification_preferences SET `$col` = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
        exit();
    }
    $stmt->bind_param('ii', $value, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'לא ניתן לשמור.']);
    }
    $stmt->close();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

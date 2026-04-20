<?php
/**
 * פרופיל משתמש + העדפות התראות (טוקן API) — מקביל ל־user_profile.php / update_profile.php
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
    require_once ROOT_PATH . '/app/helpers/phone_uniqueness.php';
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
    if ($user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא תקין.']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $notify_prefs = selectOne('user_notification_preferences', ['user_id' => $user_id]);
        $notify_home = isset($notify_prefs['notify_home_transactions']) ? (int) $notify_prefs['notify_home_transactions'] : 1;
        $notify_budget = isset($notify_prefs['notify_budget']) ? (int) $notify_prefs['notify_budget'] : 1;
        $notify_system = isset($notify_prefs['notify_system']) ? (int) $notify_prefs['notify_system'] : 1;

        echo json_encode([
            'status' => 'success',
            'data' => [
                'email' => $user['email'] ?? '',
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'nickname' => $user['nickname'] ?? '',
                'phone' => tazrim_phone_for_display($user['phone'] ?? ''),
                'notify_home_transactions' => $notify_home ? 1 : 0,
                'notify_budget' => $notify_budget ? 1 : 0,
                'notify_system' => $notify_system ? 1 : 0,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
            exit();
        }

        $first_name = trim((string) ($body['first_name'] ?? ''));
        $last_name = trim((string) ($body['last_name'] ?? ''));
        $nickname = trim((string) ($body['nickname'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));

        if ($first_name === '' || $last_name === '' || $phone === '') {
            echo json_encode(['status' => 'error', 'message' => 'אנא מלא את כל שדות החובה (שם פרטי, שם משפחה וטלפון).']);
            exit();
        }

        $phoneNorm = tazrim_normalize_phone_key($phone);
        if ($phoneNorm === '') {
            echo json_encode(['status' => 'error', 'message' => 'מספר הטלפון אינו תקין.']);
            exit();
        }
        if (tazrim_user_id_with_normalized_phone($phoneNorm, $user_id)) {
            echo json_encode(['status' => 'error', 'message' => 'מספר הטלפון כבר רשום אצל משתמש אחר במערכת.']);
            exit();
        }

        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'nickname' => $nickname,
            'phone' => $phoneNorm,
        ];

        $updateResult = update('users', $user_id, $data);
        if ($updateResult === false) {
            echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה בעדכון הנתונים במסד.']);
            exit();
        }

        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת.',
    ]);
}

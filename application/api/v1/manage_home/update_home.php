<?php
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

    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $home_name = trim((string) ($body['home_name'] ?? ''));
    if ($home_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'שם הבית לא יכול להיות ריק.']);
        exit();
    }

    $home_name_clean = mysqli_real_escape_string($conn, $home_name);
    $parts = ["name = '$home_name_clean'"];

    if (array_key_exists('show_bank_balance', $body)) {
        $show = !empty($body['show_bank_balance']) ? 1 : 0;
        $parts[] = 'show_bank_balance = ' . $show;
    }

    $update_query = 'UPDATE homes SET ' . implode(', ', $parts) . " WHERE id = $home_id";
    if (!mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
        exit();
    }

    if (array_key_exists('initial_balance', $body)) {
        $target = isset($body['initial_balance']) && is_numeric($body['initial_balance'])
            ? (float) $body['initial_balance']
            : 0.0;
        $today = date('Y-m-d');
        tazrim_apply_user_bank_balance_target($conn, $home_id, $target, $today);
    }

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

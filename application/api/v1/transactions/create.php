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
    require_once ROOT_PATH . '/app/functions/budget_overrun_push.php';

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'שיטת בקשה לא נתמכת.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }

    $token = trim($body['token'] ?? '');
    $type = $body['type'] ?? 'expense';
    $amount = isset($body['amount']) ? (float) $body['amount'] : 0;
    $category_id = isset($body['category_id']) ? (int) $body['category_id'] : 0;
    $description = trim($body['description'] ?? '');
    $transaction_date = trim($body['transaction_date'] ?? date('Y-m-d'));
    $is_recurring = !empty($body['is_recurring']);

    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    if (!in_array($type, ['expense', 'income'], true)) {
        $type = 'expense';
    }

    if ($amount <= 0 || $category_id <= 0 || $description === '') {
        echo json_encode(['status' => 'error', 'message' => 'נא למלא סכום, קטגוריה ותיאור.']);
        exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
        echo json_encode(['status' => 'error', 'message' => 'תאריך לא תקין.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }

    $user_id = (int) $user['id'];
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית למשתמש.']);
        exit();
    }

    $cat = selectOne('categories', ['id' => $category_id, 'home_id' => $home_id]);
    if (!$cat || ($cat['type'] ?? '') !== $type) {
        echo json_encode(['status' => 'error', 'message' => 'קטגוריה לא תואמת לסוג הפעולה.']);
        exit();
    }

    $desc_esc = mysqli_real_escape_string($conn, $description);
    $type_esc = mysqli_real_escape_string($conn, $type);
    $date_esc = mysqli_real_escape_string($conn, $transaction_date);

    $insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, category, description, transaction_date) 
                     VALUES ($home_id, $user_id, '$type_esc', $amount, $category_id, '$desc_esc', '$date_esc')";

    if (!mysqli_query($conn, $insert_query)) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת בשמירת הנתונים.']);
        exit();
    }

    if ($is_recurring) {
        $day_of_month = (int) date('d', strtotime($transaction_date));
        $current_month_start = date('Y-m-01');
        $insert_recurring = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, category, description, day_of_month, last_injected_month, is_active) 
                             VALUES ($home_id, $user_id, '$type_esc', $amount, $category_id, '$desc_esc', $day_of_month, '$current_month_start', 1)";
        mysqli_query($conn, $insert_recurring);
    }

    $user_name = $user['first_name'] ?? 'משתמש';
    $amount_formatted = number_format($amount, 2);
    $notif_title = $user_name;
    $notif_msg = "הוסיף פעולה חדשה: <span class='notif-bold'>$description</span> בסך $amount_formatted ₪";
    addNotification($home_id, $notif_title, $notif_msg, 'info', null);

    if ($type === 'expense') {
        $push_title = 'הוצאה חדשה בתזרים 💸';
        $action_word = 'הוסיף/ה הוצאה של';
    } else {
        $push_title = 'הכנסה חדשה בתזרים 💰';
        $action_word = 'הוסיף/ה הכנסה של';
    }
    $push_body = "$user_name $action_word $amount_formatted ₪ עבור '$description'.";
    sendPushToHome($home_id, $user_id, $push_title, $push_body, BASE_URL);

    if ($type === 'expense') {
        maybeSendBudgetOverrunPush($home_id, $category_id);
    }

    echo json_encode(['status' => 'success', 'data' => ['message' => 'הפעולה נשמרה.']]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

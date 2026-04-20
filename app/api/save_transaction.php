<?php
// כותרות אבטחה ו-CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/budget_overrun_push.php'; // פוש + maybeSendBudgetOverrunPush
require_once ROOT_PATH . '/app/functions/currency.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = $_POST['api_token'] ?? '';
$currency_code = tazrim_normalize_currency_code($_POST['currency_code'] ?? 'ILS');
$raw_amount = trim((string) ($_POST['amount'] ?? '0'));
$normalized_amount = preg_replace('/[^0-9.,-]/', '', $raw_amount);
$normalized_amount = str_replace(',', '', $normalized_amount);
$amount = is_numeric($normalized_amount) ? (float) $normalized_amount : 0;
$debug_run_id = uniqid('shortcut_tx_', true);

$description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
$category_id = (int)($_POST['category_id'] ?? 0);

// קליטת סוג הפעולה מהאייפון. אם לא נשלח, ברירת המחדל היא הוצאה.
$type = $_POST['type'] ?? 'expense';

// אבטחה: מוודאים שזה רק הוצאה או הכנסה
if (!in_array($type, ['expense', 'income'])) {
    $type = 'expense';
}

if (empty($token) || $amount <= 0 || empty($description) || $category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid data.']);
    exit();
}

$token_query = "SELECT user_id, home_id FROM api_tokens WHERE token = '$token' LIMIT 1";
$token_result = mysqli_query($conn, $token_query);

if (mysqli_num_rows($token_result) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}

$auth_data = mysqli_fetch_assoc($token_result);
$home_id = $auth_data['home_id'];
$user_id = $auth_data['user_id'];

$cat_check = mysqli_query(
    $conn,
    "SELECT id, type FROM categories WHERE id = $category_id AND home_id = $home_id AND is_active = 1 LIMIT 1"
);
$cat_row = $cat_check ? mysqli_fetch_assoc($cat_check) : null;
if (!$cat_row || ($cat_row['type'] ?? '') !== $type) {
    echo json_encode(['status' => 'error', 'message' => 'Category does not match the selected transaction type.']);
    exit();
}

$transaction_date = date('Y-m-d');

// #region agent log
tazrim_debug_log('app/api/save_transaction.php:69', 'Shortcut transaction parsed input', [
    'currency_code' => $currency_code,
    'raw_amount' => substr($raw_amount, 0, 32),
    'normalized_amount' => $normalized_amount,
    'amount' => $amount,
    'type' => $type,
    'category_id' => $category_id,
], 'H2', $debug_run_id);
// #endregion

try {
    $conversion = tazrim_convert_amount_to_ils($conn, $amount, $currency_code);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch exchange rate. Please try again shortly.']);
    exit();
}

$amount_ils = (float) $conversion['converted_amount'];
$currency_code_esc = mysqli_real_escape_string($conn, $currency_code);

// הזרקת הפעולה למערכת
$insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, currency_code, category, description, transaction_date) 
                 VALUES ($home_id, $user_id, '$type', $amount_ils, '$currency_code_esc', $category_id, '$description', '$transaction_date')";

if (mysqli_query($conn, $insert_query)) {
    $today_ledger = date('Y-m-d');
    $newLedgerRow = [
        'type' => $type,
        'amount' => $amount_ils,
        'transaction_date' => $transaction_date,
    ];
    tazrim_after_transaction_row_change($conn, (int) $home_id, null, $newLedgerRow, $today_ledger);

    mysqli_query($conn, "UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = '$token'");

    $user_data = selectOne('users', ['id' => $user_id]);
    $user_name = $user_data['first_name'] ?? 'משתמש';
    $amount_formatted = number_format($amount_ils, 0);
    
    // התאמת טקסט ההתראה הפנימית באפליקציה
    $action_text = ($type === 'income') ? 'הכנסה חדשה' : 'הוצאה חדשה';
    $emoji = ($type === 'income') ? '💰' : '💳';
    
    $notif_msg = "הוסיף $action_text מהאייפון $emoji: <span class='notif-bold'>$description</span> בסך $amount_formatted ₪";
    
    addNotification($home_id, "פעולה מהירה", $notif_msg, 'info', null);

    // ==========================================
    // 1. שליחת התראת Push לשאר בני הבית (על הפעולה)
    // ==========================================
    if ($type === 'expense') {
        $push_title = "הוצאה חדשה (מקיצור הדרך) ⚡";
        $action_word = "הוסיף/ה הוצאה של";
    } else {
        $push_title = "הכנסה חדשה (מקיצור הדרך) ⚡";
        $action_word = "הוסיף/ה הכנסה של";
    }

    $push_body = "$user_name $action_word $amount_formatted ₪ עבור '$description'.";
    sendPushToHome($home_id, $user_id, $push_title, $push_body, BASE_URL);

    // ==========================================
    // 2. בדיקת חריגה מתקציב הקטגוריה (Budget Alert)
    // ==========================================
    if ($type === 'expense') {
        maybeSendBudgetOverrunPush($home_id, $category_id);
    }
    // ==========================================

    echo json_encode(['status' => 'success', 'message' => 'Transaction saved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save transaction. Database error.']);
}
?>

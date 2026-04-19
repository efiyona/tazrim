<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/currency.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit;
}

$home_id = $_SESSION['home_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if (!$home_id || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
    exit;
}

$recurring_id = !empty($_POST['recurring_id']) ? (int) $_POST['recurring_id'] : 0;
$type = ($_POST['rec_type'] ?? 'expense') === 'income' ? 'income' : 'expense';
$category_id = isset($_POST['rec_category']) ? (int) $_POST['rec_category'] : 0;
$amount = isset($_POST['rec_amount']) ? (float) $_POST['rec_amount'] : 0;
$currency_code = tazrim_normalize_currency_code($_POST['currency_code'] ?? 'ILS');
$debug_run_id = uniqid('save_rec_', true);
$description = mysqli_real_escape_string($conn, trim($_POST['rec_description'] ?? ''));

$day_of_month = 0;
if (!empty($_POST['transaction_date'])) {
    $ts = strtotime($_POST['transaction_date']);
    if ($ts) {
        $day_of_month = (int) date('d', $ts);
    }
}

if ($amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נא להזין סכום חיובי.']);
    exit;
}
if ($description === '') {
    echo json_encode(['status' => 'error', 'message' => 'נא להזין תיאור.']);
    exit;
}
if ($category_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נא לבחור קטגוריה.']);
    exit;
}
if ($day_of_month < 1 || $day_of_month > 31) {
    echo json_encode(['status' => 'error', 'message' => 'יום בחודש חייב להיות בין 1 ל־31.']);
    exit;
}

$cat_check = mysqli_query(
    $conn,
    "SELECT id, type FROM categories WHERE id = $category_id AND home_id = $home_id AND is_active = 1 LIMIT 1"
);
$cat_row = $cat_check ? mysqli_fetch_assoc($cat_check) : null;
if (!$cat_row) {
    echo json_encode(['status' => 'error', 'message' => 'קטגוריה לא תקינה.']);
    exit;
}
if ($cat_row['type'] !== $type) {
    echo json_encode(['status' => 'error', 'message' => 'הקטגוריה אינה תואמת לסוג הפעולה.']);
    exit;
}

// #region agent log
tazrim_debug_log('app/ajax/save_recurring.php:66', 'Recurring template payload accepted', [
    'recurring_id' => $recurring_id,
    'type' => $type,
    'amount' => $amount,
    'currency_code' => $currency_code,
    'category_id' => $category_id,
    'day_of_month' => $day_of_month,
], 'H4', $debug_run_id);
// #endregion

if ($recurring_id > 0) {
    $verify = mysqli_query(
        $conn,
        "SELECT id FROM recurring_transactions WHERE id = $recurring_id AND home_id = $home_id LIMIT 1"
    );
    if (!$verify || mysqli_num_rows($verify) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'פעולה קבועה לא נמצאה.']);
        exit;
    }

    $currency_code_esc = mysqli_real_escape_string($conn, $currency_code);
    $q = "UPDATE recurring_transactions SET type='$type', amount=$amount, currency_code='$currency_code_esc', category=$category_id, description='$description', day_of_month=$day_of_month WHERE id=$recurring_id AND home_id=$home_id";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בעדכון הנתונים.']);
    }
} else {
    $currency_code_esc = mysqli_real_escape_string($conn, $currency_code);
    $q = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, currency_code, category, description, day_of_month, last_injected_month, is_active) 
          VALUES ($home_id, $user_id, '$type', $amount, '$currency_code_esc', $category_id, '$description', $day_of_month, NULL, 1)";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
}

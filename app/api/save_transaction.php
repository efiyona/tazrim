<?php
// כותרות אבטחה ו-CORS ששמנו מקודם
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = $_POST['api_token'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);
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

$transaction_date = date('Y-m-d');

// הזרקת הפעולה למערכת עם המשתנה הדינאמי $type
$insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, category, description, transaction_date) 
                 VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', '$transaction_date')";

if (mysqli_query($conn, $insert_query)) {
    
    mysqli_query($conn, "UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = '$token'");
    mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");

    $user_data = selectOne('users', ['id' => $user_id]);
    $user_name = $user_data['first_name'] ?? 'משתמש';
    $amount_formatted = number_format($amount, 0);
    
    // התאמת טקסט ההתראה לשאר בני הבית
    $action_text = ($type === 'income') ? 'הכנסה חדשה' : 'הוצאה חדשה';
    $emoji = ($type === 'income') ? '💰' : '💳';
    
    $notif_msg = "הוסיף $action_text מהאייפון $emoji: <span class='notif-bold'>$description</span> בסך $amount_formatted ₪";
    
    addNotification($home_id, "פעולה מהירה", $notif_msg, 'info', null);

    echo json_encode(['status' => 'success', 'message' => 'Transaction saved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save transaction. Database error.']);
}
?>
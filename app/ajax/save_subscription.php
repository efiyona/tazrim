<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// הגדרת פורמט התשובה כ-JSON
header('Content-Type: application/json');

// קבלת נתוני המנוי הגולמיים מהאייפון
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// בדיקה בסיסית שהמשתמש מחובר ושיש נתונים
if (!$data || !isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or missing data']);
    exit;
}

$user_id = $_SESSION['id'];
$endpoint = $data['endpoint'];
$p256dh = $data['keys']['p256dh'];
$auth = $data['keys']['auth'];

// ניקוי נתונים למניעת הזרקות SQL
$endpoint = mysqli_real_escape_string($conn, $endpoint);
$p256dh = mysqli_real_escape_string($conn, $p256dh);
$auth = mysqli_real_escape_string($conn, $auth);

// בדיקה אם המכשיר הזה כבר רשום למשתמש הזה
$check_query = "SELECT id FROM user_subscriptions WHERE endpoint = '$endpoint' AND user_id = $user_id LIMIT 1";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    // אם המכשיר חדש - מוסיפים אותו
    $query = "INSERT INTO user_subscriptions (user_id, endpoint, p256dh, auth) 
              VALUES ($user_id, '$endpoint', '$p256dh', '$auth')";
} else {
    // אם המכשיר כבר קיים - מעדכנים את מפתחות האבטחה שלו (ליתר ביטחון)
    $query = "UPDATE user_subscriptions 
              SET p256dh = '$p256dh', auth = '$auth' 
              WHERE endpoint = '$endpoint' AND user_id = $user_id";
}

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
}
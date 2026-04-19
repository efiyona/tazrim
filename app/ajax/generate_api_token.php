<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit;
}

$user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : 0;

if ($user_id <= 0 || $home_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נדרשת התחברות.']);
    exit;
}

$dup = mysqli_query($conn, "SELECT id FROM api_tokens WHERE user_id = $user_id LIMIT 1");
if ($dup && mysqli_num_rows($dup) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'כבר קיים מפתח חיבור. ניתן למחוק אותו ואז ליצור חדש.']);
    exit;
}

$random_string = bin2hex(random_bytes(8));
$new_token = 'TAZRIM_APP_' . strtoupper($random_string);

$token_esc = mysqli_real_escape_string($conn, $new_token);
$query = "INSERT INTO api_tokens (user_id, home_id, token) VALUES ($user_id, $home_id, '$token_esc')";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success', 'token' => $new_token]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת המפתח.']);
}

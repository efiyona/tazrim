<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
session_start();

$user_id = $_SESSION['id'];
$home_id = $_SESSION['home_id'];

$random_string = bin2hex(random_bytes(8));
$new_token = "EFI_APP_" . strtoupper($random_string);

// הכנסה למסד הנתונים
$query = "INSERT INTO api_tokens (user_id, home_id, token) VALUES ($user_id, $home_id, '$new_token')";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success', 'token' => $new_token]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
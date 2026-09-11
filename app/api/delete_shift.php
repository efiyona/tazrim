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

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = trim((string) ($_POST['api_token'] ?? ''));
$shift_id = (int) ($_POST['id'] ?? 0);

if ($token === '' || $shift_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid data.']);
    exit();
}

$tq = mysqli_prepare($conn, 'SELECT user_id, home_id FROM api_tokens WHERE token = ? LIMIT 1');
mysqli_stmt_bind_param($tq, 's', $token);
mysqli_stmt_execute($tq);
$tres = mysqli_stmt_get_result($tq);
$auth = $tres ? mysqli_fetch_assoc($tres) : null;
mysqli_stmt_close($tq);

if (!$auth) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}

$user_id = (int) $auth['user_id'];

// המשמרת חייבת להשתייך למשתמש
$sq = mysqli_prepare($conn, 'SELECT id FROM `user_work_shifts` WHERE `id` = ? AND `user_id` = ? LIMIT 1');
mysqli_stmt_bind_param($sq, 'ii', $shift_id, $user_id);
mysqli_stmt_execute($sq);
$sres = mysqli_stmt_get_result($sq);
$shift = $sres ? mysqli_fetch_assoc($sres) : null;
mysqli_stmt_close($sq);

if (!$shift) {
    echo json_encode(['status' => 'error', 'message' => 'Shift not found.']);
    exit();
}

$dq = mysqli_prepare($conn, 'DELETE FROM `user_work_shifts` WHERE `id` = ? AND `user_id` = ?');
mysqli_stmt_bind_param($dq, 'ii', $shift_id, $user_id);
mysqli_stmt_execute($dq);
$affected = mysqli_stmt_affected_rows($dq);
mysqli_stmt_close($dq);

if ($affected < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete shift. Database error.']);
    exit();
}

$lu = mysqli_prepare($conn, 'UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = ?');
mysqli_stmt_bind_param($lu, 's', $token);
mysqli_stmt_execute($lu);
mysqli_stmt_close($lu);

echo json_encode(['status' => 'success', 'message' => 'Shift deleted successfully.', 'id' => $shift_id]);
?>
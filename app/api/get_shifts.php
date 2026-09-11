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
if ($token === '') {
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

$u = selectOne('users', ['id' => $user_id]);
if (!$u || empty($u['work_schedule_enabled'])) {
    echo json_encode(['status' => 'error', 'message' => 'Work schedule feature is not enabled for this account.']);
    exit();
}

// חודש אופציונלי; ברירת מחדל - החודש הנוכחי
$y = (int) ($_POST['year'] ?? 0);
$m = (int) ($_POST['month'] ?? 0);
if ($y < 1970 || $y > 2100 || $m < 1 || $m > 12) {
    $y = (int) date('Y');
    $m = (int) date('n');
}

$start = sprintf('%04d-%02d-01 00:00:00', $y, $m);
$mEnd = $m + 1;
$yEnd = $y;
if ($mEnd > 12) {
    $mEnd = 1;
    $yEnd++;
}
$end = sprintf('%04d-%02d-01 00:00:00', $yEnd, $mEnd);

// רשימת העבודות של המשתמש (לצורך בחירת job_id)
$jobs = [];
$jq = mysqli_prepare($conn, 'SELECT id, title, color, sort_order FROM `user_work_jobs` WHERE `user_id` = ? ORDER BY `sort_order` ASC, `id` ASC');
mysqli_stmt_bind_param($jq, 'i', $user_id);
mysqli_stmt_execute($jq);
$jres = mysqli_stmt_get_result($jq);
while ($row = mysqli_fetch_assoc($jres)) {
    $jobs[] = $row;
}
mysqli_stmt_close($jq);

// המשמרות של החודש
$shifts = [];
$sq = mysqli_prepare(
    $conn,
    'SELECT s.id, s.job_id, j.title AS job_title, s.shift_type_id, t.name AS type_name, s.starts_at, s.ends_at, s.note
     FROM `user_work_shifts` s
     INNER JOIN `user_work_jobs` j ON j.id = s.job_id
     LEFT JOIN `user_work_shift_types` t ON t.id = s.shift_type_id
     WHERE s.user_id = ? AND s.starts_at >= ? AND s.starts_at < ?
     ORDER BY s.starts_at ASC, s.id ASC'
);
mysqli_stmt_bind_param($sq, 'iss', $user_id, $start, $end);
mysqli_stmt_execute($sq);
$sres = mysqli_stmt_get_result($sq);
while ($row = mysqli_fetch_assoc($sres)) {
    $shifts[] = $row;
}
mysqli_stmt_close($sq);

$lu = mysqli_prepare($conn, 'UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = ?');
mysqli_stmt_bind_param($lu, 's', $token);
mysqli_stmt_execute($lu);
mysqli_stmt_close($lu);

echo json_encode(['status' => 'success', 'year' => $y, 'month' => $m, 'jobs' => $jobs, 'shifts' => $shifts], JSON_UNESCAPED_UNICODE);
?>
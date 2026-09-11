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

// אימות טוקן (prepared statement)
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

// קלט: תאריך + עבודה + שעות
$job_id = (int) ($_POST['job_id'] ?? 0);
$shift_type_id = (int) ($_POST['shift_type_id'] ?? 0);
$date_raw = trim((string) ($_POST['date'] ?? ''));
$start_raw = trim((string) ($_POST['start_time'] ?? ''));
$end_raw = trim((string) ($_POST['end_time'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
if (mb_strlen($note) > 500) {
    $note = mb_substr($note, 0, 500);
}

if ($job_id < 1 || $date_raw === '' || $start_raw === '' || $end_raw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: job_id, date, start_time, end_time.']);
    exit();
}

$dt = DateTime::createFromFormat('Y-m-d', $date_raw);
if (!$dt || $dt->format('Y-m-d') !== $date_raw) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date. Expected format: Y-m-d.']);
    exit();
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start_raw) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end_raw)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid time. Expected format: HH:MM.']);
    exit();
}

// העבודה חייבת להשתייך למשתמש
$jq = mysqli_prepare($conn, 'SELECT id FROM `user_work_jobs` WHERE `id` = ? AND `user_id` = ? LIMIT 1');
mysqli_stmt_bind_param($jq, 'ii', $job_id, $user_id);
mysqli_stmt_execute($jq);
$jres = mysqli_stmt_get_result($jq);
$job = $jres ? mysqli_fetch_assoc($jres) : null;
mysqli_stmt_close($jq);
if (!$job) {
    echo json_encode(['status' => 'error', 'message' => 'Job not found for this user.']);
    exit();
}

// סוג משמרת (אופציונלי) חייב להשתייך לאותה עבודה
if ($shift_type_id > 0) {
    $sq = mysqli_prepare(
        $conn,
        'SELECT t.id FROM `user_work_shift_types` t INNER JOIN `user_work_jobs` j ON j.id = t.job_id WHERE t.id = ? AND j.user_id = ? AND t.job_id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($sq, 'iii', $shift_type_id, $user_id, $job_id);
    mysqli_stmt_execute($sq);
    $sres = mysqli_stmt_get_result($sq);
    $srow = $sres ? mysqli_fetch_assoc($sres) : null;
    mysqli_stmt_close($sq);
    if (!$srow) {
        echo json_encode(['status' => 'error', 'message' => 'Shift type does not match this job.']);
        exit();
    }
}

$starts_ts = strtotime($date_raw . ' ' . $start_raw . ':00');
$ends_ts = strtotime($date_raw . ' ' . $end_raw . ':00');
// משמרת לילה: שעת סיום קטנה/שווה להתחלה - הסיום למחרת
if ($ends_ts <= $starts_ts) {
    $ends_ts = strtotime('+1 day', $ends_ts);
}

$starts_at = date('Y-m-d H:i:s', $starts_ts);
$ends_at = date('Y-m-d H:i:s', $ends_ts);

if ($shift_type_id > 0) {
    $st = mysqli_prepare(
        $conn,
        'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`shift_type_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?,?)'
    );
    mysqli_stmt_bind_param($st, 'iiisss', $user_id, $job_id, $shift_type_id, $starts_at, $ends_at, $note);
} else {
    $st = mysqli_prepare(
        $conn,
        'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?)'
    );
    mysqli_stmt_bind_param($st, 'iisss', $user_id, $job_id, $starts_at, $ends_at, $note);
}
mysqli_stmt_execute($st);
$new_id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($st);

if ($new_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save shift. Database error.']);
    exit();
}

$lu = mysqli_prepare($conn, 'UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = ?');
mysqli_stmt_bind_param($lu, 's', $token);
mysqli_stmt_execute($lu);
mysqli_stmt_close($lu);

echo json_encode(['status' => 'success', 'message' => 'Shift saved successfully.', 'shift_id' => $new_id, 'starts_at' => $starts_at, 'ends_at' => $ends_at]);
?>
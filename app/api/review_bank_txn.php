<?php
declare(strict_types=1);
// Review endpoint for pending bank transactions (api_token authenticated).
// POST api_token, id, action(approve|reject), category?
// approve -> inserts a real row into transactions and marks the pending row approved.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token  = trim((string)($_POST['api_token'] ?? ''));
$id     = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

if ($token === '' || $id <= 0 || !in_array($action, ['approve','reject'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing api_token, id or valid action.']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id, home_id FROM api_tokens WHERE token = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$auth = mysqli_stmt_get_result($stmt);
if (!$auth || mysqli_num_rows($auth) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}
$auth_data = mysqli_fetch_assoc($auth);
$user_id = (int)$auth_data['user_id'];
$home_id = (int)$auth_data['home_id'];

$q = mysqli_prepare($conn,
    "SELECT * FROM bank_pending_transactions WHERE id = ? AND home_id = ? AND status = 'pending' LIMIT 1");
mysqli_stmt_bind_param($q, 'ii', $id, $home_id);
mysqli_stmt_execute($q);
$res = mysqli_stmt_get_result($q);
if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pending transaction not found.']);
    exit();
}
$row = mysqli_fetch_assoc($res);

if ($action === 'reject') {
    $u = mysqli_prepare($conn, "UPDATE bank_pending_transactions SET status='rejected', decided_at=NOW() WHERE id = ?");
    mysqli_stmt_bind_param($u, 'i', $id);
    mysqli_stmt_execute($u);
    echo json_encode(['status' => 'ok', 'rejected' => $id]);
    exit();
}

// approve -> real transaction
$category = mb_substr(trim((string)($_POST['category'] ?? 'בנק')), 0, 100);
$ti = mysqli_prepare($conn,
    "INSERT INTO transactions (home_id, user_id, amount, currency_code, type, category, description, transaction_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($ti, 'iidsssss',
    $home_id, $user_id, $row['amount'], $row['currency_code'], $row['type'], $category, $row['description'], $row['txn_date']);
mysqli_stmt_execute($ti);
$new_id = mysqli_insert_id($conn);

$u = mysqli_prepare($conn, "UPDATE bank_pending_transactions SET status='approved', decided_at=NOW() WHERE id = ?");
mysqli_stmt_bind_param($u, 'i', $id);
mysqli_stmt_execute($u);

echo json_encode(['status' => 'ok', 'approved' => $id, 'transaction_id' => $new_id], JSON_UNESCAPED_UNICODE);

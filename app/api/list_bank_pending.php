<?php
// List pending bank transactions + latest balance snapshot for the app (token-auth, home-scoped).
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
header('Content-Type: application/json; charset=utf-8');

$token = trim((string)($_POST['api_token'] ?? $_GET['api_token'] ?? ''));
if ($token === '') { echo json_encode(['status' => 'error', 'message' => 'Missing api_token']); exit(); }
$stmt = mysqli_prepare($conn, "SELECT user_id, home_id FROM api_tokens WHERE token = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$auth = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$auth) { echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']); exit(); }
$home_id = (int)$auth['home_id'];

$items = [];
$q = mysqli_prepare($conn, "SELECT id, txn_date, amount, type, description, reference, bank FROM bank_pending_transactions WHERE home_id = ? AND status = 'pending' ORDER BY txn_date DESC, id DESC");
mysqli_stmt_bind_param($q, 'i', $home_id);
mysqli_stmt_execute($q);
$res = mysqli_stmt_get_result($q);
while ($row = mysqli_fetch_assoc($res)) $items[] = $row;

$balance = null; $balance_at = '';
$s = mysqli_prepare($conn, "SELECT balance, captured_at FROM bank_balance_snapshots WHERE home_id = ? ORDER BY captured_at DESC LIMIT 1");
mysqli_stmt_bind_param($s, 'i', $home_id);
mysqli_stmt_execute($s);
$snap = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
if ($snap) { $balance = (float)$snap['balance']; $balance_at = $snap['captured_at']; }

echo json_encode(['status' => 'success', 'count' => count($items), 'items' => $items, 'balance' => $balance, 'balance_at' => $balance_at], JSON_UNESCAPED_UNICODE);

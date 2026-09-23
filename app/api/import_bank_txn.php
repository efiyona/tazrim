<?php
declare(strict_types=1);
// Bank scraper import endpoint (agent pipeline, api_token authenticated).
// POST api_token, bank, account_ref, balance?, items (JSON array of
// {event_id, date(Y-m-d), amount, type(income|expense), description, reference})
// Rows land in bank_pending_transactions (dedup hash, INSERT IGNORE) - Efi/agent
// approves each before it becomes a real transaction. Nothing posts directly.
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

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bank_pending_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  home_id INT NOT NULL,
  user_id INT NOT NULL,
  dedup_hash CHAR(40) NOT NULL,
  bank VARCHAR(40) NOT NULL,
  account_ref VARCHAR(64) NOT NULL,
  txn_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency_code VARCHAR(3) NOT NULL DEFAULT 'ILS',
  type ENUM('income','expense') NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  reference VARCHAR(64) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  decided_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY dedup_hash (dedup_hash),
  KEY home_status (home_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bank_balance_snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  home_id INT NOT NULL,
  bank VARCHAR(40) NOT NULL,
  account_ref VARCHAR(64) NOT NULL,
  balance DECIMAL(14,2) NOT NULL,
  currency_code VARCHAR(3) NOT NULL DEFAULT 'ILS',
  captured_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  KEY home_bank (home_id, bank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$token       = trim((string)($_POST['api_token'] ?? ''));
$bank        = mb_substr(trim((string)($_POST['bank'] ?? '')), 0, 40);
$account_ref = mb_substr(trim((string)($_POST['account_ref'] ?? '')), 0, 64);
$items_json  = (string)($_POST['items'] ?? '[]');
$balance_raw = $_POST['balance'] ?? null;

if ($token === '' || $bank === '' || $account_ref === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing api_token, bank or account_ref.']);
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

$items = json_decode($items_json, true);
if (!is_array($items) || count($items) > 500) {
    echo json_encode(['status' => 'error', 'message' => 'items must be a JSON array of up to 500 entries.']);
    exit();
}

$inserted = 0; $duplicates = 0; $skipped = 0;
$ins = mysqli_prepare($conn,
    "INSERT IGNORE INTO bank_pending_transactions
     (home_id, user_id, dedup_hash, bank, account_ref, txn_date, amount, currency_code, type, description, reference)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'ILS', ?, ?, ?)");

foreach ($items as $it) {
    if (!is_array($it)) { $skipped++; continue; }
    $date = trim((string)($it['date'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    $amount = (float)($it['amount'] ?? 0);
    $type = (string)($it['type'] ?? '');
    if (!$dt || $dt->format('Y-m-d') !== $date || $amount <= 0 || !in_array($type, ['income','expense'], true)) {
        $skipped++; continue;
    }
    $desc = mb_substr(trim((string)($it['description'] ?? '')), 0, 255);
    $ref  = mb_substr(trim((string)($it['reference'] ?? '')), 0, 64);
    $event_id = trim((string)($it['event_id'] ?? ''));
    $key = ($event_id !== '' && $event_id !== '0') ? 'e'.$event_id : sha1($date.'|'.$amount.'|'.$desc.'|'.$type.'|'.$ref);
    $hash = sha1($bank.'|'.$account_ref.'|'.$key);
    mysqli_stmt_bind_param($ins, 'iisssssdss',
        $home_id, $user_id, $hash, $bank, $account_ref, $date, $amount, $type, $desc, $ref);
    mysqli_stmt_execute($ins);
    if (mysqli_stmt_affected_rows($ins) === 1) { $inserted++; } else { $duplicates++; }
}

$balance_recorded = false;
if ($balance_raw !== null && is_numeric($balance_raw)) {
    $bal = (float)$balance_raw;
    $bs = mysqli_prepare($conn,
        "INSERT INTO bank_balance_snapshots (home_id, bank, account_ref, balance) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($bs, 'issd', $home_id, $bank, $account_ref, $bal);
    mysqli_stmt_execute($bs);
    $balance_recorded = mysqli_stmt_affected_rows($bs) === 1;
}

$pending_total = 0;
$pc = mysqli_prepare($conn, "SELECT COUNT(*) c FROM bank_pending_transactions WHERE home_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($pc, 'i', $home_id);
mysqli_stmt_execute($pc);
$pr = mysqli_stmt_get_result($pc);
if ($pr && $row = mysqli_fetch_assoc($pr)) { $pending_total = (int)$row['c']; }

echo json_encode([
    'status' => 'ok',
    'inserted' => $inserted,
    'duplicates' => $duplicates,
    'skipped' => $skipped,
    'balance_recorded' => $balance_recorded,
    'pending_total' => $pending_total,
], JSON_UNESCAPED_UNICODE);

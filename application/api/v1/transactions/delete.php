<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'שיטת בקשה לא נתמכת.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }

    $token = trim($body['token'] ?? '');
    $trans_id = isset($body['transaction_id']) ? (int) $body['transaction_id'] : 0;

    if ($token === '' || $trans_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }

    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית למשתמש.']);
        exit();
    }

    $existing = selectOne('transactions', ['id' => $trans_id, 'home_id' => $home_id]);
    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'הפעולה לא נמצאה.']);
        exit();
    }

    $today_ledger = date('Y-m-d');
    $oldRow = [
        'type' => (string) ($existing['type'] ?? ''),
        'amount' => (float) ($existing['amount'] ?? 0),
        'transaction_date' => (string) ($existing['transaction_date'] ?? ''),
    ];

    $delete_query = "DELETE FROM transactions WHERE id = $trans_id AND home_id = $home_id";
    if (!mysqli_query($conn, $delete_query)) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
        exit();
    }

    tazrim_after_transaction_row_change($conn, $home_id, $oldRow, null, $today_ledger);

    echo json_encode(['status' => 'success', 'data' => ['message' => 'הפעולה נמחקה.']]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

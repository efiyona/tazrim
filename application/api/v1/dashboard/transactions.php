<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    $status = $_GET['status'] ?? 'recent';
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 20)) : 4;
    $selected_month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
    $selected_year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
    $today_il = date('Y-m-d');

    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }
    if (!in_array($status, ['recent', 'pending'], true)) {
        $status = 'recent';
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }
    require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
    tazrim_api_v1_json_exit_if_email_unverified($user);
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית למשתמש.']);
        exit();
    }

    if ($status === 'pending') {
        $where_clause = "AND t.transaction_date > '$today_il'";
        $order_clause = "ORDER BY t.transaction_date ASC, t.id ASC";
    } else {
        $where_clause = "AND t.transaction_date <= '$today_il'";
        $order_clause = "ORDER BY t.transaction_date DESC, t.id DESC";
    }

    $query = "SELECT t.*, c.name as category_name, c.icon as category_icon, u.first_name as user_name
              FROM transactions t
              LEFT JOIN categories c ON t.category = c.id
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.home_id = $home_id
              $where_clause
              AND MONTH(t.transaction_date) = $selected_month
              AND YEAR(t.transaction_date) = $selected_year
              $order_clause
              LIMIT $limit OFFSET $offset";
    $res = mysqli_query($conn, $query);
    $transactions = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $transactions[] = $row;
    }

    $count_query = "SELECT COUNT(id) as total
                    FROM transactions t
                    WHERE t.home_id = $home_id
                    " . ($status === 'pending' ? "AND t.transaction_date > '$today_il'" : "AND t.transaction_date <= '$today_il'") . "
                    AND MONTH(t.transaction_date) = $selected_month
                    AND YEAR(t.transaction_date) = $selected_year";
    $count_res = mysqli_query($conn, $count_query);
    $total = (int) (mysqli_fetch_assoc($count_res)['total'] ?? 0);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'transactions' => $transactions,
            'has_more' => ($offset + count($transactions)) < $total,
            'next_offset' => $offset + count($transactions),
        ],
    ]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

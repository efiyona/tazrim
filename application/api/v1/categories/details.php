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
    $cat_id = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
    $selected_month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
    $selected_year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
    $today_il = date('Y-m-d');

    if ($token === '' || $cat_id <= 0) {
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

    $query = "SELECT t.*, c.icon as category_icon, u.first_name as user_name
              FROM transactions t
              LEFT JOIN categories c ON t.category = c.id
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.category = $cat_id
              AND t.home_id = $home_id
              AND t.type = 'expense'
              AND MONTH(t.transaction_date) = $selected_month
              AND YEAR(t.transaction_date) = $selected_year
              ORDER BY
                CASE WHEN t.transaction_date > '$today_il' THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > '$today_il' THEN t.transaction_date END ASC,
                t.transaction_date DESC, t.id DESC";
    $res = mysqli_query($conn, $query);
    $transactions = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $transactions[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data' => ['transactions' => $transactions],
    ]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

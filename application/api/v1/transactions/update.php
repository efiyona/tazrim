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
    require_once ROOT_PATH . '/app/functions/budget_overrun_push.php';

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
    $amount = isset($body['amount']) ? (float) $body['amount'] : 0;
    $category_id = isset($body['category_id']) ? (int) $body['category_id'] : 0;
    $description = trim($body['description'] ?? '');

    if ($token === '' || $trans_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    if ($amount <= 0 || $category_id <= 0 || $description === '') {
        echo json_encode(['status' => 'error', 'message' => 'אנא ודא שהסכום גדול מ-0 ושבחרת קטגוריה ותיאור.']);
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

    $row_type = $existing['type'] ?? 'expense';
    $cat = selectOne('categories', ['id' => $category_id, 'home_id' => $home_id]);
    if (!$cat || ($cat['type'] ?? '') !== $row_type) {
        echo json_encode(['status' => 'error', 'message' => 'קטגוריה לא תואמת לסוג הפעולה.']);
        exit();
    }

    $desc_esc = mysqli_real_escape_string($conn, $description);

    $update_query = "UPDATE transactions 
                     SET amount = $amount, category = $category_id, description = '$desc_esc' 
                     WHERE id = $trans_id AND home_id = $home_id";

    if (!mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
        exit();
    }

    mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");

    $after = mysqli_query($conn, "SELECT type, category FROM transactions WHERE id = $trans_id AND home_id = $home_id LIMIT 1");
    if ($after) {
        $row = mysqli_fetch_assoc($after);
        if ($row && ($row['type'] ?? '') === 'expense') {
            maybeSendBudgetOverrunPush($home_id, (int) $row['category']);
        }
    }

    echo json_encode(['status' => 'success', 'data' => ['message' => 'הפעולה עודכנה.']]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

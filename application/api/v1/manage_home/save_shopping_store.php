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

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן לא חוקי.']);
        exit();
    }
    require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
    tazrim_api_v1_json_exit_if_email_unverified($user);

    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $store_id = !empty($body['store_id']) ? (int) $body['store_id'] : null;
    $store_name = mysqli_real_escape_string($conn, trim((string) ($body['store_name'] ?? '')));
    $store_icon = mysqli_real_escape_string($conn, trim((string) ($body['store_icon'] ?? 'fa-cart-shopping')));

    if ($store_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'שם החנות לא יכול להיות ריק.']);
        exit();
    }

    if ($store_id) {
        $query = "UPDATE shopping_categories SET name = '$store_name', icon = '$store_icon' WHERE id = $store_id AND home_id = $home_id";
    } else {
        $sort_q = "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM shopping_categories WHERE home_id = $home_id";
        $sort_res = mysqli_query($conn, $sort_q);
        $next_sort = 1;
        if ($sort_res && ($sort_row = mysqli_fetch_assoc($sort_res))) {
            $next_sort = (int) $sort_row['next_sort'];
        }
        $query = "INSERT INTO shopping_categories (home_id, name, icon, sort_order) VALUES ($home_id, '$store_name', '$store_icon', $next_sort)";
    }

    if (mysqli_query($conn, $query)) {
        if ($store_id) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'success', 'new_store_id' => (int) $conn->insert_id]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

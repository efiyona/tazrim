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
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $store_id = isset($body['id']) ? (int) $body['id'] : 0;

    if ($home_id <= 0 || $store_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    $check_query = "SELECT COUNT(*) AS items_count FROM shopping_items WHERE home_id = $home_id AND category_id = $store_id";
    $check_result = mysqli_query($conn, $check_query);
    $items_count = 0;
    if ($check_result && ($check_row = mysqli_fetch_assoc($check_result))) {
        $items_count = (int) $check_row['items_count'];
    }

    if ($items_count > 0) {
        $fallback_query = "SELECT id FROM shopping_categories WHERE home_id = $home_id AND id <> $store_id ORDER BY sort_order ASC, id ASC LIMIT 1";
        $fallback_result = mysqli_query($conn, $fallback_query);
        $fallback = $fallback_result ? mysqli_fetch_assoc($fallback_result) : null;

        if (!$fallback) {
            echo json_encode([
                'status' => 'error',
                'message' => 'לא ניתן למחוק את החנות האחרונה כשיש בה פריטים. הוסף קודם חנות חלופית.',
            ]);
            exit();
        }

        $fallback_id = (int) $fallback['id'];
        mysqli_query($conn, "UPDATE shopping_items SET category_id = $fallback_id WHERE home_id = $home_id AND category_id = $store_id");
    }

    $delete_query = "DELETE FROM shopping_categories WHERE id = $store_id AND home_id = $home_id";
    if (mysqli_query($conn, $delete_query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

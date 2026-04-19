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

    require_once __DIR__ . '/_auth.php';
    $auth = shopping_api_require_user($conn);
    $home_id = $auth['home_id'];

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $cat_id = (int) ($body['category_id'] ?? 0);
    if ($cat_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
        exit();
    }

    $chk = mysqli_query($conn, "SELECT id FROM shopping_categories WHERE id = $cat_id AND home_id = $home_id LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
        exit();
    }

    $stmt = $conn->prepare('DELETE FROM shopping_items WHERE home_id=? AND category_id=?');
    $stmt->bind_param('ii', $home_id, $cat_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
    }
    $stmt->close();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

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

    $item_id = (int) ($body['item_id'] ?? 0);
    if ($item_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'חסר מזהה פריט']);
        exit();
    }

    $stmt = $conn->prepare('DELETE FROM shopping_items WHERE id=? AND home_id=?');
    $stmt->bind_param('ii', $item_id, $home_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    $stmt->close();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

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

    $item_id = isset($body['item_id']) ? $body['item_id'] : 'new';
    $cat_id = (int) ($body['category_id'] ?? 0);
    $item_name = trim((string) ($body['item_name'] ?? ''));
    $quantity = trim((string) ($body['quantity'] ?? '1'));
    if ($quantity === '' || (int) $quantity < 1) {
        $quantity = '1';
    }

    if ($item_name === '' || $cat_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'חסרים נתונים']);
        exit();
    }

    $chk = mysqli_query($conn, "SELECT id FROM shopping_categories WHERE id = $cat_id AND home_id = $home_id LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
        exit();
    }

    if ($item_id === 'new' || $item_id === '' || $item_id === null) {
        $sr = mysqli_query($conn, "SELECT COALESCE(MIN(sort_order), 1000) AS m FROM shopping_items WHERE home_id = $home_id AND category_id = $cat_id");
        $rmin = mysqli_fetch_assoc($sr);
        $new_sort = (int) ($rmin['m'] ?? 1000) - 1;

        $stmt = $conn->prepare('INSERT INTO shopping_items (home_id, category_id, item_name, quantity, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iissi', $home_id, $cat_id, $item_name, $quantity, $new_sort);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'new_id' => (int) $conn->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
        }
        $stmt->close();
    } else {
        $iid = (int) $item_id;
        $stmt = $conn->prepare('UPDATE shopping_items SET item_name=?, quantity=? WHERE id=? AND home_id=?');
        $stmt->bind_param('ssii', $item_name, $quantity, $iid, $home_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

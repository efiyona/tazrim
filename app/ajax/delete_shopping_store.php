<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit();
}

$home_id = $_SESSION['home_id'] ?? null;
$store_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$home_id || !$store_id) {
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
        echo json_encode(['status' => 'error', 'message' => 'לא ניתן למחוק את החנות האחרונה כשיש בה פריטים. הוסף קודם חנות חלופית.']);
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

<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$home_id = (int) $_SESSION['home_id'];
$cat_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($cat_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
    exit();
}

$chk = mysqli_query($conn, "SELECT id FROM shopping_categories WHERE id = $cat_id AND home_id = $home_id LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM shopping_items WHERE home_id = ? AND category_id = ?');
$stmt->bind_param('ii', $home_id, $cat_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
}
$stmt->close();

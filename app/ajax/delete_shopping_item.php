<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
session_start();

if (!isset($_SESSION['home_id']) || !isset($_POST['item_id'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

$home_id = $_SESSION['home_id'];
$item_id = $_POST['item_id'];

// מחיקה פיזית של המוצר ממסד הנתונים (ולא העברה לארכיון)
$query = "DELETE FROM shopping_items WHERE id=? AND home_id=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $item_id, $home_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}
?>
<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
session_start();

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

$home_id = $_SESSION['home_id'];

// מוחק את כל הפריטים ששייכים לבית הזה במכה אחת
$query = "DELETE FROM shopping_items WHERE home_id=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $home_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}
?>
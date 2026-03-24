<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
session_start();

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$home_id = $_SESSION['home_id'];
$item_id = $_POST['item_id'] ?? 'new';
$cat_id = $_POST['category_id'] ?? 0;
$item_name = trim($_POST['item_name'] ?? '');
$quantity = trim($_POST['quantity'] ?? '1');

// אימות נתונים בסיסי
if (empty($item_name) || empty($cat_id)) {
    echo json_encode(['status' => 'error', 'message' => 'חסרים נתונים']);
    exit();
}

if ($item_id === 'new') {
    // 1. הוספת מוצר חדש
    $query = "INSERT INTO shopping_items (home_id, category_id, item_name, quantity) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $home_id, $cat_id, $item_name, $quantity);
    
    if ($stmt->execute()) {
        // מחזיר ל-JS את ה-ID החדש שנוצר במסד הנתונים!
        echo json_encode(['status' => 'success', 'new_id' => $conn->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
    }
} else {
    // 2. עדכון מוצר קיים (שינוי שם או כמות תוך כדי הקלדה)
    $query = "UPDATE shopping_items SET item_name=?, quantity=? WHERE id=? AND home_id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $item_name, $quantity, $item_id, $home_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת מסד נתונים']);
    }
}
?>
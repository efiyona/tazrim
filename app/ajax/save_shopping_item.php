<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$home_id = $_SESSION['home_id'];
$item_id = $_POST['item_id'] ?? 'new';
$cat_id = $_POST['category_id'] ?? 0;
$item_name = trim($_POST['item_name'] ?? '');

// --- רשת ביטחון לכמות ---
$quantity = trim($_POST['quantity'] ?? '1');
// אם הכמות ריקה, או מכילה טקסט בטעות, או שהיא קטנה מ-1 - נכריח אותה להיות 1.
if ($quantity === '' || (int)$quantity < 1) {
    $quantity = '1';
}

// אימות נתונים בסיסי
if (empty($item_name) || empty($cat_id)) {
    echo json_encode(['status' => 'error', 'message' => 'חסרים נתונים']);
    exit();
}

if ($item_id === 'new') {
    // 1. הוספת מוצר חדש — sort_order נמוך מהמינימום הקיים כדי שיופיע מתחת לשורת ההוספה (למעלה ברשימה)
    $sr = mysqli_query($conn, "SELECT COALESCE(MIN(sort_order), 1000) AS m FROM shopping_items WHERE home_id = $home_id AND category_id = $cat_id");
    $rmin = mysqli_fetch_assoc($sr);
    $new_sort = (int) ($rmin['m'] ?? 1000) - 1;

    $query = "INSERT INTO shopping_items (home_id, category_id, item_name, quantity, sort_order) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissi", $home_id, $cat_id, $item_name, $quantity, $new_sort);
    
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
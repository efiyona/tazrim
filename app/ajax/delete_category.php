<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_id = $_SESSION['home_id'] ?? null;
    $cat_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!$home_id || !$cat_id) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    // מחיקה רכה: הופכים את הקטגוריה ללא פעילה
    $update_query = "UPDATE categories SET is_active = 0 WHERE id = $cat_id AND home_id = $home_id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
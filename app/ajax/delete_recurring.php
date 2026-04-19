<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// הגדרת סוג התשובה ל-JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $home_id = $_SESSION['home_id'] ?? null;
    $rec_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // וידוא נתונים
    if (!$home_id || !$rec_id) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים או משתמש לא מחובר.']);
        exit();
    }

    // מחיקה מוחלטת ממסד הנתונים (Hard Delete)
    // חובה לבדוק שהפעולה שייכת לבית הנוכחי מטעמי אבטחה
    $delete_query = "DELETE FROM recurring_transactions WHERE id = $rec_id AND home_id = $home_id";

    if (mysqli_query($conn, $delete_query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים: ' . mysqli_error($conn)]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
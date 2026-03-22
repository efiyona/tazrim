<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_id = $_SESSION['home_id'] ?? null;
    $trans_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!$home_id || !$trans_id) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    // מחיקה מוחלטת של הפעולה
    $delete_query = "DELETE FROM transactions WHERE id = $trans_id AND home_id = $home_id";

    if (mysqli_query($conn, $delete_query)) {
        // מוחקים את הקאש של הבינה המלאכותית כי הנתונים הפיננסיים השתנו
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
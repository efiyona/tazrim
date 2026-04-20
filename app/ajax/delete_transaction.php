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

    $snap = mysqli_query($conn, "SELECT type, amount, transaction_date FROM transactions WHERE id = $trans_id AND home_id = $home_id LIMIT 1");
    $oldRow = $snap ? mysqli_fetch_assoc($snap) : null;
    if (!$oldRow) {
        echo json_encode(['status' => 'error', 'message' => 'הפעולה לא נמצאה.']);
        exit();
    }

    // מחיקה מוחלטת של הפעולה
    $delete_query = "DELETE FROM transactions WHERE id = $trans_id AND home_id = $home_id";

    if (mysqli_query($conn, $delete_query)) {
        global $today_il;
        $today_for_ledger = isset($today_il) ? (string) $today_il : date('Y-m-d');
        tazrim_after_transaction_row_change($conn, (int) $home_id, $oldRow, null, $today_for_ledger);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/budget_overrun_push.php';
require_once ROOT_PATH . '/app/functions/payment_methods.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_id = $_SESSION['home_id'] ?? null;
    $trans_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;

    if (!$home_id || !$trans_id) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $payment_method_update = '';
    if (array_key_exists('payment_method_id', $_POST)) {
        $payment_method_id = tazrim_resolve_payment_method_id((int)$home_id, $_POST['payment_method_id']);
        $payment_method_update = ", payment_method_id = $payment_method_id";
    }

    if ($amount <= 0 || empty($category_id) || empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'אנא ודא שהסכום גדול מ-0 ושבחרת קטגוריה ותיאור.']);
        exit();
    }

    $snap = mysqli_query($conn, "SELECT type, amount, transaction_date FROM transactions WHERE id = $trans_id AND home_id = $home_id LIMIT 1");
    $oldRow = $snap ? mysqli_fetch_assoc($snap) : null;
    if (!$oldRow) {
        echo json_encode(['status' => 'error', 'message' => 'הפעולה לא נמצאה.']);
        exit();
    }

    $category_check = mysqli_query($conn, "SELECT id FROM categories WHERE id = $category_id AND home_id = $home_id AND type = '" . mysqli_real_escape_string($conn, (string) $oldRow['type']) . "' AND is_active = 1 LIMIT 1");
    if (!$category_check || !mysqli_fetch_assoc($category_check)) {
        echo json_encode(['status' => 'error', 'message' => 'הקטגוריה אינה שייכת לבית או לסוג הפעולה.']);
        exit();
    }

    $update_query = "UPDATE transactions
                     SET amount = $amount, category = $category_id, description = '$description'$payment_method_update
                     WHERE id = $trans_id AND home_id = $home_id";

    if (mysqli_query($conn, $update_query)) {
        $after = mysqli_query($conn, "SELECT type, category FROM transactions WHERE id = $trans_id AND home_id = $home_id LIMIT 1");
        if ($after) {
            $row = mysqli_fetch_assoc($after);
            if ($row && $row['type'] === 'expense') {
                maybeSendBudgetOverrunPush($home_id, (int) $row['category']);
            }
        }

        global $today_il;
        $today_for_ledger = isset($today_il) ? (string) $today_il : date('Y-m-d');
        $newRow = [
            'type' => (string) $oldRow['type'],
            'amount' => $amount,
            'transaction_date' => (string) $oldRow['transaction_date'],
        ];
        tazrim_after_transaction_row_change($conn, (int) $home_id, $oldRow, $newRow, $today_for_ledger);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>

<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
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

    if ($amount <= 0 || empty($category_id) || empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'אנא ודא שהסכום גדול מ-0 ושבחרת קטגוריה ותיאור.']);
        exit();
    }

    $update_query = "UPDATE transactions 
                     SET amount = $amount, category = $category_id, description = '$description' 
                     WHERE id = $trans_id AND home_id = $home_id";

    if (mysqli_query($conn, $update_query)) {
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
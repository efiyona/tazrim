<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $home_id = $_SESSION['home_id'] ?? null;

    if (!$home_id) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
        exit();
    }

    $home_name = trim($_POST['home_name'] ?? '');
    $initial_balance = isset($_POST['initial_balance']) ? (float)$_POST['initial_balance'] : 0;

    if (empty($home_name)) {
        echo json_encode(['status' => 'error', 'message' => 'שם הבית לא יכול להיות ריק.']);
        exit();
    }

    // הגנה מ-SQL Injection
    $home_name_clean = mysqli_real_escape_string($conn, $home_name);

    // עדכון טבלת הבית (homes)
    $update_query = "UPDATE homes SET name = '$home_name_clean', initial_balance = $initial_balance WHERE id = $home_id";

    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים: ' . mysqli_error($conn)]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
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

    $cat_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $cat_name = mysqli_real_escape_string($conn, trim($_POST['cat_name']));
    $cat_type = $_POST['cat_type'] === 'income' ? 'income' : 'expense';
    $cat_budget = isset($_POST['cat_budget']) ? (float)$_POST['cat_budget'] : 0;
    $cat_icon = mysqli_real_escape_string($conn, trim($_POST['cat_icon']));

    if (empty($cat_name)) {
        echo json_encode(['status' => 'error', 'message' => 'שם הקטגוריה לא יכול להיות ריק.']);
        exit();
    }

    if ($cat_id) {
        // עדכון קטגוריה קיימת
        $query = "UPDATE categories SET name='$cat_name', budget_limit=$cat_budget, icon='$cat_icon' WHERE id=$cat_id AND home_id=$home_id";
    } else {
        // הוספת קטגוריה חדשה
        $query = "INSERT INTO categories (home_id, name, type, budget_limit, icon) VALUES ($home_id, '$cat_name', '$cat_type', $cat_budget, '$cat_icon')";
    }

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
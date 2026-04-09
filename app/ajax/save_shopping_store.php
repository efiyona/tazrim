<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit();
}

$home_id = $_SESSION['home_id'] ?? null;
if (!$home_id) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
    exit();
}

$store_id = !empty($_POST['store_id']) ? (int) $_POST['store_id'] : null;
$store_name = mysqli_real_escape_string($conn, trim($_POST['store_name'] ?? ''));
$store_icon = mysqli_real_escape_string($conn, trim($_POST['store_icon'] ?? 'fa-cart-shopping'));

if ($store_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'שם החנות לא יכול להיות ריק.']);
    exit();
}

if ($store_id) {
    $query = "UPDATE shopping_categories SET name = '$store_name', icon = '$store_icon' WHERE id = $store_id AND home_id = $home_id";
} else {
    $sort_q = "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM shopping_categories WHERE home_id = $home_id";
    $sort_res = mysqli_query($conn, $sort_q);
    $next_sort = 1;
    if ($sort_res && ($sort_row = mysqli_fetch_assoc($sort_res))) {
        $next_sort = (int) $sort_row['next_sort'];
    }

    $query = "INSERT INTO shopping_categories (home_id, name, icon, sort_order) VALUES ($home_id, '$store_name', '$store_icon', $next_sort)";
}

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
}

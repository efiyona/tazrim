<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// נוודא שיש סשן פעיל
if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$home_id = $_SESSION['home_id'];

// 1. שליפת כל הקטגוריות של הבית הזה
$cats_query = "SELECT * FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
$cats_result = mysqli_query($conn, $cats_query);

// 2. שליפת כל הפריטים שעדיין לא בארכיון
$items_query = "SELECT * FROM shopping_items WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
$items_result = mysqli_query($conn, $items_query);

// נסדר את הפריטים לפי מזהה קטגוריה כדי שיהיה קל לשלוף אותם
$items_by_cat = [];
while($item = mysqli_fetch_assoc($items_result)) {
    $items_by_cat[$item['category_id']][] = $item;
}

$active_categories = [];
$empty_categories = [];

// 3. חלוקה חכמה: למי יש פריטים ולמי אין?
while($cat = mysqli_fetch_assoc($cats_result)) {
    if (isset($items_by_cat[$cat['id']]) && count($items_by_cat[$cat['id']]) > 0) {
        $cat['items'] = $items_by_cat[$cat['id']];
        $active_categories[] = $cat;
    } else {
        $empty_categories[] = $cat;
    }
}

// החזרת התשובה כ-JSON טהור
echo json_encode([
    'status' => 'success',
    'active_categories' => $active_categories,
    'empty_categories' => $empty_categories
]);
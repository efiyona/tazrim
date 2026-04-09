<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : 0;
if ($home_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

$categories_query = "SELECT * FROM categories WHERE home_id = $home_id AND is_active = 1 ORDER BY type ASC, name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$expenses_cats = [];
$income_cats = [];
if ($categories_result) {
    while ($cat = mysqli_fetch_assoc($categories_result)) {
        if ($cat['type'] === 'expense') {
            $expenses_cats[] = $cat;
        } else {
            $income_cats[] = $cat;
        }
    }
}

ob_start();
include ROOT_PATH . '/app/includes/partials/manage_home_categories_panel.php';
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);

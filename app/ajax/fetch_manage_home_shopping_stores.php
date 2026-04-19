<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : 0;
if ($home_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

$shopping_stores_query = "SELECT id, name, icon, sort_order FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
$shopping_stores_result = mysqli_query($conn, $shopping_stores_query);
$shopping_stores = [];
if ($shopping_stores_result) {
    while ($store = mysqli_fetch_assoc($shopping_stores_result)) {
        $shopping_stores[] = $store;
    }
}

ob_start();
include ROOT_PATH . '/app/includes/partials/manage_home_shopping_stores_panel.php';
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);

<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . '/_auth.php';
    $auth = shopping_api_require_user($conn);
    $home_id = $auth['home_id'];

    $cats_query = "SELECT id, name, icon, sort_order FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
    $cats_result = mysqli_query($conn, $cats_query);

    $items_query = "SELECT id, category_id, item_name, quantity, sort_order, created_at FROM shopping_items WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
    $items_result = mysqli_query($conn, $items_query);

    $items_by_cat = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $cid = (int) $item['category_id'];
        if (!isset($items_by_cat[$cid])) {
            $items_by_cat[$cid] = [];
        }
        $items_by_cat[$cid][] = $item;
    }

    $categories = [];
    while ($cat = mysqli_fetch_assoc($cats_result)) {
        $cid = (int) $cat['id'];
        $cat['items'] = $items_by_cat[$cid] ?? [];
        $categories[] = $cat;
    }

    // כל החנויות ב-active_categories (גם ללא פריטים) — תאימות ללקוחות שמשתמשים בשדה; empty_categories נשאר ריק
    $active_categories = $categories;
    $empty_categories = [];

    echo json_encode([
        'status' => 'success',
        'categories' => $categories,
        'active_categories' => $active_categories,
        'empty_categories' => $empty_categories,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

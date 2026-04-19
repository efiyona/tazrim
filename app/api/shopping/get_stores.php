<?php
require('../../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

// 1. קבלת הטוקן ובדיקת שיטת הבקשה
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = $_POST['api_token'] ?? '';

if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'API Token is missing.']);
    exit();
}

// 2. אימות הטוקן מול מסד הנתונים
$token_query = "SELECT user_id, home_id FROM api_tokens WHERE token = '$token' LIMIT 1";
$token_result = mysqli_query($conn, $token_query);

if (mysqli_num_rows($token_result) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}

$auth_data = mysqli_fetch_assoc($token_result);
$home_id = (int)$auth_data['home_id'];

// עדכון תאריך שימוש אחרון
mysqli_query($conn, "UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = '$token'");

// 3. שליפת החנויות (shopping_categories) של הבית
$stores_query = "SELECT id, name, icon FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
$stores_result = mysqli_query($conn, $stores_query);

function getStoreEmoji($fa_icon) {
    $conversion = [
        'fa-cart-shopping'   => '🛒',
        'fa-leaf'            => '🥬',
        'fa-medkit'          => '💊',
        'fa-drumstick-bite'  => '🍗',
        'fa-bread-slice'     => '🥖',
        'fa-store'           => '🏪',
        'fa-plug'            => '🔌',
        'fa-box'             => '📦',
        'fa-shop'            => '🏬',
        'fa-cart-plus'       => '➕',
        'fa-basket-shopping' => '🧺'
    ];
    return $conversion[$fa_icon] ?? '🏪';
}

$stores = [];
while ($row = mysqli_fetch_assoc($stores_result)) {
    $emoji = getStoreEmoji($row['icon']);
    $display_name = $emoji . " " . $row['name'];
    $stores[$display_name] = (int)$row['id'];
}

echo json_encode([
    'status' => 'success',
    'categories' => $stores
], JSON_UNESCAPED_UNICODE);
exit();

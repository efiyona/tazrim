<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require('../../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = $_POST['api_token'] ?? '';
$category_id = (int)($_POST['category_id'] ?? 0);
$item_name = trim($_POST['item_name'] ?? '');

$quantity = trim($_POST['quantity'] ?? '1');
if ($quantity === '' || (int)$quantity < 1) {
    $quantity = '1';
}

if (empty($token) || $category_id <= 0 || $item_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid data.']);
    exit();
}

$token_query = "SELECT user_id, home_id FROM api_tokens WHERE token = '$token' LIMIT 1";
$token_result = mysqli_query($conn, $token_query);

if (mysqli_num_rows($token_result) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}

$auth_data = mysqli_fetch_assoc($token_result);
$home_id = (int)$auth_data['home_id'];

$check = $conn->prepare('SELECT id FROM shopping_categories WHERE id = ? AND home_id = ? LIMIT 1');
$check->bind_param('ii', $category_id, $home_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['status' => 'error', 'message' => 'Invalid store (category) for this home.']);
    exit();
}
$check->close();

$insert = $conn->prepare('INSERT INTO shopping_items (home_id, category_id, item_name, quantity) VALUES (?, ?, ?, ?)');
$insert->bind_param('iiss', $home_id, $category_id, $item_name, $quantity);

if ($insert->execute()) {
    $new_id = $conn->insert_id;
    $insert->close();
    mysqli_query($conn, "UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = '$token'");
    echo json_encode([
        'status' => 'success',
        'message' => 'Item saved successfully.',
        'new_id' => (int)$new_id,
    ]);
} else {
    $insert->close();
    echo json_encode(['status' => 'error', 'message' => 'Failed to save item. Database error.']);
}

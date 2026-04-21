<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['home_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

$homeId = (int) $_SESSION['home_id'];
$categoryId = (int) ($_POST['category_id'] ?? 0);
$itemsRaw = (string) ($_POST['items'] ?? '');
$items = json_decode($itemsRaw, true);

if ($categoryId <= 0 || !is_array($items) || count($items) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים']);
    exit();
}

$catStmt = $conn->prepare('SELECT id FROM shopping_categories WHERE id = ? AND home_id = ? LIMIT 1');
$catStmt->bind_param('ii', $categoryId, $homeId);
$catStmt->execute();
$catRes = $catStmt->get_result();
if (!$catRes || $catRes->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'חנות יעד לא נמצאה']);
    exit();
}

$normalized = [];
foreach ($items as $row) {
    if (!is_array($row)) continue;
    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') continue;
    $normalized[] = $name;
}
$normalized = array_values(array_unique($normalized));
if (count($normalized) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'לא נמצאו פריטים תקינים להוספה']);
    exit();
}

$conn->begin_transaction();
try {
    $minSort = 1000;
    $sortStmt = $conn->prepare('SELECT COALESCE(MIN(sort_order), 1000) AS m FROM shopping_items WHERE home_id = ? AND category_id = ?');
    $sortStmt->bind_param('ii', $homeId, $categoryId);
    $sortStmt->execute();
    $sortRes = $sortStmt->get_result();
    if ($sortRes && ($row = $sortRes->fetch_assoc())) {
        $minSort = (int) ($row['m'] ?? 1000);
    }

    $insertStmt = $conn->prepare('INSERT INTO shopping_items (home_id, category_id, item_name, quantity, sort_order) VALUES (?, ?, ?, ?, ?)');
    $inserted = 0;
    $quantity = '1';
    $sortOrder = $minSort - 1;

    foreach ($normalized as $name) {
        $insertStmt->bind_param('iissi', $homeId, $categoryId, $name, $quantity, $sortOrder);
        if (!$insertStmt->execute()) {
            throw new RuntimeException('insert_failed');
        }
        $inserted++;
        $sortOrder--;
    }

    $conn->commit();
    echo json_encode([
        'status' => 'success',
        'inserted_count' => $inserted
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => 'לא ניתן להוסיף את כל הפריטים כרגע. נסו שוב.'
    ], JSON_UNESCAPED_UNICODE);
}


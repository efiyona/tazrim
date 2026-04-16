<?php
/**
 * רשימת משתמשים לחיפוש (פופאפים וכו') — program_admin בלבד.
 */
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$q = trim((string) ($_GET['q'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 40;
if ($limit < 1) {
    $limit = 40;
}
if ($limit > 100) {
    $limit = 100;
}

global $conn;

$orderBy = 'last_name ASC, first_name ASC';
if (!preg_match('/^[a-zA-Z0-9_ ,]+$/', $orderBy)) {
    $orderBy = 'id DESC';
}

$selectSql = '`id`, `first_name`, `last_name`, `email`, `nickname`';
$from = '`users`';

$params = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $parts = [
        '`first_name` LIKE ?',
        '`last_name` LIKE ?',
        '`email` LIKE ?',
        '`nickname` LIKE ?',
    ];
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
    if (ctype_digit($q)) {
        $parts[] = '`id` = ?';
        $params[] = (int) $q;
        $types .= 'i';
    }
    $whereSql = '(' . implode(' OR ', $parts) . ')';
} else {
    $whereSql = '1=1';
}

$sql = "SELECT $selectSql FROM $from WHERE $whereSql ORDER BY $orderBy LIMIT " . (int) $limit;

$stmt = null;
if ($params !== []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
}

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vid = isset($row['id']) ? (int) $row['id'] : 0;
    if ($vid <= 0) {
        continue;
    }
    $fn = trim((string) ($row['first_name'] ?? ''));
    $ln = trim((string) ($row['last_name'] ?? ''));
    $em = trim((string) ($row['email'] ?? ''));
    $label = trim($fn . ' ' . $ln);
    if ($label === '') {
        $label = $em !== '' ? $em : '#' . $vid;
    }
    $label .= ' · ' . $em;
    $items[] = ['id' => $vid, 'label' => $label];
}

if ($stmt) {
    $stmt->close();
}

tazrim_admin_json_response(['status' => 'ok', 'items' => $items]);

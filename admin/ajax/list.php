<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

$tableKey = isset($_GET['t']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['t']) : '';
if ($tableKey === '' || $tableKey !== ($_GET['t'] ?? '')) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'בקשה לא תקינה.'], 400);
}

$config = tazrim_admin_get_table_config($tableKey);
if (!$config) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא קיימת.'], 404);
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($config['per_page']) ? (int) $config['per_page'] : 20;
if ($perPage < 1 || $perPage > 200) {
    $perPage = 20;
}

$sqlTable = $config['table'];
$listCols = $config['list_columns'] ?? ['id'];
$orderBy = $config['order_by'] ?? 'id DESC';
$searchCols = tazrim_admin_searchable_columns($config);
$q = trim((string) ($_GET['q'] ?? ''));

global $conn;

// אימות שמות עמודות — רק אותיות, מספרים, קו תחתון
foreach ($listCols as $c) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'תצורת עמודות לא תקינה.'], 500);
    }
}
if (!preg_match('/^[a-zA-Z0-9_ ,]+$/', $orderBy)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'תצורת מיון לא תקינה.'], 500);
}

$escTable = mysqli_real_escape_string($conn, $sqlTable);
$whereSql = '1=1';
$params = [];
$types = '';
if ($q !== '' && $searchCols !== []) {
    $parts = [];
    foreach ($searchCols as $column) {
        $parts[] = 'CAST(`' . $column . '` AS CHAR) LIKE ?';
        $params[] = '%' . $q . '%';
        $types .= 's';
    }
    $whereSql = '(' . implode(' OR ', $parts) . ')';
}

$countSql = "SELECT COUNT(*) AS c FROM `$escTable` WHERE $whereSql";
if ($params !== []) {
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countRes = $countStmt->get_result();
} else {
    $countRes = mysqli_query($conn, $countSql);
    if (!$countRes) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
}
$total = (int) mysqli_fetch_assoc($countRes)['c'];
$offset = ($page - 1) * $perPage;

$colSql = '`' . implode('`,`', $listCols) . '`';
$sql = "SELECT $colSql FROM `$escTable` WHERE $whereSql ORDER BY $orderBy LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

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
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת שאילתה.'], 500);
    }
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    if ($sqlTable === 'homes' && isset($row['initial_balance'])) {
        $row['initial_balance'] = decryptBalance($row['initial_balance']);
    }
    foreach ($listCols as $column) {
        $row[$column] = tazrim_admin_list_display_value($config, $column, $row);
    }
    $rows[] = $row;
}

if (isset($stmt)) {
    $stmt->close();
}
if (isset($countStmt)) {
    $countStmt->close();
}

tazrim_admin_json_response([
    'status' => 'ok',
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'columns' => $listCols,
]);

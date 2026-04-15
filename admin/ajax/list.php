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

$countRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `" . mysqli_real_escape_string($conn, $sqlTable) . "`");
if (!$countRes) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
}
$total = (int) mysqli_fetch_assoc($countRes)['c'];
$offset = ($page - 1) * $perPage;

$colSql = '`' . implode('`,`', $listCols) . '`';
$escTable = mysqli_real_escape_string($conn, $sqlTable);
$sql = "SELECT $colSql FROM `$escTable` ORDER BY $orderBy LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

$result = mysqli_query($conn, $sql);
if (!$result) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת שאילתה.'], 500);
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    if ($sqlTable === 'homes' && isset($row['initial_balance'])) {
        $row['initial_balance'] = decryptBalance($row['initial_balance']);
    }
    $rows[] = $row;
}

tazrim_admin_json_response([
    'status' => 'ok',
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'columns' => $listCols,
]);

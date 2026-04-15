<?php
/**
 * חיפוש AJAX לשדות fk_lookup — רק לפי הגדרות registry + program_admin.
 */
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$entityKey = isset($_GET['t']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['t']) : '';
$fieldName = isset($_GET['field']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['field']) : '';
if ($entityKey === '' || $fieldName === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'בקשה לא תקינה.'], 400);
}

$fk = tazrim_admin_get_fk_lookup_config($entityKey, $fieldName);
if (!$fk) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שדה לא נתמך.'], 404);
}

$table = $fk['table'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'תצורה לא תקינה.'], 500);
}

$valueCol = $fk['value_column'] ?? 'id';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $valueCol)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'תצורה לא תקינה.'], 500);
}

$template = isset($fk['label_template']) ? (string) $fk['label_template'] : '{' . $valueCol . '}';
$tplCols = tazrim_admin_fk_label_template_columns($template);

$searchCols = isset($fk['search_columns']) && is_array($fk['search_columns']) ? $fk['search_columns'] : $tplCols;
$cleanSearch = [];
foreach ($searchCols as $sc) {
    if (preg_match('/^[a-zA-Z0-9_]+$/', (string) $sc)) {
        $cleanSearch[] = (string) $sc;
    }
}
if ($cleanSearch === []) {
    $cleanSearch = $tplCols;
}
if ($cleanSearch === []) {
    $cleanSearch = [$valueCol];
}

$selectCols = array_unique(array_merge([$valueCol], $tplCols, $cleanSearch));
foreach ($selectCols as $c) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'תצורת עמודות לא תקינה.'], 500);
    }
}

$orderBy = trim((string) ($fk['order_by'] ?? 'id DESC'));
if (!preg_match('/^[a-zA-Z0-9_ ,]+$/', $orderBy)) {
    $orderBy = 'id DESC';
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
if ($limit < 1) {
    $limit = 30;
}
if ($limit > 100) {
    $limit = 100;
}

$q = trim((string) ($_GET['q'] ?? ''));

global $conn;

$selectSql = '`' . implode('`,`', $selectCols) . '`';
$escTable = mysqli_real_escape_string($conn, $table);
$from = '`' . $escTable . '`';

$params = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $parts = [];
    foreach ($cleanSearch as $sc) {
        $parts[] = '`' . $sc . '` LIKE ?';
        $params[] = $like;
        $types .= 's';
    }
    if (ctype_digit($q)) {
        $parts[] = '`' . $valueCol . '` = ?';
        $params[] = (int) $q;
        $types .= 'i';
    }
    if ($parts === []) {
        $whereSql = '1=1';
    } else {
        $whereSql = '(' . implode(' OR ', $parts) . ')';
    }
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
    if ($table === 'homes' && isset($row['initial_balance'])) {
        $row['initial_balance'] = decryptBalance($row['initial_balance']);
    }
    $vid = isset($row[$valueCol]) ? (int) $row[$valueCol] : 0;
    if ($vid <= 0) {
        continue;
    }
    $label = tazrim_admin_fk_format_label($template, $row);
    if ($label === '') {
        $label = '#' . $vid;
    }
    $items[] = ['id' => $vid, 'label' => $label];
}

if ($stmt) {
    $stmt->close();
}

tazrim_admin_json_response(['status' => 'ok', 'items' => $items]);

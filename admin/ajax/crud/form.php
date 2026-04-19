<?php
require_once dirname(__DIR__, 2) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$tableKey = isset($_GET['t']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['t']) : '';
if ($tableKey === '' || $tableKey !== ($_GET['t'] ?? '')) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא תקינה.'], 400);
}

$config = tazrim_admin_get_table_config($tableKey);
if (!$config) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא קיימת.'], 404);
}

if (!empty($config['list_only'])) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה זו לצפייה בלבד.'], 403);
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
$sqlTable = $config['table'];
if ($editId > 0) {
    $row = selectOne($sqlTable, ['id' => $editId]);
    if (!$row) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'הרשומה לא נמצאה.'], 404);
    }
}

ob_start();
require dirname(__DIR__, 2) . '/includes/partials/crud_form_fields.php';
$fieldsHtml = ob_get_clean();

tazrim_admin_json_response([
    'status' => 'ok',
    'title' => $editId > 0 ? 'עריכת רשומה #' . $editId : 'הוספת רשומה',
    'fields_html' => $fieldsHtml,
    'mode' => $editId > 0 ? 'update' : 'create',
    'id' => $editId,
    'allow_delete' => (!isset($config['allow_delete']) || $config['allow_delete'] !== false) && $editId > 0,
]);

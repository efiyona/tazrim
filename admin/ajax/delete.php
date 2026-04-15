<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'גוף בקשה לא תקין.'], 400);
}

$csrf = $body['csrf_token'] ?? '';
if (!tazrim_admin_csrf_validate($csrf)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'פג תוקף אבטחה. רעננו את הדף.'], 419);
}

$tableKey = isset($body['t']) ? preg_replace('/[^a-z0-9_]/', '', $body['t']) : '';
if ($tableKey === '' || $tableKey !== ($body['t'] ?? '')) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא תקינה.'], 400);
}

$config = tazrim_admin_get_table_config($tableKey);
if (!$config) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא קיימת.'], 404);
}

if (!empty($config['list_only'])) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה זו לצפייה בלבד.'], 403);
}

if (isset($config['allow_delete']) && $config['allow_delete'] === false) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מחיקה לא מופעלת לטבלה זו.'], 403);
}

$id = isset($body['id']) ? (int) $body['id'] : 0;
if ($id <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מזהה לא תקין.'], 400);
}

$sqlTable = $config['table'];
global $conn;

$block = tazrim_admin_delete_row_allowed($tableKey, $id);
if ($block !== null) {
    tazrim_admin_json_response(['status' => 'error', 'message' => $block], 403);
}

delete($sqlTable, $id);
if (!empty($conn->errno)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא ניתן למחוק (ייתכן קשר לרשומות אחרות).'], 500);
}
tazrim_admin_json_response(['status' => 'ok']);

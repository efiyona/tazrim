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

$rawIds = $body['ids'] ?? [];
if (!is_array($rawIds)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'רשימת מזהים לא תקינה.'], 400);
}

$ids = [];
foreach ($rawIds as $rid) {
    $n = (int) $rid;
    if ($n > 0) {
        $ids[$n] = true;
    }
}
$ids = array_keys($ids);
sort($ids, SORT_NUMERIC);

if ($ids === []) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא נבחרו רשומות למחיקה.'], 400);
}

if (count($ids) > 200) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'ניתן למחוק לכל היותר 200 רשומות בבת אחת.'], 400);
}

foreach ($ids as $id) {
    $err = tazrim_admin_delete_row_allowed($tableKey, $id);
    if ($err !== null) {
        tazrim_admin_json_response(['status' => 'error', 'message' => $err], 403);
    }
}

$sqlTable = $config['table'];
global $conn;

$conn->begin_transaction();
try {
    foreach ($ids as $id) {
        delete($sqlTable, $id);
        if (!empty($conn->errno)) {
            throw new RuntimeException($conn->error ?: 'שגיאת מסד נתונים');
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא ניתן למחוק (ייתכן קשר לרשומות אחרות).'], 500);
}

tazrim_admin_json_response(['status' => 'ok', 'deleted' => count($ids)]);

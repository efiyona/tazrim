<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$body = tazrim_admin_read_json_body();
tazrim_admin_validate_csrf_or_fail((string) ($body['csrf_token'] ?? ''));
[$tableKey, $config] = tazrim_admin_resolve_table_config_from_body($body);
tazrim_admin_crud_guard_delete_allowed($config);

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

$txHomesBulk = [];
if ($sqlTable === 'transactions') {
    foreach ($ids as $tid) {
        $rowTx = selectOne('transactions', ['id' => $tid]);
        if ($rowTx && isset($rowTx['home_id'])) {
            $txHomesBulk[(int) $rowTx['home_id']] = true;
        }
    }
}

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

if ($sqlTable === 'transactions') {
    tazrim_admin_recompute_ledger_for_home_ids($conn, array_keys($txHomesBulk));
}

tazrim_admin_json_response(['status' => 'ok', 'deleted' => count($ids)]);

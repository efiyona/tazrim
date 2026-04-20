<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$body = tazrim_admin_read_json_body();
tazrim_admin_validate_csrf_or_fail((string) ($body['csrf_token'] ?? ''));
[$tableKey, $config] = tazrim_admin_resolve_table_config_from_body($body);
tazrim_admin_crud_guard_delete_allowed($config);

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

$txHomeDel = 0;
if ($sqlTable === 'transactions') {
    $rowTx = selectOne('transactions', ['id' => $id]);
    $txHomeDel = $rowTx ? (int) ($rowTx['home_id'] ?? 0) : 0;
}

delete($sqlTable, $id);
if (!empty($conn->errno)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא ניתן למחוק (ייתכן קשר לרשומות אחרות).'], 500);
}
if ($sqlTable === 'transactions') {
    tazrim_admin_recompute_ledger_for_home_ids($conn, [$txHomeDel]);
}
tazrim_admin_json_response(['status' => 'ok']);

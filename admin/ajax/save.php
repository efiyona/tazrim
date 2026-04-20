<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$body = tazrim_admin_read_json_body();
tazrim_admin_validate_csrf_or_fail((string) ($body['csrf_token'] ?? ''));
[$tableKey, $config] = tazrim_admin_resolve_table_config_from_body($body);
tazrim_admin_crud_guard_table_mutation($config);

$action = $body['action'] ?? '';
if ($action !== 'create' && $action !== 'update') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'פעולה לא מורשית.'], 400);
}

$sqlTable = $config['table'];
$fieldDefs = $config['fields'] ?? [];
$allowed = tazrim_admin_allowed_field_keys($config);
$dataIn = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

$data = [];
foreach ($allowed as $name) {
    if (!array_key_exists($name, $dataIn)) {
        continue;
    }
    $fd = $fieldDefs[$name] ?? [];
    $type = $fd['type'] ?? 'text';
    $data[$name] = tazrim_admin_normalize_field_value($type, $dataIn[$name], $fd);
}

$data = tazrim_admin_apply_encrypt_for_save($tableKey, $data);

if ($tableKey === 'users') {
    $newPw = isset($data['new_password']) ? (string) $data['new_password'] : '';
    unset($data['new_password']);
    if ($newPw !== '') {
        $data['password'] = password_hash($newPw, PASSWORD_DEFAULT);
    }
    if ($action === 'create' && empty($data['password'])) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'נדרשת סיסמה למשתמש חדש (שדה סיסמה חדשה).'], 400);
    }
    if ($action === 'update' && empty($data['password'])) {
        unset($data['password']);
    }

    if (array_key_exists('phone', $data)) {
        require_once ROOT_PATH . '/app/helpers/phone_uniqueness.php';
        $rawPhone = (string) $data['phone'];
        $phoneNorm = tazrim_normalize_phone_key($rawPhone);
        if (trim($rawPhone) !== '' && $phoneNorm === '') {
            tazrim_admin_json_response(['status' => 'error', 'message' => 'מספר הטלפון אינו תקין.'], 400);
        }
        if ($phoneNorm !== '') {
            $excludeId = null;
            if ($action === 'update') {
                $uid = (int) ($body['id'] ?? 0);
                if ($uid > 0) {
                    $excludeId = $uid;
                }
            }
            if (tazrim_user_id_with_normalized_phone($phoneNorm, $excludeId)) {
                tazrim_admin_json_response(['status' => 'error', 'message' => 'מספר הטלפון כבר רשום אצל משתמש אחר במערכת.'], 400);
            }
        }
        $data['phone'] = $phoneNorm;
    }
}

global $conn;

if ($action === 'create') {
    if (empty($data)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'אין נתונים לשמירה.'], 400);
    }
    $newId = create($sqlTable, $data);
    if (!empty($conn->errno)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים (בדקו שדות חובה וייחודיות).'], 500);
    }
    tazrim_admin_tos_terms_after_save($tableKey, (int) $newId, $data);
    if ($sqlTable === 'transactions') {
        tazrim_admin_recompute_ledger_for_home_ids($conn, [(int) ($data['home_id'] ?? 0)]);
    }
    tazrim_admin_json_response(['status' => 'ok', 'id' => (int) $newId]);
}

$id = isset($body['id']) ? (int) $body['id'] : 0;
if ($id <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מזהה שורה חסר.'], 400);
}

if (empty($data)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'אין שדות לעדכון.'], 400);
}

$txHomeBefore = 0;
if ($sqlTable === 'transactions') {
    $beforeTx = selectOne('transactions', ['id' => $id]);
    $txHomeBefore = $beforeTx ? (int) ($beforeTx['home_id'] ?? 0) : 0;
}

update($sqlTable, $id, $data);
if (!empty($conn->errno)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים (בדקו שדות חובה וייחודיות).'], 500);
}
tazrim_admin_tos_terms_after_save($tableKey, $id, $data);
if ($sqlTable === 'transactions') {
    $afterTx = selectOne('transactions', ['id' => $id]);
    $txHomeAfter = $afterTx ? (int) ($afterTx['home_id'] ?? 0) : 0;
    tazrim_admin_recompute_ledger_for_home_ids($conn, [$txHomeAfter, $txHomeBefore]);
}
tazrim_admin_json_response(['status' => 'ok', 'id' => $id]);

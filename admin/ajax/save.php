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
    tazrim_admin_json_response(['status' => 'ok', 'id' => (int) $newId]);
}

$id = isset($body['id']) ? (int) $body['id'] : 0;
if ($id <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מזהה שורה חסר.'], 400);
}

if (empty($data)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'אין שדות לעדכון.'], 400);
}

update($sqlTable, $id, $data);
if (!empty($conn->errno)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים (בדקו שדות חובה וייחודיות).'], 500);
}
tazrim_admin_tos_terms_after_save($tableKey, $id, $data);
tazrim_admin_json_response(['status' => 'ok', 'id' => $id]);

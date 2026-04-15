<?php

/**
 * שדות שלא ניתן לערוך בפאנל (בכל טבלה), אלא אם יוגדרו במפורש בעתיד (לא מומש).
 */
function tazrim_admin_blocked_field_names(): array
{
    return ['password', 'remember_token', 'api_token'];
}

/**
 * שדות שדורשים encryptBalance לפני שמירה בטבלה homes.
 */
function tazrim_admin_balance_encrypt_map(): array
{
    return [
        'homes' => ['initial_balance'],
    ];
}

function tazrim_admin_registry(): array
{
    static $reg = null;
    if ($reg === null) {
        $reg = require dirname(__DIR__) . '/config/registry.php';
    }
    return $reg;
}

function tazrim_admin_get_table_config(string $key): ?array
{
    $reg = tazrim_admin_registry();
    return isset($reg[$key]) ? $reg[$key] : null;
}

/**
 * הגדרת fk_lookup לשדה בטבלה רשומה ב-registry (או null).
 */
function tazrim_admin_get_fk_lookup_config(string $entityKey, string $fieldName): ?array
{
    $config = tazrim_admin_get_table_config($entityKey);
    if (!$config || empty($config['fields'][$fieldName])) {
        return null;
    }
    $fd = $config['fields'][$fieldName];
    if (($fd['type'] ?? '') !== 'fk_lookup' || empty($fd['fk']) || !is_array($fd['fk'])) {
        return null;
    }
    return $fd['fk'];
}

/**
 * עמודות מופיעות בתבנית {col}.
 *
 * @return array<int, string>
 */
function tazrim_admin_fk_label_template_columns(string $template): array
{
    if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $m)) {
        return array_values(array_unique($m[1]));
    }
    return [];
}

/**
 * בונה תווית תצוגה משורה בטבלת היעד.
 */
function tazrim_admin_fk_format_label(string $template, array $row): string
{
    $out = $template;
    foreach ($row as $k => $v) {
        if (!is_scalar($v) && $v !== null) {
            continue;
        }
        $rep = $v === null ? '' : (string) $v;
        $out = str_replace('{' . $k . '}', $rep, $out);
    }
    return trim(preg_replace('/\s+/', ' ', $out));
}

/**
 * טוען תווית לפי מזהה (לטעינה ראשונית בטופס).
 */
function tazrim_admin_fk_lookup_resolve_label(array $fk, $id): string
{
    $id = (int) $id;
    if ($id <= 0) {
        return '';
    }
    $table = $fk['table'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return '';
    }
    $valueCol = $fk['value_column'] ?? 'id';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $valueCol)) {
        return '';
    }
    $template = isset($fk['label_template']) ? (string) $fk['label_template'] : '{' . $valueCol . '}';
    $row = selectOne($table, [$valueCol => $id]);
    if (!$row) {
        return '';
    }
    if ($table === 'homes' && isset($row['initial_balance'])) {
        $row['initial_balance'] = decryptBalance($row['initial_balance']);
    }
    return tazrim_admin_fk_format_label($template, $row);
}

function tazrim_admin_allowed_field_keys(array $config): array
{
    $fields = $config['fields'] ?? [];
    $blocked = array_flip(tazrim_admin_blocked_field_names());
    $out = [];
    foreach (array_keys($fields) as $name) {
        if (!isset($blocked[$name])) {
            $out[] = $name;
        }
    }
    return $out;
}

/**
 * מיישם הצפנה לפני create/update לפי מפתח הטבלה ב־registry (לא שם מלא ב-SQL).
 */
function tazrim_admin_apply_encrypt_for_save(string $tableKey, array $data): array
{
    $map = tazrim_admin_balance_encrypt_map();
    if (!isset($map[$tableKey])) {
        return $data;
    }
    foreach ($map[$tableKey] as $col) {
        if (!array_key_exists($col, $data)) {
            continue;
        }
        $v = $data[$col];
        if ($v === null || $v === '') {
            $data[$col] = null;
            continue;
        }
        $data[$col] = encryptBalance((string) $v);
    }
    return $data;
}

/**
 * ערכי טופס (מחרוזות) -> סוגים לשמירה ב-SQL.
 *
 * @param array $fieldDef empty_zero: מספר ריק יהפוך ל-0 (למשל sort_order)
 */
function tazrim_admin_normalize_field_value(string $type, $raw, array $fieldDef = [])
{
    if ($raw === null) {
        return null;
    }
    switch ($type) {
        case 'checkbox':
            return ($raw === '' || $raw === '0' || $raw === 0 || $raw === false) ? 0 : 1;
        case 'number':
        case 'balance':
            if ($raw === '') {
                if (!empty($fieldDef['empty_zero'])) {
                    return 0;
                }
                return null;
            }
            return is_numeric($raw) ? 0 + $raw : null;
        case 'datetime':
            $raw = trim((string) $raw);
            return $raw === '' ? null : $raw;
        case 'password_new':
            return trim((string) $raw);
        case 'enum':
            $raw = trim((string) $raw);
            $opts = isset($fieldDef['enum_options']) && is_array($fieldDef['enum_options'])
                ? array_keys($fieldDef['enum_options'])
                : [];
            return in_array($raw, $opts, true) ? $raw : ($opts[0] ?? '');
        case 'fk_lookup':
            if ($raw === '' || $raw === null) {
                return null;
            }
            return is_numeric($raw) ? (int) $raw : null;
        default:
            return (string) $raw;
    }
}

function tazrim_admin_json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @return string|null הודעת שגיאה או null אם מותר למחוק
 */
function tazrim_admin_delete_row_allowed(string $tableKey, int $id): ?string
{
    if ($id <= 0) {
        return 'מזהה לא תקין.';
    }
    if ($tableKey === 'tos_terms') {
        $row = selectOne('tos_terms', ['id' => $id]);
        if ($row && !empty($row['is_current'])) {
            return 'לא ניתן למחוק את הנוסח הנוכחי. קבעו גרסה אחרת כנוכחית ואז מחקו.';
        }
    }
    return null;
}

/**
 * לאחר שמירת נוסח תקנון: אם סומן כנוכחי — מבטל נוכחיות בשאר השורות.
 */
function tazrim_admin_tos_terms_after_save(string $tableKey, int $rowId, array $data): void
{
    if ($tableKey !== 'tos_terms') {
        return;
    }
    if (!isset($data['is_current']) || (int) $data['is_current'] !== 1) {
        return;
    }
    global $conn;
    $rowId = (int) $rowId;
    if ($rowId <= 0) {
        return;
    }
    mysqli_query($conn, 'UPDATE `tos_terms` SET `is_current` = 0 WHERE `id` <> ' . $rowId);
}

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
        'homes' => ['bank_balance_ledger_cached', 'bank_balance_manual_adjustment'],
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

function tazrim_admin_nav_group_defs(): array
{
    return [
        'legal' => [
            'label' => 'תקנון והסכמות',
            'icon' => 'fa-file-contract',
        ],
    ];
}

function tazrim_admin_nav_items(): array
{
    $registry = tazrim_admin_registry();
    $groupDefs = tazrim_admin_nav_group_defs();
    $items = [
        [
            'type' => 'link',
            'key' => 'dashboard',
            'label' => 'לוח בקרה',
            'icon' => 'fa-gauge-high',
            'href' => BASE_URL . 'admin/dashboard.php',
        ],
        [
            'type' => 'group',
            'key' => 'broadcast_user_messages',
            'label' => 'הודעות למשתמשים',
            'icon' => 'fa-bullhorn',
            'children' => [
                [
                    'type' => 'link',
                    'key' => 'push_broadcast',
                    'label' => 'שידור פוש',
                    'icon' => 'fa-bullhorn',
                    'href' => BASE_URL . 'admin/push_broadcast.php',
                ],
                [
                    'type' => 'link',
                    'key' => 'mass_email',
                    'label' => 'שליחת מיילים',
                    'icon' => 'fa-envelope',
                    'href' => BASE_URL . 'admin/mass_email.php',
                ],
                [
                    'type' => 'link',
                    'key' => 'popup_campaigns',
                    'label' => 'פופאפים למשתמשים',
                    'icon' => 'fa-message',
                    'href' => BASE_URL . 'admin/popup_campaigns.php',
                ],
            ],
        ],
    ];
    $groupOrder = [];
    $groupChildren = [];

    foreach ($registry as $key => $cfg) {
        if (empty($cfg['table'])) {
            continue;
        }
        $item = [
            'type' => 'link',
            'key' => $key,
            'label' => $cfg['label'] ?? $key,
            'icon' => $cfg['nav_icon'] ?? 'fa-table',
            'href' => BASE_URL . 'admin/table.php?t=' . urlencode($key),
        ];
        $groupKey = isset($cfg['nav_group']) ? trim((string) $cfg['nav_group']) : '';
        if ($groupKey !== '') {
            if (!isset($groupChildren[$groupKey])) {
                $groupChildren[$groupKey] = [];
                $groupOrder[] = $groupKey;
            }
            $groupChildren[$groupKey][] = $item;
            continue;
        }
        $items[] = $item;
    }

    foreach ($groupOrder as $groupKey) {
        $def = $groupDefs[$groupKey] ?? [];
        $items[] = [
            'type' => 'group',
            'key' => $groupKey,
            'label' => $def['label'] ?? $groupKey,
            'icon' => $def['icon'] ?? 'fa-folder-tree',
            'children' => $groupChildren[$groupKey] ?? [],
        ];
    }

    return $items;
}

function tazrim_admin_nav_item_is_active(array $item, string $navCtx): bool
{
    if (($item['type'] ?? 'link') === 'group') {
        foreach (($item['children'] ?? []) as $child) {
            if (($child['key'] ?? '') === $navCtx) {
                return true;
            }
        }
        return false;
    }
    return ($item['key'] ?? '') === $navCtx;
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
    static $cache = [];
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
    $cacheKey = md5(json_encode([$table, $valueCol, $template, $id], JSON_UNESCAPED_UNICODE));
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $row = selectOne($table, [$valueCol => $id]);
    if (!$row) {
        return '';
    }
    if ($table === 'homes') {
        if (isset($row['bank_balance_ledger_cached'])) {
            $row['bank_balance_ledger_cached'] = decryptBalance($row['bank_balance_ledger_cached']);
        }
        if (isset($row['bank_balance_manual_adjustment'])) {
            $row['bank_balance_manual_adjustment'] = decryptBalance($row['bank_balance_manual_adjustment']);
        }
    }
    $cache[$cacheKey] = tazrim_admin_fk_format_label($template, $row);
    return $cache[$cacheKey];
}

function tazrim_admin_searchable_columns(array $config): array
{
    $searchCols = isset($config['search_columns']) && is_array($config['search_columns'])
        ? $config['search_columns']
        : ($config['list_columns'] ?? ['id']);
    $clean = [];
    foreach ($searchCols as $column) {
        $column = (string) $column;
        if (preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            $clean[] = $column;
        }
    }
    return array_values(array_unique($clean));
}

function tazrim_admin_list_display_value(array $config, string $column, array $row): string
{
    $raw = $row[$column] ?? '';
    if ($raw === null) {
        return '';
    }
    $fieldDef = $config['fields'][$column] ?? null;
    if (is_array($fieldDef) && ($fieldDef['type'] ?? '') === 'checkbox') {
        return (string) ((int) $raw === 1 ? 'כן' : 'לא');
    }
    if (is_array($fieldDef) && ($fieldDef['type'] ?? '') === 'fk_lookup' && !empty($fieldDef['fk'])) {
        $label = tazrim_admin_fk_lookup_resolve_label($fieldDef['fk'], $raw);
        if ($label !== '') {
            return $label;
        }
    }
    if (is_scalar($raw)) {
        return (string) $raw;
    }
    return json_encode($raw, JSON_UNESCAPED_UNICODE);
}

function tazrim_admin_field_value(array $fieldDef, $row, string $name)
{
    if (($fieldDef['type'] ?? '') === 'password_new') {
        return '';
    }
    if ($row && array_key_exists($name, $row)) {
        $v = $row[$name];
        if ($v === null) {
            return '';
        }
        if (($fieldDef['type'] ?? '') === 'checkbox') {
            return (int) $v ? '1' : '0';
        }
        return (string) $v;
    }
    if (($fieldDef['type'] ?? '') === 'checkbox') {
        return '1';
    }
    return '';
}

function tazrim_admin_sidebar_metrics(): array
{
    global $conn;
    $queries = [
        'users_total' => 'SELECT COUNT(*) AS c FROM `users`',
        'homes_total' => 'SELECT COUNT(*) AS c FROM `homes`',
        'pending_reports' => "SELECT COUNT(*) AS c FROM `feedback_reports` WHERE `status` IN ('new', 'in_review')",
    ];
    $out = [];
    foreach ($queries as $key => $sql) {
        $res = mysqli_query($conn, $sql);
        $out[$key] = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
    }
    return $out;
}

function tazrim_admin_asset_href(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');
    $href = rtrim(BASE_URL, '/') . '/' . $relativePath;
    $fullPath = rtrim(ROOT_PATH, '/') . '/' . $relativePath;
    if (is_file($fullPath)) {
        $mtime = @filemtime($fullPath);
        if ($mtime) {
            $href .= '?v=' . rawurlencode((string) $mtime);
        }
    }
    return $href;
}

function tazrim_admin_push_link_options(): array
{
    return [
        '/' => 'דף הבית',
        '/pages/reports.php' => 'דוחות ותובנות',
        '/pages/shopping.php' => 'רשימת קניות',
        '/pages/settings/manage_home.php' => 'ניהול בית',
        '/pages/settings/user_profile.php' => 'הפרופיל שלי',
        '/pages/accept_tos.php' => 'תקנון והסכמות',
        '/pages/welcome.php' => 'ברוכים הבאים',
    ];
}

/**
 * ביצוע שידור פוש / פעמון — לוגיקה משותפת ל־ajax/push_broadcast ולסוכן AI.
 *
 * @param array $body כמו גוף JSON של ajax: title, body, link?, target?, delivery?, home_ids?
 * @return array{ok:bool,message:string,homes_count?:int,http_code?:int}
 */
function tazrim_admin_push_broadcast_execute(mysqli $conn, array $body): array
{
    $title = trim((string) ($body['title'] ?? ''));
    $bodyText = trim((string) ($body['body'] ?? ''));
    $link = trim((string) ($body['link'] ?? '/'));
    if ($link === '') {
        $link = '/';
    }

    if ($title === '' || $bodyText === '') {
        return ['ok' => false, 'message' => 'שדות כותרת ותוכן הם חובה.', 'http_code' => 400];
    }

    $target = isset($body['target']) ? (string) $body['target'] : 'all';
    if ($target !== 'all' && $target !== 'homes') {
        return ['ok' => false, 'message' => 'יעד שידור לא תקין.', 'http_code' => 400];
    }

    $delivery = isset($body['delivery']) ? (string) $body['delivery'] : 'push';
    if (!in_array($delivery, ['push', 'bell', 'both'], true)) {
        $delivery = 'push';
    }

    require_once ROOT_PATH . '/app/functions/push_functions.php';

    $homeIds = [];

    if ($target === 'all') {
        $homes_result = mysqli_query($conn, 'SELECT id FROM homes');
        if (!$homes_result) {
            return ['ok' => false, 'message' => 'שגיאת מסד נתונים.', 'http_code' => 500];
        }
        while ($home = mysqli_fetch_assoc($homes_result)) {
            $homeIds[] = (int) $home['id'];
        }
    } else {
        $rawIds = $body['home_ids'] ?? [];
        if (!is_array($rawIds)) {
            return ['ok' => false, 'message' => 'רשימת בתים לא תקינה.', 'http_code' => 400];
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
            return ['ok' => false, 'message' => 'נא לבחור לפחות בית אחד.', 'http_code' => 400];
        }
        if (count($ids) > 500) {
            return ['ok' => false, 'message' => 'יותר מדי בתים בבת אחת.', 'http_code' => 400];
        }
        $inList = implode(',', array_map('intval', $ids));
        $chk = mysqli_query($conn, "SELECT id FROM homes WHERE id IN ($inList)");
        if (!$chk) {
            return ['ok' => false, 'message' => 'שגיאת מסד נתונים.', 'http_code' => 500];
        }
        $found = [];
        while ($row = mysqli_fetch_assoc($chk)) {
            $found[] = (int) $row['id'];
        }
        sort($found, SORT_NUMERIC);
        if ($found !== $ids) {
            return ['ok' => false, 'message' => 'אחד או יותר מזהי הבתים אינם קיימים במערכת.', 'http_code' => 400];
        }
        $homeIds = $ids;
    }

    $bellHref = $link;
    if ($bellHref !== '' && $bellHref !== '/' && !preg_match('#^https?://#i', $bellHref)) {
        $bellHref = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/' . ltrim($bellHref, '/');
    }
    $bellMessage = nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));
    if ($link !== '' && $link !== '/') {
        $bellMessage .= '<br><a href="' . htmlspecialchars($bellHref, ENT_QUOTES, 'UTF-8') . '">מעבר לעמוד</a>';
    }

    $sent_count = 0;
    foreach ($homeIds as $hid) {
        if ($delivery === 'push' || $delivery === 'both') {
            sendPushToEntireHome($hid, $title, $bodyText, $link, 'system');
        }
        if ($delivery === 'bell' || $delivery === 'both') {
            addNotification($hid, $title, $bellMessage, 'info', null);
        }
        $sent_count++;
    }

    $channelDesc = $delivery === 'bell' ? 'התראות פעמון' : ($delivery === 'both' ? 'Push ופעמון' : 'Push');
    if ($target === 'all') {
        $msg = 'ההודעה נשלחה (' . $channelDesc . ') לכל הבתים במערכת (' . $sent_count . ' בתים).';
    } else {
        $msg = 'ההודעה נשלחה (' . $channelDesc . ') ל-' . $sent_count . ' בתים נבחרים.';
    }

    return [
        'ok' => true,
        'message' => $msg,
        'homes_count' => $sent_count,
    ];
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
            $data[$col] = encryptBalance(0.0);
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

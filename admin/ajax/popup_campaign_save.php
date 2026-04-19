<?php
/**
 * שמירת קמפיין פופאפ — program_admin בלבד.
 */
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

$id = isset($body['id']) ? (int) $body['id'] : 0;
$title = trim((string) ($body['title'] ?? ''));
$bodyHtml = (string) ($body['body_html'] ?? '');
$targetScope = (string) ($body['target_scope'] ?? 'all');
$status = (string) ($body['status'] ?? 'draft');
$isActive = !empty($body['is_active']) ? 1 : 0;
$sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;

$startsRaw = isset($body['starts_at']) ? trim((string) $body['starts_at']) : '';
$endsRaw = isset($body['ends_at']) ? trim((string) $body['ends_at']) : '';

$homeIds = isset($body['home_ids']) && is_array($body['home_ids']) ? $body['home_ids'] : [];
$userIds = isset($body['user_ids']) && is_array($body['user_ids']) ? $body['user_ids'] : [];

if ($title === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'כותרת היא חובה.'], 400);
}

if (!in_array($targetScope, ['all', 'homes', 'users'], true)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'יעד לא תקין.'], 400);
}

if (!in_array($status, ['draft', 'published'], true)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'סטטוס לא תקין.'], 400);
}

$homeIdsClean = [];
foreach ($homeIds as $hid) {
    $n = (int) $hid;
    if ($n > 0) {
        $homeIdsClean[$n] = true;
    }
}
$homeIdsClean = array_keys($homeIdsClean);
sort($homeIdsClean, SORT_NUMERIC);

$userIdsClean = [];
foreach ($userIds as $uid) {
    $n = (int) $uid;
    if ($n > 0) {
        $userIdsClean[$n] = true;
    }
}
$userIdsClean = array_keys($userIdsClean);
sort($userIdsClean, SORT_NUMERIC);

if ($targetScope === 'homes' && $homeIdsClean === []) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא לבחור לפחות בית אחד.'], 400);
}

if ($targetScope === 'users' && $userIdsClean === []) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא לבחור לפחות משתמש אחד.'], 400);
}

/**
 * @return string|null datetime 'Y-m-d H:i:s'
 */
function tazrim_admin_popup_parse_dt_local(?string $s): ?string
{
    if ($s === null || $s === '') {
        return null;
    }
    $s = trim($s);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})(?::(\d{2}))?$/', $s, $m)) {
        $sec = isset($m[3]) && $m[3] !== '' ? $m[3] : '00';

        return $m[1] . ' ' . $m[2] . ':' . $sec;
    }

    return null;
}

$startsAt = tazrim_admin_popup_parse_dt_local($startsRaw);
$endsAt = tazrim_admin_popup_parse_dt_local($endsRaw);

global $conn;

$titleEsc = mysqli_real_escape_string($conn, $title);
$bodyEsc = mysqli_real_escape_string($conn, $bodyHtml);
$scopeEsc = mysqli_real_escape_string($conn, $targetScope);
$statusEsc = mysqli_real_escape_string($conn, $status);
$startsSql = $startsAt === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $startsAt) . "'";
$endsSql = $endsAt === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $endsAt) . "'";

mysqli_begin_transaction($conn);

try {
    if ($id <= 0) {
        $sql = "INSERT INTO `popup_campaigns` (`title`, `body_html`, `target_scope`, `status`, `is_active`, `sort_order`, `starts_at`, `ends_at`)
                VALUES ('{$titleEsc}', '{$bodyEsc}', '{$scopeEsc}', '{$statusEsc}', {$isActive}, {$sortOrder}, {$startsSql}, {$endsSql})";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException(mysqli_error($conn));
        }
        $id = (int) mysqli_insert_id($conn);
    } else {
        $sql = "UPDATE `popup_campaigns` SET
                `title` = '{$titleEsc}',
                `body_html` = '{$bodyEsc}',
                `target_scope` = '{$scopeEsc}',
                `status` = '{$statusEsc}',
                `is_active` = {$isActive},
                `sort_order` = {$sortOrder},
                `starts_at` = {$startsSql},
                `ends_at` = {$endsSql}
                WHERE `id` = {$id} LIMIT 1";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }

    if (!mysqli_query($conn, 'DELETE FROM `popup_campaign_homes` WHERE `campaign_id` = ' . (int) $id)) {
        throw new RuntimeException(mysqli_error($conn));
    }
    if (!mysqli_query($conn, 'DELETE FROM `popup_campaign_users` WHERE `campaign_id` = ' . (int) $id)) {
        throw new RuntimeException(mysqli_error($conn));
    }

    if ($targetScope === 'homes' && $homeIdsClean !== []) {
        foreach ($homeIdsClean as $hid) {
            $hid = (int) $hid;
            $ins = "INSERT INTO `popup_campaign_homes` (`campaign_id`, `home_id`) VALUES ({$id}, {$hid})";
            if (!mysqli_query($conn, $ins)) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }
    }

    if ($targetScope === 'users' && $userIdsClean !== []) {
        foreach ($userIdsClean as $uid) {
            $uid = (int) $uid;
            $ins = "INSERT INTO `popup_campaign_users` (`campaign_id`, `user_id`) VALUES ({$id}, {$uid})";
            if (!mysqli_query($conn, $ins)) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שמירה נכשלה: ' . $e->getMessage()], 500);
}

tazrim_admin_json_response(['status' => 'ok', 'message' => 'נשמר בהצלחה.', 'id' => $id]);

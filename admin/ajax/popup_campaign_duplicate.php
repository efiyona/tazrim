<?php
/**
 * שכפול קמפיין פופאפ — עותק חדש כטיוטה — program_admin בלבד.
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
if ($id <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מזהה לא תקין.'], 400);
}

global $conn;

$row = selectOne('popup_campaigns', ['id' => $id]);
if (!$row) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'קמפיין לא נמצא.'], 404);
}

$baseTitle = trim((string) ($row['title'] ?? ''));
$suffix = ' (עותק)';
if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    $newTitle = $baseTitle . $suffix;
    if (mb_strlen($newTitle, 'UTF-8') > 255) {
        $newTitle = mb_substr($baseTitle, 0, 255 - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
    }
} else {
    $newTitle = $baseTitle . $suffix;
    if (strlen($newTitle) > 255) {
        $newTitle = substr($baseTitle, 0, 255 - strlen($suffix)) . $suffix;
    }
}

$bodyHtml = (string) ($row['body_html'] ?? '');
$scope = (string) ($row['target_scope'] ?? 'all');
if (!in_array($scope, ['all', 'homes', 'users'], true)) {
    $scope = 'all';
}
$sortOrder = (int) ($row['sort_order'] ?? 0);
$startsAt = $row['starts_at'] ?? null;
$endsAt = $row['ends_at'] ?? null;

$titleEsc = mysqli_real_escape_string($conn, $newTitle);
$bodyEsc = mysqli_real_escape_string($conn, $bodyHtml);
$scopeEsc = mysqli_real_escape_string($conn, $scope);
$startsSql = $startsAt !== null && $startsAt !== '' ? "'" . mysqli_real_escape_string($conn, (string) $startsAt) . "'" : 'NULL';
$endsSql = $endsAt !== null && $endsAt !== '' ? "'" . mysqli_real_escape_string($conn, (string) $endsAt) . "'" : 'NULL';

mysqli_begin_transaction($conn);

try {
    $sql = "INSERT INTO `popup_campaigns` (`title`, `body_html`, `target_scope`, `status`, `is_active`, `sort_order`, `starts_at`, `ends_at`)
            VALUES ('{$titleEsc}', '{$bodyEsc}', '{$scopeEsc}', 'draft', 1, {$sortOrder}, {$startsSql}, {$endsSql})";
    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException(mysqli_error($conn));
    }
    $newId = (int) mysqli_insert_id($conn);

    $resH = mysqli_query($conn, 'SELECT `home_id` FROM `popup_campaign_homes` WHERE `campaign_id` = ' . (int) $id);
    if ($resH) {
        while ($h = mysqli_fetch_assoc($resH)) {
            $hid = (int) $h['home_id'];
            if ($hid <= 0) {
                continue;
            }
            if (!mysqli_query($conn, "INSERT INTO `popup_campaign_homes` (`campaign_id`, `home_id`) VALUES ({$newId}, {$hid})")) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }
    }

    $resU = mysqli_query($conn, 'SELECT `user_id` FROM `popup_campaign_users` WHERE `campaign_id` = ' . (int) $id);
    if ($resU) {
        while ($u = mysqli_fetch_assoc($resU)) {
            $uid = (int) $u['user_id'];
            if ($uid <= 0) {
                continue;
            }
            if (!mysqli_query($conn, "INSERT INTO `popup_campaign_users` (`campaign_id`, `user_id`) VALUES ({$newId}, {$uid})")) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שכפול נכשל: ' . $e->getMessage()], 500);
}

tazrim_admin_json_response([
    'status' => 'ok',
    'message' => 'נוצר עותק כטיוטה.',
    'id' => $newId,
]);

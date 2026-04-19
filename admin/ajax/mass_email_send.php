<?php
declare(strict_types=1);

/**
 * שליחת מייל המוני מפאנל הניהול — יוצר broadcast + לוגים ושולח.
 */
require_once dirname(__DIR__) . '/includes/init_ajax.php';
require_once dirname(__DIR__) . '/includes/mass_email_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'גוף בקשה לא תקין.'], 400);
}

$csrf = (string) ($body['csrf_token'] ?? '');
if (!tazrim_admin_csrf_validate($csrf)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'פג תוקף אבטחה. רעננו את הדף.'], 419);
}

global $conn;

if (!tazrim_admin_mass_email_tables_ok($conn)) {
    tazrim_admin_json_response([
        'status' => 'error',
        'message' => 'טבלאות מייל לא קיימות. הריצו את המיגרציה docs/database/migrations/20260419_admin_email_broadcasts.sql',
    ], 503);
}

$targetType = trim((string) ($body['target_type'] ?? ''));
$allowed = ['all_users', 'all_homes', 'homes', 'users'];
if (!in_array($targetType, $allowed, true)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'יעד לא תקין.'], 400);
}

$ids = isset($body['ids']) && is_array($body['ids']) ? $body['ids'] : [];
$subject = trim((string) ($body['subject'] ?? ''));
$htmlBody = (string) ($body['html_body'] ?? '');
$textBody = trim((string) ($body['text_body'] ?? ''));

if ($subject === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא למלא נושא.'], 400);
}
if (trim(strip_tags($htmlBody)) === '' && $textBody === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא למלא גוף HTML או טקסט.'], 400);
}

if ($targetType === 'homes' || $targetType === 'users') {
    if ($ids === []) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'נא לבחור לפחות מזהה אחד.'], 400);
    }
}

$res = tazrim_admin_mass_email_resolve_recipients($conn, $targetType, $ids);
if (empty($res['ok'])) {
    $map = [
        'no_valid_recipients' => 'לא נמצאו כתובות מייל תקפות.',
        'too_many_recipients' => 'יותר מדי נמענים (מקסימום 200). צמצמו את הבחירה.',
        'no_home_ids' => 'נא לבחור בתים.',
        'no_user_ids' => 'נא לבחור משתמשים.',
    ];
    $msg = $map[(string) ($res['error'] ?? '')] ?? ('לא ניתן לבנות רשימת נמענים: ' . (string) ($res['error'] ?? ''));

    tazrim_admin_json_response(['status' => 'error', 'message' => $msg], 400);
}

$rows = $res['rows'] ?? [];
$targetJson = null;
if ($targetType === 'homes' || $targetType === 'users') {
    $key = $targetType === 'homes' ? 'home_ids' : 'user_ids';
    $targetJson = json_encode([$key => array_values(array_map('intval', $ids))], JSON_UNESCAPED_UNICODE);
}

$adminId = (int) ($_SESSION['id'] ?? 0);
if ($adminId <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא מחובר.'], 401);
}

@set_time_limit(600);

$out = tazrim_admin_mass_email_run_broadcast(
    $conn,
    $adminId,
    $targetType,
    $targetJson,
    $subject,
    $htmlBody,
    $textBody,
    $rows
);

if (empty($out['ok'])) {
    tazrim_admin_json_response(['status' => 'error', 'message' => (string) ($out['error'] ?? 'שגיאה')], 500);
}

tazrim_admin_json_response([
    'status' => 'ok',
    'message' => (string) ($out['message'] ?? 'נשלח.'),
    'broadcast_id' => (int) ($out['broadcast_id'] ?? 0),
]);

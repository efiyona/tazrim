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

$title = trim((string) ($body['title'] ?? ''));
$bodyText = trim((string) ($body['body'] ?? ''));
$link = trim((string) ($body['link'] ?? '/'));
if ($link === '') {
    $link = '/';
}

if ($title === '' || $bodyText === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שדות כותרת ותוכן הם חובה.'], 400);
}

$target = isset($body['target']) ? (string) $body['target'] : 'all';
if ($target !== 'all' && $target !== 'homes') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'יעד שידור לא תקין.'], 400);
}

require_once ROOT_PATH . '/app/functions/push_functions.php';

global $conn;

$homeIds = [];

if ($target === 'all') {
    $homes_result = mysqli_query($conn, 'SELECT id FROM homes');
    if (!$homes_result) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
    while ($home = mysqli_fetch_assoc($homes_result)) {
        $homeIds[] = (int) $home['id'];
    }
} else {
    $rawIds = $body['home_ids'] ?? [];
    if (!is_array($rawIds)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'רשימת בתים לא תקינה.'], 400);
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
        tazrim_admin_json_response(['status' => 'error', 'message' => 'נא לבחור לפחות בית אחד.'], 400);
    }
    if (count($ids) > 500) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'יותר מדי בתים בבת אחת.'], 400);
    }
    $inList = implode(',', array_map('intval', $ids));
    $chk = mysqli_query($conn, "SELECT id FROM homes WHERE id IN ($inList)");
    if (!$chk) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
    }
    $found = [];
    while ($row = mysqli_fetch_assoc($chk)) {
        $found[] = (int) $row['id'];
    }
    sort($found, SORT_NUMERIC);
    if ($found !== $ids) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'אחד או יותר מזהי הבתים אינם קיימים במערכת.'], 400);
    }
    $homeIds = $ids;
}

$sent_count = 0;
foreach ($homeIds as $hid) {
    sendPushToEntireHome($hid, $title, $bodyText, $link, 'system');
    $sent_count++;
}

if ($target === 'all') {
    $msg = 'ההודעה נשלחה בהצלחה לכל הבתים במערכת (' . $sent_count . ' בתים).';
} else {
    $msg = 'ההודעה נשלחה בהצלחה ל-' . $sent_count . ' בתים נבחרים.';
}

tazrim_admin_json_response([
    'status' => 'ok',
    'message' => $msg,
    'homes_count' => $sent_count,
]);

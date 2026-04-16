<?php
/**
 * מחיקת קמפיין פופאפ — program_admin בלבד.
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

if (!mysqli_query($conn, 'DELETE FROM `popup_campaigns` WHERE `id` = ' . $id . ' LIMIT 1')) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
}

if (mysqli_affected_rows($conn) === 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'קמפיין לא נמצא.'], 404);
}

tazrim_admin_json_response(['status' => 'ok', 'message' => 'נמחק.']);

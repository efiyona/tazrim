<?php
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'גוף בקשה לא תקין.'], 400);
}

$csrf = $body['csrf_token'] ?? '';
if (!tazrim_admin_csrf_validate($csrf)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'פג תוקף אבטחה. רעננו את הדף.'], 419);
}

global $conn;

$result = tazrim_admin_push_broadcast_execute($conn, $body);
if (!$result['ok']) {
    tazrim_admin_json_response(
        ['status' => 'error', 'message' => $result['message']],
        (int) ($result['http_code'] ?? 400)
    );
}

tazrim_admin_json_response([
    'status' => 'ok',
    'message' => $result['message'],
    'homes_count' => $result['homes_count'],
]);

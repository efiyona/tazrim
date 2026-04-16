<?php
/**
 * דוח קריאות לפי קמפיין — program_admin בלבד.
 */
require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$campaignId = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
if ($campaignId <= 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מזהה קמפיין לא תקין.'], 400);
}

global $conn;

$chk = mysqli_query($conn, 'SELECT `id` FROM `popup_campaigns` WHERE `id` = ' . $campaignId . ' LIMIT 1');
if (!$chk || mysqli_num_rows($chk) === 0) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'קמפיין לא נמצא.'], 404);
}

$sql = "SELECT r.`read_at`, u.`id` AS user_id, u.`first_name`, u.`last_name`, u.`email`
        FROM `popup_reads` r
        INNER JOIN `users` u ON u.`id` = r.`user_id`
        WHERE r.`campaign_id` = {$campaignId}
        ORDER BY r.`read_at` DESC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ts = strtotime((string) $row['read_at']);
    $rows[] = [
        'user_id' => (int) $row['user_id'],
        'first_name' => (string) $row['first_name'],
        'last_name' => (string) $row['last_name'],
        'email' => (string) $row['email'],
        'read_at' => (string) $row['read_at'],
        'read_at_label' => $ts ? date('d/m/Y H:i', $ts) : '',
    ];
}

tazrim_admin_json_response(['status' => 'ok', 'reads' => $rows, 'count' => count($rows)]);

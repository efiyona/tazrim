<?php
/**
 * דוח קריאות לפי קמפיין — program_admin בלבד.
 * משתמשים שאישרו (popup_reads) + סגירות ברמת בית (popup_home_reads) כשמדיניות אחת לכל הבית.
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

$rows = [];

$sqlUser = "SELECT r.`read_at`, r.`user_id`, u.`first_name`, u.`last_name`, u.`email`
        FROM `popup_reads` r
        INNER JOIN `users` u ON u.`id` = r.`user_id`
        WHERE r.`campaign_id` = {$campaignId}
        ORDER BY r.`read_at` DESC";

$result = mysqli_query($conn, $sqlUser);
if (!$result) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שגיאת מסד נתונים.'], 500);
}

while ($row = mysqli_fetch_assoc($result)) {
    $ts = strtotime((string) $row['read_at']);
    $rows[] = [
        'kind' => 'user',
        'user_id' => (int) $row['user_id'],
        'first_name' => (string) $row['first_name'],
        'last_name' => (string) $row['last_name'],
        'email' => (string) $row['email'],
        'read_at' => (string) $row['read_at'],
        'read_at_label' => $ts ? date('d/m/Y H:i', $ts) : '',
        'home_id' => null,
        'home_name' => null,
    ];
}

$hrTable = @mysqli_query($conn, "SHOW TABLES LIKE 'popup_home_reads'");
if ($hrTable && mysqli_num_rows($hrTable) > 0) {
    $sqlHome = "SELECT phr.`read_at`, phr.`home_id`, phr.`read_by_user_id`,
            h.`name` AS home_name,
            u.`first_name`, u.`last_name`, u.`email`
        FROM `popup_home_reads` phr
        INNER JOIN `homes` h ON h.`id` = phr.`home_id`
        LEFT JOIN `users` u ON u.`id` = phr.`read_by_user_id`
        WHERE phr.`campaign_id` = {$campaignId}
        ORDER BY phr.`read_at` DESC";

    $resH = mysqli_query($conn, $sqlHome);
    if ($resH) {
        while ($row = mysqli_fetch_assoc($resH)) {
            $ts = strtotime((string) $row['read_at']);
            $rows[] = [
                'kind' => 'home',
                'user_id' => null,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'read_at' => (string) $row['read_at'],
                'read_at_label' => $ts ? date('d/m/Y H:i', $ts) : '',
                'home_id' => (int) $row['home_id'],
                'home_name' => (string) ($row['home_name'] ?? ''),
                'read_by_user_id' => isset($row['read_by_user_id']) ? (int) $row['read_by_user_id'] : null,
            ];
        }
    }
}

tazrim_admin_json_response(['status' => 'ok', 'reads' => $rows, 'count' => count($rows)]);

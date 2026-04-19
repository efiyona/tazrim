<?php
/**
 * אישור קריאה לפופאפ בודד.
 */
require '../../path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/popup_campaigns.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'שיטה לא מורשית']);
    exit;
}

$user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'לא מחובר']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$campaign_id = isset($body['campaign_id']) ? (int) $body['campaign_id'] : 0;
if ($campaign_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'מזהה לא תקין']);
    exit;
}

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : null;
if ($home_id !== null && $home_id <= 0) {
    $home_id = null;
}

if (!tazrim_popup_campaigns_table_ready()) {
    echo json_encode(['status' => 'error', 'message' => 'לא זמין']);
    exit;
}

if (!tazrim_popup_campaign_ack_allowed($conn, $user_id, $home_id, $campaign_id)) {
    echo json_encode(['status' => 'error', 'message' => 'לא ניתן לאשר קריאה להודעה זו.']);
    exit;
}

$uid = (int) $user_id;
$cid = (int) $campaign_id;

$sql = "INSERT IGNORE INTO `popup_reads` (`user_id`, `campaign_id`) VALUES ({$uid}, {$cid})";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת שמירה']);
    exit;
}

echo json_encode(['status' => 'ok']);

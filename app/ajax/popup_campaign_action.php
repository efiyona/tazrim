<?php
/**
 * פעולות מובנות מתוך גוף פופאפ קמפיין (ללא סקריפט ב-body_html) — מאומתות מול יעד הקמפיין והרשאות.
 * רשימת הפעולות: app/functions/popup_campaign_actions.php
 */
require '../../path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/popup_campaigns.php';
require_once ROOT_PATH . '/app/functions/popup_campaign_actions.php';

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

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : null;
if ($home_id !== null && $home_id <= 0) {
    $home_id = null;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין']);
    exit;
}

$campaign_id = isset($body['campaign_id']) ? (int) $body['campaign_id'] : 0;
$action = isset($body['action']) ? trim((string) $body['action']) : '';

if ($campaign_id <= 0 || $action === '') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא תקינה']);
    exit;
}

if (!tazrim_popup_campaigns_table_ready()) {
    echo json_encode(['status' => 'error', 'message' => 'לא זמין']);
    exit;
}

if (!tazrim_popup_campaign_ack_allowed($conn, $user_id, $home_id, $campaign_id)) {
    echo json_encode(['status' => 'error', 'message' => 'פעולה לא מותרת עבור הודעה זו.']);
    exit;
}

$uid = (int) $user_id;
$cid = (int) $campaign_id;

$result = tazrim_popup_campaign_run_action($conn, $uid, $home_id, $cid, $action, $body);
echo json_encode($result);

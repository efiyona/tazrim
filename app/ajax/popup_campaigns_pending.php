<?php
/**
 * רשימת פופאפים ממתינים למשתמש המחובר.
 */
require '../../path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/popup_campaigns.php';

header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'לא מחובר']);
    exit;
}

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : null;
if ($home_id !== null && $home_id <= 0) {
    $home_id = null;
}

if (!tazrim_popup_campaigns_table_ready()) {
    echo json_encode(['status' => 'ok', 'campaigns' => []]);
    exit;
}

$campaigns = tazrim_popup_campaigns_pending_for_user($conn, $user_id, $home_id);

echo json_encode([
    'status' => 'ok',
    'campaigns' => $campaigns,
]);

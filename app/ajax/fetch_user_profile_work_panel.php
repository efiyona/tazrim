<?php
/**
 * JSON: { ok, html } — פאנל ניהול עבודות/סוגי משמרת בפרופיל.
 */
require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';
include ROOT_PATH . '/assets/includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$uid = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($uid < 1) {
    echo json_encode(['ok' => false, 'message' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

$u = selectOne('users', ['id' => $uid]);
if (!$u || empty($u['work_schedule_enabled'])) {
    echo json_encode(['ok' => false, 'message' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$work_jobs = [];
$qj = @mysqli_query(
    $conn,
    'SELECT * FROM `user_work_jobs` WHERE `user_id` = ' . (int) $uid . ' ORDER BY `sort_order` ASC, `id` ASC'
);
if ($qj) {
    while ($j = mysqli_fetch_assoc($qj)) {
        $jid = (int) $j['id'];
        $j['types'] = [];
        $qt = @mysqli_query(
            $conn,
            'SELECT * FROM `user_work_shift_types` WHERE `job_id` = ' . $jid . ' ORDER BY `sort_order` ASC, `id` ASC'
        );
        if ($qt) {
            while ($t = mysqli_fetch_assoc($qt)) {
                $j['types'][] = $t;
            }
        }
        $work_jobs[] = $j;
    }
}

ob_start();
$work_panel_uid = (int) $uid;
include ROOT_PATH . '/app/includes/partials/user_profile_work_panel.php';
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);

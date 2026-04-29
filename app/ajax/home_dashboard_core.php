<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['home_id'])) {
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

$home_id = (int) $_SESSION['home_id'];
$m = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
$y = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
if ($m < 1 || $m > 12) {
    $m = (int) date('m');
}
if ($y < 2000 || $y > 2100) {
    $y = (int) date('Y');
}

$_SESSION['view_month'] = $m;
$_SESSION['view_year']  = $y;

require_once ROOT_PATH . '/app/includes/render_home_dashboard_core.php';

try {
    $html = tazrim_render_home_dashboard_core($conn, $home_id, $m, $y);
    echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'render']);
}

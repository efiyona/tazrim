<?php
/**
 * מחזיר JSON עם HTML מוכן לאזור «התזרים באייפון» (לטעינה ב-AJAX).
 */
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit;
}

$user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נדרשת התחברות.']);
    exit;
}

require_once ROOT_PATH . '/app/includes/ios_tazrim_panel_visibility.php';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!tazrim_show_ios_tazrim_panel($user_agent)) {
    echo json_encode([
        'status' => 'forbidden',
        'message' => 'האזור מיועד לאייפון, אייפד או מק. באנדרואיד אין תמיכה בקיצורי דרך אלה.',
    ]);
    exit;
}

require_once ROOT_PATH . '/app/includes/ensure_ios_shortcut_links_table.php';
ensure_ios_shortcut_links_table($conn);

$token_check_query = "SELECT token FROM api_tokens WHERE user_id = $user_id LIMIT 1";
$token_check_result = mysqli_query($conn, $token_check_query);
$existing_token = $token_check_result ? mysqli_fetch_assoc($token_check_result) : null;

$shortcuts = [];
$sq = "SELECT title, url FROM ios_shortcut_links WHERE is_active = 1 ORDER BY sort_order ASC, id ASC";
$sr = mysqli_query($conn, $sq);
if ($sr === false) {
    $shortcuts = [];
} else {
    while ($r = mysqli_fetch_assoc($sr)) {
        $url = trim($r['url'] ?? '');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }
        $shortcuts[] = [
            'title' => $r['title'],
            'url' => $url,
        ];
    }
}

ob_start();
include ROOT_PATH . '/app/includes/partials/ios_tazrim_panel.php';
$html = ob_get_clean();

echo json_encode([
    'status' => 'success',
    'html' => $html,
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * נתונים לאזור קיצורי iOS (ללא HTML) — אפליקציה נייטיבית
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }
    require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
    tazrim_api_v1_json_exit_if_email_unverified($user);

    $user_id = (int) ($user['id'] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא תקין.']);
        exit();
    }

    require_once ROOT_PATH . '/app/includes/ensure_ios_shortcut_links_table.php';
    ensure_ios_shortcut_links_table($conn);

    $token_check_query = "SELECT token FROM api_tokens WHERE user_id = $user_id LIMIT 1";
    $token_check_result = mysqli_query($conn, $token_check_query);
    $existing_token = $token_check_result ? mysqli_fetch_assoc($token_check_result) : null;
    $shortcut_token = $existing_token['token'] ?? '';

    $shortcuts = [];
    $sq = 'SELECT title, url FROM ios_shortcut_links WHERE is_active = 1 ORDER BY sort_order ASC, id ASC';
    $sr = mysqli_query($conn, $sq);
    if ($sr) {
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

    echo json_encode([
        'status' => 'success',
        'data' => [
            'has_token' => $shortcut_token !== '',
            'token' => $shortcut_token,
            'shortcuts' => $shortcuts,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

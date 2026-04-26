<?php
/**
 * דיווח מהיר מהאפליקציה: באג / רעיון.
 * שומר במסד ושולח התראה למשתמשים עם role=program_admin
 * (Push לפי notify_system + התראה למסך הפעמון).
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    require_once ROOT_PATH . '/app/functions/push_functions.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'שיטת בקשה לא נתמכת.']);
        exit();
    }

    $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
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

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }

    $kind = trim((string) ($body['kind'] ?? 'bug'));
    if ($kind !== 'bug' && $kind !== 'idea') {
        echo json_encode(['status' => 'error', 'message' => 'סוג דיווח לא תקין.']);
        exit();
    }

    $title = trim((string) ($body['title'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));
    $screen = trim((string) ($body['screen'] ?? ''));

    if (mb_strlen($message) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'נא לצרף פירוט קצר של לפחות 8 תווים.']);
        exit();
    }

    if (mb_strlen($title) > 190) {
        $title = mb_substr($title, 0, 190);
    }
    if (mb_strlen($screen) > 120) {
        $screen = mb_substr($screen, 0, 120);
    }
    if (mb_strlen($message) > 4000) {
        $message = mb_substr($message, 0, 4000);
    }

    $user_id = (int) ($user['id'] ?? 0);
    $home_id = (int) ($user['home_id'] ?? 0);
    $reporter = trim((string) ($user['nickname'] ?? ''));
    if ($reporter === '') {
        $reporter = trim((string) ($user['first_name'] ?? '')) ?: 'משתמש';
    }

    $report_id = create('feedback_reports', [
        'user_id' => $user_id,
        'home_id' => $home_id,
        'kind' => $kind,
        'title' => $title !== '' ? $title : null,
        'message' => $message,
        'context_screen' => $screen !== '' ? $screen : null,
        'status' => 'new',
    ]);

    $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'program_admin'");
    $kind_text = $kind === 'idea' ? 'רעיון לפיצ׳ר' : 'דיווח באג';
    $notif_title = $kind === 'idea' ? 'רעיון חדש מהאפליקציה' : 'דיווח באג חדש מהאפליקציה';
    $screen_line = $screen !== '' ? " | מסך: " . $screen : '';

    $message_html = "<span class='notif-bold'>" . htmlspecialchars($reporter, ENT_QUOTES, 'UTF-8') . "</span> שלח/ה " .
        htmlspecialchars($kind_text, ENT_QUOTES, 'UTF-8');
    if ($title !== '') {
        $message_html .= " — <span class='notif-bold'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</span>";
    }
    $message_html .= $screen_line !== '' ? " (" . htmlspecialchars($screen_line, ENT_QUOTES, 'UTF-8') . ")" : '';

    $push_body = $reporter . " שלח/ה " . $kind_text;
    if ($title !== '') {
        $push_body .= ": " . $title;
    }
    if ($screen !== '') {
        $push_body .= " | מסך: " . $screen;
    }

    if ($admin_res) {
        while ($admin = mysqli_fetch_assoc($admin_res)) {
            $admin_id = (int) ($admin['id'] ?? 0);
            if ($admin_id <= 0) {
                continue;
            }
            addNotification(0, $notif_title, $message_html, 'info', $admin_id);
            sendPushNotification($admin_id, $notif_title, $push_body, '/pages/settings/notifications.php', 'system');
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'report_id' => (int) $report_id,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת.',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

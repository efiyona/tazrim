<?php
/**
 * הסרת אסימון Expo Push מהמכשיר — POST JSON: expo_push_token (חובה)
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

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $expo_push_token = trim((string) ($body['expo_push_token'] ?? ''));
    if (strlen($expo_push_token) < 32 || strlen($expo_push_token) > 400) {
        echo json_encode(['status' => 'error', 'message' => 'אסימון לא תקין.']);
        exit();
    }

    $chk = $conn->query("SHOW TABLES LIKE 'user_expo_push_tokens'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['status' => 'success']);
        exit();
    }

    $stmt = $conn->prepare('DELETE FROM user_expo_push_tokens WHERE user_id = ? AND expo_push_token = ?');
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
        exit();
    }
    $stmt->bind_param('is', $user_id, $expo_push_token);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

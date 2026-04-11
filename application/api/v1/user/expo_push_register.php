<?php
/**
 * רישום אסימון Expo Push למכשיר (אפליקציה) — POST JSON: expo_push_token, platform (ios|android)
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
    $platform = strtolower(trim((string) ($body['platform'] ?? '')));
    if ($platform !== 'ios' && $platform !== 'android') {
        $platform = 'unknown';
    }

    if (strlen($expo_push_token) < 32 || strlen($expo_push_token) > 400) {
        echo json_encode(['status' => 'error', 'message' => 'אסימון התראות לא תקין.']);
        exit();
    }
    if (strpos($expo_push_token, 'ExponentPushToken[') !== 0 && strpos($expo_push_token, 'ExpoPushToken[') !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'פורמט אסימון לא נתמך.']);
        exit();
    }

    $chk = $conn->query("SHOW TABLES LIKE 'user_expo_push_tokens'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'שרת: טבלת התראות מובייל טרם הוגדרה.']);
        exit();
    }

    $stmt = $conn->prepare(
        'INSERT INTO user_expo_push_tokens (user_id, expo_push_token, platform) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE platform = VALUES(platform), updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
        exit();
    }
    $stmt->bind_param('iss', $user_id, $expo_push_token, $platform);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

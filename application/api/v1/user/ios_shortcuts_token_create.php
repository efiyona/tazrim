<?php
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
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($user_id <= 0 || $home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתוני משתמש לא תקינים.']);
        exit();
    }

    $dup = mysqli_query($conn, "SELECT id FROM api_tokens WHERE user_id = $user_id LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'כבר קיים מפתח חיבור. ניתן למחוק אותו ואז ליצור חדש.']);
        exit();
    }

    $random_string = bin2hex(random_bytes(8));
    $new_token = 'TAZRIM_APP_' . strtoupper($random_string);
    $token_esc = mysqli_real_escape_string($conn, $new_token);
    $query = "INSERT INTO api_tokens (user_id, home_id, token) VALUES ($user_id, $home_id, '$token_esc')";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'data' => ['token' => $new_token]], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת המפתח.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

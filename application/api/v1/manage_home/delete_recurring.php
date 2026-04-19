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
        echo json_encode(['status' => 'error', 'message' => 'טוקן לא חוקי.']);
        exit();
    }

    $home_id = (int) ($user['home_id'] ?? 0);
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $rec_id = isset($body['id']) ? (int) $body['id'] : 0;

    if ($home_id <= 0 || $rec_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים.']);
        exit();
    }

    $delete_query = "DELETE FROM recurring_transactions WHERE id = $rec_id AND home_id = $home_id";
    if (mysqli_query($conn, $delete_query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

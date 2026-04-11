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
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $cat_id = !empty($body['category_id']) ? (int) $body['category_id'] : null;
    $cat_name = mysqli_real_escape_string($conn, trim((string) ($body['cat_name'] ?? '')));
    $cat_type = (($body['cat_type'] ?? '') === 'income') ? 'income' : 'expense';
    $cat_budget = isset($body['cat_budget']) ? (float) $body['cat_budget'] : 0;
    $cat_icon = mysqli_real_escape_string($conn, trim((string) ($body['cat_icon'] ?? 'fa-tag')));

    if ($cat_name === '') {
        echo json_encode(['status' => 'error', 'message' => 'שם הקטגוריה לא יכול להיות ריק.']);
        exit();
    }

    if ($cat_id) {
        $query = "UPDATE categories SET name='$cat_name', budget_limit=$cat_budget, icon='$cat_icon' WHERE id=$cat_id AND home_id=$home_id";
    } else {
        $query = "INSERT INTO categories (home_id, name, type, budget_limit, icon) VALUES ($home_id, '$cat_name', '$cat_type', $cat_budget, '$cat_icon')";
    }

    if (mysqli_query($conn, $query)) {
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

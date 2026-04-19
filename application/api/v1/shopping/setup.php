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

    require_once __DIR__ . '/_auth.php';
    $auth = shopping_api_require_user($conn);
    $home_id = $auth['home_id'];

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $categories = $body['categories'] ?? [];
    if (!is_array($categories) || count($categories) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'אנא בחרו לפחות חנות אחת.']);
        exit();
    }

    $added = 0;
    foreach ($categories as $cat) {
        if (!is_array($cat)) {
            continue;
        }
        $name = mysqli_real_escape_string($conn, trim((string) ($cat['name'] ?? '')));
        $icon = mysqli_real_escape_string($conn, trim((string) ($cat['icon'] ?? 'fa-cart-shopping')));
        if ($name === '') {
            continue;
        }
        $sort_order = $added + 1;
        $q = "INSERT INTO shopping_categories (home_id, name, icon, sort_order) VALUES ($home_id, '$name', '$icon', $sort_order)";
        if (mysqli_query($conn, $q)) {
            $added++;
        }
    }

    if ($added > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה בשמירת החנויות.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

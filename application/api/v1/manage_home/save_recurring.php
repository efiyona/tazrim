<?php
/** מקביל ל־app/ajax/save_recurring.php — טוקן API */
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

    $home_id = (int) ($user['home_id'] ?? 0);
    $user_id = (int) ($user['id'] ?? 0);
    if ($home_id <= 0 || $user_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים לא תקינים.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $recurring_id = !empty($body['recurring_id']) ? (int) $body['recurring_id'] : 0;
    $type = (($body['rec_type'] ?? 'expense') === 'income') ? 'income' : 'expense';
    $category_id = isset($body['rec_category']) ? (int) $body['rec_category'] : 0;
    $amount = isset($body['rec_amount']) ? (float) $body['rec_amount'] : 0;
    $description = mysqli_real_escape_string($conn, trim((string) ($body['rec_description'] ?? '')));

    $day_of_month = 0;
    if (!empty($body['transaction_date'])) {
        $ts = strtotime($body['transaction_date']);
        if ($ts) {
            $day_of_month = (int) date('d', $ts);
        }
    }

    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נא להזין סכום חיובי.']);
        exit();
    }
    if ($description === '') {
        echo json_encode(['status' => 'error', 'message' => 'נא להזין תיאור.']);
        exit();
    }
    if ($category_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נא לבחור קטגוריה.']);
        exit();
    }
    if ($day_of_month < 1 || $day_of_month > 31) {
        echo json_encode(['status' => 'error', 'message' => 'יום בחודש חייב להיות בין 1 ל־31.']);
        exit();
    }

    $cat_check = mysqli_query(
        $conn,
        "SELECT id, type FROM categories WHERE id = $category_id AND home_id = $home_id AND is_active = 1 LIMIT 1"
    );
    $cat_row = $cat_check ? mysqli_fetch_assoc($cat_check) : null;
    if (!$cat_row) {
        echo json_encode(['status' => 'error', 'message' => 'קטגוריה לא תקינה.']);
        exit();
    }
    if ($cat_row['type'] !== $type) {
        echo json_encode(['status' => 'error', 'message' => 'הקטגוריה אינה תואמת לסוג הפעולה.']);
        exit();
    }

    if ($recurring_id > 0) {
        $verify = mysqli_query(
            $conn,
            "SELECT id FROM recurring_transactions WHERE id = $recurring_id AND home_id = $home_id LIMIT 1"
        );
        if (!$verify || mysqli_num_rows($verify) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'פעולה קבועה לא נמצאה.']);
            exit();
        }

        $q = "UPDATE recurring_transactions SET type='$type', amount=$amount, category=$category_id, description='$description', day_of_month=$day_of_month WHERE id=$recurring_id AND home_id=$home_id";
        if (mysqli_query($conn, $q)) {
            mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'שגיאה בעדכון הנתונים.']);
        }
    } else {
        $q = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, category, description, day_of_month, last_injected_month, is_active) 
              VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', $day_of_month, NULL, 1)";
        if (mysqli_query($conn, $q)) {
            mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת הנתונים.']);
        }
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

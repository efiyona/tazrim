<?php
/**
 * נתוני עמוד ניהול הבית — מקביל ל־manage_home.php (JSON לאפליקציה)
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
    require_once ROOT_PATH . '/app/functions/currency.php';
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
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($user_id <= 0 || $home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא משתמש או בית.']);
        exit();
    }

    $home_data = selectOne('homes', ['id' => $home_id]);
    if (!$home_data) {
        echo json_encode(['status' => 'error', 'message' => 'בית לא נמצא.']);
        exit();
    }

    $today_il = date('Y-m-d');
    $bank_disp = tazrim_home_display_bank_balance($conn, $home_id, $today_il);
    /** @deprecated שם השדה נשמר לתאימות אפליקציה — הערך הוא יתרה מוצגת (מוערכת), לא עמודת DB ישנה */
    $initial_balance = (float) $bank_disp['display'];

    $categories_query = "SELECT * FROM categories WHERE home_id = $home_id AND is_active = 1 ORDER BY type ASC, name ASC";
    $categories_result = mysqli_query($conn, $categories_query);
    $expenses_cats = [];
    $income_cats = [];
    while ($cat = mysqli_fetch_assoc($categories_result)) {
        $cat['id'] = (int) $cat['id'];
        $cat['budget_limit'] = (float) ($cat['budget_limit'] ?? 0);
        if ($cat['type'] === 'expense') {
            $expenses_cats[] = $cat;
        } else {
            $income_cats[] = $cat;
        }
    }
    $categories_all = array_merge($expenses_cats, $income_cats);

    $recurring_query = "SELECT r.*, c.name as cat_name, c.icon as cat_icon 
                        FROM recurring_transactions r 
                        LEFT JOIN categories c ON r.category = c.id 
                        WHERE r.home_id = $home_id AND r.is_active = 1 
                        ORDER BY r.day_of_month ASC";
    $recurring_result = mysqli_query($conn, $recurring_query);
    $recurring_expenses = [];
    $recurring_income = [];
    if ($recurring_result) {
        while ($rec = mysqli_fetch_assoc($recurring_result)) {
            $rec['id'] = (int) $rec['id'];
            $rec['amount'] = (float) $rec['amount'];
            $rec['currency_code'] = tazrim_normalize_currency_code($rec['currency_code'] ?? 'ILS');
            $rec['category'] = (int) $rec['category'];
            $rec['day_of_month'] = (int) $rec['day_of_month'];
            if ($rec['type'] === 'expense') {
                $recurring_expenses[] = $rec;
            } else {
                $recurring_income[] = $rec;
            }
        }
    }

    $shopping_stores = [];
    $sq = "SELECT id, name, icon, sort_order FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
    $sr = mysqli_query($conn, $sq);
    if ($sr) {
        while ($row = mysqli_fetch_assoc($sr)) {
            $row['id'] = (int) $row['id'];
            $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
            $shopping_stores[] = $row;
        }
    }

    $members = [];
    $mq = "SELECT first_name, nickname, role, email FROM users WHERE home_id = $home_id ORDER BY (role IN ('admin','home_admin','program_admin')) DESC, first_name ASC";
    $mr = mysqli_query($conn, $mq);
    if ($mr) {
        while ($row = mysqli_fetch_assoc($mr)) {
            $members[] = $row;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'home' => [
                'name' => $home_data['name'] ?? '',
                'join_code' => $home_data['join_code'] ?? '',
                'initial_balance' => $initial_balance,
                'show_bank_balance' => (int) ($home_data['show_bank_balance'] ?? 0),
                'bank_balance_display' => (float) $bank_disp['display'],
            ],
            'current_user_email' => $user['email'] ?? '',
            'members' => $members,
            'categories' => $categories_all,
            'recurring_expenses' => $recurring_expenses,
            'recurring_income' => $recurring_income,
            'shopping_stores' => $shopping_stores,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

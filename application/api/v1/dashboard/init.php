<?php
// application/api/v1/dashboard/init.php

// 1. הסתרת שגיאות גולמיות כדי לא לשבור את ה-JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. כותרות גישה לאפליקציה
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// 3. תפיסת שגיאות גלובלית - כל קריסה תהפוך להודעת JSON מסודרת!
try {
    require('../../../../path.php'); // נתיב לשורש המערכת
    include(ROOT_PATH . "/app/database/db.php");

    // הגדרת דיווח שגיאות SQL חמורות (כדי שייתפסו ב-catch ולא יפילו את השרת)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if (empty($token)) {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }

    $home_id = $user['home_id'];
    if (empty($home_id)) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית למשתמש.']);
        exit();
    }

    $selected_month = (int)date('m');
    $selected_year = (int)date('Y');
    $today_il = date('Y-m-d');

    $hebrew_months = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל', 
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט', 
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
    ];
    $month_name = $hebrew_months[$selected_month];

    // שליפת נתוני בית ליתרה התחלתית
    $home_data = selectOne('homes', ['id' => $home_id]);
    $initial_balance = $home_data ? ($home_data['initial_balance'] ?? 0) : 0;

    // חישוב יתרה ריאלית
    $real_balance_query = "SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date <= '$today_il' THEN amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as net_balance
        FROM transactions 
        WHERE home_id = $home_id";
    $balance_result = mysqli_query($conn, $real_balance_query);
    $balance_data = mysqli_fetch_assoc($balance_result);
    $current_bank_balance = $initial_balance + ($balance_data['net_balance'] ?? 0);

    // חישוב הכנסות
    $month_income_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                           WHERE home_id = $home_id AND type = 'income' 
                           AND MONTH(transaction_date) = $selected_month AND YEAR(transaction_date) = $selected_year";
    $month_income_result = mysqli_query($conn, $month_income_query);
    $month_income = mysqli_fetch_assoc($month_income_result)['total'] ?? 0;

    // חישוב הוצאות
    $month_expense_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                            WHERE home_id = $home_id AND type = 'expense' 
                            AND MONTH(transaction_date) = $selected_month AND YEAR(transaction_date) = $selected_year";
    $month_expense_result = mysqli_query($conn, $month_expense_query);
    $month_expense = mysqli_fetch_assoc($month_expense_result)['total'] ?? 0;

    // שליפת 5 פעולות אחרונות (הוסר c.color מהשאילתה המקורית כדי למנוע קריסות)
    $recent_transactions_query = "SELECT t.*, c.name as category_name, c.icon as category_icon 
                                  FROM transactions t
                                  LEFT JOIN categories c ON t.category = c.id
                                  WHERE t.home_id = $home_id 
                                  ORDER BY t.transaction_date DESC, t.id DESC 
                                  LIMIT 5";
    $recent_transactions_result = mysqli_query($conn, $recent_transactions_query);
    $recent_transactions = [];
    while ($row = mysqli_fetch_assoc($recent_transactions_result)) {
        $recent_transactions[] = $row;
    }

    // הכל עבר בהצלחה! מחזירים JSON לאפליקציה
    echo json_encode([
        'status' => 'success',
        'data' => [
            'month_info' => ['name' => $month_name, 'number' => $selected_month, 'year' => $selected_year],
            'balances' => ['current' => $current_bank_balance, 'monthly_income' => $month_income, 'monthly_expense' => $month_expense],
            'recent_transactions' => $recent_transactions
        ]
    ]);
    exit();

} catch (Throwable $e) {
    // במקרה של שגיאת SQL, נתיב חסר, או כל קריסה אחרת - היא תוחזר לאפליקציה במקום להפיל את השרת!
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage() . ' בשורה ' . $e->getLine()
    ]);
    exit();
}
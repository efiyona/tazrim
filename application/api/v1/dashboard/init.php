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

    $selected_month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
    $selected_year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
    if ($selected_month < 1 || $selected_month > 12) {
        $selected_month = (int) date('m');
    }
    if ($selected_year < 2000 || $selected_year > 2100) {
        $selected_year = (int) date('Y');
    }
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

    // שליפת פעולות ממתינות לחודש הנבחר (עתידיות בלבד) — תואם ל־dashboard/transactions.php
    $pending_transactions_query = "SELECT t.*, c.name as category_name, c.icon as category_icon, u.first_name as user_name
                                   FROM transactions t
                                   LEFT JOIN categories c ON t.category = c.id
                                   LEFT JOIN users u ON t.user_id = u.id
                                   WHERE t.home_id = $home_id
                                   AND t.transaction_date > '$today_il'
                                   AND MONTH(t.transaction_date) = $selected_month
                                   AND YEAR(t.transaction_date) = $selected_year
                                   ORDER BY t.transaction_date ASC, t.id ASC
                                   LIMIT 4";
    $pending_transactions_result = mysqli_query($conn, $pending_transactions_query);
    $pending_transactions = [];
    while ($row = mysqli_fetch_assoc($pending_transactions_result)) {
        $pending_transactions[] = $row;
    }

    $pending_count_query = "SELECT COUNT(id) as total FROM transactions
                            WHERE home_id = $home_id
                            AND transaction_date > '$today_il'
                            AND MONTH(transaction_date) = $selected_month
                            AND YEAR(transaction_date) = $selected_year";
    $pending_count_res = mysqli_query($conn, $pending_count_query);
    $pending_total = (int) (mysqli_fetch_assoc($pending_count_res)['total'] ?? 0);

    // שליפת 4 פעולות אחרונות בחודש הנבחר (לא עתידיות) — תואם ל־dashboard/transactions.php
    $recent_transactions_query = "SELECT t.*, c.name as category_name, c.icon as category_icon 
                                  FROM transactions t
                                  LEFT JOIN categories c ON t.category = c.id
                                  WHERE t.home_id = $home_id 
                                  AND t.transaction_date <= '$today_il'
                                  AND MONTH(t.transaction_date) = $selected_month
                                  AND YEAR(t.transaction_date) = $selected_year
                                  ORDER BY t.transaction_date DESC, t.id DESC 
                                  LIMIT 4";
    $recent_transactions_result = mysqli_query($conn, $recent_transactions_query);
    $recent_transactions = [];
    while ($row = mysqli_fetch_assoc($recent_transactions_result)) {
        $recent_transactions[] = $row;
    }

    $recent_count_query = "SELECT COUNT(id) as total FROM transactions
                           WHERE home_id = $home_id
                           AND transaction_date <= '$today_il'
                           AND MONTH(transaction_date) = $selected_month
                           AND YEAR(transaction_date) = $selected_year";
    $recent_count_res = mysqli_query($conn, $recent_count_query);
    $recent_total = (int) (mysqli_fetch_assoc($recent_count_res)['total'] ?? 0);

    // קטגוריות הוצאה לחודש הנוכחי (רק עם spending > 0 כמו בווב)
    $categories_query = "SELECT c.id, c.name, c.icon, c.budget_limit,
                                COALESCE(SUM(t.amount), 0) as current_spending
                         FROM categories c
                         LEFT JOIN transactions t
                           ON c.id = t.category
                           AND t.type = 'expense'
                           AND MONTH(t.transaction_date) = $selected_month
                           AND YEAR(t.transaction_date) = $selected_year
                         WHERE c.home_id = $home_id
                           AND c.type = 'expense'
                           AND c.is_active = 1
                         GROUP BY c.id
                         HAVING current_spending > 0
                         ORDER BY current_spending DESC";
    $categories_result = mysqli_query($conn, $categories_query);
    $categories = [];
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $budget = (float) ($row['budget_limit'] ?? 0);
        $spent = (float) ($row['current_spending'] ?? 0);
        $percent = $budget > 0 ? min(($spent / $budget) * 100, 100) : 0;
        $real_percent = $budget > 0 ? round(($spent / $budget) * 100) : 0;
        $categories[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'icon' => $row['icon'],
            'budget_limit' => $budget,
            'current_spending' => $spent,
            'progress_percent' => $percent,
            'real_percent' => $real_percent,
            'is_over_budget' => $budget > 0 && $spent > $budget,
        ];
    }

    // הכל עבר בהצלחה! מחזירים JSON לאפליקציה
    $prev_month = $selected_month - 1;
    $prev_year = $selected_year;
    if ($prev_month < 1) {
        $prev_month = 12;
        $prev_year--;
    }

    $next_month = $selected_month + 1;
    $next_year = $selected_year;
    if ($next_month > 12) {
        $next_month = 1;
        $next_year++;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'month_info' => [
                'name' => $month_name,
                'number' => $selected_month,
                'year' => $selected_year,
                'prev_month' => $prev_month,
                'prev_year' => $prev_year,
                'next_month' => $next_month,
                'next_year' => $next_year,
                'current_month' => (int) date('m'),
                'current_year' => (int) date('Y'),
            ],
            'home' => [
                'name' => $home_data['name'] ?? '',
            ],
            'balances' => ['current' => $current_bank_balance, 'monthly_income' => $month_income, 'monthly_expense' => $month_expense],
            'pending_transactions' => $pending_transactions,
            'recent_transactions' => $recent_transactions,
            'categories' => $categories,
            'has_more_pending' => $pending_total > count($pending_transactions),
            'has_more_recent' => $recent_total > count($recent_transactions),
            'pending_transaction_count' => $pending_total,
            'recent_transaction_count' => $recent_total,
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
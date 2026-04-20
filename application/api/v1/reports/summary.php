<?php
/**
 * סיכום דוחות — תואם ל־pages/reports.php (KPI, עוגה, מדי תקציב).
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    if ($home_id <= 0) {
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

    $start_date = sprintf('%04d-%02d-01', $selected_year, $selected_month);
    $end_date = date('Y-m-t', strtotime($start_date));

    $hebrew_months = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר',
    ];
    $month_name = $hebrew_months[$selected_month];

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

    $exp_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                  WHERE home_id = $home_id AND type = 'expense' 
                  AND transaction_date BETWEEN '$start_date' AND '$end_date'";
    $total_expenses = (float) (mysqli_fetch_assoc(mysqli_query($conn, $exp_query))['total'] ?? 0);

    $inc_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                  WHERE home_id = $home_id AND type = 'income' 
                  AND transaction_date BETWEEN '$start_date' AND '$end_date'";
    $total_income = (float) (mysqli_fetch_assoc(mysqli_query($conn, $inc_query))['total'] ?? 0);

    // מאזן החודש הנבחר בלבד (הכנסות − הוצאות בטווח התאריכים) — לא יתרת בנק גלובלית
    $month_net = $total_income - $total_expenses;

    $home_row = selectOne('homes', ['id' => $home_id]);
    $show_bank_balance = $home_row && !empty($home_row['show_bank_balance']);
    $today_il = date('Y-m-d');
    $bank_balance_block = null;
    if ($show_bank_balance) {
        $disp = tazrim_home_display_bank_balance($conn, $home_id, $today_il);
        $bank_balance_block = [
            'display' => (float) $disp['display'],
            'ledger' => (float) $disp['ledger_dec'],
            'adjustment' => (float) $disp['adjustment_dec'],
            'future_expenses' => (float) $disp['future_expenses_sum'],
        ];
    }

    $days_in_month = (int) date('t', strtotime($start_date));
    $current_day = ($selected_month === (int) date('m') && $selected_year === (int) date('Y'))
        ? (int) date('d')
        : $days_in_month;
    $daily_avg = $current_day > 0 ? $total_expenses / $current_day : 0.0;

    $pie_labels = [];
    $pie_values = [];
    $pie_query = "SELECT c.name, SUM(t.amount) as total 
                  FROM transactions t 
                  JOIN categories c ON t.category = c.id 
                  WHERE t.home_id = $home_id 
                  AND t.type = 'expense' 
                  AND t.transaction_date BETWEEN '$start_date' AND '$end_date' 
                  GROUP BY t.category 
                  ORDER BY total DESC";
    $pie_result = mysqli_query($conn, $pie_query);
    while ($row = mysqli_fetch_assoc($pie_result)) {
        $pie_labels[] = $row['name'];
        $pie_values[] = (float) $row['total'];
    }

    $budgets = [];
    $budget_query = "SELECT c.id, c.name, c.budget_limit, c.icon,
                       COALESCE(SUM(t.amount), 0) as spent
                       FROM categories c
                       LEFT JOIN transactions t ON c.id = t.category 
                          AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
                       WHERE c.home_id = $home_id 
                       AND c.type = 'expense'
                       AND c.is_active = 1
                       AND c.budget_limit > 0
                       GROUP BY c.id
                       ORDER BY (COALESCE(SUM(t.amount), 0) / c.budget_limit) DESC";
    $budget_result = mysqli_query($conn, $budget_query);
    while ($row = mysqli_fetch_assoc($budget_result)) {
        $limit = (float) ($row['budget_limit'] ?? 0);
        $spent = (float) ($row['spent'] ?? 0);
        $percent = $limit > 0 ? ($spent / $limit) * 100 : 0.0;
        $color = 'success';
        if ($percent >= 90) {
            $color = 'error';
        } elseif ($percent >= 75) {
            $color = 'warning';
        }
        $budgets[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'icon' => $row['icon'] ?: 'fa-tag',
            'budget_limit' => $limit,
            'spent' => $spent,
            'percent' => round($percent, 1),
            'color' => $color,
            'over' => $percent > 100,
        ];
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
            // kpis.balance / month_net = מאזן החודש הנבחר בלבד (לא יתרת בנק גלובלית)
            'kpis' => [
                'income' => $total_income,
                'expense' => $total_expenses,
                'balance' => $month_net,
                'month_net' => $month_net,
                'daily_avg_expense' => $daily_avg,
            ],
            'show_bank_balance' => $show_bank_balance ? 1 : 0,
            'bank_balance_estimated' => $bank_balance_block,
            'pie' => [
                'labels' => $pie_labels,
                'values' => $pie_values,
            ],
            'budgets' => $budgets,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

<?php
/**
 * JSON: { ok, data } — נתוני דוחות (KPIs, פאי, תקציבים, תאריכים) לחודש מבוקש.
 * משמש לניווט בין חודשים ב-pages/reports.php ללא רענון.
 */
require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';

header('Content-Type: application/json; charset=utf-8');

$uid = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : 0;
if ($uid < 1 || $home_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'נדרשת התחברות.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$current_month = (int) ($_POST['m'] ?? $_GET['m'] ?? 0);
$current_year  = (int) ($_POST['y'] ?? $_GET['y'] ?? 0);
if ($current_month < 1 || $current_month > 12) {
    $current_month = (int) date('m');
}
if ($current_year < 2000 || $current_year > 2100) {
    $current_year = (int) date('Y');
}

$_SESSION['view_month'] = $current_month;
$_SESSION['view_year']  = $current_year;

$start_date = sprintf('%04d-%02d-01', $current_year, $current_month);
$end_date   = date('Y-m-t', strtotime($start_date));

$today_m = (int) date('m');
$today_y = (int) date('Y');
$is_current_month = ($current_month === $today_m && $current_year === $today_y);

$prev_month = $current_month - 1;
$prev_year  = $current_year;
if ($prev_month === 0) {
    $prev_month = 12;
    $prev_year--;
}
$next_month = $current_month + 1;
$next_year  = $current_year;
if ($next_month === 13) {
    $next_month = 1;
    $next_year++;
}

$month_names = ['', 'ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];

$exp_q = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE home_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($exp_q, 'iss', $home_id, $start_date, $end_date);
mysqli_stmt_execute($exp_q);
$exp_res = mysqli_stmt_get_result($exp_q);
$total_expenses = (float) (mysqli_fetch_assoc($exp_res)['total'] ?? 0);
mysqli_stmt_close($exp_q);

$inc_q = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE home_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($inc_q, 'iss', $home_id, $start_date, $end_date);
mysqli_stmt_execute($inc_q);
$inc_res = mysqli_stmt_get_result($inc_q);
$total_income = (float) (mysqli_fetch_assoc($inc_res)['total'] ?? 0);
mysqli_stmt_close($inc_q);

$balance = $total_income - $total_expenses;

$days_in_month = (int) date('t', strtotime($start_date));
$current_day = $is_current_month ? (int) date('d') : $days_in_month;
$daily_avg = $current_day > 0 ? $total_expenses / $current_day : 0.0;

$pie_q = mysqli_prepare(
    $conn,
    "SELECT c.name, SUM(t.amount) AS total
     FROM transactions t
     JOIN categories c ON t.category = c.id
     WHERE t.home_id = ? AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
     GROUP BY t.category
     ORDER BY total DESC"
);
mysqli_stmt_bind_param($pie_q, 'iss', $home_id, $start_date, $end_date);
mysqli_stmt_execute($pie_q);
$pie_res = mysqli_stmt_get_result($pie_q);
$pie_labels = [];
$pie_data = [];
while ($row = mysqli_fetch_assoc($pie_res)) {
    $pie_labels[] = $row['name'];
    $pie_data[]   = (float) $row['total'];
}
mysqli_stmt_close($pie_q);

$bud_q = mysqli_prepare(
    $conn,
    "SELECT c.id, c.name, c.budget_limit, c.icon,
            COALESCE(SUM(t.amount), 0) AS spent
     FROM categories c
     LEFT JOIN transactions t ON c.id = t.category
        AND t.transaction_date BETWEEN ? AND ?
     WHERE c.home_id = ?
       AND c.type = 'expense'
       AND c.is_active = 1
       AND c.budget_limit > 0
     GROUP BY c.id
     ORDER BY (COALESCE(SUM(t.amount), 0) / c.budget_limit) DESC"
);
mysqli_stmt_bind_param($bud_q, 'ssi', $start_date, $end_date, $home_id);
mysqli_stmt_execute($bud_q);
$bud_res = mysqli_stmt_get_result($bud_q);
$budgets = [];
while ($row = mysqli_fetch_assoc($bud_res)) {
    $budgets[] = $row;
}
mysqli_stmt_close($bud_q);

ob_start();
include ROOT_PATH . '/app/includes/partials/reports_kpis.php';
$kpis_html = ob_get_clean();

ob_start();
include ROOT_PATH . '/app/includes/partials/reports_budgets.php';
$budgets_html = ob_get_clean();

echo json_encode([
    'ok'   => true,
    'data' => [
        'header' => [
            'currentLabel'   => $month_names[$current_month] . ' ' . $current_year,
            'isCurrentMonth' => $is_current_month,
            'prev' => [
                'm' => $prev_month,
                'y' => $prev_year,
                'label' => $month_names[$prev_month],
            ],
            'next' => [
                'm' => $next_month,
                'y' => $next_year,
                'label' => $month_names[$next_month],
            ],
            'today' => [
                'm' => $today_m,
                'y' => $today_y,
            ],
            'currentM' => $current_month,
            'currentY' => $current_year,
        ],
        'dates' => [
            'start' => $start_date,
            'end'   => $end_date,
        ],
        'pie' => [
            'labels' => $pie_labels,
            'data'   => $pie_data,
        ],
        'kpisHtml'    => $kpis_html,
        'budgetsHtml' => $budgets_html,
    ],
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * רינדור ליבת דף הבית (KPI, סטטיסטיקות, ממתינות, אחרונות, קטגוריות).
 * משמש את index.php ואת AJAX לרענון חלקי.
 */
function tazrim_render_home_dashboard_core(mysqli $conn, int $home_id, int $selected_month, int $selected_year): string
{
    global $today_il;

    $limit = 4;

    $home_data = selectOne('homes', ['id' => $home_id]);
    $show_bank_balance = (int) ($home_data['show_bank_balance'] ?? 0);

    $balance_parts = tazrim_home_display_bank_balance($conn, $home_id, $today_il);
    $current_bank_balance = $balance_parts['display'];
    $current_bank_balance_raw = $balance_parts['ledger_dec'] + $balance_parts['adjustment_dec'];

    $month_income_query = "SELECT SUM(amount) as total FROM transactions 
                       WHERE home_id = $home_id AND type = 'income' 
                       AND MONTH(transaction_date) = $selected_month 
                       AND YEAR(transaction_date) = $selected_year";
    $result_income = mysqli_query($conn, $month_income_query);
    $income_data = mysqli_fetch_assoc($result_income);
    $total_income = $income_data['total'] ?? 0;

    $month_expense_query = "SELECT SUM(amount) as total FROM transactions 
                        WHERE home_id = $home_id AND type = 'expense' 
                        AND MONTH(transaction_date) = $selected_month 
                        AND YEAR(transaction_date) = $selected_year";
    $result_expense = mysqli_query($conn, $month_expense_query);
    $expense_data = mysqli_fetch_assoc($result_expense);
    $total_expense = $expense_data['total'] ?? 0;

    $pending_query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
                  FROM transactions t 
                  LEFT JOIN categories c ON t.category = c.id 
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE t.home_id = $home_id 
                  AND t.transaction_date > '$today_il'
                  AND MONTH(t.transaction_date) = $selected_month 
                  AND YEAR(t.transaction_date) = $selected_year
                  ORDER BY t.transaction_date ASC, t.created_at ASC
                  LIMIT $limit";
    $pending_result = mysqli_query($conn, $pending_query);

    $recent_query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
                 FROM transactions t 
                 LEFT JOIN categories c ON t.category = c.id 
                 LEFT JOIN users u ON t.user_id = u.id
                 WHERE t.home_id = $home_id 
                 AND t.transaction_date <= '$today_il'
                 AND MONTH(t.transaction_date) = $selected_month 
                 AND YEAR(t.transaction_date) = $selected_year
                 ORDER BY t.transaction_date DESC, t.created_at DESC 
                 LIMIT $limit";
    $recent_result = mysqli_query($conn, $recent_query);

    $pending_count_query = "SELECT COUNT(*) AS cnt FROM transactions t
                  WHERE t.home_id = $home_id
                  AND t.transaction_date > '$today_il'
                  AND MONTH(t.transaction_date) = $selected_month
                  AND YEAR(t.transaction_date) = $selected_year";
    $pending_count_row = mysqli_fetch_assoc(mysqli_query($conn, $pending_count_query));
    $has_more_pending = ((int) ($pending_count_row['cnt'] ?? 0)) > $limit;

    $recent_count_query = "SELECT COUNT(*) AS cnt FROM transactions t
                 WHERE t.home_id = $home_id
                 AND t.transaction_date <= '$today_il'
                 AND MONTH(t.transaction_date) = $selected_month
                 AND YEAR(t.transaction_date) = $selected_year";
    $recent_count_row = mysqli_fetch_assoc(mysqli_query($conn, $recent_count_query));
    $has_more_recent = ((int) ($recent_count_row['cnt'] ?? 0)) > $limit;

    $categories_budget_query = "SELECT 
                            c.id, c.name, c.icon, c.budget_limit,
                            COALESCE(SUM(CASE 
                                WHEN t.type = 'expense' 
                                AND MONTH(t.transaction_date) = $selected_month 
                                AND YEAR(t.transaction_date) = $selected_year 
                                THEN t.amount ELSE 0 END), 0) as current_spending
                        FROM categories c
                        LEFT JOIN transactions t ON c.id = t.category AND t.home_id = $home_id
                        WHERE c.home_id = $home_id 
                        AND c.type = 'expense'
                        AND c.is_active = 1
                        GROUP BY c.id, c.name, c.icon, c.budget_limit
                        ORDER BY current_spending DESC";
    $result_categories = mysqli_query($conn, $categories_budget_query);

    ob_start();
    include __DIR__ . '/partials/home_dashboard_core_markup.php';
    $inner = ob_get_clean();

    return '<div id="home-dashboard-core">' . $inner . '</div>';
}

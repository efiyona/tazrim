<?php
// עולים שתי רמות למעלה כדי להגיע לשורש ולמצוא את path.php
require('../../path.php'); 
include(ROOT_PATH . '/app/database/db.php');

$home_id = $_SESSION['home_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 4;
$status = isset($_GET['status']) ? $_GET['status'] : 'recent'; // מושכים את הסטטוס (ברירת מחדל: אחרונות)
$limit = 4;

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// ה-$today_il כבר קיים לנו בזיכרון בזכות ה-connect.php!

if ($status === 'pending') {
    // שאילתה לממתינות
    $where_clause = "AND t.transaction_date > '$today_il'";
    $order_clause = "ORDER BY t.transaction_date ASC, t.created_at ASC";
} else {
    // שאילתה לאחרונות
    $where_clause = "AND t.transaction_date <= '$today_il'";
    $order_clause = "ORDER BY t.transaction_date DESC, t.created_at DESC";
}

$query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
          FROM transactions t 
          LEFT JOIN categories c ON t.category = c.id 
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.home_id = $home_id 
          $where_clause
          AND MONTH(t.transaction_date) = $selected_month 
          AND YEAR(t.transaction_date) = $selected_year
          $order_clause 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        $is_future = strtotime($row['transaction_date']) > strtotime($today_il);
        $pending_class = $is_future ? 'pending-trans' : '';
        $display_icon = $is_future ? 'fa-regular fa-clock' : ($row['cat_icon'] ?: 'fa-tag');
        
        $amount_formatted = number_format($row['amount'], 0);
        $symbol = ($row['type'] == 'income') ? '+' : '-';
        $safe_desc = htmlspecialchars($row['description'], ENT_QUOTES);
        $date_formatted = date('d/m/Y', strtotime($row['transaction_date']));
        
        $user_badge = "";
        if ($row['user_name']) {
            $user_badge = "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>({$row['user_name']})</span>";
        }
        
        // מדפיסים את העיצוב בדיוק כמו ב-index.php
        echo "
        <div class='transaction-item {$row['type']} {$pending_class}'>
            <div class='transaction-info'>
                <div class='cat-icon-wrapper'>
                    <i class='fa-solid {$display_icon}'></i>
                </div>
                <div class='details'>
                    <span class='desc'>
                        {$row['description']}
                        {$user_badge}
                    </span>
                    <span class='date'>{$date_formatted}</span>
                </div>
            </div>
            <div class=׳transaction-actions׳>
                <div class='transaction-amount'>
                    {$symbol} {$amount_formatted} ₪
                </div>
                <div style='display:flex; gap: 5px;'>
                    <button onclick=\"openEditTransModal({$row['id']}, {$row['amount']}, {$row['category']}, '{$safe_desc}', '{$row['type']}')\" style='background: var(--gray); border: none; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;' title='ערוך פעולה'>
                        <i class='fa-solid fa-pen' style='font-size: 1rem;'></i>
                    </button>
                    <button onclick=\"deleteTransaction({$row['id']})\" style='background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;' title='מחק פעולה'>
                        <i class='fa-solid fa-trash-can' style='font-size: 1rem;'></i>
                    </button>
                </div>
            </div>
        </div>";
    }
} else {
    echo "NO_MORE";
}
?>
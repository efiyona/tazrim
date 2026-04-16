<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

$cat_id = $_GET['cat_id'] ?? null;
$mode = $_GET['mode'] ?? 'category';
$requested_type = $_GET['trans_type'] ?? 'expense';
$allowed_types = ['expense', 'income'];
$selected_type = in_array($requested_type, $allowed_types, true) ? $requested_type : 'expense';

// משיכת מזהה הבית מהסשן
$home_id = $_SESSION['home_id']; 

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

if ($mode === 'type') {
    $query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
              FROM transactions t 
              LEFT JOIN categories c ON t.category = c.id 
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.home_id = $home_id 
              AND t.type = '$selected_type'
              AND MONTH(t.transaction_date) = $selected_month
              AND YEAR(t.transaction_date) = $selected_year
              ORDER BY 
                CASE WHEN t.transaction_date > '$today_il' THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > '$today_il' THEN t.transaction_date END ASC,
                t.transaction_date DESC";
    $empty_text = $selected_type === 'income'
        ? 'אין הכנסות רשומות החודש.'
        : 'אין הוצאות רשומות החודש.';
} elseif ($cat_id) {
    $selected_type = 'expense';
    $query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
              FROM transactions t 
              LEFT JOIN categories c ON t.category = c.id 
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.category = $cat_id 
              AND t.home_id = $home_id 
              AND t.type = 'expense'
              AND MONTH(t.transaction_date) = $selected_month
              AND YEAR(t.transaction_date) = $selected_year
              ORDER BY 
                CASE WHEN t.transaction_date > '$today_il' THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > '$today_il' THEN t.transaction_date END ASC,
                t.transaction_date DESC";
    $empty_text = 'אין פעולות רשומות לקטגוריה זו החודש.';
} else {
    exit;
}

$result = mysqli_query($conn, $query);
if (!$result) {
    exit;
}

if (mysqli_num_rows($result) > 0) {
    echo '<div class="modal-transactions-list">';
    while ($row = mysqli_fetch_assoc($result)) {
        $is_future = strtotime($row['transaction_date']) > strtotime(date('Y-m-d'));
        $pending_class = $is_future ? 'pending-trans' : '';
        $icon_class = $is_future ? 'fa-regular fa-clock' : ('fa-solid ' . ($row['cat_icon'] ?: 'fa-tag'));
        $waiting_badge = $is_future ? '<span style="font-size:0.7rem; background:#eee; padding:2px 6px; border-radius:10px; margin-right:5px; color:#777;">ממתין</span>' : '';
        $safe_desc = htmlspecialchars($row['description'], ENT_QUOTES);
        $safe_desc_html = htmlspecialchars($row['description']);
        $safe_user = htmlspecialchars($row['user_name'] ?? '');
        $user_badge = $safe_user !== '' ? "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>(" . $safe_user . ")</span>" : "";
        $amount_prefix = $row['type'] === 'income' ? '+' : '-';

        echo '<div class="transaction-item ' . $row['type'] . ' ' . $pending_class . '" 
            onclick="openEditTransModal(' . $row['id'] . ', ' . $row['amount'] . ', ' . $row['category'] . ', \'' . $safe_desc . '\', \'' . $row['type'] . '\', \'category-details\')"
            style="margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); cursor: pointer;">';
        echo '  <div class="transaction-info">';
        echo '      <div class="cat-icon-wrapper"><i class="' . $icon_class . '"></i></div>';
        echo '      <div class="details">';
        echo '          <span class="desc">' . $safe_desc_html . ' ' . $user_badge . ' ' . $waiting_badge . '</span>';
        echo '          <span class="date">' . date('d/m/Y', strtotime($row['transaction_date'])) . '</span>';
        echo '      </div>';
        echo '  </div>';
        echo '  <div class="transaction-actions">';
        echo '      <div class="transaction-amount">' . $amount_prefix . ' ' . number_format($row['amount'], 0) . ' ₪</div>';
        echo '      <div class="transaction-row-actions">';
        echo '          <button type="button" onclick="openEditTransModal(' . $row['id'] . ', ' . $row['amount'] . ', ' . $row['category'] . ', \'' . $safe_desc . '\', \'' . $row['type'] . '\', \'category-details\')" class="transaction-action-pill" title="ערוך פעולה">';
        echo '              <i class="fa-solid fa-pen" style="font-size: 1rem;"></i>';
        echo '          </button>';
        echo '          <button type="button" onclick="event.stopPropagation(); deleteTransaction(' . $row['id'] . ', \'category-details\')" class="transaction-action-pill transaction-action-pill--danger" title="מחק פעולה">';
        echo '              <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>';
        echo '          </button>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="empty-state text-center" style="padding: 30px;">';
    echo '  <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; color: var(--gray); margin-bottom: 15px;"></i>';
    echo '  <p style="color: var(--text-light);">' . $empty_text . '</p>';
    echo '</div>';
}
?>
<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

$cat_id = $_GET['cat_id'] ?? null;
// משיכת מזהה הבית מהסשן
$home_id = $_SESSION['home_id']; 

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

if ($cat_id) {
    // מוסיפים את חוקר השמות לשאילתה
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
                CASE WHEN t.transaction_date > CURRENT_DATE() THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > CURRENT_DATE() THEN t.transaction_date END ASC,
                t.transaction_date DESC";
              
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        echo '<div class="modal-transactions-list">';
        while ($row = mysqli_fetch_assoc($result)) {
            $is_future = strtotime($row['transaction_date']) > strtotime(date('Y-m-d'));
            $pending_class = $is_future ? 'pending-trans' : '';
            $icon = $is_future ? 'fa-regular fa-clock' : ($row['cat_icon'] ?: 'fa-tag');
            $waiting_badge = $is_future ? '<span style="font-size:0.7rem; background:#eee; padding:2px 6px; border-radius:10px; margin-right:5px; color:#777;">ממתין</span>' : '';
            
            // השם המבצע בסוגריים אפורים קטנים
            $user_badge = $row['user_name'] ? "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>({$row['user_name']})</span>" : "";

            echo '<div class="transaction-item expense ' . $pending_class . '" style="margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">';
            echo '  <div class="transaction-info">';
            echo '      <div class="cat-icon-wrapper"><i class="fa-solid ' . $icon . '"></i></div>';
            echo '      <div class="details">';
            echo '          <span class="desc">' . $row['description'] . ' ' . $user_badge . ' ' . $waiting_badge . '</span>';
            echo '          <span class="date">' . date('d/m/Y', strtotime($row['transaction_date'])) . '</span>';
            echo '      </div>';
            echo '  </div>';
            echo '  <div style="display: flex; align-items: center; gap: 10px;">';
            echo '      <div class="transaction-amount">- ' . number_format($row['amount'], 0) . ' ₪</div>';
            echo '      <div style="display:flex; gap: 5px;">';
            echo '          <button onclick="openEditTransModal(' . $row['id'] . ', ' . $row['amount'] . ', ' . $row['category'] . ', \'' . htmlspecialchars($row['description'], ENT_QUOTES) . '\', \'expense\')" style="background: var(--gray); border: none; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="ערוך פעולה">';
            echo '              <i class="fa-solid fa-pen" style="font-size: 1rem;"></i>';
            echo '          </button>';
            echo '          <button onclick="deleteTransaction(' . $row['id'] . ')" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק פעולה">';
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
        echo '  <p style="color: var(--text-light);">אין פעולות רשומות לקטגוריה זו החודש.</p>';
        echo '</div>';
    }
}
?>
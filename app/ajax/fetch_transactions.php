<?php

// עולים שתי רמות למעלה כדי להגיע לשורש ולמצוא את path.php
require('../../path.php'); 
include(ROOT_PATH . '/app/database/db.php');

$home_id = $_SESSION['home_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 4;
$limit = 4;

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// מוסיפים את הטבלת משתמשים כדי למשוך את שם המשתמש
$query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
          FROM transactions t 
          LEFT JOIN categories c ON t.category = c.id 
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.home_id = $home_id 
          AND MONTH(t.transaction_date) = $selected_month 
          AND YEAR(t.transaction_date) = $selected_year
          ORDER BY 
                CASE WHEN t.transaction_date > '$today_il' THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > '$today_il' THEN t.transaction_date END ASC,
                t.transaction_date DESC, 
                t.created_at DESC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $typeClass = $row['type'];
        $date = date('d/m/Y', strtotime($row['transaction_date']));
        $amount = number_format($row['amount'], 0);
        $symbol = ($row['type'] == 'income') ? '+' : '-';

        $is_future = strtotime($row['transaction_date']) > strtotime(date('Y-m-d'));
        $pending_class = $is_future ? 'pending-trans' : '';
        $icon = $is_future ? 'fa-regular fa-clock' : ($row['cat_icon'] ?: 'fa-tag');
        $waiting_badge = $is_future ? '<span style="font-size:0.7rem; background:#eee; padding:2px 6px; border-radius:10px; margin-right:5px; color:#777;">ממתין</span>' : '';
        
        // יצירת תגית השם אם קיים משתמש
        $user_badge = $row['user_name'] ? "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>({$row['user_name']})</span>" : "";
        
        // טיפול בגרשיים בתיאור הפעולה כדי שלא ישברו את ה-HTML
        $safe_desc = htmlspecialchars($row['description'], ENT_QUOTES);

        echo "
        <div class='transaction-item $typeClass $pending_class'>
            <div class='transaction-info'>
                <div class='cat-icon-wrapper'>
                    <i class='fa-solid $icon'></i>
                </div>
                <div class='details'>
                    <span class='desc'>{$row['description']} $user_badge $waiting_badge</span>
                    <span class='date'>$date</span>
                </div>
            </div>
            <div style='display: flex; align-items: center; gap: 10px;'>
                <div class='transaction-amount'>
                    $symbol $amount ₪
                </div>
                <div style='display:flex; gap: 5px;'>
                    <button onclick=\"openEditTransModal({$row['id']}, {$row['amount']}, {$row['category']}, '{$safe_desc}', '{$row['type']}')\" style='background: var(--gray); border: none; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;' title='ערוך פעולה'>
                        <i class='fa-solid fa-pen' style='font-size: 1rem;'></i>
                    </button>
                    <button onclick='deleteTransaction({$row['id']})' style='background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;' title='מחק פעולה'>
                        <i class='fa-solid fa-trash-can' style='font-size: 1rem;'></i>
                    </button>
                </div>
            </div>
        </div>";
    }
} else {
    // חשוב! זה אומר ל-JS שאין יותר נתונים
    echo "NO_MORE"; 
}
?>
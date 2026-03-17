<?php

// עולים שתי רמות למעלה כדי להגיע לשורש ולמצוא את path.php
require('../../path.php'); 
include(ROOT_PATH . '/app/database/db.php');

$home_id = $_SESSION['home_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 3;
$limit = 3;

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// השאילתה נשארת זהה
// השאילתה נשארת זהה בעיקרון, אבל מוסיפה את הסידור החכם
$query = "SELECT t.*, c.icon as cat_icon 
          FROM transactions t 
          LEFT JOIN categories c ON t.category = c.id 
          WHERE t.home_id = $home_id 
          AND MONTH(t.transaction_date) = $selected_month 
          AND YEAR(t.transaction_date) = $selected_year
          ORDER BY 
                CASE WHEN t.transaction_date > CURRENT_DATE() THEN 1 ELSE 0 END DESC,
                CASE WHEN t.transaction_date > CURRENT_DATE() THEN t.transaction_date END ASC,
                t.transaction_date DESC, 
                t.created_at DESC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $typeClass = $row['type'];
        $date = date('d/m/Y', strtotime($row['transaction_date']));
        $amount = number_format($row['amount'], 2);
        $symbol = ($row['type'] == 'income') ? '+' : '-';

        // הוספת לוגיקת שעון ממתין
        $is_future = strtotime($row['transaction_date']) > strtotime(date('Y-m-d'));
        $pending_class = $is_future ? 'pending-trans' : '';
        $icon = $is_future ? 'fa-regular fa-clock' : ($row['cat_icon'] ?: 'fa-tag');
        $waiting_badge = $is_future ? '<span style="font-size:0.7rem; background:#eee; padding:2px 6px; border-radius:10px; margin-right:5px; color:#777;">ממתין</span>' : '';

        echo "
        <div class='transaction-item $typeClass $pending_class'>
            <div class='transaction-info'>
                <div class='cat-icon-wrapper'>
                    <i class='fa-solid $icon'></i>
                </div>
                <div class='details'>
                    <span class='desc'>{$row['description']} $waiting_badge</span>
                    <span class='date'>$date</span>
                </div>
            </div>
            <div class='transaction-amount'>
                $symbol $amount ₪
            </div>
        </div>";
    }
} else {
    echo "NO_MORE";
}
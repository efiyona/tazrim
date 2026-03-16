<?php

// עולים שתי רמות למעלה כדי להגיע לשורש ולמצוא את path.php
require('../../path.php'); 
include(ROOT_PATH . '/app/database/db.php');

$home_id = $_SESSION['home_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 5;
$limit = 5;

// השאילתה נשארת זהה
$query = "SELECT t.*, c.icon as cat_icon 
          FROM transactions t 
          LEFT JOIN categories c ON t.category = c.id 
          WHERE t.home_id = $home_id 
          ORDER BY t.transaction_date DESC, t.created_at DESC 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $typeClass = $row['type'];
        $icon = $row['cat_icon'] ?: 'fa-tag';
        $date = date('d/m/Y', strtotime($row['transaction_date']));
        $amount = number_format($row['amount'], 2);
        $symbol = ($row['type'] == 'income') ? '+' : '-';

        echo "
        <div class='transaction-item $typeClass'>
            <div class='transaction-info'>
                <div class='cat-icon-wrapper'>
                    <i class='fa-solid $icon'></i>
                </div>
                <div class='details'>
                    <span class='desc'>{$row['description']}</span>
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
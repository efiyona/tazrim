<?php
/**
 * תלוי ב-push_functions (sendPushToEntireHome). קובץ נפרד כדי שטעינה תהיה יציבה גם לכלי ניתוח סטטי.
 */
require_once __DIR__ . '/push_functions.php';

if (!function_exists('maybeSendBudgetOverrunPush')) {
    /**
     * אם סך ההוצאות בחודש הנוכחי בקטגוריה מגיע לתקרה או עובר אותה — שולח פוש לכל בני הבית.
     */
    function maybeSendBudgetOverrunPush($home_id, $category_id) {
        global $conn;

        $home_id = (int) $home_id;
        $category_id = (int) $category_id;
        if ($home_id <= 0 || $category_id <= 0) {
            return;
        }

        $cat_query = "SELECT name, budget_limit FROM categories WHERE id = $category_id LIMIT 1";
        $cat_result = mysqli_query($conn, $cat_query);
        if (!$cat_result) {
            return;
        }
        $cat_data = mysqli_fetch_assoc($cat_result);

        if (!$cat_data || (float) $cat_data['budget_limit'] <= 0) {
            return;
        }

        $budget_limit = (float) $cat_data['budget_limit'];
        $cat_name = $cat_data['name'];

        $start_of_month = date('Y-m-01');
        $end_of_month = date('Y-m-t');

        $sum_query = "SELECT SUM(amount) as total_spent FROM transactions 
                      WHERE home_id = $home_id 
                      AND category = $category_id 
                      AND type = 'expense' 
                      AND transaction_date BETWEEN '$start_of_month' AND '$end_of_month'";
        $sum_result = mysqli_query($conn, $sum_query);
        if (!$sum_result) {
            return;
        }
        $sum_data = mysqli_fetch_assoc($sum_result);
        $total_spent = isset($sum_data['total_spent']) && $sum_data['total_spent'] !== null
            ? (float) $sum_data['total_spent']
            : 0.0;

        if ($total_spent >= $budget_limit) {
            $alert_title = "⚠️ חריגה מתקציב!";
            $total_spent_formatted = number_format($total_spent);
            $budget_formatted = number_format($budget_limit);
            $alert_body = "הגעתם ל-100% מהתקציב בקטגוריית '$cat_name'. (הוצאתם $total_spent_formatted מתוך $budget_formatted ₪).";
            $base = defined('BASE_URL') ? BASE_URL : '/';
            sendPushToEntireHome($home_id, $alert_title, $alert_body, $base);
        }
    }
}

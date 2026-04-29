<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/budget_overrun_push.php'; // פוש + maybeSendBudgetOverrunPush
require_once ROOT_PATH . '/app/functions/currency.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_id = $_SESSION['home_id'] ?? null;
    $user_id = $_SESSION['id'] ?? null;

    if (!$home_id || !$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר או פג תוקף חיבור.']);
        exit();
    }

    $type = $_POST['type'] ?? 'expense';
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $currency_code = tazrim_normalize_currency_code($_POST['currency_code'] ?? 'ILS');
    $debug_run_id = uniqid('add_tx_', true);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $interval_months = isset($_POST['interval_months']) ? (int) $_POST['interval_months'] : 1;
    if (!in_array($interval_months, [1, 2], true)) {
        $interval_months = 1;
    }

    // ולידציה בסיסית
    if ($amount <= 0 || empty($category_id) || empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'נא למלא את כל השדות בצורה תקינה.']);
        exit();
    }

    $cat_check = mysqli_query(
        $conn,
        "SELECT id, type FROM categories WHERE id = $category_id AND home_id = $home_id AND is_active = 1 LIMIT 1"
    );
    $cat_row = $cat_check ? mysqli_fetch_assoc($cat_check) : null;
    if (!$cat_row || ($cat_row['type'] ?? '') !== $type) {
        echo json_encode(['status' => 'error', 'message' => 'קטגוריה לא תקינה עבור סוג הפעולה שנבחר.']);
        exit();
    }

    try {
        $conversion = tazrim_convert_amount_to_ils($conn, $amount, $currency_code);
    } catch (Throwable $e) {
        // #region agent log
        tazrim_debug_log('app/ajax/add_transaction.php:45', 'Add transaction conversion failed', [
            'type' => $type,
            'amount' => $amount,
            'currency_code' => $currency_code,
            'category_id' => $category_id,
            'is_recurring' => $is_recurring,
            'error_class' => get_class($e),
        ], 'H3', $debug_run_id);
        // #endregion
        echo json_encode(['status' => 'error', 'message' => 'לא הצלחנו למשוך שער המרה כרגע. נסו שוב בעוד רגע.']);
        exit();
    }

    $amount_ils = (float) $conversion['converted_amount'];
    $currency_code_esc = mysqli_real_escape_string($conn, $currency_code);
    // #region agent log
    tazrim_debug_log('app/ajax/add_transaction.php:54', 'Add transaction conversion succeeded', [
        'type' => $type,
        'amount_original' => $amount,
        'amount_ils' => $amount_ils,
        'currency_code' => $currency_code,
        'category_id' => $category_id,
        'is_recurring' => $is_recurring,
    ], 'H3', $debug_run_id);
    // #endregion
    $insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, currency_code, category, description, transaction_date) 
                     VALUES ($home_id, $user_id, '$type', $amount_ils, '$currency_code_esc', $category_id, '$description', '$transaction_date')";
    
    if (mysqli_query($conn, $insert_query)) {
        
        if ($is_recurring) {
            $day_of_month = (int)date('d', strtotime($transaction_date));
            $current_month_start = date('Y-m-01');

            $insert_recurring = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, currency_code, category, description, day_of_month, interval_months, last_injected_month, is_active) 
                                 VALUES ($home_id, $user_id, '$type', $amount, '$currency_code_esc', $category_id, '$description', $day_of_month, $interval_months, '$current_month_start', 1)";
            mysqli_query($conn, $insert_recurring);
        }

        // 6. יצירת התראה פנימית לבית (בתוך האפליקציה)
        $user_name = $_SESSION['first_name'];
        $amount_formatted = number_format($amount_ils, 2);

        $notif_title = $user_name; 
        $notif_msg = "הוסיף פעולה חדשה: <span class='notif-bold'>$description</span> בסך $amount_formatted ₪";

        addNotification($home_id, $notif_title, $notif_msg, 'info', null);

        // ==========================================
        // 7. שליחת התראת Push לשאר בני הבית
        // ==========================================
        if ($type === 'expense') {
            $push_title = "הוצאה חדשה בתזרים 💸";
            $action_word = "הוסיף/ה הוצאה של";
        } else {
            $push_title = "הכנסה חדשה בתזרים 💰";
            $action_word = "הוסיף/ה הכנסה של";
        }

        // גוף ההודעה מבוסס על התיאור שהזנת
        $push_body = "$user_name $action_word $amount_formatted ₪ עבור '$description'.";
        $push_url = BASE_URL; // לחיצה על ההתראה תוביל למסך הראשי

        // הפעלת פונקציית העזר שלנו - שולחת לכולם בבית *חוץ* מלמי שביצע את הפעולה
        sendPushToHome($home_id, $user_id, $push_title, $push_body, $push_url);
        // ==========================================

        // ==========================================
        // 8. בדיקת חריגה מתקציב הקטגוריה (Budget Alert)
        // ==========================================
        if ($type === 'expense') {
            maybeSendBudgetOverrunPush($home_id, $category_id);
        }
        // ==========================================

        global $today_il;
        $today_for_ledger = isset($today_il) ? (string) $today_il : date('Y-m-d');
        $newRow = [
            'type' => $type,
            'amount' => $amount_ils,
            'transaction_date' => $transaction_date,
        ];
        tazrim_after_transaction_row_change($conn, (int) $home_id, null, $newRow, $today_for_ledger);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת בשמירת הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
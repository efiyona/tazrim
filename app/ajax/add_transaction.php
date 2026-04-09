<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/budget_overrun_push.php'; // פוש + maybeSendBudgetOverrunPush

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
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;

    // ולידציה בסיסית
    if ($amount <= 0 || empty($category_id) || empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'נא למלא את כל השדות בצורה תקינה.']);
        exit();
    }

    $insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, category, description, transaction_date) 
                     VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', '$transaction_date')";
    
    if (mysqli_query($conn, $insert_query)) {
        
        if ($is_recurring) {
            $day_of_month = (int)date('d', strtotime($transaction_date));
            $current_month_start = date('Y-m-01');

            $insert_recurring = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, category, description, day_of_month, last_injected_month, is_active) 
                                 VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', $day_of_month, '$current_month_start', 1)";
            mysqli_query($conn, $insert_recurring);
        }

        // ניקוי Cache של ה-AI
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");

        // 6. יצירת התראה פנימית לבית (בתוך האפליקציה)
        $user_name = $_SESSION['first_name'];
        $amount_formatted = number_format($amount, 2);

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

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת בשמירת הנתונים.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
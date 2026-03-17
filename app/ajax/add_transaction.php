<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// הגדרת סוג התשובה ל-JSON (כדי שה-JavaScript ידע לקרוא אותה)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. קבלת נתוני משתמש ובית
    $home_id = $_SESSION['home_id'] ?? null;
    $user_id = $_SESSION['id'] ?? null;

    if (!$home_id || !$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר או פג תוקף חיבור. נא לרענן את הדף.']);
        exit();
    }

    // 2. איסוף וניקוי הנתונים מהטופס
    $type = $_POST['type'] ?? 'expense';
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $transaction_date = $_POST['transaction_date'] ?? date('Y-m-d');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;

    // אימות נתונים בסיסי (Validation)
    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'הסכום חייב להיות גדול מאפס.']);
        exit();
    }
    if (empty($category_id)) {
        echo json_encode(['status' => 'error', 'message' => 'חובה לבחור קטגוריה.']);
        exit();
    }
    if (empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'חובה להזין תיאור לפעולה.']);
        exit();
    }

    // 3. שמירת הפעולה במסד הנתונים (טבלת ההוצאות/הכנסות הרגילה)
    $insert_query = "INSERT INTO transactions (home_id, user_id, type, amount, category, description, transaction_date) 
                     VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', '$transaction_date')";
    
    if (mysqli_query($conn, $insert_query)) {
        
        // 4. טיפול בפעולה קבועה (רק אם סומן הצ'קבוקס!)
        if ($is_recurring) {
            // חילוץ היום בחודש מתוך התאריך שנבחר
            $day_of_month = (int)date('d', strtotime($transaction_date));
            // הגדרת חודש ההזרקה האחרון לחודש הנוכחי (כדי שהמנוע לא ישכפל את זה שוב מחר בבוקר)
            $current_month_start = date('Y-m-01');

            $insert_recurring = "INSERT INTO recurring_transactions (home_id, user_id, type, amount, category, description, day_of_month, last_injected_month, is_active) 
                                 VALUES ($home_id, $user_id, '$type', $amount, $category_id, '$description', $day_of_month, '$current_month_start', 1)";
            mysqli_query($conn, $insert_recurring);
        }

        // 5. מחיקת ה-Cache של הבינה המלאכותית
        // ברגע שהתווספה פעולה חדשה, הניתוח הישן כבר לא רלוונטי
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");

        // הכל עבד! החזרת תשובת הצלחה ל-JavaScript
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת: לא הצלחנו לשמור את הנתונים.']);
    }

} else {
    // אם מישהו ניסה לגשת לקובץ הזה ישירות דרך שורת הכתובת בדפדפן
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
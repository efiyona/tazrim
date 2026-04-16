<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home_id = $_SESSION['home_id'] ?? null;
    
    if (!$home_id) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר או שפג תוקף החיבור.']);
        exit();
    }

    $has_active_categories = (bool) selectOne('categories', ['home_id' => $home_id, 'is_active' => 1]);
    if ($has_active_categories) {
        echo json_encode(['status' => 'error', 'message' => 'ההגדרות הראשוניות כבר הושלמו לבית זה.']);
        exit();
    }

    // 1. עדכון פרטי הבית (שם ויתרה התחלתית)
    $home_name = isset($_POST['home_name']) ? mysqli_real_escape_string($conn, trim($_POST['home_name'])) : '';
    $initial_balance = isset($_POST['initial_balance']) ? (float)$_POST['initial_balance'] : 0;

    $encrypted_balance = encryptBalance($initial_balance);
    
    // אם המשתמש השאיר שם בית ריק, לא נדרוס את הקיים (נשאיר את ברירת המחדל שנוצרה בהרשמה)
    if (!empty($home_name)) {
        mysqli_query($conn, "UPDATE homes SET name = '$home_name', initial_balance = '$encrypted_balance' WHERE id = $home_id");
    } else {
        mysqli_query($conn, "UPDATE homes SET initial_balance = '$encrypted_balance' WHERE id = $home_id");
    }

    // 2. עיבוד והזרקת קטגוריות
    $cats_added_count = 0;
    
    if (isset($_POST['cats']) && is_array($_POST['cats'])) {
        foreach ($_POST['cats'] as $index => $cat_data) {
            // פירוק הנתונים: שם|אייקון|סוג
            $parts = explode('|', $cat_data);
            if (count($parts) < 3) continue; // הגנה ממקרה קצה של נתון פגום

            $name = mysqli_real_escape_string($conn, trim($parts[0]));
            $icon = mysqli_real_escape_string($conn, trim($parts[1]));
            $type = mysqli_real_escape_string($conn, trim($parts[2]));
            
            // שליפת התקציב התואם לפי האינדקס
            $budget = isset($_POST['budgets'][$index]) ? (float)$_POST['budgets'][$index] : 0;

            /**
             * טיפול במקרי קצה:
             * אם השם ריק - אנחנו מתעלמים מהקטגוריה לחלוטין (גם אם הוזן תקציב).
             */
            if (empty($name)) {
                continue;
            }

            // מניעת כפילויות (למקרה שהמשתמש לחץ פעמיים או הזין שם זהה)
            $check_exists = mysqli_query($conn, "SELECT id FROM categories WHERE home_id = $home_id AND name = '$name' AND is_active = 1");
            
            if (mysqli_num_rows($check_exists) == 0) {
                $insert_query = "INSERT INTO categories (home_id, name, type, budget_limit, icon, is_active) 
                                 VALUES ($home_id, '$name', '$type', $budget, '$icon', 1)";
                
                if (mysqli_query($conn, $insert_query)) {
                    $cats_added_count++;
                }
            } else {
                // אם הקטגוריה כבר קיימת, נחשיב אותה כ"נוספה" כדי לא לחסום את המשתמש
                $cats_added_count++;
            }
        }
    }

    // בדיקה סופית: האם יש לנו לפחות קטגוריה אחת פעילה?
    if ($cats_added_count > 0) {
        // ניקוי קאש של הבינה המלאכותית (כדי שתנתח את המבנה החדש)
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
        
        echo json_encode(['status' => 'success']);
    } else {
        // מקרה קצה: המשתמש מחק את הכל או הוסיף בלוקים ריקים בלבד
        echo json_encode([
            'status' => 'error', 
            'message' => 'חובה להגדיר לפחות קטגוריה אחת עם שם כדי להתחיל להשתמש במערכת.'
        ]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'גישה ישירה לקובץ אסורה.']);
}
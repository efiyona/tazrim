<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/payment_methods.php';

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

    // 1. עדכון פרטי הבית (שם, הצגת יתרה, יתרה מוצגת)
    $home_name = isset($_POST['home_name']) ? mysqli_real_escape_string($conn, trim($_POST['home_name'])) : '';
    $bank_raw = isset($_POST['initial_balance']) ? trim((string) $_POST['initial_balance']) : '';
    $bank_val = ($bank_raw !== '' && is_numeric($bank_raw)) ? (float) $bank_raw : null;
    $show_bank_balance = isset($_POST['show_bank_balance']) ? 1 : 0;

    // אם המשתמש השאיר שם בית ריק, לא נדרוס את הקיים (נשאיר את ברירת המחדל שנוצרה בהרשמה)
    if (!empty($home_name)) {
        mysqli_query($conn, "UPDATE homes SET name = '$home_name', show_bank_balance = $show_bank_balance WHERE id = $home_id");
    } else {
        mysqli_query($conn, "UPDATE homes SET show_bank_balance = $show_bank_balance WHERE id = $home_id");
    }

    tazrim_recompute_home_ledger_cached_from_db($conn, (int) $home_id);
    if ($bank_val !== null) {
        tazrim_apply_user_bank_balance_target($conn, (int) $home_id, $bank_val, date('Y-m-d'));
    }

    // 2. אמצעי תשלום. גם אם הלקוח לא שלח נתונים, הבית מתחיל עם "בנק".
    $methods = json_decode((string)($_POST['payment_methods'] ?? '[]'), true);
    if (!is_array($methods) || count($methods) === 0) {
        $methods = [
            ['type' => 'cash', 'name' => 'מזומן', 'last4' => '', 'issuer' => ''],
            ['type' => 'bank_transfer', 'name' => 'העברה', 'last4' => '', 'issuer' => ''],
        ];
    }
    $allowed_method_types = ['cash','credit_card','check','bank_transfer','bank_debit'];
    $methods_added = 0;
    $existing_default = tazrim_payment_methods((int)$home_id, true);
    foreach ($methods as $i => $method) {
        $method_type = (string)($method['type'] ?? '');
        $method_name = trim((string)($method['name'] ?? ''));
        $method_last4 = trim((string)($method['last4'] ?? ''));
        $method_issuer = trim((string)($method['issuer'] ?? ''));
        if (!in_array($method_type, $allowed_method_types, true) || $method_name === '') continue;
        if ($method_type === 'credit_card' && !preg_match('/^\d{4}$/', $method_last4)) continue;
        if ($method_type !== 'credit_card') { $method_last4 = ''; $method_issuer = ''; }
        $is_default = $methods_added === 0 ? 1 : 0;
        if ($is_default && !empty($existing_default)) {
            $default_id = (int)$existing_default[0]['id'];
            $stmt = $conn->prepare("UPDATE payment_methods SET type=?,name=?,last4=NULLIF(?,''),issuer=NULLIF(?,'') WHERE id=? AND home_id=?");
            $stmt->bind_param('ssssii', $method_type, $method_name, $method_last4, $method_issuer, $default_id, $home_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO payment_methods(home_id,type,name,last4,issuer,is_default,is_active) VALUES(?,?,?,NULLIF(?,''),NULLIF(?,''),?,1)");
            $stmt->bind_param('issssi', $home_id, $method_type, $method_name, $method_last4, $method_issuer, $is_default);
        }
        if ($stmt->execute()) $methods_added++;
        $stmt->close();
    }
    if ($methods_added === 0) {
        echo json_encode(['status'=>'error','message'=>'חובה להגדיר לפחות אמצעי תשלום אחד.']); exit();
    }

    // 3. עיבוד והזרקת קטגוריות
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
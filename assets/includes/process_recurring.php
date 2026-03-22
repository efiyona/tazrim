<?php
// קובץ זה בודק אם יש פעולות קבועות שצריך להזריק לחודש הנוכחי (או לחודשים שפוספסו)

$home_id = $_SESSION['home_id'];
$current_month_start = date('Y-m-01'); 

// שליפת כל הפעולות הקבועות שטרם הוזרקו עד לחודש הנוכחי (כולל)
$check_recurring_query = "SELECT * FROM recurring_transactions 
                          WHERE home_id = $home_id 
                          AND is_active = 1 
                          AND (last_injected_month IS NULL OR last_injected_month < '$current_month_start')";

$recurring_result = mysqli_query($conn, $check_recurring_query);

if (mysqli_num_rows($recurring_result) > 0) {
    
    // מערך שמות חודשים להתראות
    $hebrew_months = [1=>'ינואר', 2=>'פברואר', 3=>'מרץ', 4=>'אפריל', 5=>'מאי', 6=>'יוני', 7=>'יולי', 8=>'אוגוסט', 9=>'ספטמבר', 10=>'אוקטובר', 11=>'נובמבר', 12=>'דצמבר'];

    while ($template = mysqli_fetch_assoc($recurring_result)) {
        $template_id = $template['id'];
        $user_id = $template['user_id'];
        $type = $template['type'];
        $amount = $template['amount'];
        $category = $template['category'];
        $description = mysqli_real_escape_string($conn, $template['description']);
        $day_of_month = $template['day_of_month'];

        $last_injected = $template['last_injected_month'];
        
        // הגדרת חודש ההתחלה להשלמת הפערים
        if (!$last_injected) {
            $start_date = new DateTime($current_month_start);
        } else {
            $start_date = new DateTime($last_injected);
            $start_date->modify('+1 month'); // מתחילים מהחודש שאחרי ההזרקה האחרונה
        }
        
        $end_date = new DateTime($current_month_start);

        // לולאת זמן: רצה על כל החודשים שפוספסו עד לחודש הנוכחי
        while ($start_date <= $end_date) {
            $loop_year = (int)$start_date->format('Y');
            $loop_month = (int)$start_date->format('m');
            $loop_month_start = $start_date->format('Y-m-01');
            
            // חישוב היום המדויק (מטפל במצבים של 31 לחודש בפברואר למשל)
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $loop_month, $loop_year);
            $actual_day = ($day_of_month > $days_in_month) ? $days_in_month : $day_of_month;
            $transaction_date = sprintf('%04d-%02d-%02d', $loop_year, $loop_month, $actual_day);

            // 1. הזרקה לטבלת הפעולות הרגילה
            $insert_trans = "INSERT INTO transactions (home_id, user_id, amount, type, category, description, transaction_date) 
                             VALUES ($home_id, $user_id, $amount, '$type', $category, '$description', '$transaction_date')";
            
            if (mysqli_query($conn, $insert_trans)) {
                
                // 2. עדכון חודש ההזרקה בתבנית
                mysqli_query($conn, "UPDATE recurring_transactions SET last_injected_month = '$loop_month_start' WHERE id = $template_id");

                // 3. יצירת התראה מותאמת אישית לאותו חודש שבוצע
                $amount_fmt = number_format($amount, 0);
                $month_name = $hebrew_months[$loop_month];
                $notif_msg = "הזרקה אוטומטית ($month_name): <span class='notif-bold'>{$template['description']}</span> בסך {$amount_fmt} ₪";
                
                addNotification($home_id, "פעולה קבועה ($month_name)", $notif_msg, 'success');
            }
            
            // קידום הלולאה לחודש הבא
            $start_date->modify('+1 month');
        }

        // מחיקת Cache של ה-AI בסיום הטיפול בפעולה זו
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
    }
}
?>
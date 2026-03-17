<?php
// קובץ זה נועד לרוץ בכל טעינה של דף הבית, בצורה שקטה וקלה מאוד
// הוא בודק אם יש פעולות קבועות שצריך להזריק לחודש הנוכחי

$home_id = $_SESSION['home_id'];
$current_month_start = date('Y-m-01'); // מחזיר תמיד את ה-1 לחודש הנוכחי (למשל: 2026-03-01)
$current_year = date('Y');
$current_month = date('m');

// השאילתה הממוקדת: תביא רק פעולות של הבית הזה, שפעילות, ושעדיין לא הוזרקו החודש
$check_recurring_query = "SELECT * FROM recurring_transactions 
                          WHERE home_id = $home_id 
                          AND is_active = 1 
                          AND (last_injected_month IS NULL OR last_injected_month < '$current_month_start')";

$recurring_result = mysqli_query($conn, $check_recurring_query);

// אם חזר 0 (וזה מה שיקרה כמעט תמיד) - הקוד פשוט מדלג הלאה
if (mysqli_num_rows($recurring_result) > 0) {
    
    while ($template = mysqli_fetch_assoc($recurring_result)) {
        $template_id = $template['id'];
        $user_id = $template['user_id'];
        $type = $template['type'];
        $amount = $template['amount'];
        $category = $template['category'];
        $description = mysqli_real_escape_string($conn, $template['description']);
        $day_of_month = $template['day_of_month'];

        // 1. חישוב חכם של התאריך להזרקה (טיפול בחודשים קצרים)
        $days_in_current_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
        $actual_day = ($day_of_month > $days_in_current_month) ? $days_in_current_month : $day_of_month;
        
        // יצירת התאריך המדויק באותו חודש (למשל 2026-03-10)
        $transaction_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $actual_day);

        // 2. הזרקה לטבלת הפעולות הרגילה (עם התאריך העתידי/הנוכחי)
        $insert_trans = "INSERT INTO transactions (home_id, user_id, amount, type, category, description, transaction_date) 
                         VALUES ($home_id, $user_id, $amount, '$type', $category, '$description', '$transaction_date')";
        mysqli_query($conn, $insert_trans);

        // 3. עדכון התבנית כדי שלא תשתכפל שוב מחר!
        $update_template = "UPDATE recurring_transactions 
                            SET last_injected_month = '$current_month_start' 
                            WHERE id = $template_id";
        mysqli_query($conn, $update_template);
    }
}
?>
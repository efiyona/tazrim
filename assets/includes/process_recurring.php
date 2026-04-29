<?php
// קובץ זה בודק אם יש פעולות קבועות שצריך להזריק לחודש הנוכחי (או לחודשים שפוספסו)

require_once ROOT_PATH . '/app/functions/currency.php';

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
        $debug_run_id = uniqid('proc_rec_', true);
        $user_id = $template['user_id'];
        $type = $template['type'];
        $amount = (float) $template['amount'];
        $currency_code = tazrim_normalize_currency_code($template['currency_code'] ?? 'ILS');
        $category = $template['category'];
        $description = mysqli_real_escape_string($conn, $template['description']);
        $day_of_month = $template['day_of_month'];
        $interval_months = (int) ($template['interval_months'] ?? 1);
        if (!in_array($interval_months, [1, 2], true)) {
            $interval_months = 1;
        }

        $last_injected = $template['last_injected_month'];
        
        // הגדרת חודש ההתחלה להשלמת הפערים
        if (!$last_injected) {
            $start_date = new DateTime($current_month_start);
        } else {
            $start_date = new DateTime($last_injected);
            $start_date->modify('+' . $interval_months . ' month'); // מתחילים מהחודש שאחרי ההזרקה האחרונה (לפי מרווח)
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

            try {
                $conversion = tazrim_convert_amount_to_ils($conn, $amount, $currency_code);
            } catch (Throwable $e) {
                // #region agent log
                tazrim_debug_log('assets/includes/process_recurring.php:58', 'Recurring injection conversion failed', [
                    'template_id' => $template_id,
                    'currency_code' => $currency_code,
                    'amount_original' => $amount,
                    'transaction_date' => $transaction_date,
                    'error_class' => get_class($e),
                ], 'H5', $debug_run_id);
                // #endregion
                addNotification($home_id, 'שגיאת המרת מטבע', 'לא הצלחנו להזריק כרגע את הפעולה הקבועה "' . htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8') . '". ננסה שוב בכניסה הבאה.', 'error');
                break;
            }
            $amount_ils = (float) $conversion['converted_amount'];

            $currency_code_esc = mysqli_real_escape_string($conn, $currency_code);
            // #region agent log
            tazrim_debug_log('assets/includes/process_recurring.php:71', 'Recurring injection conversion succeeded', [
                'template_id' => $template_id,
                'currency_code' => $currency_code,
                'amount_original' => $amount,
                'amount_ils' => $amount_ils,
                'transaction_date' => $transaction_date,
            ], 'H5', $debug_run_id);
            // #endregion

            // 1. הזרקה לטבלת הפעולות הרגילה
            $insert_trans = "INSERT INTO transactions (home_id, user_id, amount, currency_code, type, category, description, transaction_date) 
                             VALUES ($home_id, $user_id, $amount_ils, '$currency_code_esc', '$type', $category, '$description', '$transaction_date')";
            
            if (mysqli_query($conn, $insert_trans)) {
                global $today_il;
                $today_for_ledger = isset($today_il) ? (string) $today_il : date('Y-m-d');
                $newLedgerRow = [
                    'type' => $type,
                    'amount' => $amount_ils,
                    'transaction_date' => $transaction_date,
                ];
                tazrim_after_transaction_row_change($conn, (int) $home_id, null, $newLedgerRow, $today_for_ledger);

                // 2. עדכון חודש ההזרקה בתבנית
                mysqli_query($conn, "UPDATE recurring_transactions SET last_injected_month = '$loop_month_start' WHERE id = $template_id");

                // 3. יצירת התראה מותאמת אישית לאותו חודש שבוצע
                $amount_fmt = number_format($amount_ils, 0);
                $month_name = $hebrew_months[$loop_month];
                $notif_msg = "הזרקה אוטומטית ($month_name): <span class='notif-bold'>{$template['description']}</span> בסך {$amount_fmt} ₪";
                
                addNotification($home_id, "פעולה קבועה ($month_name)", $notif_msg, 'success');
            }
            
            // קידום הלולאה לחודש הבא (חודשי/דו־חודשי)
            $start_date->modify('+' . $interval_months . ' month');
        }
    }
}
?>
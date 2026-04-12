 <?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
    exit();
}

$user_id = (int)$_SESSION['id'];
$home_id = (int)$_SESSION['home_id'];
$user_role = $_SESSION['role'];

// 1. בדיקה כמה משתמשים יש בבית הזה
$users_in_home_query = "SELECT id, role, email FROM users WHERE home_id = $home_id";
$users_in_home_result = mysqli_query($conn, $users_in_home_query);
$users_count = mysqli_num_rows($users_in_home_result);

$is_only_user = ($users_count === 1);

// שליפת האימייל של המשתמש הנוכחי לצורך מחיקת בקשות איפוס סיסמה
$user_email = '';
while($row = mysqli_fetch_assoc($users_in_home_result)) {
    if($row['id'] == $user_id) $user_email = $row['email'];
}
// איפוס המצביע של התוצאות לשימוש חוזר
mysqli_data_seek($users_in_home_result, 0);

// מתחילים תהליך מחיקה בטוח (Transaction)
mysqli_begin_transaction($conn);

try {
    // =========================================================================
    // שלב 1: מחיקת נתונים אישיים ופרטיים של המשתמש (קורה תמיד, בכל מצב)
    // =========================================================================
    mysqli_query($conn, "DELETE FROM api_tokens WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM tos_agreements WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM user_subscriptions WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM notification_reads WHERE user_id = $user_id");
    mysqli_query($conn, "DELETE FROM notifications WHERE user_id = $user_id"); // התראות שמופנות אישית אליו
    
    if (!empty($user_email)) {
        $safe_email = mysqli_real_escape_string($conn, $user_email);
        mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$safe_email'");
    }

    // =========================================================================
    // שלב 2: טיפול בנתונים משותפים (לפי כמות השותפים בבית)
    // =========================================================================
    if ($is_only_user) {
        // --- מצב א': המשתמש הוא היחיד בבית - מוחקים הכל, כולל את הבית עצמו! ---
        
        mysqli_query($conn, "DELETE FROM transactions WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM recurring_transactions WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM shopping_items WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM shopping_categories WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM categories WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM notifications WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM ai_insights_cache WHERE home_id = $home_id");
        mysqli_query($conn, "DELETE FROM ai_api_logs WHERE home_id = $home_id");
        
        // מחיקת הבית עצמו
        mysqli_query($conn, "DELETE FROM homes WHERE id = $home_id");
        
    } else {
        // --- מצב ב': יש שותפים נוספים בבית - העברת בעלות (Ownership Transfer) ---
        
        // שולפים יורש: עדיפות לאדמין אחר, ואם אין - שותף אחר כלשהו
        $heir_query = "SELECT id, role FROM users WHERE home_id = $home_id AND id != $user_id ORDER BY (role IN ('home_admin','program_admin','admin')) DESC LIMIT 1";
        $heir_res = mysqli_query($conn, $heir_query);
        
        if ($heir_row = mysqli_fetch_assoc($heir_res)) {
            $heir_id = $heir_row['id'];
            
            // העברת בעלות בטבלאות שיש בהן user_id
            mysqli_query($conn, "UPDATE transactions SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
            mysqli_query($conn, "UPDATE recurring_transactions SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
            mysqli_query($conn, "UPDATE ai_api_logs SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
            
            // העברת בעלות על התראות שהוא יצר (כדי שלא יהיו יתומות)
            mysqli_query($conn, "UPDATE notifications SET creator_id = $heir_id WHERE creator_id = $user_id AND home_id = $home_id");

            // העברת בעלות על הבית עצמו אם הוא היה ה-primary
            mysqli_query($conn, "UPDATE homes SET primary_user_id = $heir_id WHERE primary_user_id = $user_id AND id = $home_id");

            // טיפול חכם: אם העוזב היה מנהל בית, נוודא שלפחות אחד מהנשארים מקבל הרשאות מנהל
            if (in_array($user_role, ['home_admin', 'program_admin', 'admin'], true)) {
                $other_admins_query = "SELECT id FROM users WHERE home_id = $home_id AND id != $user_id AND role IN ('home_admin','program_admin','admin')";
                $other_admins_res = mysqli_query($conn, $other_admins_query);
                
                if (mysqli_num_rows($other_admins_res) == 0) {
                    // אם הוא היה האדמין היחיד, היורש מקבל קידום להיות מנהל הבית החדש!
                    mysqli_query($conn, "UPDATE users SET role = 'home_admin' WHERE id = $heir_id");
                }
            }
        }
    }

    // =========================================================================
    // שלב 3: מחיקת המשתמש עצמו מהמערכת (סיום)
    // =========================================================================
    mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");

    // אישור וביצוע סופי למסד הנתונים
    mysqli_commit($conn);

    // ניקוי הסשן (ניתוק הדפדפן של המשתמש הנוכחי)
    session_destroy();
    
    // מחיקת עוגיית ההתחברות האוטומטית
    if(isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, "/");
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    // אם משהו קורס, מבטלים הכל כדי לשמור על שלמות המידע
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה במסד הנתונים בתהליך המחיקה.']);
}
?>
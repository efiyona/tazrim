<?php
include(ROOT_PATH . "/app/database/db.php");
require_once ROOT_PATH . '/app/helpers/phone_uniqueness.php';
require_once ROOT_PATH . '/app/functions/push_functions.php';

$errors = array();
// משתנים לשמירת הנתונים בטופס במקרה של שגיאה
$first_name = '';
$last_name = '';
$nickname = '';
$email = '';
$phone = '';


if (isset($_POST['login_btn'])) {
    // קבלת הנתונים מהטופס
    $email = $_POST['email'];
    $password = $_POST['password'];

    // בדיקה בסיסית שהשדות לא ריקים
    if (empty($email)) {
        array_push($errors, "כתובת אימייל היא חובה");
    }
    if (empty($password)) {
        array_push($errors, "סיסמה היא חובה");
    }

    if (count($errors) === 0) {
        // חיפוש המשתמש במסד הנתונים לפי אימייל
        $user = selectOne('users', ['email' => $email]);

        // בדיקה אם המשתמש קיים והסיסמה נכונה
        if ($user && password_verify($password, $user['password'])) {
            // התחברות מוצלחת - יצירת סשן
            $_SESSION['id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['home_id'] = $user['home_id'];
            $_SESSION['role'] = $user['role'];
            if (function_exists('tazrim_session_refresh_email_status')) {
                tazrim_session_refresh_email_status((int) $user['id'], $user);
            }
            
            // טיפול ב"זכור אותי"
            if(isset($_POST['remember_me'])) {
                // 1. יצירת מחרוזת אקראית ומאובטחת (טוקן)
                $token = bin2hex(random_bytes(32));
                
                // 2. שמירת הטוקן במסד הנתונים בעמודה שכבר יש לנו
                update('users', $user['id'], ['remember_token' => $token]);
                
                // 3. יצירת עוגייה בדפדפן שתקפה ל-30 ימים (86400 שניות ביום)
                setcookie('remember_token', $token, time() + (86400 * 30), "/");
            }

            // הפניה לדף הבית (index.php בחוץ)
            header('location: ' . BASE_URL . 'index.php');
            exit();
        } else {
            array_push($errors, "פרטי ההתחברות אינם נכונים");
        }
    }
}

if (isset($_POST['register_btn'])) {
    // קבלת נתונים
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $nickname   = $_POST['nickname'];
    $email      = $_POST['email'];
    $phone      = $_POST['phone'];
    $password   = $_POST['password'];
    $home_action = $_POST['home_action'];

    // --- בדיקות אימות (Validation) ---
    if (empty($first_name)) array_push($errors, "חובה להזין שם פרטי");
    if (empty($email)) array_push($errors, "חובה להזין כתובת אימייל");
    if (strlen($password) < 4) array_push($errors, "הסיסמה חייבת להיות לפחות 4 תווים");
    if (!isset($_POST['accept_tos'])) array_push($errors, "חובה לקרוא ולאשר את תנאי השימוש");

    // בדיקה אם המשתמש קיים
    $existingUser = selectOne('users', ['email' => $email]);
    if ($existingUser) array_push($errors, "כתובת האימייל הזו כבר רשומה במערכת");

    if (trim((string) $phone) === '') {
        array_push($errors, "חובה להזין מספר טלפון");
    } else {
        $phoneNorm = tazrim_normalize_phone_key($phone);
        if ($phoneNorm === '') {
            array_push($errors, "מספר הטלפון אינו תקין");
        } elseif (tazrim_user_id_with_normalized_phone($phoneNorm, null)) {
            array_push($errors, "מספר הטלפון כבר רשום במערכת");
        }
    }

    // --- בדיקת קוד הבית לפני הכל ---
    $target_home_id = null;

    if ($home_action === 'join') {
        $home_code = $_POST['home_code'];
        if (empty($home_code)) {
            array_push($errors, "חובה להזין קוד בית כדי להצטרף");
        } else {
            $home = selectOne('homes', ['join_code' => $home_code]);
            if (!$home) {
                array_push($errors, "קוד הבית שהזנת לא קיים במערכת");
            } else {
                $target_home_id = $home['id'];
            }
        }
    }

    // --- ביצוע ההרשמה (רק אם אין שגיאות) ---
    if (count($errors) === 0) {
        
        // הצפנת סיסמה
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 1. יצירת המשתמש
        $userData = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'nickname'   => $nickname,
            'email'      => $email,
            'password'   => $hashed_password,
            'phone'      => tazrim_normalize_phone_key($phone),
            'role'       => ($home_action === 'create') ? 'home_admin' : 'user'
        ];

        $user_id = create('users', $userData);

        if ($user_id) {
            // 2. טיפול בבית (יצירה או שיוך)
            if ($home_action === 'create') {
                $new_home_code = rand(1000, 9999);
                $home_name = $_POST['home_name'] ?: "הבית של " . $first_name;
                
                $target_home_id = create('homes', [
                    'name' => $home_name,
                    'join_code' => $new_home_code,
                    'primary_user_id' => $user_id,
                    'bank_balance_ledger_cached' => encryptBalance(0.0),
                    'bank_balance_manual_adjustment' => encryptBalance(0.0),
                    'show_bank_balance' => 0,
                ]);
            }

            // 3. עדכון ה-home_id של המשתמש חזרה
            update('users', $user_id, ['home_id' => $target_home_id]);

            // ==========================================
            // 3.5 התראה למנהלי מערכת על משתמש חדש
            // ==========================================
            $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'program_admin'");
            $admin_panel_url = BASE_URL . 'admin/dashboard.php';
            $full_name = trim($first_name . ' ' . $last_name);
            if ($full_name === '') {
                $full_name = $email;
            }

            $notif_title = 'משתמש חדש נרשם למערכת';
            $message_html = "<span class='notif-bold'>" . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . "</span> נרשמ/ה למערכת";
            if (trim((string) $email) !== '') {
                $message_html .= " — " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            }

            $push_body = $full_name . ' נרשמ/ה עכשיו למערכת';
            if (trim((string) $email) !== '') {
                $push_body .= ' (' . $email . ')';
            }

            if ($admin_res) {
                while ($admin = mysqli_fetch_assoc($admin_res)) {
                    $admin_id = (int) ($admin['id'] ?? 0);
                    if ($admin_id <= 0) {
                        continue;
                    }
                    addNotification(0, $notif_title, $message_html, 'info', $admin_id);
                    sendPushNotification($admin_id, $notif_title, $push_body, $admin_panel_url, 'system');
                }
            }

            // ==========================================
            // 4. שליחת התראת Push לשאר בני הבית (רק בהצטרפות)
            // ==========================================
            if ($home_action === 'join') {
                $push_title = "שותף חדש בבית! 🏠";
                $push_body = "$first_name הצטרף הרגע לניהול 'התזרים' של הבית. ברוך הבא!";
                $push_url = BASE_URL;

                // שולח לכל בני הבית הקיימים, חוץ מהמשתמש החדש
                sendPushToHome($target_home_id, $user_id, $push_title, $push_body, $push_url);
            }
            // ==========================================

            // ==========================================
            // 4.5 שמירת תיעוד אישור התקנון בטבלת tos_agreements
            // ==========================================
            create('tos_agreements', [
                'user_id' => $user_id,
                'tos_version' => tazrim_tos_version(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            // הגדרת הסשן כדי שה-auth_check.php לא יזרוק אותו חזרה לדף אישור מיד כשיתחבר
            $_SESSION['tos_version'] = tazrim_tos_version();
            // ==========================================

            // 5. התחברות אוטומטית והפניה
            $_SESSION['id'] = $user_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['nickname'] = $nickname;
            $_SESSION['home_id'] = $target_home_id;
            $_SESSION['role'] = $userData['role'];
            if (function_exists('tazrim_session_refresh_email_status')) {
                $uRow = selectOne('users', ['id' => (int) $user_id]);
                if ($uRow) {
                    tazrim_session_refresh_email_status((int) $user_id, $uRow);
                }
            }

            header('location: ' . BASE_URL . 'index.php');
            exit();
        }
    }
}
<?php
include(ROOT_PATH . "/app/database/db.php");

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
            $_SESSION['nickname'] = $user['nickname'];
            $_SESSION['home_id'] = $user['home_id'];
            $_SESSION['role'] = $user['role'];
            
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

    // בדיקה אם המשתמש קיים
    $existingUser = selectOne('users', ['email' => $email]);
    if ($existingUser) array_push($errors, "כתובת האימייל הזו כבר רשומה במערכת");

    // --- ה בדיקת קוד הבית לפני הכל ---
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
            'phone'      => $phone,
            'role'       => ($home_action === 'create') ? 'home_admin' : 'user'
        ];

        $user_id = create('users', $userData);

        if ($user_id) {
            // 2. טיפול בבית (יצירה או שיוך)
            if ($home_action === 'create') {
                // יצירת קוד רנדומלי (מספרים בלבד)
                $new_home_code = rand(1000, 9999);
                $home_name = $_POST['home_name'] ?: "הבית של " . $first_name;
                
                // יצירת הבית ושמירת ה-user_id שיצר אותו כ-primary_user (או השדה המקביל אצלך)
                $target_home_id = create('homes', [
                    'name' => $home_name,
                    'join_code' => $new_home_code,
                    'primary_user_id' => $user_id // כאן אנחנו מחברים את המשתמש לבית
                ]);
            }

            // 3. עדכון ה-home_id של המשתמש חזרה (כדי שיוכל להיכנס לבית)
            update('users', $user_id, ['home_id' => $target_home_id]);

            // 4. התחברות אוטומטית והפניה
            $_SESSION['id'] = $user_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['home_id'] = $target_home_id;
            $_SESSION['role'] = $userData['role'];

            header('location: ' . BASE_URL . 'index.php');
            exit();
        }
    }
}
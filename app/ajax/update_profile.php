<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/helpers/phone_uniqueness.php';
require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';

header('Content-Type: application/json');

// בדיקה שהמשתמש אכן מחובר
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר או שהסשן פג.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int) $_SESSION['id'];
    $row = selectOne('users', ['id' => $user_id]);
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא נמצא.']);
        exit();
    }

    // קליטת הנתונים מהטופס וניקוי רווחים מיותרים
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $nickname   = trim($_POST['nickname'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $new_email  = trim((string) ($_POST['email'] ?? ''));

    // ולידציה בסיסית בצד שרת
    if (empty($first_name) || empty($last_name) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'אנא מלא את כל שדות החובה (שם פרטי, שם משפחה וטלפון).']);
        exit();
    }

    $phoneNorm = tazrim_normalize_phone_key($phone);
    if ($phoneNorm === '') {
        echo json_encode(['status' => 'error', 'message' => 'מספר הטלפון אינו תקין.']);
        exit();
    }
    if (tazrim_user_id_with_normalized_phone($phoneNorm, $user_id)) {
        echo json_encode(['status' => 'error', 'message' => 'מספר הטלפון כבר רשום אצל משתמש אחר במערכת.']);
        exit();
    }

    $data = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'nickname'   => $nickname,
        'phone'      => $phoneNorm,
    ];

    $curEmail = (string) ($row['email'] ?? '');

    if (tazrim_email_verified_column_exists()) {
        if ($new_email === '') {
            $new_email = $curEmail;
        }
        if (strcasecmp($new_email, $curEmail) !== 0) {
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'כתובת המייל אינה תקינה.']);
                exit();
            }
            $taken = selectOne('users', ['email' => $new_email]);
            if ($taken && (int) $taken['id'] !== $user_id) {
                echo json_encode(['status' => 'error', 'message' => 'כתובת המייל כבר רשומה בחשבון אחר.']);
                exit();
            }
            $data['email'] = $new_email;
            $data['email_verified_at'] = null;
        }
    }

    // עדכון טבלת המשתמשים
    $updateResult = update('users', $user_id, $data);

    if ($updateResult !== false) {
        if (isset($data['email'])) {
            $del = $conn->prepare('DELETE FROM `email_verification_codes` WHERE `user_id` = ?');
            if ($del) {
                $del->bind_param('i', $user_id);
                $del->execute();
                $del->close();
            }
        }
        // חשוב: עדכון הסשן הנוכחי כדי שהשם/כינוי ישתנו מיד בבר העליון בלי צורך בהתחברות מחדש
        $_SESSION['first_name'] = $first_name;
        $_SESSION['nickname'] = $nickname;
        if (function_exists('tazrim_session_refresh_email_status') && tazrim_email_verified_column_exists()) {
            tazrim_session_refresh_email_status($user_id, array_merge($row, $data));
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה בעדכון הנתונים במסד.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/secrets.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require ROOT_PATH . '/vendor/autoload.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');

// --- פעולה 1: בדיקת מייל ושליחת קוד ---
if ($action === 'send_code') {
    $user = selectOne('users', ['email' => $email]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'כתובת המייל לא קיימת במערכת.']);
        exit();
    }

    $code = rand(100000, 999999);
    $expires = date("Y-m-d H:i:s", strtotime('+10 minutes'));

    mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$email'");
    mysqli_query($conn, "INSERT INTO password_resets (email, code, expires_at) VALUES ('$email', '$code', '$expires')");

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME; 
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('support@trofaplus.com', 'התזרים');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'קוד אימות לאיפוס סיסמה';
        $mail->Body    = "<div dir='rtl'><h2>קוד האימות שלך הוא: <b style='font-size:24px;'>$code</b></h2></div>";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'קוד נשלח בהצלחה.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשליחה: ' . $mail->ErrorInfo]);
    }
    exit();
}

// --- פעולה 2: אימות קוד ---
if ($action === 'verify_code') {
    $code = $_POST['code'] ?? '';
    $reset = selectOne('password_resets', ['email' => $email, 'code' => $code]);

    if ($reset && strtotime($reset['expires_at']) > time()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'הקוד שגוי או פג תוקף.']);
    }
    exit();
}

// --- פעולה 3: עדכון סיסמה ---
if ($action === 'reset_password') {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($pass) < 4 || $pass !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'הסיסמאות לא תואמות או קצרות מדי.']);
        exit();
    }

    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    $user = selectOne('users', ['email' => $email]);
    update('users', $user['id'], ['password' => $hashed]);
    mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$email'");

    echo json_encode(['status' => 'success', 'message' => 'הסיסמה עודכנה בהצלחה!']);
    exit();
}
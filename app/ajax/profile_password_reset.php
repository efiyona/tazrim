<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/secrets.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require ROOT_PATH . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
    exit;
}

$user_id = (int) $_SESSION['id'];
$user = selectOne('users', ['id' => $user_id]);
if (!$user || empty($user['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא נמצא חשבון משתמש תקין.']);
    exit;
}

$action = $_POST['action'] ?? '';
$email = $user['email'];

if ($action === 'send_code') {
    $code = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $deleteStmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('s', $email);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $insertStmt = $conn->prepare('INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)');
    if (!$insertStmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת קוד האימות.']);
        exit;
    }
    $insertStmt->bind_param('sss', $email, $code, $expires);
    $ok = $insertStmt->execute();
    $insertStmt->close();
    if (!$ok) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשמירת קוד האימות.']);
        exit;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // SMTP providers like Hostinger may reject MAIL FROM unless it exactly matches the authenticated user.
        $fromAddress = trim((string) MAIL_USERNAME);
        $fromName = defined('MAIL_FROM_NAME') && trim((string) constant('MAIL_FROM_NAME')) !== ''
            ? trim((string) constant('MAIL_FROM_NAME'))
            : 'התזרים';
        $mail->setFrom($fromAddress, $fromName);
        $mail->Sender = $fromAddress;
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'קוד אימות לשינוי סיסמה';
        $mail->Body = "<div dir='rtl' style='font-family:Arial,sans-serif;'>
            <p style='font-size:16px;margin:0 0 8px;'>קוד האימות שלך:</p>
            <p style='font-size:34px;font-weight:700;letter-spacing:4px;margin:0 0 10px;'>$code</p>
            <p style='font-size:14px;margin:0;color:#475569;'>הקוד בתוקף ל-10 דקות.</p>
        </div>";
        $mail->AltBody = "קוד האימות שלך: $code\nהקוד בתוקף ל-10 דקות.";

        $mail->send();
        $_SESSION['profile_password_reset_verified'] = false;
        echo json_encode(['status' => 'success', 'message' => 'קוד אימות נשלח למייל שלך.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה בשליחת המייל. נסה שוב.']);
    }
    exit;
}

if ($action === 'verify_code') {
    $code = trim((string)($_POST['code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['status' => 'error', 'message' => 'הקוד חייב להכיל 6 ספרות.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id, expires_at FROM password_resets WHERE email = ? AND code = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
        exit;
    }
    $stmt->bind_param('ss', $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || strtotime((string)$row['expires_at']) <= time()) {
        echo json_encode(['status' => 'error', 'message' => 'הקוד שגוי או שפג תוקפו.']);
        exit;
    }

    $_SESSION['profile_password_reset_verified'] = true;
    echo json_encode(['status' => 'success', 'message' => 'הקוד אומת בהצלחה.']);
    exit;
}

if ($action === 'reset_password') {
    $is_verified = isset($_SESSION['profile_password_reset_verified']) && $_SESSION['profile_password_reset_verified'] === true;
    if (!$is_verified) {
        echo json_encode(['status' => 'error', 'message' => 'יש לאמת קוד לפני שינוי הסיסמה.']);
        exit;
    }

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($password) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'הסיסמה חייבת להכיל לפחות 4 תווים.']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'הסיסמאות אינן תואמות.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $updated = update('users', $user_id, ['password' => $hashed]);
    if ($updated === false) {
        echo json_encode(['status' => 'error', 'message' => 'לא ניתן לעדכן סיסמה כרגע.']);
        exit;
    }

    $cleanup = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
    if ($cleanup) {
        $cleanup->bind_param('s', $email);
        $cleanup->execute();
        $cleanup->close();
    }

    unset($_SESSION['profile_password_reset_verified']);
    echo json_encode(['status' => 'success', 'message' => 'הסיסמה עודכנה בהצלחה.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'פעולה לא חוקית.']);
exit;
?>

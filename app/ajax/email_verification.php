<?php
/**
 * send_code: שליחת קוד 6 ספרות לכתובת המייל בחשבון
 * verify_code: אימות + עדכון email_verified_at
 */
require_once '../../path.php';
include ROOT_PATH . '/app/database/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id']) || (int) $_SESSION['id'] <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נדרשת התחברות.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once ROOT_PATH . '/app/functions/email_verification_runtime.php';
if (!tazrim_email_verified_column_exists()) {
    echo json_encode(['status' => 'error', 'message' => 'המערכת עוד לא מעודכנת לאימות מייל.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists(ROOT_PATH . '/secrets.php')) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת תצורה.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once ROOT_PATH . '/secrets.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once ROOT_PATH . '/vendor/autoload.php';

$action = (string) ($_POST['action'] ?? '');
$user_id = (int) $_SESSION['id'];
$user = selectOne('users', ['id' => $user_id]);

if (!$user || empty($user['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא נמצאה כתובת מייל בחשבון.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = (string) $user['email'];

if (tazrim_user_email_is_verified($user)) {
    tazrim_session_mark_email_verified((string) ($user['email_verified_at'] ?? date('Y-m-d H:i:s')));
    echo json_encode(['status' => 'success', 'message' => 'כתובת המייל כבר אומתה.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'send_code') {
    $code = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $del = $conn->prepare('DELETE FROM `email_verification_codes` WHERE `user_id` = ?');
    if ($del) {
        $del->bind_param('i', $user_id);
        $del->execute();
        $del->close();
    }
    $ins = $conn->prepare('INSERT INTO `email_verification_codes` (`user_id`, `code`, `expires_at`) VALUES (?, ?, ?)');
    if (!$ins) {
        echo json_encode(['status' => 'error', 'message' => 'שמירת קוד אימות נכשלה.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ins->bind_param('iss', $user_id, $code, $expires);
    if (!$ins->execute()) {
        $ins->close();
        echo json_encode(['status' => 'error', 'message' => 'שמירת קוד אימות נכשלה.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ins->close();

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
        $fromAddress = trim((string) MAIL_USERNAME);
        $fromName = defined('MAIL_FROM_NAME') && trim((string) constant('MAIL_FROM_NAME')) !== ''
            ? trim((string) constant('MAIL_FROM_NAME'))
            : 'התזרים';
        $mail->setFrom($fromAddress, $fromName);
        $mail->Sender = $fromAddress;
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'קוד אימות לכתובת המייל – התזרים';
        $mail->Body = "<div dir='rtl' style='font-family:Arial,sans-serif;'>
            <p style='font-size:16px;margin:0 0 8px;'>קוד אימות לכתובת המייל:</p>
            <p style='font-size:34px;font-weight:700;letter-spacing:4px;margin:0 0 10px;'>$code</p>
            <p style='font-size:14px;margin:0;color:#475569;'>הקוד בתוקף ל-10 דקות. אם לא ביקשת, התעלם מההודעה.</p>
        </div>";
        $mail->AltBody = "קוד אימות: $code\nהקוד בתוקף ל-10 דקות.";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'נשלח קוד אימות למייל.'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'שליחת המייל נכשלה. נסו שוב בעוד כמה דקות.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'verify_code') {
    $code = trim((string) ($_POST['code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['status' => 'error', 'message' => 'נא להזין 6 ספרות.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = $conn->prepare('SELECT `expires_at` FROM `email_verification_codes` WHERE `user_id` = ? AND `code` = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param('is', $user_id, $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || strtotime((string) $row['expires_at']) <= time()) {
        echo json_encode(['status' => 'error', 'message' => 'הקוד שגוי או שפג תוקפו.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    update('users', $user_id, ['email_verified_at' => $now]);

    $cl = $conn->prepare('DELETE FROM `email_verification_codes` WHERE `user_id` = ?');
    if ($cl) {
        $cl->bind_param('i', $user_id);
        $cl->execute();
        $cl->close();
    }

    tazrim_session_mark_email_verified($now);
    tazrim_session_refresh_email_status($user_id, array_merge($user, ['email_verified_at' => $now, 'email' => $email]));

    echo json_encode(['status' => 'success', 'message' => 'כתובת המייל אומתה בהצלחה.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'בקשה לא תקינה.'], JSON_UNESCAPED_UNICODE);

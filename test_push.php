<?php
require_once('path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/app/functions/push_functions.php');

// בדיקה שהמשתמש מחובר
if (!isset($_SESSION['id'])) {
    die("עליך להיות מחובר למערכת כדי לבצע בדיקה.");
}

$user_id = $_SESSION['id'];
$user_name = $_SESSION['first_name'];

$title = "היי $user_name! 👋";
$body = "התראות Push ב'התזרים' עובדות עכשיו רשמית!";
$url = BASE_URL . "pages/manage_home.php";

echo "מנסה לשלוח התראה למשתמש ID: $user_id...<br>";

if (sendPushNotification($user_id, $title, $body, $url)) {
    echo "<b>הצלחה!</b> ההתראה נשלחה למכשירים הרשומים שלך. בדוק את האייפון!";
} else {
    echo "<b>שגיאה:</b> לא נמצאו מכשירים רשומים עבורך בטבלה user_subscriptions.";
}
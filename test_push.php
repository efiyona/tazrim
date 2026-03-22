<?php
session_start();
require_once('path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/app/functions/push_functions.php');

// הגדרת ההודעה הגלובלית שאתה רוצה לשלוח
$title = "הודעת מערכת מ'התזרים' 📣";
$body = "המערכת לא נועדה למטרות רווח! 🍷";
$url = BASE_URL . "pages/manage_home.php";

echo "<h2>שליחת הודעת מערכת לכל המנויים</h2>";
echo "מתחיל תהליך שליחה...<br><br>";

// שליפת כל המשתמשים שיש להם מכשיר רשום במערכת
$query = "SELECT DISTINCT user_id FROM user_subscriptions";
$result = mysqli_query($conn, $query);

$success_count = 0;

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $target_user_id = $row['user_id'];
        
        // שימוש בפונקציה הקיימת שלנו כדי לשלוח לכל משתמש בתורו
        if (sendPushNotification($target_user_id, $title, $body, $url)) {
            echo "✅ התראה נשלחה בהצלחה למשתמש ID: $target_user_id <br>";
            $success_count++;
        } else {
            echo "❌ תקלה בשליחה למשתמש ID: $target_user_id <br>";
        }
    }
    echo "<br><b>🎉 סיום! ההתראה נשלחה ל-$success_count משתמשים שונים.</b>";
} else {
    echo "לא נמצאו משתמשים רשומים להתראות במערכת.";
}
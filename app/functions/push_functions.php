<?php
// טעינת הספרייה שקומפוזר התקין
require_once(ROOT_PATH . '/vendor/autoload.php');

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($user_id, $title, $body, $url = '/') {
    global $conn;

    // 1. שליפת כל המכשירים הרשומים של המשתמש מהמסד
    $query = "SELECT * FROM user_subscriptions WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    // 2. הגדרת אימות VAPID (המפתחות שיצרת בטרמינל)
    $auth = [
        'VAPID' => [
            'subject' => SITE_URL,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);
    
    // בניית תוכן ההודעה (Payload)
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url
    ]);

    // 3. הכנת ההודעות לכל המכשירים של המשתמש
    while ($sub = mysqli_fetch_assoc($result)) {
        $subscription = Subscription::create([
            'endpoint'  => $sub['endpoint'],
            'publicKey' => $sub['p256dh'],
            'authToken' => $sub['auth'],
        ]);

        $webPush->queueNotification($subscription, $payload);
    }

    // 4. שליחה בפועל
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            // כאן אפשר להוסיף לוג של שגיאות אם תרצה
        }
    }
    return true;
}
<?php
// טעינת הספרייה שקומפוזר התקין
require_once(ROOT_PATH . '/vendor/autoload.php');

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// ========================================================
// 1. פונקציה לשליחת התראה למשתמש ספציפי (לפי user_id)
// ========================================================
function sendPushNotification($user_id, $title, $body, $url = '/') {
    global $conn;

    // שליפת כל המכשירים הרשומים של המשתמש מהמסד
    $query = "SELECT * FROM user_subscriptions WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    // הגדרת אימות VAPID 
    $auth = [
        'VAPID' => [
            'subject' => SITE_URL,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);
    
    // בניית תוכן ההודעה
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url
    ]);

    // הכנת ההודעות לכל המכשירים של המשתמש
    while ($sub = mysqli_fetch_assoc($result)) {
        $subscription = Subscription::create([
            'endpoint'  => $sub['endpoint'],
            'publicKey' => $sub['p256dh'],
            'authToken' => $sub['auth'],
        ]);

        $webPush->queueNotification($subscription, $payload);
    }

    // שליחה בפועל
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            // לוג שגיאות במידת הצורך
        }
    }
    return true;
}

// ========================================================
// 2. פונקציה לשליחת התראה לכל בני הבית (חוץ ממי שביצע את הפעולה)
// ========================================================
function sendPushToHome($home_id, $exclude_user_id, $title, $body, $url = '/') {
    global $conn;

    // שליפת כל המכשירים של כל המשתמשים בבית, למעט המשתמש שהחריגו
    $query = "SELECT us.* FROM user_subscriptions us
              JOIN users u ON us.user_id = u.id
              WHERE u.home_id = $home_id AND u.id != 8";
    
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    $auth = [
        'VAPID' => [
            'subject' => SITE_URL,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

    while ($sub = mysqli_fetch_assoc($result)) {
        $subscription = Subscription::create([
            'endpoint'  => $sub['endpoint'],
            'publicKey' => $sub['p256dh'],
            'authToken' => $sub['auth'],
        ]);

        $webPush->queueNotification($subscription, $payload);
    }

    foreach ($webPush->flush() as $report) {

    }
    return true;
}
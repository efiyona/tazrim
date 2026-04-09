<?php
// טעינת הספרייה שקומפוזר התקין
require_once(ROOT_PATH . '/vendor/autoload.php');

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// פונקציית עזר שמונעת שגיאות במקרה שהמפתחות טרם הוגדרו בקובץ ההגדרות
function getWebPushAuth() {
    if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY')) {
        return false;
    }
    $publicKey = VAPID_PUBLIC_KEY;
    $privateKey = VAPID_PRIVATE_KEY;
    if ($publicKey === '' || $privateKey === '') {
        return false;
    }
    return [
        'VAPID' => [
            'subject' => defined('SITE_URL') ? SITE_URL : 'mailto:admin@yourdomain.com',
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ],
    ];
}

// ========================================================
// 1. פונקציה לשליחת התראה למשתמש ספציפי (לפי user_id)
// $preference: null = ללא סינון העדפה | home_transactions | budget | system
// ========================================================
function sendPushNotification($user_id, $title, $body, $url = '/', $preference = null) {
    global $conn;
    
    $auth = getWebPushAuth();
    if (!$auth) return false;

    $prefSql = '';
    if ($preference === 'home_transactions') {
        $prefSql = ' AND COALESCE(unp.notify_home_transactions, 1) = 1';
    } elseif ($preference === 'budget') {
        $prefSql = ' AND COALESCE(unp.notify_budget, 1) = 1';
    } elseif ($preference === 'system') {
        $prefSql = ' AND COALESCE(unp.notify_system, 1) = 1';
    }

    $query = "SELECT us.* FROM user_subscriptions us
              JOIN users u ON u.id = us.user_id
              LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
              WHERE us.user_id = $user_id" . $prefSql;
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    try {
        $webPush = new WebPush($auth);
        $payload = json_encode(['title' => $title, 'body'  => $body, 'url'   => $url]);

        while ($sub = mysqli_fetch_assoc($result)) {
            $subscription = Subscription::create([
                'endpoint'  => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $report) {}
        return true;
    } catch (\Exception $e) {
        // בולע את השגיאה כדי לא לשבור את המערכת, אך אפשר לרשום ללוג אם נרצה
        error_log("WebPush Error: " . $e->getMessage());
        return false;
    }
}

// ========================================================
// 2. פונקציה לשליחת התראה לכל בני הבית (חוץ ממי שביצע את הפעולה)
// ========================================================
function sendPushToHome($home_id, $exclude_user_id, $title, $body, $url = '/') {
    global $conn;
    
    $auth = getWebPushAuth();
    if (!$auth) return false;

    $query = "SELECT us.* FROM user_subscriptions us
              JOIN users u ON us.user_id = u.id
              LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
              WHERE u.home_id = $home_id AND u.id != $exclude_user_id
              AND COALESCE(unp.notify_home_transactions, 1) = 1";
    
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    try {
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

        foreach ($webPush->flush() as $report) {}
        return true;
    } catch (\Exception $e) {
        error_log("WebPush Error: " . $e->getMessage());
        return false;
    }
}

// ========================================================
// 3. פונקציה לשליחת התראה ל*כל* בני הבית (ללא החרגות)
// $preference: budget | system — לפי טבלת user_notification_preferences
// ========================================================
function sendPushToEntireHome($home_id, $title, $body, $url = '/', $preference = 'budget') {
    global $conn;
    
    $auth = getWebPushAuth();
    if (!$auth) return false;

    $prefCol = 'notify_budget';
    if ($preference === 'system') {
        $prefCol = 'notify_system';
    } elseif ($preference === 'budget') {
        $prefCol = 'notify_budget';
    }

    $query = "SELECT us.* FROM user_subscriptions us
              JOIN users u ON us.user_id = u.id
              LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
              WHERE u.home_id = $home_id AND COALESCE(unp.`$prefCol`, 1) = 1";
    
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 0) return false;

    try {
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

        foreach ($webPush->flush() as $report) {}
        return true;
    } catch (\Exception $e) {
        error_log("WebPush Error: " . $e->getMessage());
        return false;
    }
}
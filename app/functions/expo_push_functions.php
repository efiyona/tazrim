<?php
/**
 * שליחת התראות דרך שירות Expo Push (אפליקציית React Native).
 * לא תלוי ב־Composer; משתמש ב־cURL ל־https://exp.host/--/api/v2/push/send
 */

if (!function_exists('expo_push_table_ready')) {
    function expo_push_table_ready() {
        global $conn;
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $ok = false;
        try {
            $r = mysqli_query($conn, "SHOW TABLES LIKE 'user_expo_push_tokens'");
            if ($r && mysqli_num_rows($r) > 0) {
                $ok = true;
            }
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }
}

if (!function_exists('expo_push_send_messages')) {
    /**
     * @param array<int, array{to: string, title: string, body: string, data?: array}> $messages
     */
    function expo_push_send_messages(array $messages) {
        if (empty($messages)) {
            return;
        }
        $payload = json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

if (!function_exists('sendExpoPushToUser')) {
    /**
     * מקביל ל־sendPushNotification — אותן העדפות (home_transactions | budget | system | null).
     */
    function sendExpoPushToUser($user_id, $title, $body, $url = '/', $preference = null) {
        global $conn;
        if (!expo_push_table_ready()) {
            return false;
        }
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $prefSql = '';
        if ($preference === 'home_transactions') {
            $prefSql = ' AND COALESCE(unp.notify_home_transactions, 1) = 1';
        } elseif ($preference === 'budget') {
            $prefSql = ' AND COALESCE(unp.notify_budget, 1) = 1';
        } elseif ($preference === 'system') {
            $prefSql = ' AND COALESCE(unp.notify_system, 1) = 1';
        }

        $query = "SELECT e.expo_push_token FROM user_expo_push_tokens e
                  JOIN users u ON u.id = e.user_id
                  LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
                  WHERE e.user_id = $user_id" . $prefSql;
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }

        $messages = [];
        $urlStr = is_string($url) ? $url : '/';
        while ($row = mysqli_fetch_assoc($result)) {
            $tok = trim((string) ($row['expo_push_token'] ?? ''));
            if ($tok === '') {
                continue;
            }
            $messages[] = [
                'to' => $tok,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'data' => ['url' => $urlStr],
            ];
        }
        expo_push_send_messages($messages);
        return count($messages) > 0;
    }
}

if (!function_exists('sendExpoPushToHome')) {
    function sendExpoPushToHome($home_id, $exclude_user_id, $title, $body, $url = '/') {
        global $conn;
        if (!expo_push_table_ready()) {
            return false;
        }
        $home_id = (int) $home_id;
        $exclude_user_id = (int) $exclude_user_id;
        if ($home_id <= 0) {
            return false;
        }

        $query = "SELECT e.expo_push_token FROM user_expo_push_tokens e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
                  WHERE u.home_id = $home_id AND u.id != $exclude_user_id
                  AND COALESCE(unp.notify_home_transactions, 1) = 1";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }

        $messages = [];
        $urlStr = is_string($url) ? $url : '/';
        while ($row = mysqli_fetch_assoc($result)) {
            $tok = trim((string) ($row['expo_push_token'] ?? ''));
            if ($tok === '') {
                continue;
            }
            $messages[] = [
                'to' => $tok,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'data' => ['url' => $urlStr],
            ];
        }
        expo_push_send_messages($messages);
        return count($messages) > 0;
    }
}

if (!function_exists('sendExpoPushToEntireHome')) {
    function sendExpoPushToEntireHome($home_id, $title, $body, $url = '/', $preference = 'budget') {
        global $conn;
        if (!expo_push_table_ready()) {
            return false;
        }
        $home_id = (int) $home_id;
        if ($home_id <= 0) {
            return false;
        }

        $prefCol = 'notify_budget';
        if ($preference === 'system') {
            $prefCol = 'notify_system';
        } elseif ($preference === 'budget') {
            $prefCol = 'notify_budget';
        }

        $query = "SELECT e.expo_push_token FROM user_expo_push_tokens e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN user_notification_preferences unp ON unp.user_id = u.id
                  WHERE u.home_id = $home_id AND COALESCE(unp.`$prefCol`, 1) = 1";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }

        $messages = [];
        $urlStr = is_string($url) ? $url : '/';
        while ($row = mysqli_fetch_assoc($result)) {
            $tok = trim((string) ($row['expo_push_token'] ?? ''));
            if ($tok === '') {
                continue;
            }
            $messages[] = [
                'to' => $tok,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'data' => ['url' => $urlStr],
            ];
        }
        expo_push_send_messages($messages);
        return count($messages) > 0;
    }
}

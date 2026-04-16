<?php
session_start();

require("connect.php");
require_once ROOT_PATH . '/app/functions/tos_runtime.php';
tazrim_ensure_tos_terms_table();

/**
 * מבטיח שטבלת העדפות התראות משתמש קיימת.
 */
function tazrim_ensure_user_notification_table() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }
    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `notify_home_transactions` TINYINT(1) NOT NULL DEFAULT 1,
            `notify_budget` TINYINT(1) NOT NULL DEFAULT 1,
            `notify_system` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_id` (`user_id`),
            CONSTRAINT `user_notification_preferences_ibfk_1`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

tazrim_ensure_user_notification_table();

/**
 * מבטיח שטבלת אסימוני Expo Push (אפליקציה) קיימת — מקביל ל־add_user_expo_push_tokens.sql
 */
function tazrim_ensure_user_expo_push_tokens_table() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }
    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `user_expo_push_tokens` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `expo_push_token` varchar(400) NOT NULL,
            `platform` varchar(16) NOT NULL DEFAULT '',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_expo_token` (`user_id`, `expo_push_token`(191)),
            KEY `idx_uept_user` (`user_id`),
            CONSTRAINT `fk_uept_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

tazrim_ensure_user_expo_push_tokens_table();

/**
 * מבטיח שטבלת דיווחי משתמשים מהאפליקציה קיימת.
 */
function tazrim_ensure_feedback_reports_table() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }
    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `feedback_reports` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `home_id` int NOT NULL DEFAULT 0,
            `kind` enum('bug','idea') NOT NULL DEFAULT 'bug',
            `title` varchar(190) DEFAULT NULL,
            `message` text NOT NULL,
            `context_screen` varchar(120) DEFAULT NULL,
            `status` enum('new','in_review','done') NOT NULL DEFAULT 'new',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_feedback_created` (`created_at`),
            KEY `idx_feedback_kind` (`kind`),
            KEY `idx_feedback_status` (`status`),
            CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

tazrim_ensure_feedback_reports_table();

/**
 * מבטיח עמודת מטבע בטבלת הפעולות.
 */
function tazrim_ensure_transactions_currency_column() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }

    $result = @mysqli_query($conn, "SHOW COLUMNS FROM `transactions` LIKE 'currency_code'");
    if ($result && mysqli_num_rows($result) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE `transactions`
             ADD COLUMN `currency_code` VARCHAR(3) NOT NULL DEFAULT 'ILS' AFTER `amount`"
        );
    }
}

tazrim_ensure_transactions_currency_column();

/**
 * מבטיח עמודת מטבע בטבלת הפעולות הקבועות.
 */
function tazrim_ensure_recurring_currency_column() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }

    $result = @mysqli_query($conn, "SHOW COLUMNS FROM `recurring_transactions` LIKE 'currency_code'");
    if ($result && mysqli_num_rows($result) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE `recurring_transactions`
             ADD COLUMN `currency_code` VARCHAR(3) NOT NULL DEFAULT 'ILS' AFTER `amount`"
        );
    }
}

tazrim_ensure_recurring_currency_column();

/**
 * מבטיח טבלת cache לשערי מטבע.
 */
function tazrim_ensure_fx_rates_cache_table() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `fx_rates_cache` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `base_currency` varchar(3) NOT NULL,
            `quote_currency` varchar(3) NOT NULL,
            `rate` decimal(14,6) NOT NULL,
            `provider` varchar(64) NOT NULL DEFAULT 'frankfurter',
            `fetched_at` datetime NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_fx_pair` (`base_currency`, `quote_currency`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

tazrim_ensure_fx_rates_cache_table();

function dd($value)
{
    echo "<pre>", print_r($value, true), "</pre>";
    die();
}

function excuteQuery($sql, $data)
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $values = array_values($data);
    $types = str_repeat('s', count($values));
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    return $stmt;
}


function selectAll($table, $conditions = [])
{
    global $conn;
    $sql = "SELECT * FROM $table";
    if(empty ($conditions))
    {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    else
    {
        $i=0;
        foreach($conditions as $key=> $value){
            if($i === 0){
                $sql = $sql . " WHERE $key=?";
            }else{
                $sql = $sql . " AND $key=?";
            }
            $i++;
        } 
        
        $stmt = excuteQuery($sql, $conditions);
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // --- מנגנון פענוח אוטומטי ---
    if ($records) {
        // רשימת העמודות שדורשות פענוח (אפשר להוסיף עוד בעתיד עם פסיק)
        $encrypted_columns = ['initial_balance']; 
        
        foreach ($records as &$row) {
            foreach ($encrypted_columns as $col) {
                if (array_key_exists($col, $row)) {
                    $row[$col] = decryptBalance($row[$col]);
                }
            }
        }
    }
    // ----------------------------

    return $records;
}

function selectOne($table, $conditions)
{
    global $conn;
    $sql = "SELECT * FROM $table";

    $i=0;
    foreach($conditions as $key=> $value){
        if($i === 0){
            $sql = $sql . " WHERE $key=?";
        }else{
            $sql = $sql . " AND $key=?";
        }
        $i++;
    } 
    
    $sql = $sql . " LIMIT 1";
    $stmt = excuteQuery($sql, $conditions);
    $records = $stmt->get_result()->fetch_assoc();

    // --- מנגנון פענוח אוטומטי ---
    if ($records) {
        $encrypted_columns = ['initial_balance']; 
        
        foreach ($encrypted_columns as $col) {
            if (array_key_exists($col, $records)) {
                $records[$col] = decryptBalance($records[$col]);
            }
        }
    }
    // ----------------------------

    return $records;
}

function create($table, $data)
{
    global $conn;
    
    $sql = "INSERT INTO $table SET ";
    $i=0;
    foreach($data as $key=> $value){
        if($i === 0){
            $sql = $sql . " $key=?";
        }else{
            $sql = $sql . ", $key=?";
        }
        $i++;
    } 
    
    $stmt = excuteQuery($sql, $data);

    $id = $stmt->insert_id;
    return $id;
}

function update($table, $id, $data)
{
    global $conn;
    
    $sql = "UPDATE $table SET ";

    $i=0;
    foreach($data as $key=> $value){
        if($i === 0){
            $sql = $sql . " $key=?";
        }else{
            $sql = $sql . ", $key=?";
        }
        $i++;
    } 
    
    $sql = $sql . " WHERE id=?";
    $data['id'] = $id;  
    $stmt = excuteQuery($sql, $data);
    return $stmt->affected_rows;
}

function delete($table, $id)
{
    global $conn;
    
    $sql = "DELETE FROM $table WHERE id=?";

    $stmt = excuteQuery($sql, ['id' => $id]);
    return $stmt->affected_rows;
}

function addNotification($home_id, $title, $message, $type = 'info', $user_id = null) {
    $data = [
        'home_id'   => $home_id,
        'user_id'   => $user_id,
        'title'     => $title,
        'message'   => $message,
        'type'      => $type
    ];
    
    return create('notifications', $data);
}

function encryptBalance($value) {
    if ($value === null || $value === '') return null;
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptBalance($value) {
    if (empty($value)) return 0;
    if (is_numeric($value)) return (float)$value; 
    
    $key = ENCRYPTION_KEY;
    $parts = explode('::', base64_decode($value), 2);
    
    if (count($parts) === 2) {
        $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $key, 0, $parts[1]);
        if ($decrypted !== false) return (float)$decrypted;
    }
    return 0; 
}
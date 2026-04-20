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
 * טבלאות הודעות פופאפ מנוהלות (קמפיינים, יעדים, קריאות).
 */
function tazrim_ensure_popup_campaign_tables() {
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
        "CREATE TABLE IF NOT EXISTS `popup_campaigns` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `body_html` mediumtext NOT NULL,
            `target_scope` enum('all','homes','users') NOT NULL DEFAULT 'all',
            `ack_policy` enum('each_user','one_per_home','primary_only') NOT NULL DEFAULT 'each_user',
            `status` enum('draft','published') NOT NULL DEFAULT 'draft',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 0,
            `starts_at` datetime DEFAULT NULL,
            `ends_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_popup_status_active` (`status`,`is_active`),
            KEY `idx_popup_sort` (`sort_order`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `popup_campaign_homes` (
            `campaign_id` int(11) NOT NULL,
            `home_id` int(11) NOT NULL,
            PRIMARY KEY (`campaign_id`,`home_id`),
            KEY `idx_pch_home` (`home_id`),
            CONSTRAINT `fk_pch_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pch_home` FOREIGN KEY (`home_id`) REFERENCES `homes` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `popup_campaign_users` (
            `campaign_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            PRIMARY KEY (`campaign_id`,`user_id`),
            KEY `idx_pcu_user` (`user_id`),
            CONSTRAINT `fk_pcu_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pcu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `popup_reads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `campaign_id` int(11) NOT NULL,
            `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_user_campaign` (`user_id`,`campaign_id`),
            KEY `idx_pr_campaign` (`campaign_id`),
            CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $colAck = @mysqli_query($conn, "SHOW COLUMNS FROM `popup_campaigns` LIKE 'ack_policy'");
    if ($colAck && mysqli_num_rows($colAck) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE `popup_campaigns`
             ADD COLUMN `ack_policy` ENUM('each_user','one_per_home','primary_only') NOT NULL DEFAULT 'each_user' AFTER `target_scope`"
        );
    }

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `popup_home_reads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `campaign_id` int(11) NOT NULL,
            `home_id` int(11) NOT NULL,
            `read_by_user_id` int(11) DEFAULT NULL,
            `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_campaign_home` (`campaign_id`,`home_id`),
            KEY `idx_phr_home` (`home_id`),
            KEY `idx_phr_read_by` (`read_by_user_id`),
            CONSTRAINT `fk_phr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_phr_home` FOREIGN KEY (`home_id`) REFERENCES `homes` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_phr_user` FOREIGN KEY (`read_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

tazrim_ensure_popup_campaign_tables();

/**
 * מבטיח טבלאות שליחת מיילים המונית מהאדמין + יומן נמענים (מקביל ל־docs/database/migrations/20260419_admin_email_broadcasts.sql).
 */
function tazrim_ensure_admin_email_broadcast_tables() {
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
        "CREATE TABLE IF NOT EXISTS `admin_email_broadcasts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_user_id` INT UNSIGNED NOT NULL,
            `target_type` ENUM('all_users','all_homes','homes','users') NOT NULL,
            `target_json` TEXT NULL COMMENT 'JSON: home_ids או user_ids כשה-target לא כולל את כולם',
            `subject` VARCHAR(500) NOT NULL,
            `html_body` MEDIUMTEXT NOT NULL,
            `text_body` TEXT NULL,
            `status` ENUM('pending','sending','completed','failed') NOT NULL DEFAULT 'pending',
            `recipient_total` INT UNSIGNED NOT NULL DEFAULT 0,
            `sent_ok` INT UNSIGNED NOT NULL DEFAULT 0,
            `sent_fail` INT UNSIGNED NOT NULL DEFAULT 0,
            `error_summary` VARCHAR(2000) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `started_at` TIMESTAMP NULL DEFAULT NULL,
            `completed_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_aeb_created` (`created_at`),
            KEY `idx_aeb_admin` (`admin_user_id`),
            KEY `idx_aeb_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `admin_email_broadcast_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `broadcast_id` INT UNSIGNED NOT NULL,
            `recipient_email` VARCHAR(255) NOT NULL,
            `user_id` INT UNSIGNED NULL DEFAULT NULL,
            `home_id` INT UNSIGNED NULL DEFAULT NULL,
            `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            `error_message` VARCHAR(2000) NULL DEFAULT NULL,
            `detail` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_aebl_broadcast` (`broadcast_id`),
            KEY `idx_aebl_email` (`recipient_email`(191)),
            CONSTRAINT `fk_aebl_broadcast` FOREIGN KEY (`broadcast_id`) REFERENCES `admin_email_broadcasts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

tazrim_ensure_admin_email_broadcast_tables();

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
        $encrypted_columns = ['bank_balance_ledger_cached', 'bank_balance_manual_adjustment'];
        
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
        $encrypted_columns = ['bank_balance_ledger_cached', 'bank_balance_manual_adjustment'];
        
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
    if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
        $value = 0.0;
    } else {
        $value = (float) $value;
    }
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $plain = (string) $value;
    $encrypted = openssl_encrypt($plain, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decryptBalance($value) {
    if ($value === null || $value === '') {
        return 0.0;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }

    $key = ENCRYPTION_KEY;
    $raw = @base64_decode((string) $value, true);
    if ($raw === false || $raw === '') {
        return 0.0;
    }
    $parts = explode('::', $raw, 2);

    if (count($parts) === 2) {
        $decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $key, 0, $parts[1]);
        if ($decrypted !== false && is_numeric($decrypted)) {
            return (float) $decrypted;
        }
    }
    return 0.0;
}

/**
 * מיגרציית homes: שדות יתרת בנק (ledger + adjustment) והסרת initial_balance לאחר העתקה.
 */
function tazrim_ensure_homes_bank_balance_columns() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    if (!$conn) {
        return;
    }

    $r = @mysqli_query($conn, "SHOW COLUMNS FROM `homes` LIKE 'bank_balance_ledger_cached'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE `homes`
             ADD COLUMN `bank_balance_ledger_cached` VARCHAR(255) NULL DEFAULT NULL AFTER `join_code`,
             ADD COLUMN `bank_balance_manual_adjustment` VARCHAR(255) NULL DEFAULT NULL AFTER `bank_balance_ledger_cached`,
             ADD COLUMN `show_bank_balance` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bank_balance_manual_adjustment`"
        );
    }

    $rOld = @mysqli_query($conn, "SHOW COLUMNS FROM `homes` LIKE 'initial_balance'");
    if ($rOld && mysqli_num_rows($rOld) > 0) {
        $result = tazrim_run_homes_bank_balance_data_migration($conn);
        if (empty($result['ok'])) {
            error_log('tazrim homes bank migration: ' . ($result['message'] ?? 'unknown'));
            return;
        }
    }

    $done = true;
}

require_once ROOT_PATH . '/app/functions/home_bank_balance.php';
tazrim_ensure_homes_bank_balance_columns();
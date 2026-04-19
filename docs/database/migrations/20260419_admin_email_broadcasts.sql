-- שליחת מיילים המונית מפאנל הניהול + יומן נמענים
-- הרצה ידנית: mysql ... < 20260419_admin_email_broadcasts.sql

CREATE TABLE IF NOT EXISTS `admin_email_broadcasts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_email_broadcast_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

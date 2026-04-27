-- סידור עבודה: דגל ב-users + טבלאות עבודות/סוגי משמרות/משמרות
SELECT COUNT(*) INTO @c FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'work_schedule_enabled';
SET @q = IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `work_schedule_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`',
  'SELECT 1');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `user_work_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#5B8DEF',
  `payday_day_of_month` tinyint unsigned NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_work_jobs_user` (`user_id`),
  CONSTRAINT `fk_user_work_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_work_shift_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `icon_preset` varchar(24) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_work_shift_types_job` (`job_id`),
  CONSTRAINT `fk_user_work_st_job` FOREIGN KEY (`job_id`) REFERENCES `user_work_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_work_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `shift_type_id` int(11) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_work_shifts_user_start` (`user_id`, `starts_at`),
  KEY `idx_user_work_shifts_job` (`job_id`),
  KEY `idx_user_work_shifts_type` (`shift_type_id`),
  CONSTRAINT `fk_user_work_shifts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_work_shifts_job` FOREIGN KEY (`job_id`) REFERENCES `user_work_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_work_shifts_st` FOREIGN KEY (`shift_type_id`) REFERENCES `user_work_shift_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

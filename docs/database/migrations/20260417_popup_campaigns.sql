-- הודעות פופאפ מנוהלות (מנהל מערכת): קמפיינים, יעדים, קריאות פר-משתמש

CREATE TABLE IF NOT EXISTS `popup_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `body_html` mediumtext NOT NULL,
  `target_scope` enum('all','homes','users') NOT NULL DEFAULT 'all',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `popup_campaign_homes` (
  `campaign_id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  PRIMARY KEY (`campaign_id`,`home_id`),
  KEY `idx_pch_home` (`home_id`),
  CONSTRAINT `fk_pch_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pch_home` FOREIGN KEY (`home_id`) REFERENCES `homes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `popup_campaign_users` (
  `campaign_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`campaign_id`,`user_id`),
  KEY `idx_pcu_user` (`user_id`),
  CONSTRAINT `fk_pcu_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `popup_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_campaign` (`user_id`,`campaign_id`),
  KEY `idx_pr_campaign` (`campaign_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

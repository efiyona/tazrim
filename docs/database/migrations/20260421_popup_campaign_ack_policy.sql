-- מדיניות אישור קריאה לפופאפ: פר משתמש / בית אחד / רק אב בית

ALTER TABLE `popup_campaigns`
  ADD COLUMN `ack_policy` ENUM('each_user','one_per_home','primary_only') NOT NULL DEFAULT 'each_user'
  AFTER `target_scope`;

CREATE TABLE IF NOT EXISTS `popup_home_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `home_id` int(11) NOT NULL,
  `read_by_user_id` int(11) DEFAULT NULL COMMENT 'מי אישר ראשון מבית זה',
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_campaign_home` (`campaign_id`,`home_id`),
  KEY `idx_phr_home` (`home_id`),
  KEY `idx_phr_read_by` (`read_by_user_id`),
  CONSTRAINT `fk_phr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_phr_home` FOREIGN KEY (`home_id`) REFERENCES `homes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_phr_user` FOREIGN KEY (`read_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

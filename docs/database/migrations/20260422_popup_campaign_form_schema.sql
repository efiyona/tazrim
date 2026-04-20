-- סכמת טופס גנרית לקמפיין פופאפ + טבלת הגשות

ALTER TABLE `popup_campaigns`
  ADD COLUMN `form_schema` MEDIUMTEXT NULL COMMENT 'JSON: handler + fields לאימות ושמירה'
  AFTER `ack_policy`;

CREATE TABLE IF NOT EXISTS `popup_campaign_form_submissions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `home_id` int(11) DEFAULT NULL,
  `payload_json` MEDIUMTEXT NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pcfs_campaign` (`campaign_id`,`created_at`),
  KEY `idx_pcfs_user` (`user_id`),
  KEY `idx_pcfs_home` (`home_id`),
  CONSTRAINT `fk_pcfs_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `popup_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcfs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcfs_home` FOREIGN KEY (`home_id`) REFERENCES `homes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

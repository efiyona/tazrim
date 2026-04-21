-- AI user agent: preferences, HITL telemetry, optional RAG chunks (idempotent)

CREATE TABLE IF NOT EXISTS `ai_user_preferences` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `pref_key` varchar(120) NOT NULL,
  `pref_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ai_user_pref` (`user_id`, `pref_key`),
  KEY `idx_ai_user_pref_user` (`user_id`),
  CONSTRAINT `fk_ai_user_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_user_hitl_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `home_id` int NOT NULL DEFAULT 0,
  `chat_id` int unsigned NOT NULL DEFAULT 0,
  `proposal_type` varchar(80) NOT NULL DEFAULT '',
  `outcome` enum('ACCEPTED','REJECTED') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hitl_user_time` (`user_id`, `created_at`),
  KEY `idx_hitl_outcome` (`outcome`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_help_chunks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `section_key` varchar(160) NOT NULL,
  `body_text` mediumtext NOT NULL,
  `embedding_json` longtext DEFAULT NULL,
  `doc_version` varchar(32) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_help_section` (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

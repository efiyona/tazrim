-- אסימוני Expo Push למכשירי האפליקציה (iOS / Android), נפרדים מ־Web Push ב־user_subscriptions
-- הטבלה נוצרת אוטומטית בטעינת app/database/db.php (tazrim_ensure_user_expo_push_tokens_table).
-- קובץ זה לתיעוד / התקנה ידנית אם צריך.
CREATE TABLE IF NOT EXISTS `user_expo_push_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

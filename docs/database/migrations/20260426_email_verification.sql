-- אימות מייל: חותמת ב-users + קודים חד-פעמיים (לא משותף עם password_resets)
-- אופציונלי: לבעלי מסד מלא מראש — להריץ גם tazrim_ensure (ב-db.php) יוצר אם חסר.

ALTER TABLE `users` ADD COLUMN `email_verified_at` datetime DEFAULT NULL
  COMMENT 'NULL = מייל לא אומת' AFTER `api_token`;

CREATE TABLE IF NOT EXISTS `email_verification_codes` (
  `user_id` int(11) NOT NULL,
  `code` char(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_evc_expires` (`expires_at`),
  CONSTRAINT `fk_evc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- אופציונלי: לתת "גראנדפאדר" לחשבונות היסטוריים (כולם ייחשבו מאומתים בלי אימייל)
-- UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL;

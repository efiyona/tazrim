-- העדפות התראות Push ברמת משתמש בטבלה ייעודית.
CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

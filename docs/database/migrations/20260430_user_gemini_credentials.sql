-- =============================================================================
-- מפתחות Gemini אישיים (ריבוי שורות + רוטציה)
-- -----------------------------------------------------------------------------
-- הרצה באתר חי / phpMyAdmin:
--   1) הפעלה מותנית באמופחת: אימות ידני לפני/אחרי (גיבוי DB מומלץ).
--   2) אם טבלה `user_gemini_credentials` לא קיימת → הרץ בלוק §א׳ בלבד.
--   3) אם קיימת בפורמט ישן (מפתח ראשי רק על user_id ללא עמודת id) → הרץ §ב׳.
--   4) אם כבר יש עמודת id — אין מה להריץ (הטבלה עדכנית).
-- =============================================================================

-- §א׳ — יצירה ראשונית כשאין טבלה (אם אחרי הרצת §ב׳ — דלג)
-- הפעלה: הצע מהשאלה: SHOW TABLES LIKE 'user_gemini_credentials';
-- רק כשלא מוחזרת שורה:
CREATE TABLE IF NOT EXISTS `user_gemini_credentials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `api_key_cipher` text NOT NULL,
  `key_suffix` char(4) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ugk_user_ord` (`user_id`,`sort_order`,`id`),
  CONSTRAINT `fk_ugc_user_gem` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- §ב׳ — שדרוג מפורמט ישן: PRIMARY KEY(`user_id`) בלבד, בלי עמודת `id`
-- לפני הרצה הרץ ובדוק אם העמודה קיימת:
--   SELECT COUNT(*) FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_gemini_credentials'
--     AND COLUMN_NAME = 'id';
-- אם 0 וגם טבלת המקור קיימת — הרץ את הבלוק הבא בשלימות:

SET NAMES utf8mb4;
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;

DROP TABLE IF EXISTS `user_gemini_creds_mig_live`;

CREATE TABLE `user_gemini_creds_mig_live` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `api_key_cipher` text NOT NULL,
  `key_suffix` char(4) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ugk_user_ord` (`user_id`,`sort_order`,`id`),
  CONSTRAINT `fk_ugc_user_gem` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_gemini_creds_mig_live` (`user_id`, `sort_order`, `api_key_cipher`, `key_suffix`)
SELECT `user_id`, 0, `api_key_cipher`, `key_suffix` FROM `user_gemini_credentials`;

DROP TABLE `user_gemini_credentials`;

RENAME TABLE `user_gemini_creds_mig_live` TO `user_gemini_credentials`;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;

-- =============================================================================
-- הערות:
-- • אחרי הרצת §ב׳ העמודות created_at/updated_at לשורות ישנות יקבלו ערך ברירת מחדל;
-- • אין שינוי בשדות api_key_cipher / key_suffix — העתקה כמות שהיא מהטבלה הישנה.

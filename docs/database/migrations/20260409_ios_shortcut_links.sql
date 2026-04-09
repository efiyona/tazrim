-- קישורים לקיצורי דרך (מוצגים בעמוד החשבון; הקיצורים רצים באייפון/אייפד)
-- ניהול: הוספה/עריכה ישירות בטבלה או דרך כלי ניהול עתידי

CREATE TABLE IF NOT EXISTS `ios_shortcut_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

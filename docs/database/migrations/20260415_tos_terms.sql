-- נוסחי תקנון (ניהול בפאל האדמין). אם הטבלה כבר נוצרה אוטומטית ע"י האפליקציה — אין חובה להריץ.

CREATE TABLE IF NOT EXISTS `tos_terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `last_updated_label` varchar(120) NOT NULL DEFAULT '',
  `content_html` mediumtext NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- אם הותקן הסכימה הישנה (עם visitor_id / user_id), זה מוחק ויוצר מחדש את הטבלה במבנה המינימלי.
-- אזהרה: אובדן נתוני הטבלה הישנה.

DROP TABLE IF EXISTS `landing_page_events`;

CREATE TABLE `landing_page_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `page_path` varchar(512) NOT NULL,
  `referer` varchar(1024) NOT NULL DEFAULT '',
  `user_agent` varchar(512) NOT NULL DEFAULT '',
  `query_string` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_lpe_created` (`created_at`),
  KEY `idx_lpe_path_created` (`page_path`(191),`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

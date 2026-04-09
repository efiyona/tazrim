-- בוליאני עם ברירת מחדל "כן" (1) לטבלת ai_api_logs
SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_api_logs' AND COLUMN_NAME = 'סטטוס';
SET @q = IF(@col = 0,
  'ALTER TABLE `ai_api_logs` ADD COLUMN `סטטוס` tinyint(1) NOT NULL DEFAULT 1 AFTER `action_type`',
  'SELECT 1 AS `status_column_already_exists`');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

-- הסרת עמודת סטטוס מ-ai_api_logs (אידמפוטנטי)
SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_api_logs' AND COLUMN_NAME = 'סטטוס';
SET @q = IF(@col > 0,
  'ALTER TABLE `ai_api_logs` DROP COLUMN `סטטוס`',
  'SELECT 1 AS `status_column_already_absent`');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

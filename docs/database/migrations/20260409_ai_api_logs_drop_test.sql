-- הסרת עמודת test מ-ai_api_logs (אידמפוטנטי)
SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_api_logs' AND COLUMN_NAME = 'test';
SET @q = IF(@col > 0,
  'ALTER TABLE `ai_api_logs` DROP COLUMN `test`',
  'SELECT 1 AS `test_column_already_absent`');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Add phone to users if missing (idempotent)
SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone';
SET @q = IF(@col = 0,
  'ALTER TABLE `users` ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `password`',
  'SELECT 1 AS `phone_column_already_exists`');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

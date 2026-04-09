-- Drop google_sub from users (Google Sign-In removed; idempotent)
SELECT COUNT(*) INTO @col FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_sub';
SET @q = IF(@col > 0,
  'ALTER TABLE `users` DROP COLUMN `google_sub`',
  'SELECT 1 AS `google_sub_column_already_removed`');
PREPARE s FROM @q;
EXECUTE s;
DEALLOCATE PREPARE s;

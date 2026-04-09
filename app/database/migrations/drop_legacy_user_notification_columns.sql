-- ניקוי עמודות legacy של העדפות Push מטבלת users
-- מומלץ להריץ רק אחרי שאומת שהמערכת עובדת עם user_notification_preferences.
--
-- גיבוי מומלץ לפני מחיקה:
-- CREATE TABLE users_backup_before_drop_notif_cols AS SELECT * FROM users;
--
-- אם אתה רוצה לוודא שהעמודות קיימות לפני המחיקה:
-- SHOW COLUMNS FROM users LIKE 'notify_home_transactions';
-- SHOW COLUMNS FROM users LIKE 'notify_budget';
-- SHOW COLUMNS FROM users LIKE 'notify_system';

ALTER TABLE `users` DROP COLUMN `notify_home_transactions`;
ALTER TABLE `users` DROP COLUMN `notify_budget`;
ALTER TABLE `users` DROP COLUMN `notify_system`;


-- הוספת שדה "בדיקה" לטבלת משתמשים
ALTER TABLE `users` ADD COLUMN `בדיקה` VARCHAR(255) DEFAULT NULL AFTER `remember_token`;

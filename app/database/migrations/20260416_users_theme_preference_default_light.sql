ALTER TABLE `users`
MODIFY COLUMN `theme_preference` ENUM('light','dark','system') NOT NULL DEFAULT 'light';

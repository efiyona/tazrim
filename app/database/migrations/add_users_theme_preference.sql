ALTER TABLE `users`
ADD COLUMN `theme_preference` ENUM('light','dark','system') NOT NULL DEFAULT 'light'
AFTER `api_token`;

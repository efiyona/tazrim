# Admin AI Chat — התקנה

## 1. טבלאות מסד נתונים

```sql
CREATE TABLE IF NOT EXISTS `admin_ai_chats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'שיחה חדשה',
  `scope_snapshot` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_updated` (`user_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_ai_chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT UNSIGNED NOT NULL,
  `role` ENUM('user','assistant') NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `model` VARCHAR(60) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  CONSTRAINT `fk_admin_ai_msg_chat` FOREIGN KEY (`chat_id`) REFERENCES `admin_ai_chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. שילוב בפאנל ניהול

בקובץ layout הרלוונטי של האדמין (למשל `layout_shell_end.php`):

```php
<?php
require_once ROOT_PATH . '/admin/features/ai_chat/bootstrap.php';
admin_ai_chat_render_launcher_button();
admin_ai_chat_render_modal();
admin_ai_chat_render_assets();
```

או בטעינה עצלה:

```php
<?php
require_once ROOT_PATH . '/admin/features/ai_chat/bootstrap.php';
admin_ai_chat_render_launcher_button();
admin_ai_chat_render_lazy_loader();
```

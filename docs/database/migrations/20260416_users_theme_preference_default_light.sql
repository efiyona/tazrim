-- ברירת מחדל למצב תצוגה: בהיר (במקום מערכת)
-- הרץ אם העמודה theme_preference כבר קיימת עם DEFAULT 'system'

ALTER TABLE `users`
MODIFY COLUMN `theme_preference` ENUM('light','dark','system') NOT NULL DEFAULT 'light';

-- אופציונלי: משתמשים שעדיין שמורים כ־system (כמו ברירת המחדל הישנה) יעברו לבהיר.
-- אם תרצה להשאיר למי שבחר במפורש "אוטומטי" — אל תריץ את השורה הבאה.
-- UPDATE `users` SET `theme_preference` = 'light' WHERE `theme_preference` = 'system';

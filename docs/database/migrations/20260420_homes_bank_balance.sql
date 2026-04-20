-- =============================================================================
-- יתרת בנק: ledger + adjustment + הצגה (homes)
-- =============================================================================
-- המלצה לייצור: לפרוס קוד מעודכן, ואז לפתוח דף אחד באתר (או אדמין) —
--   `app/database/db.php` קורא ל־`tazrim_ensure_homes_bank_balance_columns()`:
--   מוסיף עמודות אם חסרות, ממיר נתונים מ־initial_balance (הצפנה ב־PHP),
--   ואז מריץ DROP ל־initial_balance.
--
-- אם אתה חייב להריץ DDL ידנית ב־MySQL לפני/מקביל לפריסה — השתמש בבלוק למטה.
-- אל תריץ DROP ל־initial_balance לפני שהקוד החדש רץ והמיגרציה ב־PHP הסתיימה.
-- =============================================================================

-- בדיקה (אופציונלי):
-- SHOW COLUMNS FROM `homes` LIKE 'bank_balance_ledger_cached';
-- SHOW COLUMNS FROM `homes` LIKE 'initial_balance';

-- שלב 1 — הוספת עמודות (הרץ פעם אחת; אם העמודות כבר קיימות — תקבל שגיאת duplicate column)
ALTER TABLE `homes`
  ADD COLUMN `bank_balance_ledger_cached` VARCHAR(255) NULL DEFAULT NULL AFTER `join_code`,
  ADD COLUMN `bank_balance_manual_adjustment` VARCHAR(255) NULL DEFAULT NULL AFTER `bank_balance_ledger_cached`,
  ADD COLUMN `show_bank_balance` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bank_balance_manual_adjustment`;

-- שלב 2 — מילוי והעברה מ־initial_balance + הצפנה: רק דרך PHP (אין המרה בטוחה ב-SQL טהור).
-- אחרי שהאתר עם הקוד החדש עלה — אפשר לאמת שאין עוד initial_balance:
-- SHOW COLUMNS FROM `homes` LIKE 'initial_balance';

-- שלב 3 — רק אם עדיין קיימת עמודת initial_balance אחרי שהקוד כבר רץ (לא אמור):
-- ALTER TABLE `homes` DROP COLUMN `initial_balance`;

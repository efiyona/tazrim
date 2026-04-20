# מדריך פאנל ניהול "התזרים" — ידע מוצר למנהל מערכת

- **version:** 1.0.0
- **last_updated:** 2026-04-17
- **שפה ממשק:** עברית (RTL)

מסמך זה מתאר את פאנל הניהול (`/admin/`) של מערכת "התזרים". כשמפנים מנהל לעמוד, להשתמש בפורמט: `[[PAGE:/נתיב/מלא|טקסט קצר לכפתור]]`.

---

## 1. גישה והרשאות

- פאנל הניהול נגיש רק למשתמשים עם תפקיד **program_admin**.
- כניסה רגילה דרך `/pages/login.php`; הפניה אוטומטית אם אין הרשאה.
- כל פעולת AJAX בפאנל מוגנת ב-CSRF token.

---

## 2. ניווט בפאנל

### 2.1 תפריט צד (Sidebar)

- **לוח בקרה** → `/admin/dashboard.php`
- **הודעות למשתמשים:**
  - שידור פוש → `/admin/push_broadcast.php`
  - פופאפים למשתמשים → `/admin/popup_campaigns.php`
- **טבלאות CRUD:**
  - הסבר מערכת (info_messages) → `/admin/table.php?t=info_messages`
  - קיצורי דרך iOS → `/admin/table.php?t=ios_shortcut_links`
  - בתים → `/admin/table.php?t=homes`
  - משתמשים → `/admin/table.php?t=users`
  - דיווחי באג/פיצר → `/admin/table.php?t=feedback_reports`
- **תקנון והסכמות:**
  - הסכמות תקנון → `/admin/table.php?t=tos_agreements` (צפייה בלבד)
  - נוסחי תקנון → `/admin/table.php?t=tos_terms`

### 2.2 סרגל עליון

- חיפוש (בהקשר טבלה ספציפית אם `?t=` קיים)
- תפריט פרופיל

---

## 3. לוח בקרה — `/admin/dashboard.php`

- **KPI**: סה"כ משתמשים, סה"כ בתים, דיווחים ממתינים (new/in_review), סה"כ דיווחים.
- **דיווחים ממתינים** — רשימה עם קישורים ישירים לערוכה.
- **משתמשים אחרונים** — 5 האחרונים שנרשמו.
- **בתים אחרונים** — 5 האחרונים שנוצרו.
- **קיצורי דרך** — גריד לכל טבלה בפאנל.

---

## 4. מערכת CRUD גנרית — `/admin/table.php?t=<key>`

מנוהלת דרך registry (`admin/config/registry.php`). כל טבלה מוגדרת עם:
- `list_columns` — עמודות לתצוגה ברשימה
- `fields` — שדות לטופס יצירה/עריכה
- `per_page`, `order_by` — מיון ופגינציה
- `allow_delete` — האם ניתן למחוק רשומות
- `list_only` — אם true, רק צפייה (ללא יצירה/עריכה/מחיקה)

### 4.1 טבלאות מנוהלות

| מפתח | טבלת DB | תיאור |
|-------|---------|--------|
| `info_messages` | `info_messages` | הודעות הסבר במערכת (msg_key, title, content) |
| `ios_shortcut_links` | `ios_shortcut_links` | קיצורי דרך iOS (title, url, sort_order, is_active) |
| `homes` | `homes` | בתים משפחתיים (name, primary_user_id FK→users, join_code, bank_balance_ledger_cached / bank_balance_manual_adjustment מוצפנים, show_bank_balance) |
| `users` | `users` | משתמשים (home_id FK→homes, first/last name, nickname, email, phone, role, password) |
| `tos_agreements` | `tos_agreements` | הסכמות תקנון — צפייה בלבד (user_id, tos_version, accepted_at, ip_address) |
| `tos_terms` | `tos_terms` | נוסחי תקנון (version, last_updated_label, content_html, is_current). כשמסמנים is_current — מבוטלות שאר הגרסאות. לא ניתן למחוק גרסה נוכחית. |
| `feedback_reports` | `feedback_reports` | דיווחי באג/רעיון (user_id, home_id, kind [bug/idea], title, message, context_screen, status [new/in_review/done]) |

### 4.2 סוגי שדות בטופס

- `text`, `textarea`, `number` — שדות בסיסיים
- `checkbox` — מוצג כבחירה כן/לא
- `enum` — תפריט בחירה מרשימת אפשרויות קבועות
- `password_new` — סיסמה (נשמרת מוצפנת; לא מוצגת בעריכה)
- `balance` — סכום כספי שמוצפן בשמירה ומפוענח בתצוגה
- `datetime` — תאריך ושעה
- `fk_lookup` — חיפוש FK לטבלה אחרת עם autocomplete (AJAX ב-`/admin/ajax/lookup.php`)

### 4.3 AJAX endpoints של CRUD

| Endpoint | פעולה |
|----------|-------|
| `ajax/list.php` | רשימה + חיפוש + פגינציה |
| `ajax/save.php` | יצירה/עדכון |
| `ajax/delete.php` | מחיקת רשומה בודדת |
| `ajax/delete_bulk.php` | מחיקה מרובה (עד 200) |
| `ajax/lookup.php` | חיפוש FK autocomplete |
| `ajax/crud/form.php` | HTML של טופס שדות למודאל |

---

## 5. שידור פוש — `/admin/push_broadcast.php`

- **יעד**: כל הבתים או בתים נבחרים (חיפוש AJAX דרך `ajax/homes_list.php`)
- **ערוצים**: push מובייל/ווב, פעמון אפליקציה, או שניהם
- **תוכן**: כותרת + גוף הודעה
- **קישור אופציונלי**: מתוך רשימה מוגדרת (`tazrim_admin_push_link_options`) או נתיב מותאם
- **עזרת AI**: כפתור לייצור כותרת+גוף אוטומטית עם Gemini (דרך `ajax/push_broadcast_ai_generate.php`)

### קישורים מוכנים (Push link presets)

```
/ → דף הבית
/pages/reports.php → דוחות ותובנות
/pages/shopping.php → רשימת קניות
/pages/settings/manage_home.php → ניהול בית
/pages/settings/user_profile.php → הפרופיל שלי
/pages/accept_tos.php → תקנון והסכמות
/pages/welcome.php → ברוכים הבאים
```

---

## 6. פופאפים למשתמשים

### 6.1 רשימת קמפיינים — `/admin/popup_campaigns.php`

- רשימת כל הקמפיינים עם סטטוס, תאריכים, יעד
- פעולות: עריכה, שכפול, מחיקה

### 6.2 עריכת קמפיין — `/admin/popup_campaign_edit.php`

- **שדות**: כותרת, תוכן HTML (`body_html`), סטטוס (draft/published), is_active, sort_order
- **יעד (target_scope)**: כולם / בתים ספציפיים / משתמשים ספציפיים
- **תזמון**: `starts_at`, `ends_at` (אופציונלי)
- **תצוגה מקדימה**: שימוש באותם סגנונות של האפליקציה
- **AI**: כפתור לייצור כותרת + HTML מעוצב עם Gemini, כולל אפשרות לקישור CTA
- **מי קרא**: רשימת משתמשים שאישרו את הפופאפ (דרך `ajax/popup_campaign_reads.php`)

### 6.3 טבלאות DB קשורות

- `popup_campaigns` — הקמפיין עצמו
- `popup_campaign_homes` — junction: קמפיין ↔ בתים
- `popup_campaign_users` — junction: קמפיין ↔ משתמשים
- `popup_reads` — מי אישר/קרא

---

## 7. טבלאות DB עיקריות (כלל המערכת)

| טבלה | תיאור |
|-------|--------|
| `users` | משתמשים: id, email, password, first_name, last_name, nickname, phone, role, home_id, remember_token |
| `homes` | בתים: id, name, join_code, primary_user_id, bank_balance_ledger_cached / bank_balance_manual_adjustment (מוצפנים), show_bank_balance |
| `transactions` | פעולות: id, home_id, user_id, type (income/expense), amount, description, category, transaction_date |
| `categories` | קטגוריות: id, home_id, name, type (income/expense), budget_limit, is_active, sort_order |
| `recurring_transactions` | פעולות קבועות: תבנית חודשית, יום בחודש, סכום, קטגוריה |
| `shopping_categories` | חנויות/טאבים ברשימת קניות |
| `shopping_items` | פריטים ברשימת קניות |
| `notifications` | התראות: user_id, home_id, title, message, is_read, created_at |
| `push_subscriptions` | מנויי push: user_id, endpoint, keys |
| `user_notification_preferences` | העדפות התראות per user |
| `info_messages` | הודעות הסבר (msg_key → תוכן) |
| `ios_shortcut_links` | קיצורי דרך iOS |
| `feedback_reports` | דיווחי באג/רעיון |
| `tos_terms` | גרסאות תקנון |
| `tos_agreements` | הסכמות משתמשים לתקנון |
| `popup_campaigns` | קמפייני פופאפ |
| `popup_campaign_homes` | יעדי בתים לפופאפ |
| `popup_campaign_users` | יעדי משתמשים לפופאפ |
| `popup_reads` | אישורי קריאת פופאפ |
| `ai_api_logs` | לוגים של קריאות AI |
| `ai_chats` | שיחות צ'אט AI (משתמשים) |
| `ai_chat_messages` | הודעות צ'אט AI (משתמשים) |
| `admin_ai_chats` | שיחות צ'אט AI (מנהלים) |
| `admin_ai_chat_messages` | הודעות צ'אט AI (מנהלים) |

---

## 8. תפקידי משתמשים

| תפקיד | הרשאות |
|--------|--------|
| `user` | משתמש רגיל |
| `home_admin` | מנהל בית |
| `admin` | מנהל |
| `program_admin` | מנהל מערכת — גישה מלאה לפאנל ניהול |

---

## 9. פונקציות עזר חשובות (helpers)

- `tazrim_admin_registry()` — מחזיר את כל הגדרות הטבלאות מ-registry
- `tazrim_admin_sidebar_metrics()` — KPI: users_total, homes_total, pending_reports
- `tazrim_admin_push_link_options()` — רשימת קישורים מוכנים לפוש
- `tazrim_admin_fk_lookup_resolve_label()` — תווית FK לפי מזהה
- `tazrim_admin_apply_encrypt_for_save()` — הצפנת שדות balance לפני שמירה
- `tazrim_admin_delete_row_allowed()` — בדיקה האם מותר למחוק רשומה
- `tazrim_admin_tos_terms_after_save()` — לוגיקה אחרי שמירת תקנון (ביטול is_current אצל אחרים)

---

## 10. נתיבים מהירים

```
/admin/dashboard.php
/admin/table.php?t=info_messages
/admin/table.php?t=ios_shortcut_links
/admin/table.php?t=homes
/admin/table.php?t=users
/admin/table.php?t=feedback_reports
/admin/table.php?t=tos_agreements
/admin/table.php?t=tos_terms
/admin/push_broadcast.php
/admin/popup_campaigns.php
/admin/popup_campaign_edit.php
```

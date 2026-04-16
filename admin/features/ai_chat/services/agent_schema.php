<?php
declare(strict_types=1);

/**
 * סכמת טבלאות עבור סוכן ה-AI של פאנל הניהול.
 * מגדיר אילו טבלאות ושדות מותרים לקריאה/כתיבה, מה מוצפן, ומה אסור לגעת בו.
 */

if (!function_exists('admin_ai_agent_global_blocked_fields')) {
    /**
     * שדות שאסור לגעת בהם בכל טבלה (לכתיבה ולקריאה).
     */
    function admin_ai_agent_global_blocked_fields(): array
    {
        return ['password', 'remember_token', 'api_token'];
    }
}

if (!function_exists('admin_ai_agent_encrypt_map')) {
    /**
     * טבלה => עמודות שדורשות encryptBalance לפני שמירה/עדכון.
     */
    function admin_ai_agent_encrypt_map(): array
    {
        return [
            'homes' => ['initial_balance'],
        ];
    }
}

if (!function_exists('admin_ai_agent_table_registry')) {
    /**
     * מפת הרשאות לכל טבלה:
     *   read  => boolean (האם מותרת קריאה)
     *   write => boolean (האם מותרת כתיבה: create/update/delete)
     *   description => תיאור קצר בעברית
     *   fields => מערך fieldName => ['type' => ..., 'desc' => ..., 'enum' => [], 'readonly' => bool]
     *   dangerous => boolean (האם פעולות delete/update דורשות אישור מחמיר יותר)
     */
    function admin_ai_agent_table_registry(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [
            'users' => [
                'read' => true,
                'write' => true,
                'dangerous' => true,
                'description' => 'משתמשי המערכת',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'מזהה הבית שאליו שייך המשתמש (FK → homes.id)'],
                    'first_name' => ['type' => 'string', 'desc' => 'שם פרטי'],
                    'last_name' => ['type' => 'string', 'desc' => 'שם משפחה'],
                    'nickname' => ['type' => 'string', 'desc' => 'כינוי'],
                    'email' => ['type' => 'string', 'desc' => 'כתובת מייל (ייחודי)'],
                    'phone' => ['type' => 'string', 'desc' => 'טלפון'],
                    'role' => ['type' => 'enum', 'desc' => 'תפקיד', 'enum' => ['user', 'home_admin', 'admin', 'program_admin']],
                    'theme_preference' => ['type' => 'enum', 'desc' => 'ערכת נושא', 'enum' => ['light', 'dark', 'system']],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'homes' => [
                'read' => true,
                'write' => true,
                'dangerous' => true,
                'description' => 'בתים משפחתיים',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'name' => ['type' => 'string', 'desc' => 'שם הבית'],
                    'primary_user_id' => ['type' => 'int', 'desc' => 'משתמש ראשי (FK → users.id)'],
                    'join_code' => ['type' => 'string', 'desc' => 'קוד הצטרפות (4 תווים, ייחודי)'],
                    'initial_balance' => ['type' => 'balance', 'desc' => 'יתרה התחלתית (מוצפן אוטומטית)'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'transactions' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'פעולות פיננסיות (הכנסות/הוצאות)',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית (FK → homes.id)'],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש יוצר (FK → users.id)'],
                    'amount' => ['type' => 'decimal', 'desc' => 'סכום'],
                    'currency_code' => ['type' => 'string', 'desc' => 'קוד מטבע (ILS וכד\')'],
                    'type' => ['type' => 'enum', 'desc' => 'סוג', 'enum' => ['income', 'expense']],
                    'category' => ['type' => 'string', 'desc' => 'קטגוריה'],
                    'description' => ['type' => 'string', 'desc' => 'תיאור'],
                    'transaction_date' => ['type' => 'date', 'desc' => 'תאריך הפעולה (YYYY-MM-DD)'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'categories' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'קטגוריות הכנסה/הוצאה (per home)',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית (FK → homes.id)'],
                    'name' => ['type' => 'string', 'desc' => 'שם הקטגוריה'],
                    'type' => ['type' => 'enum', 'desc' => 'סוג', 'enum' => ['income', 'expense']],
                    'icon' => ['type' => 'string', 'desc' => 'מחלקת אייקון FontAwesome (fa-...)'],
                    'budget_limit' => ['type' => 'decimal', 'desc' => 'תקציב חודשי (ל-expense)'],
                    'is_active' => ['type' => 'bool', 'desc' => 'פעילה (1/0)'],
                ],
            ],
            'recurring_transactions' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'פעולות קבועות (חודשיות)',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית'],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש'],
                    'type' => ['type' => 'enum', 'desc' => 'סוג', 'enum' => ['income', 'expense']],
                    'amount' => ['type' => 'decimal', 'desc' => 'סכום'],
                    'currency_code' => ['type' => 'string', 'desc' => 'קוד מטבע'],
                    'category' => ['type' => 'int', 'desc' => 'מזהה קטגוריה (FK → categories.id)'],
                    'description' => ['type' => 'string', 'desc' => 'תיאור'],
                    'day_of_month' => ['type' => 'int', 'desc' => 'יום בחודש (1-31)'],
                    'last_injected_month' => ['type' => 'date', 'desc' => 'חודש אחרון שהוזרק'],
                    'is_active' => ['type' => 'bool', 'desc' => 'פעילה'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'feedback_reports' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'דיווחי באג/רעיונות ממשתמשים',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש מדווח'],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית'],
                    'kind' => ['type' => 'enum', 'desc' => 'סוג', 'enum' => ['bug', 'idea']],
                    'title' => ['type' => 'string', 'desc' => 'כותרת'],
                    'message' => ['type' => 'text', 'desc' => 'תיאור הדיווח'],
                    'context_screen' => ['type' => 'string', 'desc' => 'מסך/הקשר'],
                    'status' => ['type' => 'enum', 'desc' => 'סטטוס', 'enum' => ['new', 'in_review', 'done']],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'tos_terms' => [
                'read' => true,
                'write' => true,
                'dangerous' => true,
                'description' => 'נוסחי תקנון',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'version' => ['type' => 'string', 'desc' => 'גרסה (ייחודי, למשל 3.0)'],
                    'last_updated_label' => ['type' => 'string', 'desc' => 'תווית תאריך/תקופה'],
                    'content_html' => ['type' => 'text', 'desc' => 'תוכן התקנון (HTML)'],
                    'is_current' => ['type' => 'bool', 'desc' => 'גרסה נוכחית (רק אחת)'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'tos_agreements' => [
                'read' => true,
                'write' => false,
                'dangerous' => false,
                'description' => 'הסכמות תקנון של משתמשים (צפייה בלבד)',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש', 'readonly' => true],
                    'tos_version' => ['type' => 'string', 'desc' => 'גרסת תקנון', 'readonly' => true],
                    'accepted_at' => ['type' => 'timestamp', 'desc' => 'תאריך הסכמה', 'readonly' => true],
                    'ip_address' => ['type' => 'string', 'desc' => 'כתובת IP', 'readonly' => true],
                ],
            ],
            'info_messages' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'הודעות הסבר/עזרה במערכת',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'msg_key' => ['type' => 'string', 'desc' => 'מפתח ייחודי'],
                    'title' => ['type' => 'string', 'desc' => 'כותרת'],
                    'content' => ['type' => 'text', 'desc' => 'תוכן'],
                ],
            ],
            'ios_shortcut_links' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'קישורי קיצור דרך ל-iOS',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'title' => ['type' => 'string', 'desc' => 'כותרת'],
                    'url' => ['type' => 'string', 'desc' => 'כתובת URL'],
                    'sort_order' => ['type' => 'int', 'desc' => 'סדר הצגה'],
                    'is_active' => ['type' => 'bool', 'desc' => 'פעיל'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'popup_campaigns' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'קמפייני פופאפ למשתמשים',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'title' => ['type' => 'string', 'desc' => 'כותרת'],
                    'body_html' => ['type' => 'text', 'desc' => 'תוכן HTML'],
                    'target_scope' => ['type' => 'enum', 'desc' => 'יעד', 'enum' => ['all', 'homes', 'users']],
                    'status' => ['type' => 'enum', 'desc' => 'סטטוס', 'enum' => ['draft', 'published']],
                    'is_active' => ['type' => 'bool', 'desc' => 'פעיל'],
                    'sort_order' => ['type' => 'int', 'desc' => 'סדר'],
                    'starts_at' => ['type' => 'datetime', 'desc' => 'מתחיל בתאריך'],
                    'ends_at' => ['type' => 'datetime', 'desc' => 'מסתיים בתאריך'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                    'updated_at' => ['type' => 'timestamp', 'desc' => 'עודכן', 'readonly' => true],
                ],
            ],
            'notifications' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'התראות מערכת',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'בית יעד (0 = כללי)'],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש יעד (NULL = כל הבית)'],
                    'creator_id' => ['type' => 'int', 'desc' => 'יוצר ההתראה'],
                    'title' => ['type' => 'string', 'desc' => 'כותרת'],
                    'message' => ['type' => 'text', 'desc' => 'גוף הודעה'],
                    'type' => ['type' => 'enum', 'desc' => 'סוג', 'enum' => ['info', 'warning', 'success', 'error']],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'shopping_categories' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'קטגוריות (חנויות) ברשימת קניות',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית'],
                    'name' => ['type' => 'string', 'desc' => 'שם'],
                    'icon' => ['type' => 'string', 'desc' => 'מחלקת אייקון'],
                    'sort_order' => ['type' => 'int', 'desc' => 'סדר'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                ],
            ],
            'shopping_items' => [
                'read' => true,
                'write' => true,
                'dangerous' => false,
                'description' => 'פריטים ברשימת קניות',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'הבית'],
                    'category_id' => ['type' => 'int', 'desc' => 'קטגוריה'],
                    'item_name' => ['type' => 'string', 'desc' => 'שם הפריט'],
                    'quantity' => ['type' => 'string', 'desc' => 'כמות'],
                    'sort_order' => ['type' => 'int', 'desc' => 'סדר'],
                    'created_at' => ['type' => 'timestamp', 'desc' => 'תאריך יצירה', 'readonly' => true],
                    'updated_at' => ['type' => 'timestamp', 'desc' => 'עודכן', 'readonly' => true],
                ],
            ],
            'ai_api_logs' => [
                'read' => true,
                'write' => false,
                'dangerous' => false,
                'description' => 'לוגים של קריאות AI (צפייה בלבד)',
                'fields' => [
                    'id' => ['type' => 'int', 'desc' => 'מזהה', 'readonly' => true],
                    'home_id' => ['type' => 'int', 'desc' => 'בית', 'readonly' => true],
                    'user_id' => ['type' => 'int', 'desc' => 'משתמש', 'readonly' => true],
                    'action_type' => ['type' => 'string', 'desc' => 'תיאור הפעולה', 'readonly' => true],
                ],
            ],
        ];

        return $cache;
    }
}

if (!function_exists('admin_ai_agent_get_table_config')) {
    function admin_ai_agent_get_table_config(string $table): ?array
    {
        $reg = admin_ai_agent_table_registry();
        return $reg[$table] ?? null;
    }
}

if (!function_exists('admin_ai_agent_can_read')) {
    function admin_ai_agent_can_read(string $table): bool
    {
        $cfg = admin_ai_agent_get_table_config($table);
        return $cfg !== null && !empty($cfg['read']);
    }
}

if (!function_exists('admin_ai_agent_can_write')) {
    function admin_ai_agent_can_write(string $table): bool
    {
        $cfg = admin_ai_agent_get_table_config($table);
        return $cfg !== null && !empty($cfg['write']);
    }
}

if (!function_exists('admin_ai_agent_is_field_writable')) {
    function admin_ai_agent_is_field_writable(string $table, string $field): bool
    {
        if (in_array($field, admin_ai_agent_global_blocked_fields(), true)) {
            return false;
        }
        $cfg = admin_ai_agent_get_table_config($table);
        if (!$cfg || empty($cfg['fields'][$field])) {
            return false;
        }
        $def = $cfg['fields'][$field];
        if (!empty($def['readonly'])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('admin_ai_agent_sanitize_row_for_output')) {
    /**
     * מסיר שדות גלובליים חסומים משורה שחוזרת מה-DB.
     */
    function admin_ai_agent_sanitize_row_for_output(array $row): array
    {
        $blocked = array_flip(admin_ai_agent_global_blocked_fields());
        foreach (array_keys($blocked) as $field) {
            unset($row[$field]);
        }
        return $row;
    }
}

if (!function_exists('admin_ai_agent_sanitize_rows_for_output')) {
    function admin_ai_agent_sanitize_rows_for_output(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = admin_ai_agent_sanitize_row_for_output($row);
            }
        }
        return $out;
    }
}

if (!function_exists('admin_ai_agent_encrypt_write_payload')) {
    /**
     * מצפין שדות balance לפני INSERT/UPDATE.
     */
    function admin_ai_agent_encrypt_write_payload(string $table, array $data): array
    {
        $map = admin_ai_agent_encrypt_map();
        if (!isset($map[$table])) {
            return $data;
        }
        foreach ($map[$table] as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $v = $data[$col];
            if ($v === null || $v === '') {
                $data[$col] = null;
                continue;
            }
            if (function_exists('encryptBalance')) {
                $data[$col] = encryptBalance((string) $v);
            }
        }
        return $data;
    }
}

if (!function_exists('admin_ai_agent_build_schema_for_js')) {
    /**
     * מייצא את הסכמה בפורמט מצומצם לשימוש ב-JS כדי לזהות שמות טבלאות/שדות
     * ולעטוף אותם בתגי code עם סטיילינג מיוחד.
     */
    function admin_ai_agent_build_schema_for_js(): array
    {
        $reg = admin_ai_agent_table_registry();
        $tables = [];
        $fieldsByTable = [];
        $fieldsSet = [];
        foreach ($reg as $table => $cfg) {
            $tables[] = $table;
            $names = [];
            foreach (($cfg['fields'] ?? []) as $fname => $_def) {
                if (in_array($fname, admin_ai_agent_global_blocked_fields(), true)) {
                    continue;
                }
                $names[] = $fname;
                $fieldsSet[$fname] = true;
            }
            $fieldsByTable[$table] = $names;
        }
        return [
            'tables' => $tables,
            'fields_by_table' => $fieldsByTable,
            'all_fields' => array_keys($fieldsSet),
            'actions' => ['create', 'update', 'delete'],
        ];
    }
}

if (!function_exists('admin_ai_agent_build_schema_summary')) {
    /**
     * בונה סיכום טקסטואלי של סכמת הטבלאות לצורך הזרקה ל-system prompt של הבינה.
     */
    function admin_ai_agent_build_schema_summary(): string
    {
        $reg = admin_ai_agent_table_registry();
        $lines = [];
        foreach ($reg as $table => $cfg) {
            $perms = [];
            if (!empty($cfg['read'])) {
                $perms[] = 'read';
            }
            if (!empty($cfg['write'])) {
                $perms[] = 'write';
            }
            $permStr = $perms ? implode('+', $perms) : 'blocked';
            $dangerous = !empty($cfg['dangerous']) ? ' [DANGEROUS]' : '';
            $desc = $cfg['description'] ?? '';
            $lines[] = "- **`{$table}`** ({$permStr}){$dangerous} — {$desc}";
            foreach (($cfg['fields'] ?? []) as $fieldName => $def) {
                $type = $def['type'] ?? 'string';
                $fdesc = $def['desc'] ?? '';
                $ro = !empty($def['readonly']) ? ' [readonly]' : '';
                $enum = isset($def['enum']) && is_array($def['enum']) ? ' (' . implode('|', $def['enum']) . ')' : '';
                $lines[] = "    - `{$fieldName}`: {$type}{$enum}{$ro} — {$fdesc}";
            }
        }
        $lines[] = '';
        $lines[] = 'שדות גלובליים חסומים (בכל טבלה, לקריאה ולכתיבה): ' . implode(', ', admin_ai_agent_global_blocked_fields());
        $lines[] = 'שדות מוצפנים אוטומטית: homes.initial_balance (אל תצפין ידנית — ה-API דואג)';

        return implode("\n", $lines);
    }
}

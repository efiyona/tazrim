<?php
/**
 * רישום טבלאות לפאנל ניהול מערכת.
 * מפתח מערך = מזהה פנימי ל-URL (?t=...).
 *
 * שדות רגישים גלובליים נחסמים תמיד (ראו admin/includes/helpers.php).
 *
 * קשרים לטבלאות אחרות: type => fk_lookup, מפתח fk עם table, label_template, search_columns,
 * order_by, optional — חיפוש AJAX ב-admin/ajax/lookup.php.
 */
return [
    'info_messages' => [
        'table' => 'info_messages',
        'label' => 'הסבר מערכת',
        'nav_icon' => 'fa-circle-info',
        'list_columns' => ['id', 'msg_key', 'title'],
        'per_page' => 20,
        'order_by' => 'id DESC',
        'allow_delete' => true,
        'fields' => [
            'msg_key' => ['type' => 'text', 'label' => 'מפתח (msg_key)'],
            'title' => ['type' => 'text', 'label' => 'כותרת'],
            'content' => ['type' => 'textarea', 'label' => 'תוכן'],
        ],
    ],
    'ios_shortcut_links' => [
        'table' => 'ios_shortcut_links',
        'label' => 'קיצורי דרך iOS',
        'nav_icon' => 'fa-mobile-screen-button',
        'list_columns' => ['id', 'title', 'sort_order', 'is_active'],
        'per_page' => 25,
        'order_by' => 'sort_order ASC, id ASC',
        'allow_delete' => true,
        'fields' => [
            'title' => ['type' => 'text', 'label' => 'כותרת'],
            'url' => ['type' => 'text', 'label' => 'כתובת URL'],
            'sort_order' => ['type' => 'number', 'label' => 'סדר', 'empty_zero' => true],
            'is_active' => ['type' => 'checkbox', 'label' => 'פעיל'],
        ],
    ],
    'homes' => [
        'table' => 'homes',
        'label' => 'בתים',
        'nav_icon' => 'fa-house-user',
        'list_columns' => ['id', 'name', 'join_code', 'created_at'],
        'per_page' => 20,
        'order_by' => 'id DESC',
        'allow_delete' => true,
        'fields' => [
            'name' => ['type' => 'text', 'label' => 'שם הבית'],
            'primary_user_id' => [
                'type' => 'fk_lookup',
                'label' => 'משתמש ראשי (בית)',
                'fk' => [
                    'table' => 'users',
                    'value_column' => 'id',
                    'label_template' => '{first_name} {last_name} ({email})',
                    'search_columns' => ['first_name', 'last_name', 'email', 'nickname'],
                    'order_by' => 'last_name ASC, first_name ASC',
                    'optional' => true,
                ],
            ],
            'join_code' => ['type' => 'text', 'label' => 'קוד הצטרפות'],
            'initial_balance' => ['type' => 'balance', 'label' => 'יתרה התחלתית'],
        ],
    ],
    'users' => [
        'table' => 'users',
        'label' => 'משתמשים',
        'nav_icon' => 'fa-users',
        'list_columns' => ['id', 'email', 'first_name', 'last_name', 'role', 'home_id'],
        'per_page' => 25,
        'order_by' => 'id DESC',
        'allow_delete' => true,
        'fields' => [
            'home_id' => [
                'type' => 'fk_lookup',
                'label' => 'בית',
                'fk' => [
                    'table' => 'homes',
                    'value_column' => 'id',
                    'label_template' => '{name} (#{id}) · {join_code}',
                    'search_columns' => ['name', 'join_code'],
                    'order_by' => 'name ASC',
                    'optional' => true,
                ],
            ],
            'first_name' => ['type' => 'text', 'label' => 'שם פרטי'],
            'last_name' => ['type' => 'text', 'label' => 'שם משפחה'],
            'nickname' => ['type' => 'text', 'label' => 'כינוי'],
            'email' => ['type' => 'text', 'label' => 'מייל'],
            'phone' => ['type' => 'text', 'label' => 'טלפון'],
            'role' => [
                'type' => 'enum',
                'label' => 'תפקיד',
                'enum_options' => [
                    'user' => 'משתמש',
                    'home_admin' => 'מנהל בית',
                    'admin' => 'מנהל',
                    'program_admin' => 'מנהל מערכת',
                ],
            ],
            'new_password' => ['type' => 'password_new', 'label' => 'סיסמה חדשה (ביצירה חובה; בעריכה רק אם מחליפים)'],
        ],
    ],
    'tos_agreements' => [
        'table' => 'tos_agreements',
        'label' => 'הסכמות תקנון',
        'nav_icon' => 'fa-file-circle-check',
        'nav_group' => 'legal',
        'list_columns' => ['id', 'user_id', 'tos_version', 'accepted_at', 'ip_address'],
        'per_page' => 30,
        'order_by' => 'accepted_at DESC',
        'list_only' => true,
        'allow_delete' => false,
        'fields' => [
            'user_id' => [
                'type' => 'fk_lookup',
                'label' => 'משתמש',
                'fk' => [
                    'table' => 'users',
                    'value_column' => 'id',
                    'label_template' => '{first_name} {last_name} ({email})',
                    'search_columns' => ['first_name', 'last_name', 'email', 'nickname'],
                    'order_by' => 'last_name ASC, first_name ASC',
                    'optional' => false,
                ],
            ],
        ],
    ],
    'tos_terms' => [
        'table' => 'tos_terms',
        'label' => 'נוסחי תקנון',
        'nav_icon' => 'fa-file-lines',
        'nav_group' => 'legal',
        'list_columns' => ['id', 'version', 'last_updated_label', 'is_current', 'created_at'],
        'per_page' => 20,
        'order_by' => 'id DESC',
        'allow_delete' => true,
        'fields' => [
            'version' => ['type' => 'text', 'label' => 'מספר גרסה (ייחודי, למשל 3.0)'],
            'last_updated_label' => ['type' => 'text', 'label' => 'תאריך/תקופה לתצוגה (למשל אפריל 2026)'],
            'content_html' => ['type' => 'textarea', 'label' => 'תוכן התקנון (HTML)', 'rows' => 20],
            'is_current' => ['type' => 'checkbox', 'label' => 'גרסה נוכחית (רק אחת; משתמשים יידרשו לאשר אם השתנה)'],
        ],
    ],
    'feedback_reports' => [
        'table' => 'feedback_reports',
        'label' => 'דיווחי באג/פיצר',
        'nav_icon' => 'fa-bug',
        'list_columns' => ['id', 'created_at', 'kind', 'status', 'user_id', 'home_id', 'title', 'context_screen'],
        'per_page' => 30,
        'order_by' => 'created_at DESC, id DESC',
        'allow_delete' => true,
        'fields' => [
            'user_id' => [
                'type' => 'fk_lookup',
                'label' => 'משתמש מדווח',
                'fk' => [
                    'table' => 'users',
                    'value_column' => 'id',
                    'label_template' => '{first_name} {last_name} ({email})',
                    'search_columns' => ['first_name', 'last_name', 'email', 'nickname'],
                    'order_by' => 'last_name ASC, first_name ASC',
                    'optional' => false,
                ],
            ],
            'home_id' => [
                'type' => 'fk_lookup',
                'label' => 'בית',
                'fk' => [
                    'table' => 'homes',
                    'value_column' => 'id',
                    'label_template' => '{name} (#{id}) · {join_code}',
                    'search_columns' => ['name', 'join_code'],
                    'order_by' => 'name ASC',
                    'optional' => true,
                ],
            ],
            'kind' => [
                'type' => 'enum',
                'label' => 'סוג דיווח',
                'enum_options' => [
                    'bug' => 'באג',
                    'idea' => 'רעיון לפיצר',
                ],
            ],
            'title' => ['type' => 'text', 'label' => 'כותרת'],
            'message' => ['type' => 'textarea', 'label' => 'תיאור הדיווח', 'rows' => 10],
            'context_screen' => ['type' => 'text', 'label' => 'מסך / הקשר'],
            'status' => [
                'type' => 'enum',
                'label' => 'סטטוס טיפול',
                'enum_options' => [
                    'new' => 'חדש',
                    'in_review' => 'בטיפול',
                    'done' => 'טופל',
                ],
            ],
        ],
    ],
];

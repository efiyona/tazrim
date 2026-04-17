<?php
declare(strict_types=1);

/**
 * סכמת טבלאות עבור סוכן ה-AI של פאנל הניהול.
 *
 * **הכל חי — אין whitelist סטטי.**
 * בכל בקשה אנחנו שולפים את רשימת הטבלאות והעמודות ישירות מ-INFORMATION_SCHEMA
 * של MySQL. כך כל שינוי DDL (CREATE/ALTER/DROP של טבלאות או עמודות) משתקף
 * מיד בבקשה הבאה של הסוכן — בלי עדכון קוד ובלי schema drift.
 *
 * הקובץ הזה מגדיר רק את המדיניות שאי-אפשר לגזור אוטומטית מהמסד:
 *   1. שדות חסומים גלובלית (password / tokens).
 *   2. מיפוי הצפנה ברמת אפליקציה (homes.initial_balance וכד').
 *   3. רשימת טבלאות חסומות (אם נרצה להסתיר שירותיות).
 */

// ================================================================
//  מדיניות סטטית (לא תלויה במצב המסד)
// ================================================================

if (!function_exists('admin_ai_agent_global_blocked_fields')) {
    /**
     * שדות שאסור לגעת בהם בכל טבלה (לקריאה ולכתיבה).
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

if (!function_exists('admin_ai_agent_blocked_tables')) {
    /**
     * טבלאות שהסוכן לא צריך לראות / לגעת בהן. ריק כברירת מחדל —
     * אפשר להוסיף כאן שמות של טבלאות פנימיות אם נרצה להסתירן.
     */
    function admin_ai_agent_blocked_tables(): array
    {
        return [];
    }
}

// ================================================================
//  שליפה חיה מ-INFORMATION_SCHEMA
// ================================================================

if (!function_exists('admin_ai_agent_map_column_type')) {
    /**
     * ממפה MySQL DATA_TYPE (בשילוב COLUMN_TYPE) לתיוג פשוט לשימוש המודל/הלקוח.
     */
    function admin_ai_agent_map_column_type(string $dataType, string $columnType): string
    {
        $dt = strtolower($dataType);
        $ct = strtolower($columnType);
        switch ($dt) {
            case 'tinyint':
                // בדר"כ tinyint(1) משמש כ-bool במערכת
                if (strpos($ct, 'tinyint(1)') === 0) return 'bool';
                return 'int';
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
                return 'int';
            case 'decimal':
            case 'numeric':
                return 'decimal';
            case 'float':
            case 'double':
            case 'real':
                return 'float';
            case 'char':
            case 'varchar':
                return 'string';
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
                return 'text';
            case 'date':
                return 'date';
            case 'datetime':
                return 'datetime';
            case 'timestamp':
                return 'timestamp';
            case 'time':
                return 'time';
            case 'year':
                return 'year';
            case 'enum':
                return 'enum';
            case 'set':
                return 'set';
            case 'json':
                return 'json';
            case 'blob':
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'binary':
            case 'varbinary':
                return 'binary';
            default:
                return $dt !== '' ? $dt : 'string';
        }
    }
}

if (!function_exists('admin_ai_agent_parse_enum_values')) {
    /**
     * מפענח ערכים מ-COLUMN_TYPE של עמודת enum/set, לדוגמה:
     *   "enum('user','admin','home_admin')" => ['user','admin','home_admin']
     */
    function admin_ai_agent_parse_enum_values(string $columnType): array
    {
        if (!preg_match("/^\s*(?:enum|set)\s*\((.+)\)\s*$/i", $columnType, $m)) {
            return [];
        }
        $inner = $m[1];
        $values = [];
        // MySQL כותב את הערכים כ-'value', עם ' כפול ('') כ-escape של גרש
        if (preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $inner, $matches)) {
            foreach ($matches[1] as $v) {
                $v = str_replace("''", "'", $v);
                $v = stripcslashes($v);
                $values[] = $v;
            }
        }
        return $values;
    }
}

if (!function_exists('admin_ai_agent_table_registry')) {
    /**
     * בונה בזמן אמת מפה של טבלאות ועמודות מה-INFORMATION_SCHEMA של המסד הנוכחי.
     * Cache ברמת-בקשה בלבד — כל בקשת HTTP חדשה שולפת מחדש מהמסד.
     *
     * מחזיר: table => [
     *   'read'  => true,
     *   'write' => true,
     *   'fields' => [ col => [type, desc?, enum?, readonly?, encrypted?, nullable?] ]
     * ]
     */
    function admin_ai_agent_table_registry(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        global $conn;
        if (!($conn instanceof mysqli)) {
            return $cache;
        }

        $blockedTables = admin_ai_agent_blocked_tables();
        $blockedFields = admin_ai_agent_global_blocked_fields();
        $encryptMap    = admin_ai_agent_encrypt_map();

        try {
            $sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE,
                           IS_NULLABLE, COLUMN_COMMENT, EXTRA, COLUMN_KEY
                    FROM   INFORMATION_SCHEMA.COLUMNS
                    WHERE  TABLE_SCHEMA = DATABASE()
                    ORDER BY TABLE_NAME, ORDINAL_POSITION";
            $res = @mysqli_query($conn, $sql);
            if (!($res instanceof mysqli_result)) {
                return $cache;
            }

            while ($row = mysqli_fetch_assoc($res)) {
                $table = (string) ($row['TABLE_NAME'] ?? '');
                if ($table === '' || in_array($table, $blockedTables, true)) {
                    continue;
                }
                $col = (string) ($row['COLUMN_NAME'] ?? '');
                if ($col === '' || in_array($col, $blockedFields, true)) {
                    continue;
                }

                if (!isset($cache[$table])) {
                    $cache[$table] = [
                        'read'   => true,
                        'write'  => true,
                        'fields' => [],
                    ];
                }

                $dataType   = (string) ($row['DATA_TYPE']   ?? '');
                $columnType = (string) ($row['COLUMN_TYPE'] ?? '');
                $extra      = strtolower((string) ($row['EXTRA'] ?? ''));
                $comment    = trim((string) ($row['COLUMN_COMMENT'] ?? ''));
                $isNullable = strtoupper((string) ($row['IS_NULLABLE'] ?? '')) === 'YES';

                $type = admin_ai_agent_map_column_type($dataType, $columnType);
                $fieldDef = ['type' => $type];

                if ($comment !== '') {
                    $fieldDef['desc'] = $comment;
                }

                // enum/set values
                if ($type === 'enum' || $type === 'set') {
                    $vals = admin_ai_agent_parse_enum_values($columnType);
                    if (!empty($vals)) {
                        $fieldDef['enum'] = $vals;
                    }
                }

                // readonly heuristics
                $readonly = false;
                if (strpos($extra, 'auto_increment') !== false) $readonly = true;
                if (in_array($col, ['created_at', 'updated_at'], true)) $readonly = true;
                if (strpos($extra, 'on update current_timestamp') !== false) $readonly = true;
                if ($readonly) {
                    $fieldDef['readonly'] = true;
                }

                // encryption flag
                if (isset($encryptMap[$table]) && in_array($col, $encryptMap[$table], true)) {
                    $fieldDef['encrypted'] = true;
                    if (!isset($fieldDef['desc']) || $fieldDef['desc'] === '') {
                        $fieldDef['desc'] = 'מוצפן אוטומטית ב-API';
                    }
                }

                if ($isNullable) {
                    $fieldDef['nullable'] = true;
                }

                $cache[$table]['fields'][$col] = $fieldDef;
            }
            mysqli_free_result($res);
        } catch (\Throwable $e) {
            // כישלון שליפה — נחזיר את מה שיש. השיחה לא תקרוס, פשוט יהיה
            // פחות הקשר למודל.
        }

        return $cache;
    }
}

// ================================================================
//  גישות נוחות (Accessors)
// ================================================================

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
        // program_admin רואה הכל — אם הטבלה קיימת במסד וב-registry — מותר לקרוא.
        return admin_ai_agent_get_table_config($table) !== null;
    }
}

if (!function_exists('admin_ai_agent_can_write')) {
    function admin_ai_agent_can_write(string $table): bool
    {
        // אותו דבר לגבי כתיבה.
        return admin_ai_agent_get_table_config($table) !== null;
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

// ================================================================
//  Aliases לאחור (נשמרים כדי לא לשבור קוד קיים שקורא להם)
// ================================================================

if (!function_exists('admin_ai_agent_filtered_registry')) {
    /**
     * Historically פילטר על registry סטטי מול live columns. כעת ה-registry עצמו
     * הוא חי — הפונקציה משאירה את אותה חתימה לתאימות-אחורה.
     */
    function admin_ai_agent_filtered_registry(): array
    {
        return admin_ai_agent_table_registry();
    }
}

if (!function_exists('admin_ai_agent_live_columns_map')) {
    /**
     * מחזיר מפה table => [col => true]. מחושב מהרישום החי.
     */
    function admin_ai_agent_live_columns_map(): array
    {
        $map = [];
        foreach (admin_ai_agent_table_registry() as $t => $cfg) {
            foreach (($cfg['fields'] ?? []) as $f => $_def) {
                if (!isset($map[$t])) $map[$t] = [];
                $map[$t][$f] = true;
            }
        }
        return $map;
    }
}

// ================================================================
//  בניית פלט עבור JS וה-system prompt של המודל
// ================================================================

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
                $names[] = $fname;
                $fieldsSet[$fname] = true;
            }
            $fieldsByTable[$table] = $names;
        }
        return [
            'tables'          => $tables,
            'fields_by_table' => $fieldsByTable,
            'all_fields'      => array_keys($fieldsSet),
            'actions'         => ['create', 'update', 'delete', 'sql', 'sequence', 'push_broadcast'],
        ];
    }
}

if (!function_exists('admin_ai_agent_build_schema_summary')) {
    /**
     * בונה סיכום טקסטואלי של סכמת הטבלאות לצורך הזרקה ל-system prompt של הבינה.
     * הסיכום נבנה כל פעם מחדש מהמסד, ומשקף את המצב הנוכחי בדיוק.
     */
    function admin_ai_agent_build_schema_summary(): string
    {
        $reg = admin_ai_agent_table_registry();
        if (empty($reg)) {
            return "⚠️ לא הצלחתי לשלוף את סכמת המסד כרגע (INFORMATION_SCHEMA לא זמין). "
                 . "אם תבקש שאילתה, ייתכן שתידרש בדיקה ידנית.";
        }
        $lines = [];
        $lines[] = "סכמת המסד נשלפת חיה מ-INFORMATION_SCHEMA בכל בקשה "
                 . "(כל שינוי DDL משתקף מיד). להלן המצב הנוכחי:";
        $lines[] = '';
        foreach ($reg as $table => $cfg) {
            $lines[] = "- **`{$table}`**:";
            foreach (($cfg['fields'] ?? []) as $fieldName => $def) {
                $type  = $def['type'] ?? 'string';
                $fdesc = $def['desc'] ?? '';
                $ro    = !empty($def['readonly'])  ? ' [readonly]'  : '';
                $enc   = !empty($def['encrypted']) ? ' [encrypted]' : '';
                $null  = !empty($def['nullable'])  ? ''             : ' [NOT NULL]';
                $enum  = (isset($def['enum']) && is_array($def['enum']) && $def['enum'])
                    ? ' (' . implode('|', $def['enum']) . ')'
                    : '';
                $descPart = $fdesc !== '' ? ' — ' . $fdesc : '';
                $lines[] = "    - `{$fieldName}`: {$type}{$enum}{$ro}{$enc}{$null}{$descPart}";
            }
        }
        $lines[] = '';
        $lines[] = 'שדות גלובליים חסומים (בכל טבלה, לקריאה ולכתיבה): '
                 . implode(', ', admin_ai_agent_global_blocked_fields());

        $encMap = admin_ai_agent_encrypt_map();
        if (!empty($encMap)) {
            $parts = [];
            foreach ($encMap as $t => $cols) {
                $parts[] = $t . '.' . implode(',', $cols);
            }
            $lines[] = 'שדות מוצפנים אוטומטית (אל תצפין ידנית — ה-API דואג): '
                     . implode(' · ', $parts);
        }

        return implode("\n", $lines);
    }
}

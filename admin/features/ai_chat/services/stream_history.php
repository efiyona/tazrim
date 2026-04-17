<?php
declare(strict_types=1);

/**
 * עזרי היסטוריה/הצעות ל-stream_message.php — תקציר זהיר, redaction לפני שליחה למודל,
 * והעשרת snapshot לפני עדכון.
 */

if (!function_exists('admin_ai_chat_redact_action_payload_for_model')) {
    /**
     * מסיר מזהה ACTION_PROPOSED את before_row (כבד) כדי שלא יישלח שוב ל-Gemini.
     */
    function admin_ai_chat_redact_action_payload_for_model(string $content): string
    {
        return (string) preg_replace_callback(
            '/\[\[ACTION_PROPOSED\]\]([\s\S]*?)\[\[\/ACTION_PROPOSED\]\]/u',
            static function (array $m): string {
                $j = json_decode($m[1], true);
                if (!is_array($j)) {
                    return $m[0];
                }
                unset($j['before_row']);
                if (isset($j['steps']) && is_array($j['steps'])) {
                    foreach ($j['steps'] as $i => $st) {
                        if (is_array($st)) {
                            unset($j['steps'][$i]['before_row']);
                        }
                    }
                }
                return '[[ACTION_PROPOSED]]' . json_encode($j, JSON_UNESCAPED_UNICODE) . '[[/ACTION_PROPOSED]]';
            },
            $content
        );
    }
}

if (!function_exists('admin_ai_chat_enrich_proposed_action_with_snapshots')) {
    /**
     * מוסיף before_row לפני ולידטור/UI: update/delete (פעולה בודדת או שלבים ב-sequence עם id מספרי).
     */
    function admin_ai_chat_enrich_proposed_action_with_snapshots(mysqli $conn, array $action): array
    {
        $act = strtolower((string) ($action['action'] ?? ''));
        if ($act === 'update' || $act === 'delete') {
            $table = (string) ($action['table'] ?? '');
            $id = (int) ($action['id'] ?? 0);
            if ($table !== '' && $id > 0) {
                $q = admin_ai_chat_agent_query($conn, ['action' => 'get', 'table' => $table, 'id' => $id]);
                if (!empty($q['ok']) && !empty($q['found']) && isset($q['row']) && is_array($q['row'])) {
                    $action['before_row'] = $q['row'];
                }
            }
        } elseif ($act === 'sequence' && isset($action['steps']) && is_array($action['steps'])) {
            foreach ($action['steps'] as $i => $step) {
                if (!is_array($step)) {
                    continue;
                }
                $st = strtolower((string) ($step['action'] ?? ''));
                if ($st !== 'update' && $st !== 'delete') {
                    continue;
                }
                $table = (string) ($step['table'] ?? '');
                $id = (int) ($step['id'] ?? 0);
                if ($id <= 0 || $table === '') {
                    continue;
                }
                $q = admin_ai_chat_agent_query($conn, ['action' => 'get', 'table' => $table, 'id' => $id]);
                if (!empty($q['ok']) && !empty($q['found']) && isset($q['row']) && is_array($q['row'])) {
                    $action['steps'][$i]['before_row'] = $q['row'];
                }
            }
        }
        return $action;
    }
}

if (!function_exists('admin_ai_chat_validate_sequence_verification')) {
    /**
     * אימות צורת verification אופציונלי לרצף (לפני אישור הסוכן).
     */
    function admin_ai_chat_validate_sequence_verification(mixed $verification): array
    {
        if ($verification === null || $verification === []) {
            return ['ok' => true];
        }
        if (!is_array($verification)) {
            return ['ok' => false, 'error' => 'verification_not_array'];
        }
        $items = array_is_list($verification) ? $verification : [$verification];
        if (count($items) > 8) {
            return ['ok' => false, 'error' => 'verification_too_many'];
        }
        foreach ($items as $it) {
            if (!is_array($it)) {
                return ['ok' => false, 'error' => 'verification_item_not_object'];
            }
            $table = trim((string) ($it['table'] ?? ''));
            $id = (int) ($it['id'] ?? 0);
            $expect = $it['expect'] ?? null;
            if ($table === '' || $id <= 0) {
                return ['ok' => false, 'error' => 'verification_bad_table_or_id'];
            }
            if (!admin_ai_agent_can_read($table)) {
                return ['ok' => false, 'error' => 'verification_table_not_readable'];
            }
            if (!is_array($expect) || count($expect) === 0) {
                return ['ok' => false, 'error' => 'verification_expect_required'];
            }
            if (count($expect) > 16) {
                return ['ok' => false, 'error' => 'verification_expect_too_large'];
            }
            foreach (array_keys($expect) as $col) {
                $col = (string) $col;
                if ($col === '' || !admin_ai_agent_data_allowed_column($table, $col)) {
                    return ['ok' => false, 'error' => 'verification_bad_column:' . $col];
                }
            }
        }

        return ['ok' => true];
    }
}

if (!function_exists('admin_ai_chat_try_compress_history_rows')) {
    /**
     * כשיש הרבה הודעות — מסכם את הישנות (בלי מרקרים פנימיים) ומשאיר את האחרונות מלאות.
     * אם אי אפשר בבטחה — מחזיר את המערך המקורי.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    function admin_ai_chat_try_compress_history_rows(mysqli $conn, string $apiKey, array $rows): array
    {
        $tailKeep = 14;
        $minTotal = $tailKeep + 6;
        if ($apiKey === '' || count($rows) < $minTotal) {
            return $rows;
        }
        $head = array_slice($rows, 0, -$tailKeep);
        foreach ($head as $r) {
            $c = (string) ($r['content'] ?? '');
            if (strpos($c, '[[') !== false) {
                return $rows;
            }
        }
        $lines = [];
        foreach ($head as $r) {
            $role = ($r['role'] ?? '') === 'assistant' ? 'עוזר' : 'מנהל';
            $lines[] = $role . ': ' . trim(preg_replace('/\s+/u', ' ', (string) ($r['content'] ?? '')));
        }
        $blob = implode("\n", $lines);
        if (function_exists('mb_strlen') && mb_strlen($blob, 'UTF-8') < 400) {
            return $rows;
        }

        $summary = admin_ai_chat_summarize_history_blob($apiKey, $blob);
        if ($summary === '') {
            return $rows;
        }

        $synthetic = [
            'role' => 'assistant',
            'content' => "[תקציר שיחה קודמת — נוצר אוטומטית לחיסכון בטוקנים; אל תסתמך על פרטים מדויקים שלא הופיעו כאן]\n" . $summary,
            'model' => 'history-summary',
            'created_at' => null,
        ];

        return array_merge([$synthetic], array_slice($rows, -$tailKeep));
    }
}

if (!function_exists('admin_ai_chat_summarize_history_blob')) {
    function admin_ai_chat_summarize_history_blob(string $apiKey, string $blob): string
    {
        if (!function_exists('admin_ai_chat_gemini_generate_text')) {
            return '';
        }
        $body = [
            'system_instruction' => ['parts' => [[
                'text' => 'סכם בעברית בקצרה (עד 12 משפטים) את נושאי השיחה, החלטות והקשר. אל תמציא עובדות. בלי JSON, בלי קוד.',
            ]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => "הטקסט הבא הוא קטעי שיחה ישנים:\n\n" . $blob]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 512,
            ],
        ];
        $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
        foreach ($models as $m) {
            $t = admin_ai_chat_gemini_generate_text($apiKey, $m, $body);
            if (is_string($t) && trim($t) !== '') {
                $t = trim($t);
                if (function_exists('mb_substr')) {
                    $t = mb_substr($t, 0, 3500, 'UTF-8');
                } else {
                    $t = substr($t, 0, 3500);
                }
                return $t;
            }
        }
        return '';
    }
}

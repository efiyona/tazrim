<?php
declare(strict_types=1);

if (!function_exists('ai_chat_build_user_action_validator_instruction')) {
    function ai_chat_build_user_action_validator_instruction(): string
    {
        return "אתה **וולידטור** שבודק פעולות שהציע סוכן AI למשתמש קצה במערכת תזרים ביתית «התזרים».\n\n"
            . "המטרה: לוודא שהפעולה באמת תואמת את בקשת המשתמש, שהנתונים הגיוניים ושאין סיכון לטעות או פעולה לא רצויה.\n\n"
            . "סוגי פעולות שאתה עשוי לקבל ב־JSON:\n"
            . "- `create_transaction` — רישום הכנסה/הוצאה לקטגוריה **קיימת** (יש `category_id`).\n"
            . "- `create_category` — יצירת קטגוריה חדשה לבית; אופציונלי `initial_transaction` (פעולה ראשונה באותה קטגוריה).\n"
            . "- `save_user_preference` — שמירת העדפה/יעד אישי (מפתח goal_* או fact_* בלבד).\n"
            . "- `update_user_nickname` — עדכון כינוי למשתמש המחובר בלבד.\n\n"
            . "**החזר JSON בלבד** — בלי markdown, בלי ```:\n"
            . '{"approved":true|false,"confidence":"high|medium|low","analysis":"2–5 משפטים בעברית","warnings":[],"suggestion":"רק אם approved=false — משפט קצר איך לתקן"}' . "\n\n"
            . "אשר (approved=true) רק אם:\n"
            . "- הפעולה עומדת במילוי במפורש את כוונת המשתמש לפי השאלה וההיסטוריה.\n"
            . "- הסכומים והתאריכים הגיוניים (לא חריגים בלי הצדקה מהבקשה).\n"
            . "- ל־create_transaction: סוג ההוצאה/הכנסה ו־`category_id` מתאימים לתיאור המשתמש; אם נראה ניחוש — דחה.\n"
            . "- ל־create_category: השם והסוג מתאימים לבקשה; אם יש `initial_transaction` — התיאור והסכום תואמים לבקשה.\n"
            . "- ל־update_user_nickname: רק אם המשתמש ביקש במפורש לשנות כינוי.\n"
            . "- ל־save_user_preference: המפתח והערך סבירים ולא מכילים מידע רגיש של אחרים.\n\n"
            . "דחה (approved=false) אם:\n"
            . "- הכוונה לא ברורה או שהפעולה עלולה לרשום משהו שלא ביקשו.\n"
            . "- סכום או תאריך סותרים במפורש את דברי המשתמש.\n"
            . "- נראה שהסוכן מנחש קטגוריה או סכום במקום להסתמך על מה שנאמר.\n\n"
            . "ב־`analysis` — הסבר מה בדקת. ב־`suggestion` בעברית קצרה כיצד לתקן או מה לשאול.";
    }
}

if (!function_exists('ai_chat_run_user_action_validator')) {
    /**
     * @param list<array{role:string,text:string}> $historyText
     * @param array<string,mixed> $action
     * @return array{ok:bool,approved:bool,confidence:string,analysis:string,warnings:array<int,mixed>,suggestion:string,model:string}
     */
    function ai_chat_run_user_action_validator(string $apiKey, string $originalRequest, array $historyText, array $action): array
    {
        if (!function_exists('ai_chat_gemini_generate_text_timed')) {
            return [
                'ok' => false,
                'approved' => false,
                'confidence' => 'low',
                'analysis' => 'וולידטור לא זמין.',
                'warnings' => [],
                'suggestion' => '',
                'model' => '',
            ];
        }

        $instr = ai_chat_build_user_action_validator_instruction();
        $historyExcerpt = '';
        foreach (array_slice($historyText, -8) as $entry) {
            $role = (string) ($entry['role'] ?? 'user');
            $text = (string) ($entry['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $historyExcerpt .= "[{$role}] {$text}\n";
        }

        $userPayload = "בקשת המשתמש (הודעה אחרונה):\n«{$originalRequest}»\n\n"
            . "היסטוריית שיחה אחרונה:\n{$historyExcerpt}\n"
            . "הפעולה שהסוכן הציע לבצע (אחרי אישור המשתמש היא תתבצע במערכת):\n"
            . json_encode($action, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nהחזר את אובייקט ה-JSON לפי ההוראות.";

        $body = [
            'system_instruction' => ['parts' => [['text' => $instr]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPayload]]],
            ],
            'generationConfig' => [
                'temperature' => 0.15,
                'maxOutputTokens' => 500,
                'responseMimeType' => 'application/json',
            ],
        ];

        $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
        foreach ($models as $m) {
            $text = ai_chat_gemini_generate_text_timed($apiKey, $m, $body, 24);
            if ($text === null || trim($text) === '') {
                continue;
            }
            $parsed = json_decode(ai_chat_quality_strip_json_fences($text), true);
            if (!is_array($parsed)) {
                continue;
            }
            $approved = false;
            if (array_key_exists('approved', $parsed)) {
                $v = $parsed['approved'];
                if (is_bool($v)) {
                    $approved = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $approved = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $approved = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }

            return [
                'ok' => true,
                'approved' => $approved,
                'confidence' => (string) ($parsed['confidence'] ?? 'medium'),
                'analysis' => trim((string) ($parsed['analysis'] ?? '')),
                'warnings' => isset($parsed['warnings']) && is_array($parsed['warnings']) ? $parsed['warnings'] : [],
                'suggestion' => trim((string) ($parsed['suggestion'] ?? '')),
                'model' => $m,
            ];
        }

        return [
            'ok' => false,
            'approved' => false,
            'confidence' => 'low',
            'analysis' => 'לא הצלחנו להריץ את בדיקת האיכות. נסו שוב בעוד רגע.',
            'warnings' => ['validator_unavailable'],
            'suggestion' => '',
            'model' => '',
        ];
    }
}

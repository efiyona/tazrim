<?php
declare(strict_types=1);

require_once __DIR__ . '/allowed_chat_pages.php';

if (!function_exists('ai_chat_reply_quality_scan')) {
    /**
     * @return array{suspicious: bool, reasons: list<string>}
     */
    function ai_chat_reply_quality_scan(string $text): array
    {
        $reasons = [];
        $t = trim($text);
        if ($t === '') {
            return ['suspicious' => true, 'reasons' => ['empty_reply']];
        }
        if (preg_match('/\[\[\s*(?:QUESTIONS|ACTION)\s*\]\]/i', $t) || preg_match('/\[\[\s*\/\s*(?:QUESTIONS|ACTION)\s*\]\]/i', $t)) {
            $reasons[] = 'internal_marker_leaked';
        }
        $try = json_decode($t, true);
        if (is_array($try) && json_last_error() === JSON_ERROR_NONE && strlen($t) < 8000) {
            $reasons[] = 'whole_body_is_json';
        }
        if (!preg_match('/\p{Hebrew}/u', $t) && preg_match('/^\s*[\[{]/', $t)) {
            $reasons[] = 'code_like_without_hebrew';
        }
        if (preg_match('/\(\s*id\s*\d+\s*\)/iu', $t)) {
            $reasons[] = 'exposed_internal_id';
        }
        if (preg_match('/\bid\s*[:：]\s*\d+/iu', $t)) {
            $reasons[] = 'exposed_internal_id';
        }
        if (preg_match('/מזהה\s*[:：]?\s*\d+/u', $t)) {
            $reasons[] = 'exposed_internal_id';
        }
        if (preg_match('/\bcategory_id\b/ui', $t)) {
            $reasons[] = 'exposed_internal_field';
        }
        $badPages = ai_chat_collect_invalid_page_paths($t);
        if ($badPages !== []) {
            $reasons[] = 'invalid_page_link';
        }

        return ['suspicious' => $reasons !== [], 'reasons' => $reasons];
    }
}

if (!function_exists('ai_chat_reply_quality_polish_user_message')) {
    /**
     * הוראת משתמש לשלב polish — עברית, ללא סימנים טכניים, מבנה קריא.
     *
     * @param list<string> $reasons
     */
    function ai_chat_reply_quality_polish_user_message(string $finalText, array $reasons): string
    {
        $map = [
            'empty_reply' => 'התשובה ריקה.',
            'internal_marker_leaked' => 'דלפו סימנים פנימיים של המערכת.',
            'whole_body_is_json' => 'כל גוף התשובה נראה כמו JSON.',
            'code_like_without_hebrew' => 'נראה קוד או מבנה טכני בלי עברית מספקת.',
            'exposed_internal_id' => 'הוצגו מזהים פנימיים (מספרי id) — אסור; השאר רק שמות ותיאורים למשתמש.',
            'exposed_internal_field' => 'הופיעו שמות שדות טכניים (כמו category_id) — הסר מהטקסט למשתמש.',
            'invalid_page_link' => 'יש קישורי [[PAGE:...]] לנתיב שלא קיים ברשימת המערכת — הסר או החלף לנתיב מותר בלבד.',
        ];
        $lines = [
            'הטקסט הבא נועד למשתמש קצה במערכת תזרים ביתית. החזר גרסה מתוקנת בעברית בלבד.',
            'אסור בלוקים [[QUESTIONS]] / [[ACTION]], אסור JSON של פעולות, אסור מזהי קטגוריה או מספרי id, אסור המילה category_id.',
            '',
            'בעיות שזוהו:',
        ];
        foreach ($reasons as $r) {
            $lines[] = '- ' . ($map[$r] ?? $r);
        }
        $lines[] = '';
        $lines[] = 'עיצוב: כותרות משנה עם **הדגשה** (למשל **הכנסות** / **הוצאות**), רשימות עם * בשורה נפרדת לכל פריט, שורה ריקה בין קטעים.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = $finalText;

        return implode("\n", $lines);
    }
}

if (!function_exists('ai_chat_quality_strip_json_fences')) {
    function ai_chat_quality_strip_json_fences(string $raw): string
    {
        $s = trim($raw);
        if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
            return trim($m[1]);
        }

        return $s;
    }
}

if (!function_exists('ai_chat_reply_polish_default_hint')) {
    /**
     * @param list<string> $reasons
     */
    function ai_chat_reply_polish_default_hint(array $reasons): string
    {
        if (in_array('internal_marker_leaked', $reasons, true)) {
            return 'הסר כל בלוקים כמו [[QUESTIONS]] או [[ACTION]] — כתוב רק טקסט למשתמש הקצה.';
        }
        if (in_array('whole_body_is_json', $reasons, true) || in_array('code_like_without_hebrew', $reasons, true)) {
            return 'כתוב תשובה בעברית אנושית, בלי JSON וללא קוד גולמי בשורה הראשונה.';
        }
        if (in_array('exposed_internal_id', $reasons, true) || in_array('exposed_internal_field', $reasons, true)) {
            return 'הצג רק שמות (קטגוריות, תיאורים) — בלי מזהים מספריים ובלי שדות טכניים.';
        }
        if (in_array('empty_reply', $reasons, true)) {
            return 'נסח תשובה עזרה קצרה בעברית לפי השאלה והנתונים שקיבלת.';
        }
        if (in_array('invalid_page_link', $reasons, true)) {
            return 'הסר או תקן קישורי [[PAGE:...]] — רק נתיבים מהרשימה המותרת במערכת (דוחות, קניות, הגדרות בית/פרופיל, דף הבית).';
        }

        return 'נסח מחדש בעברית ברורה, קומפקטית, בלי תבניות טכניות.';
    }
}

/**
 * שער איכות (מודל קטן) — כמו admin_ai_chat_run_reply_polish_gate, מותאם למשתמש קצה.
 *
 * @param list<string> $scanReasons
 * @return array{acceptable: bool, retry_instruction_he: string}
 */
if (!function_exists('ai_chat_run_reply_polish_gate')) {
    function ai_chat_run_reply_polish_gate(string|array $apiKeyOrKeys, string $originalUserMessage, string $draftAssistant, array $scanReasons): array
    {
        if (!function_exists('ai_chat_gemini_generate_text_timed')) {
            return ['acceptable' => true, 'retry_instruction_he' => ''];
        }

        $reasonStr = implode(',', $scanReasons);
        $sys = "אתה **בודק איכות תשובה** לסוכן AI במערכת תזרים ביתית (עברית, משתמש קצה).\n\n"
            . "קבע אם הטיוטה למטה מתאימה **להצגה כתשובה סופית**, או שהיא נראית כמו טיוטת ביניים: שאריות כלי (`[[QUESTIONS]]`, `[[ACTION]]`), JSON גולמי בלי הסבר, קוד בלי מלל בעברית, חשיפת מזהים טכניים (מספרי id, category_id), או קישורי `[[PAGE:...]]` לנתיב שלא מופיע ברשימת הדפים המותרים במערכת.\n\n"
            . "החזר **אובייקט JSON בלבד** (בלי markdown):\n"
            . '{"acceptable":true|false,"retry_instruction_he":"רק אם acceptable=false — הנחיה קצרה לסוכן לנסח מחדש בעברית, בלי בלוקים פנימיים"}';

        $userPay = "שאלת המשתמש:\n«{$originalUserMessage}»\n\n"
            . "טיוטת תשובה מהסוכן:\n---\n{$draftAssistant}\n---\n\n"
            . "דגלי זיהוי מהיר: {$reasonStr}";

        $body = [
            'system_instruction' => ['parts' => [['text' => $sys]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPay]]],
            ],
            'generationConfig' => [
                'temperature' => 0.12,
                'maxOutputTokens' => 280,
                'responseMimeType' => 'application/json',
            ],
        ];

        $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
        foreach ($models as $m) {
            $text = ai_chat_gemini_generate_text_timed($apiKeyOrKeys, $m, $body, 18);
            if ($text === null || trim($text) === '') {
                continue;
            }
            $parsed = json_decode(ai_chat_quality_strip_json_fences($text), true);
            if (!is_array($parsed)) {
                continue;
            }
            $acceptable = true;
            if (array_key_exists('acceptable', $parsed)) {
                $v = $parsed['acceptable'];
                if (is_bool($v)) {
                    $acceptable = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $acceptable = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $acceptable = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }
            $hint = trim((string) ($parsed['retry_instruction_he'] ?? ''));

            return ['acceptable' => $acceptable, 'retry_instruction_he' => $hint];
        }

        return ['acceptable' => true, 'retry_instruction_he' => ''];
    }
}

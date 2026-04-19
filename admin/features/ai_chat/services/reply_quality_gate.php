<?php
declare(strict_types=1);

/**
 * זיהוי מהיר של תשובות "לא למשתמש" (שאריות כלי, JSON גולמי וכו') לפני שלב polish עם Gemini.
 */

if (!function_exists('admin_ai_chat_reply_polish_default_hint')) {
    function admin_ai_chat_reply_polish_default_hint(array $reasons): string
    {
        $r = implode(', ', $reasons);

        return "הטיוטה שנשלחה לא מתאימה להצגה למנהל (זיהוי: {$r}). "
            . "החזר תשובה **בעברית** — תקציר ברור, הסבר או טבלה מסודרת. "
            . "**אל** תכלול בלוקים פנימיים כמו [[DATA_QUERY]], [[ACTION]], [[DATA_CHART]], [[SQL_CHANGE]] או קוד מכונה בלי הקשר. "
            . "אם חסר מידע — שלוף ב-[[DATA_QUERY]] ואז ענה.";
    }
}

if (!function_exists('admin_ai_chat_reply_quality_scan')) {
    /**
     * @return array{suspicious: bool, reasons: list<string>}
     */
    function admin_ai_chat_reply_quality_scan(string $text): array
    {
        $reasons = [];
        $t = trim($text);
        if ($t === '') {
            return ['suspicious' => true, 'reasons' => ['empty_reply']];
        }

        if (preg_match('/\[\[\s*(?:DATA_QUERY|ACTION|DATA_CHART|SQL_CHANGE)\s*\]\]/i', $t)
            || preg_match('/\[\[\s*\/\s*(?:DATA_QUERY|ACTION|DATA_CHART|SQL_CHANGE)\s*\]\]/i', $t)) {
            $reasons[] = 'internal_marker_leaked';
        }

        if (preg_match('/^\s*תוצאת\s+DATA_QUERY\s*\(/u', $t)) {
            $reasons[] = 'internal_data_query_payload_leaked';
        }

        // טיוטה שהיא כמעט רק JSON (ללא משפט הסבר למנהל)
        if (strlen($t) < 12000) {
            $try = json_decode($t, true);
            if (is_array($try) && json_last_error() === JSON_ERROR_NONE) {
                $reasons[] = 'whole_body_is_json';
            }
        }

        // שורה אחת או שתיים של קוד בלי עברית ממשית (אות עברית)
        if (!preg_match('/\p{Hebrew}/u', $t) && (preg_match('/^\s*[\[{]/', $t) || substr_count($t, "\n") <= 2)) {
            if (preg_match('/\b(select|insert|update|delete|json|where)\b/i', $t)) {
                $reasons[] = 'code_like_without_hebrew';
            }
        }

        return ['suspicious' => $reasons !== [], 'reasons' => $reasons];
    }
}

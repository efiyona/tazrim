<?php
declare(strict_types=1);

if (!function_exists('ai_chat_guard_sanitize_context_block')) {
    /**
     * ניקוי דטרמיניסטי: בלי NUL, בלי תווי בקרה C0 (חוץ מ־TAB/LF/CR), UTF-8 תקין ככל האפשר.
     */
    function ai_chat_guard_sanitize_context_block(string $block): string
    {
        $block = str_replace("\0", '', $block);
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $block);
        if (is_string($cleaned)) {
            $block = $cleaned;
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $block);
            if (is_string($converted)) {
                $block = $converted;
            }
        }

        return $block;
    }
}

if (!function_exists('ai_chat_guard_context_block')) {
    /**
     * בדיקות דטרמיניסטיות לפני שליחת בלוק טקסט למודל.
     * $homeId נשמר לחתימת API עתידית (בדיקות נוספות); כרגע הגנה היא על אורך ותווים בטוחים.
     *
     * @return array{ok:bool,error?:string,content:string}
     */
    function ai_chat_guard_context_block(string $block, int $homeId, int $maxChars = 120000): array
    {
        $block = ai_chat_guard_sanitize_context_block((string) $block);
        if ($block === '') {
            return ['ok' => true, 'content' => ''];
        }
        if (strlen($block) > $maxChars) {
            $tail = function_exists('mb_substr') ? mb_substr($block, 0, $maxChars, 'UTF-8') : substr($block, 0, $maxChars);

            return ['ok' => false, 'error' => 'context_too_large', 'content' => $tail];
        }

        return ['ok' => true, 'content' => $block];
    }
}

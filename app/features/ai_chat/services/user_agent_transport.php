<?php
declare(strict_types=1);

if (!function_exists('ai_chat_strip_json_fences_local')) {
    function ai_chat_strip_json_fences_local(string $raw): string
    {
        $s = trim($raw);
        if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
            return trim($m[1]);
        }

        return $s;
    }
}

if (!function_exists('ai_chat_extract_questions_block')) {
    /** @return list<array{id:string,text:string,options:list<string>}>|null */
    function ai_chat_extract_questions_block(string $text): ?array
    {
        if (!preg_match('/\[\[QUESTIONS\]\]\s*(.*?)\s*\[\[\/QUESTIONS\]\]/s', $text, $m)) {
            return null;
        }
        $json = ai_chat_strip_json_fences_local(trim($m[1]));
        $parsed = json_decode($json, true);
        if (!is_array($parsed) || $parsed === []) {
            return null;
        }
        $questions = [];
        foreach ($parsed as $q) {
            if (!is_array($q) || !isset($q['text'])) {
                continue;
            }
            $questions[] = [
                'id' => (string) ($q['id'] ?? ('q' . count($questions))),
                'text' => (string) $q['text'],
                'options' => isset($q['options']) && is_array($q['options']) ? array_values(array_map('strval', $q['options'])) : [],
            ];
        }

        return $questions !== [] ? $questions : null;
    }
}

if (!function_exists('ai_chat_strip_questions_block')) {
    function ai_chat_strip_questions_block(string $text): string
    {
        return trim(preg_replace('/\[\[QUESTIONS\]\]\s*.*?\s*\[\[\/QUESTIONS\]\]/s', '', $text));
    }
}

if (!function_exists('ai_chat_build_questions_context_text')) {
    /**
     * בלוק מובנה לשמירה בהיסטוריה — עוזר למודל בסיבוב הבא (כמו admin_ai_chat_build_questions_context_text).
     *
     * @param list<array{id:string,text:string,options:list<string>}> $questions
     */
    function ai_chat_build_questions_context_text(array $questions): string
    {
        $lines = [];
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $text = trim((string) ($q['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $opts = [];
            if (isset($q['options']) && is_array($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $optText = trim((string) $opt);
                    if ($optText !== '') {
                        $opts[] = $optText;
                    }
                }
            }
            $line = '- ' . $text;
            if ($opts !== []) {
                $line .= ' | אפשרויות: ' . implode(' / ', array_slice($opts, 0, 6));
            }
            $lines[] = $line;
        }
        if ($lines === []) {
            return '';
        }

        return "[[QUESTIONS_CONTEXT]]\n" . implode("\n", $lines) . "\n[[/QUESTIONS_CONTEXT]]";
    }
}

if (!function_exists('ai_chat_extract_action_block')) {
    /** @return array<string,mixed>|null */
    function ai_chat_extract_action_block(string $text): ?array
    {
        if (!preg_match('/\[\[ACTION\]\]\s*(.*?)\s*\[\[\/ACTION\]\]/s', $text, $m)) {
            return null;
        }
        $json = ai_chat_strip_json_fences_local(trim($m[1]));
        $parsed = json_decode($json, true);

        return is_array($parsed) ? $parsed : null;
    }
}

if (!function_exists('ai_chat_strip_action_block')) {
    function ai_chat_strip_action_block(string $text): string
    {
        $cleaned = preg_replace('/\[\[ACTION\]\]\s*.*?\s*\[\[\/ACTION\]\]/s', '', $text);
        $cleaned = preg_replace('/\[\[ACTION\]\].*$/s', '', (string) $cleaned);

        return trim((string) $cleaned);
    }
}

if (!function_exists('ai_chat_proposal_secret')) {
    function ai_chat_proposal_secret(): string
    {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            return hash('sha256', 'ai_chat_proposal:' . GEMINI_API_KEY, true);
        }
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['id'])) {
            return hash('sha256', 'ai_chat_proposal_sess:' . (string) ($_SESSION['id'] ?? '0'), true);
        }

        return hash('sha256', 'ai_chat_proposal_fallback', true);
    }
}

if (!function_exists('ai_chat_sign_proposal')) {
    function ai_chat_sign_proposal(string $canonicalJson, int $chatId, int $proposedAtMs): string
    {
        $payload = $canonicalJson . '|' . $chatId . '|' . $proposedAtMs;
        $sig = hash_hmac('sha256', $payload, ai_chat_proposal_secret());

        return $sig;
    }
}

if (!function_exists('ai_chat_verify_proposal_signature')) {
    function ai_chat_verify_proposal_signature(string $canonicalJson, int $chatId, int $proposedAtMs, string $sig): bool
    {
        $expected = ai_chat_sign_proposal($canonicalJson, $chatId, $proposedAtMs);

        return hash_equals($expected, $sig);
    }
}

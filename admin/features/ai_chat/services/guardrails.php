<?php
declare(strict_types=1);

if (!function_exists('admin_ai_chat_guard_validate_input')) {
    function admin_ai_chat_guard_validate_input(string $message): array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return ['ok' => false, 'reason' => 'empty'];
        }
        if (mb_strlen($trimmed, 'UTF-8') > 1500) {
            return ['ok' => false, 'reason' => 'too_long'];
        }

        $blockedPatterns = [
            '/ignore\s+all\s+previous\s+instructions/i',
            '/תתעלם\s+מההוראות/u',
            '/jailbreak/i',
            '/system\s+prompt/i',
            '/malware|phishing|sqlmap|exploit/i',
        ];
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return ['ok' => false, 'reason' => 'policy'];
            }
        }

        return ['ok' => true, 'reason' => null];
    }
}

if (!function_exists('admin_ai_chat_guard_refusal_text')) {
    function admin_ai_chat_guard_refusal_text(): string
    {
        return 'אני יכול לענות רק על נושאים שקשורים למערכת התזרים ולייעוץ פיננסי. נסו לנסח שאלה בתחום הזה.';
    }
}

<?php
declare(strict_types=1);

/**
 * נתיבי דפים שמותר להפנות אליהם מבלוקי [[PAGE:...|...]] בצ'אט משתמש קצה.
 * עדכנו כאן כשמוסיפים דפים ציבוריים רלוונטיים (אחרי login).
 */
if (!function_exists('ai_chat_allowed_page_paths')) {
    /**
     * @return list<string>
     */
    function ai_chat_allowed_page_paths(): array
    {
        return [
            '/',
            '/index.php',
            '/pages/reports.php',
            '/pages/shopping.php',
            '/pages/welcome.php',
            '/pages/settings/user_profile.php',
            '/pages/settings/manage_home.php',
        ];
    }
}

if (!function_exists('ai_chat_normalize_internal_page_path')) {
    function ai_chat_normalize_internal_page_path(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $s)) {
            return '';
        }
        $path = str_starts_with($s, '/') ? $s : '/' . $s;
        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        $path = rtrim($path, '/') ?: '/';

        return $path === '' ? '/' : $path;
    }
}

if (!function_exists('ai_chat_page_path_is_allowed')) {
    function ai_chat_page_path_is_allowed(string $rawPath): bool
    {
        $n = ai_chat_normalize_internal_page_path($rawPath);
        if ($n === '') {
            return true;
        }
        $list = ai_chat_allowed_page_paths();
        foreach ($list as $ok) {
            if (strcasecmp($n, $ok) === 0) {
                return true;
            }
        }
        if ($n === '/' && in_array('/index.php', $list, true)) {
            return true;
        }
        if ($n === '/index.php' && in_array('/', $list, true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('ai_chat_collect_invalid_page_paths')) {
    /**
     * @return list<string>
     */
    function ai_chat_collect_invalid_page_paths(string $text): array
    {
        if (!preg_match_all('/\[\[PAGE:\s*([^\]|]+)\|/u', $text, $m)) {
            return [];
        }
        $bad = [];
        foreach ($m[1] as $raw) {
            $raw = trim((string) $raw);
            if (preg_match('#^https?://#i', $raw)) {
                continue;
            }
            if (!ai_chat_page_path_is_allowed($raw)) {
                $bad[] = ai_chat_normalize_internal_page_path($raw) ?: $raw;
            }
        }

        return array_values(array_unique(array_filter($bad)));
    }
}

if (!function_exists('ai_chat_format_allowed_pages_for_prompt')) {
    function ai_chat_format_allowed_pages_for_prompt(): string
    {
        $lines = [];
        foreach (ai_chat_allowed_page_paths() as $p) {
            $lines[] = '- `[[PAGE:' . $p . '|טקסט כפתור]]`';
        }

        return implode("\n", $lines);
    }
}

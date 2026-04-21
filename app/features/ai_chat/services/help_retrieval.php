<?php
declare(strict_types=1);

if (!function_exists('ai_chat_help_chunks_from_md')) {
    /**
     * @return list<array{key:string,body:string}>
     */
    function ai_chat_help_chunks_from_md(string $md): array
    {
        $parts = preg_split('/\n(?=#{1,3}\s)/u', $md);
        if (!is_array($parts)) {
            return [['key' => 'doc', 'body' => $md]];
        }
        $out = [];
        $i = 0;
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $key = 'sec_' . $i;
            if (preg_match('/^#{1,3}\s+(.+)/u', $part, $m)) {
                $slug = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $m[1]);
                $slug = strtolower(trim(preg_replace('/\s+/u', '_', $slug)));
                if ($slug !== '') {
                    $key = mb_substr($slug, 0, 80, 'UTF-8');
                }
            }
            $out[] = ['key' => $key, 'body' => $part];
            $i++;
        }

        return $out !== [] ? $out : [['key' => 'doc', 'body' => $md]];
    }
}

if (!function_exists('ai_chat_help_score_chunk')) {
    function ai_chat_help_score_chunk(string $message, string $chunk): int
    {
        $msg = mb_strtolower($message, 'UTF-8');
        $words = preg_split('/\s+/u', $msg, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words)) {
            return 0;
        }
        $score = 0;
        $hay = mb_strtolower($chunk, 'UTF-8');
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') < 2) {
                continue;
            }
            if (mb_strpos($hay, $w, 0, 'UTF-8') !== false) {
                $score++;
            }
        }

        return $score;
    }
}

if (!function_exists('ai_chat_help_retrieve_top_k')) {
    /**
     * שליפת Top-K מקטעי מדריך: אם יש שורות ב־ai_help_chunks — משם (חפיפת מילים; embeddings אופציונלי בעתיד);
     * אחרת פיצול product_knowledge.md.
     *
     * @return string טקסט מאוחד
     */
    function ai_chat_help_retrieve_top_k(string $userMessage, int $k = 5, int $maxChars = 28000, ?mysqli $conn = null): string
    {
        $path = dirname(__DIR__) . '/docs/product_knowledge.md';
        $chunks = [];
        if ($conn instanceof mysqli) {
            $cntRes = @$conn->query('SELECT COUNT(*) AS c FROM ai_help_chunks');
            if ($cntRes) {
                $cntRow = $cntRes->fetch_assoc();
                $cntRes->free();
                if ((int) ($cntRow['c'] ?? 0) > 0) {
                    $q = @$conn->query('SELECT section_key, body_text FROM ai_help_chunks LIMIT 500');
                    if ($q) {
                        while ($row = $q->fetch_assoc()) {
                            $body = trim((string) ($row['body_text'] ?? ''));
                            if ($body === '') {
                                continue;
                            }
                            $chunks[] = [
                                'key' => (string) ($row['section_key'] ?? 'sec'),
                                'body' => $body,
                            ];
                        }
                        $q->free();
                    }
                }
            }
        }
        if ($chunks === []) {
            if (!is_file($path)) {
                return '';
            }
            $md = (string) file_get_contents($path);
            $chunks = ai_chat_help_chunks_from_md($md);
        } else {
            $md = is_file($path) ? (string) file_get_contents($path) : '';
        }

        $scored = [];
        foreach ($chunks as $c) {
            $scored[] = ['s' => ai_chat_help_score_chunk($userMessage, $c['body']), 'body' => $c['body'], 'key' => $c['key']];
        }
        usort($scored, static fn ($a, $b) => $b['s'] <=> $a['s']);
        $out = [];
        $used = 0;
        $n = 0;
        foreach ($scored as $row) {
            if ($n >= $k) {
                break;
            }
            if ($row['s'] <= 0 && $n > 0) {
                break;
            }
            $hdr = '### קטע: ' . $row['key'] . "\n";
            $piece = $hdr . $row['body'];
            if ($used + strlen($piece) > $maxChars) {
                break;
            }
            $out[] = $piece;
            $used += strlen($piece);
            $n++;
        }
        if ($out === []) {
            if ($md === '' && !is_file($path)) {
                return '';
            }
            if ($md === '' && is_file($path)) {
                $md = (string) file_get_contents($path);
            }
            $fallback = mb_substr($md, 0, min($maxChars, mb_strlen($md, 'UTF-8')), 'UTF-8');

            return "### מדריך (קטע ראשון בגלל חוסר התאמה)\n" . $fallback;
        }

        return "### מדריך מערכת (קטעים רלוונטיים)\n" . implode("\n\n---\n\n", $out);
    }
}

<?php
declare(strict_types=1);

if (!function_exists('admin_ai_agent_sql_change_append')) {
    function admin_ai_agent_sql_change_append(mysqli $conn, int $chatId, string $sql, string $kind): void
    {
        if ($chatId <= 0 || trim($sql) === '') {
            return;
        }
        require_once __DIR__ . '/chat_repository.php';
        $payload = [
            'kind' => strtolower($kind) === 'ddl' ? 'ddl' : 'dml',
            'sql' => trim($sql),
            'ts' => date('c'),
        ];
        $line = '[[SQL_CHANGE]]' . json_encode($payload, JSON_UNESCAPED_UNICODE) . '[[/SQL_CHANGE]]';
        @admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $line, 'sql-audit');
    }
}

if (!function_exists('admin_ai_agent_sql_change_export_for_chat')) {
    /**
     * @return array{ok:bool,count:int,sql_script?:string,message?:string}
     */
    function admin_ai_agent_sql_change_export_for_chat(mysqli $conn, int $chatId, int $userId): array
    {
        if ($chatId <= 0) {
            return ['ok' => false, 'count' => 0, 'message' => 'missing_chat_id'];
        }
        require_once __DIR__ . '/chat_repository.php';
        $rows = admin_ai_chat_repo_get_messages($conn, $chatId, $userId, 500);
        $changes = [];
        foreach ($rows as $r) {
            $content = (string) ($r['content'] ?? '');
            if (preg_match_all('/\[\[SQL_CHANGE\]\]([\s\S]*?)\[\[\/SQL_CHANGE\]\]/u', $content, $ms)) {
                foreach (($ms[1] ?? []) as $json) {
                    $j = json_decode((string) $json, true);
                    if (is_array($j) && !empty($j['sql'])) {
                        $changes[] = $j;
                    }
                }
            }
        }
        if (count($changes) === 0) {
            return ['ok' => true, 'count' => 0, 'message' => 'no_db_changes_recorded'];
        }
        $lines = [];
        $lines[] = '-- SQL change export from admin AI chat';
        $lines[] = '-- chat_id: ' . $chatId;
        $lines[] = '-- generated_at: ' . date('c');
        $lines[] = '-- Review carefully before running on production';
        $lines[] = '';
        foreach ($changes as $idx => $c) {
            $kind = strtoupper((string) ($c['kind'] ?? 'DML'));
            $ts = (string) ($c['ts'] ?? '');
            $sql = trim((string) ($c['sql'] ?? ''));
            if ($sql === '') {
                continue;
            }
            $lines[] = '-- change ' . ($idx + 1) . ' [' . $kind . ']' . ($ts !== '' ? ' ' . $ts : '');
            $lines[] = rtrim($sql, ';') . ';';
            $lines[] = '';
        }
        return ['ok' => true, 'count' => count($changes), 'sql_script' => implode("\n", $lines)];
    }
}

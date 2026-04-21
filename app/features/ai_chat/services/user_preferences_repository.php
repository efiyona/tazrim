<?php
declare(strict_types=1);

if (!function_exists('ai_user_pref_allowed_key')) {
    /** מפתחות מותרים: goal_*, fact_* — אותיות קטנות מספרים וקו תחתון */
    function ai_user_pref_allowed_key(string $key): bool
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 110) {
            return false;
        }

        return (bool) preg_match('/^(goal|fact)_[a-z0-9_]+$/', $key);
    }
}

if (!function_exists('ai_user_pref_list_for_prompt')) {
    /**
     * @return list<array{pref_key:string,pref_value:string}>
     */
    function ai_user_pref_list_for_prompt(mysqli $conn, int $userId, int $maxRows = 24, int $maxTotalChars = 4000): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $conn->prepare('SELECT pref_key, pref_value FROM ai_user_preferences WHERE user_id = ? ORDER BY updated_at DESC LIMIT ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $userId, $maxRows);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $out = [];
        $used = 0;
        foreach ($rows as $row) {
            $k = (string) ($row['pref_key'] ?? '');
            $v = (string) ($row['pref_value'] ?? '');
            if ($k === '' || !ai_user_pref_allowed_key($k)) {
                continue;
            }
            $lineLen = strlen($k) + strlen($v) + 8;
            if ($used + $lineLen > $maxTotalChars) {
                break;
            }
            $out[] = ['pref_key' => $k, 'pref_value' => $v];
            $used += $lineLen;
        }

        return $out;
    }
}

if (!function_exists('ai_user_pref_upsert')) {
    function ai_user_pref_upsert(mysqli $conn, int $userId, string $key, string $value): bool
    {
        if ($userId <= 0 || !ai_user_pref_allowed_key($key)) {
            return false;
        }
        if (strlen($value) > 8000) {
            return false;
        }
        $sql = 'INSERT INTO ai_user_preferences (user_id, pref_key, pref_value) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = CURRENT_TIMESTAMP';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iss', $userId, $key, $value);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('ai_user_pref_delete')) {
    function ai_user_pref_delete(mysqli $conn, int $userId, string $key): bool
    {
        if ($userId <= 0 || !ai_user_pref_allowed_key($key)) {
            return false;
        }
        $stmt = $conn->prepare('DELETE FROM ai_user_preferences WHERE user_id = ? AND pref_key = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $userId, $key);
        $stmt->execute();
        $stmt->close();

        return true;
    }
}

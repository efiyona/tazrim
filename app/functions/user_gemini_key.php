<?php
declare(strict_types=1);

/**
 * מפתחות Gemini אישיים (ריבוי): הצפנה, שמירה, Ping, masking, רוטציה אוטומטית כשמגיעים למכסה.
 */

if (!function_exists('tazrim_derived_encryption_key_binary')) {
    /** מפתח AES-256 (32 בתים) מ-ENCRYPTION_KEY */
    function tazrim_derived_encryption_key_binary(): string
    {
        if (!defined('ENCRYPTION_KEY')) {
            return str_repeat("\0", 32);
        }

        return hash('sha256', (string) constant('ENCRYPTION_KEY'), true);
    }
}

if (!function_exists('tazrim_encrypt_sensitive_string')) {
    /** IV חדש בכל רשומה — blob: base64(iv || ciphertext raw) */
    function tazrim_encrypt_sensitive_string(string $plaintext): string
    {
        $key = tazrim_derived_encryption_key_binary();
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes((int) $ivLen);
        $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return '';
        }

        return base64_encode($iv . $cipher);
    }
}

if (!function_exists('tazrim_decrypt_sensitive_string')) {
    function tazrim_decrypt_sensitive_string(string $storedBlob): ?string
    {
        $raw = @base64_decode($storedBlob, true);
        if ($raw === false || $raw === '') {
            return null;
        }
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        if (strlen($raw) < $ivLen + 1) {
            return null;
        }
        $iv = substr($raw, 0, $ivLen);
        $cipher = substr($raw, $ivLen);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', tazrim_derived_encryption_key_binary(), OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : null;
    }
}

if (!function_exists('tazrim_gemini_key_format_ok')) {
    function tazrim_gemini_key_format_ok(string $key): bool
    {
        $t = trim($key);

        return $t !== '' && str_starts_with($t, 'AIza') && strlen($t) >= 20;
    }
}

if (!function_exists('tazrim_gemini_key_suffix4')) {
    function tazrim_gemini_key_suffix4(string $plainKey): string
    {
        $t = trim($plainKey);
        if (function_exists('mb_substr')) {
            $s = mb_substr($t, -4, 4, 'UTF-8');
        } else {
            $s = strlen($t) >= 4 ? substr($t, -4) : $t;
        }

        return $s === false ? '' : $s;
    }
}

if (!function_exists('tazrim_gemini_mask_display')) {
    /** תצוגה: AIza… + 4 תווים אחרונים */
    function tazrim_gemini_mask_display(string $suffix4): string
    {
        $s = preg_replace('/[^A-Za-z0-9]/', '', $suffix4);
        if ($s === null || strlen($s) < 1) {
            return 'AIza…****';
        }
        if (strlen($s) > 4) {
            $s = substr($s, -4);
        }

        return 'AIza…' . $s;
    }
}

if (!function_exists('tazrim_gemini_ping_api_key')) {
    /**
     * בדיקת תקינות מפתח מול Google (רשימת מודלים קצרה).
     *
     * @return array{ok:bool, http:int, detail?:string}
     */
    function tazrim_gemini_ping_api_key(string $apiKey): array
    {
        $key = trim($apiKey);
        if ($key === '') {
            return ['ok' => false, 'http' => 0, 'detail' => 'empty'];
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1&key=' . rawurlencode($key);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 200) {
            return ['ok' => true, 'http' => $http];
        }
        if ($http === 400 || $http === 401 || $http === 403) {
            return ['ok' => false, 'http' => $http, 'detail' => 'auth_or_bad_key'];
        }

        return ['ok' => false, 'http' => $http, 'detail' => 'network_or_api'];
    }
}

/** האם לעבור למפתח הבא ממערכת המפתחות של המשתמש (מכסת API / מפתח חסום וכו׳). */
if (!function_exists('tazrim_gemini_response_should_rotate_to_next_user_api_key')) {
    function tazrim_gemini_response_should_rotate_to_next_user_api_key(int $http, ?string $rawBody): bool
    {
        if ($http === 200) {
            return false;
        }
        if ($http === 429) {
            return true;
        }

        $body = (string) ($rawBody ?? '');
        if (stripos($body, 'RESOURCE_EXHAUSTED') !== false) {
            return true;
        }

        $decoded = json_decode($body, true);
        $status = '';
        $msg = '';
        $code = null;
        if (is_array($decoded)) {
            $e = $decoded['error'] ?? null;
            if (is_array($e)) {
                $status = strtoupper((string) ($e['status'] ?? ''));
                $msg = (string) ($e['message'] ?? '');
                $code = isset($e['code']) ? (int) $e['code'] : null;
                if ($code === 429) {
                    return true;
                }
            }
        }

        if ($status === 'RESOURCE_EXHAUSTED') {
            return true;
        }

        if ($http === 401) {
            return true;
        }

        if ($http === 403 && ($status === 'PERMISSION_DENIED' || preg_match('/billing|quota|API key|limit|exhausted/i', $body) === 1)) {
            return true;
        }

        if ($status === 'INVALID_ARGUMENT' && stripos($msg, 'API key') !== false) {
            return true;
        }

        return false;
    }
}

/**
 * משלוח generateContent (v1beta) עם רוטציית מפתחות משתמש: quota/429/401 ⇒ מפתח הבא.
 *
 * @param list<string> $orderedPlainKeys
 * @param array $body גוף JSON (מערך אסוציאטיבי) לפני ג׳יסון לאנקודה
 * @param list<int>|null $transientRetryableHttp קודים שמנסים חוזר (אותו מפתח לפני מעבר למפתח הבא). 429 ברירתית מזוהה קודם לרוטציה.
 *
 * @return array{ok:bool,http:int,raw:string,curl_err:string}
 */
if (!function_exists('tazrim_user_gemini_v1beta_generate_content_with_key_rotation')) {
    function tazrim_user_gemini_v1beta_generate_content_with_key_rotation(
        array $orderedPlainKeys,
        string $modelName,
        array $body,
        int $timeoutSeconds = 45,
        bool $sslVerifyPeer = false,
        int $maxTransientRetriesPerKey = 2,
        ?array $transientRetryableHttp = null
    ): array {
        $keys = array_values(array_filter($orderedPlainKeys, static fn ($k): bool => is_string($k) && trim($k) !== ''));
        $modelName = trim($modelName);
        if ($keys === [] || $modelName === '') {
            return ['ok' => false, 'http' => 0, 'raw' => '', 'curl_err' => 'missing_key_or_model'];
        }

        $retryableTransient = $transientRetryableHttp ?? [429, 500, 502, 503, 504];
        $payloadJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $lastHttp = 0;
        $lastRaw = '';
        $lastErr = '';

        foreach ($keys as $apiKey) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . rawurlencode(trim($apiKey));
            $attempt = 0;
            while ($attempt < $maxTransientRetriesPerKey) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payloadJson,
                    CURLOPT_SSL_VERIFYPEER => $sslVerifyPeer,
                    CURLOPT_TIMEOUT => $timeoutSeconds,
                ]);
                $raw = curl_exec($ch);
                $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $cerr = curl_error($ch);
                curl_close($ch);
                $lastHttp = $http;
                $lastRaw = is_string($raw) ? $raw : '';
                $lastErr = $cerr;

                if ($http === 200 && $lastRaw !== '') {
                    return ['ok' => true, 'http' => $http, 'raw' => $lastRaw, 'curl_err' => $lastErr];
                }

                $rawBody = $lastRaw !== '' ? $lastRaw : null;
                if (function_exists('tazrim_gemini_response_should_rotate_to_next_user_api_key')
                    && tazrim_gemini_response_should_rotate_to_next_user_api_key($http, $rawBody)) {
                    continue 2;
                }

                if (in_array($http, $retryableTransient, true)) {
                    usleep(500000);
                    $attempt++;

                    continue;
                }

                break;
            }
        }

        return ['ok' => false, 'http' => $lastHttp, 'raw' => $lastRaw, 'curl_err' => $lastErr];
    }
}

/** @return list<string> מפתחות פענוח לפי סדר שימוש רצוי (אותו סדר לרוטציה). */
if (!function_exists('tazrim_user_gemini_plain_keys_ordered')) {
    function tazrim_user_gemini_plain_keys_ordered(mysqli $conn, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $conn->prepare(
            'SELECT `api_key_cipher` FROM `user_gemini_credentials` WHERE `user_id` = ? ORDER BY `sort_order` ASC, `id` ASC'
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $p = tazrim_decrypt_sensitive_string((string) ($row['api_key_cipher'] ?? ''));
                if ($p !== null && trim($p) !== '') {
                    $out[] = $p;
                }
            }
        }
        $stmt->close();

        return $out;
    }
}

if (!function_exists('tazrim_user_has_gemini_key')) {
    function tazrim_user_has_gemini_key(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $conn->prepare('SELECT 1 FROM user_gemini_credentials WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = $res && $res->num_rows > 0;
        $stmt->close();

        return (bool) $ok;
    }
}

if (!function_exists('tazrim_user_get_gemini_key_plain')) {
    /** למורשת קוד ישן — מחזיר את המפתח הראשון בסדר הרוטציה. */
    function tazrim_user_get_gemini_key_plain(mysqli $conn, int $userId): ?string
    {
        $keys = tazrim_user_gemini_plain_keys_ordered($conn, $userId);

        return $keys[0] ?? null;
    }
}

if (!function_exists('tazrim_user_get_gemini_key_mask_parts')) {
    /**
     * @return array{
     *   configured: bool,
     *   mask: string,
     *   key_count: int,
     *   keys?: list<array{id:int,mask:string}>
     * }
     */
    function tazrim_user_get_gemini_key_mask_parts(mysqli $conn, int $userId): array
    {
        $empty = ['configured' => false, 'mask' => '', 'key_count' => 0, 'keys' => []];
        if ($userId <= 0) {
            return $empty;
        }

        $stmt = $conn->prepare(
            'SELECT `id`, `key_suffix` FROM `user_gemini_credentials` WHERE `user_id` = ? ORDER BY `sort_order` ASC, `id` ASC'
        );
        if (!$stmt) {
            return $empty;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'mask' => tazrim_gemini_mask_display((string) ($r['key_suffix'] ?? '')),
                ];
            }
        }
        $stmt->close();

        if ($rows === []) {
            return $empty;
        }

        $masksOnly = array_column($rows, 'mask');
        $maskStr = implode(', ', $masksOnly);
        if (function_exists('mb_strlen') && mb_strlen($maskStr, 'UTF-8') > 180) {
            $maskStr = mb_substr($maskStr, 0, 176, 'UTF-8') . '…';
        } elseif (strlen($maskStr) > 180) {
            $maskStr = substr($maskStr, 0, 176) . '…';
        }

        return [
            'configured' => true,
            'mask' => $maskStr,
            'key_count' => count($rows),
            'keys' => $rows,
        ];
    }
}

if (!function_exists('tazrim_user_save_gemini_key')) {
    /**
     * מוסיף מפתח נוסף למשתמש (או ראשון) — מניעת כפילות מלאה לפני אימות.
     *
     * @return array{ok:bool,code:string,message?:string,mask?:string,key_count?:int}
     */
    function tazrim_user_save_gemini_key(mysqli $conn, int $userId, string $plainKey): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'code' => 'bad_user'];
        }

        $t = trim($plainKey);
        if (!tazrim_gemini_key_format_ok($t)) {
            return ['ok' => false, 'code' => 'gemini_key_invalid', 'message' => 'פורמט המפתח אינו תקין (נדרש מפתח מתחיל ב־AIza).'];
        }

        foreach (tazrim_user_gemini_plain_keys_ordered($conn, $userId) as $existing) {
            if (hash_equals($existing, $t)) {
                return ['ok' => false, 'code' => 'gemini_key_duplicate', 'message' => 'המפתח כבר קיים אצלך ברשימה.'];
            }
        }

        $ping = tazrim_gemini_ping_api_key($t);
        if (empty($ping['ok'])) {
            return [
                'ok' => false,
                'code' => 'gemini_key_invalid',
                'message' => 'המפתח לא עבר אימות מול Google. בדקו שהעתקתם נכון או שנוצר במצב פעיל.',
            ];
        }

        $suffix = tazrim_gemini_key_suffix4($t);
        if (strlen($suffix) < 4 && function_exists('mb_strlen') && mb_strlen($t, 'UTF-8') >= 4) {
            $suffix = mb_substr($t, -4, 4, 'UTF-8');
        }
        if (strlen($suffix) > 4) {
            $suffix = substr((string) $suffix, -4);
        }

        $cipher = tazrim_encrypt_sensitive_string($t);
        if ($cipher === '') {
            return ['ok' => false, 'code' => 'encrypt_failed'];
        }

        $maxStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), -1) AS m FROM user_gemini_credentials WHERE user_id = ?');
        if (!$maxStmt) {
            return ['ok' => false, 'code' => 'db_error'];
        }
        $maxStmt->bind_param('i', $userId);
        $maxStmt->execute();
        $mx = (int) (($maxStmt->get_result()->fetch_assoc()['m'] ?? -1));
        $maxStmt->close();
        $nextOrder = $mx + 1;

        $stmt = $conn->prepare(
            'INSERT INTO user_gemini_credentials (user_id, sort_order, api_key_cipher, key_suffix) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'code' => 'db_error'];
        }
        $stmt->bind_param('iiss', $userId, $nextOrder, $cipher, $suffix);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['ok' => false, 'code' => 'db_error'];
        }

        $parts = tazrim_user_get_gemini_key_mask_parts($conn, $userId);

        return [
            'ok' => true,
            'code' => 'saved',
            'message' => 'המפתח נשמר בהצלחה.',
            'mask' => $parts['mask'] ?? tazrim_gemini_mask_display($suffix),
            'key_count' => (int) ($parts['key_count'] ?? 1),
        ];
    }
}

if (!function_exists('tazrim_user_delete_gemini_key')) {
    /**
     * @param int|null $rowId מזהה רשומה ספציפית; null = מחק את כל מפתחות המשתמש
     */
    function tazrim_user_delete_gemini_key(mysqli $conn, int $userId, ?int $rowId = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($rowId !== null && $rowId > 0) {
            $stmt = $conn->prepare('DELETE FROM user_gemini_credentials WHERE id = ? AND user_id = ?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ii', $rowId, $userId);
            $stmt->execute();
            $ok = $stmt->affected_rows >= 0;
            $stmt->close();

            return $ok;
        }

        $stmt = $conn->prepare('DELETE FROM user_gemini_credentials WHERE user_id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();

        return $ok;
    }
}

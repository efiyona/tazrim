<?php
/**
 * טפסי פופאפ מבוססי form_schema (JSON בקמפיין) — אימות + handlers מאושרים בלבד.
 */
require_once __DIR__ . '/popup_campaign_actions.php';

if (!function_exists('tazrim_popup_campaign_form_schema_from_row')) {
    /**
     * @return array<string,mixed>|null
     */
    function tazrim_popup_campaign_form_schema_from_row(?array $row): ?array
    {
        if ($row === null || empty($row['form_schema'])) {
            return null;
        }
        $raw = trim((string) $row['form_schema']);
        if ($raw === '') {
            return null;
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return null;
        }

        return $j;
    }
}

if (!function_exists('tazrim_popup_campaign_validate_form_schema_shape')) {
    /**
     * @return string|null הודעת שגיאה או null אם תקין
     */
    function tazrim_popup_campaign_validate_form_schema_shape(array $schema): ?string
    {
        $handler = isset($schema['handler']) ? trim((string) $schema['handler']) : '';
        $allowedHandlers = ['submission_store', 'bank_balance'];
        if (!in_array($handler, $allowedHandlers, true)) {
            return 'handler לא נתמך (submission_store | bank_balance)';
        }
        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            return 'חסר מערך fields';
        }
        if (count($schema['fields']) > 50) {
            return 'יותר מדי שדות';
        }
        foreach ($schema['fields'] as $i => $f) {
            if (!is_array($f)) {
                return 'שדה לא תקין';
            }
            $name = isset($f['name']) ? trim((string) $f['name']) : '';
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
                return 'שם שדה לא תקין: ' . $name;
            }
            $type = isset($f['type']) ? trim((string) $f['type']) : 'text';
            if (!in_array($type, ['text', 'textarea', 'number', 'email', 'tel', 'checkbox'], true)) {
                return 'סוג שדה לא נתמך: ' . $type;
            }
            if (isset($f['maxLength'])) {
                $ml = (int) $f['maxLength'];
                if ($ml < 1 || $ml > 32000) {
                    return 'maxLength לא תקין';
                }
            }
        }

        if ($handler === 'bank_balance') {
            $names = [];
            foreach ($schema['fields'] as $f) {
                if (is_array($f) && isset($f['name'])) {
                    $names[] = (string) $f['name'];
                }
            }
            if ($names !== [] && !in_array('bank_balance', $names, true)) {
                return 'ב-handler bank_balance חובה שדה name=bank_balance';
            }
        }

        return null;
    }
}

if (!function_exists('tazrim_popup_campaign_extract_form_values')) {
    /**
     * ערכי טופס מהגוף (ללא מטא).
     *
     * @return array<string,string>
     */
    function tazrim_popup_campaign_extract_form_values(array $body): array
    {
        $skip = ['campaign_id' => true, 'action' => true];
        $out = [];
        foreach ($body as $k => $v) {
            if (isset($skip[$k])) {
                continue;
            }
            if (!is_string($k) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $k)) {
                continue;
            }
            if (is_bool($v)) {
                $out[$k] = $v ? '1' : '';
            } elseif (is_numeric($v)) {
                $out[$k] = (string) $v;
            } elseif (is_string($v)) {
                $out[$k] = $v;
            } else {
                $out[$k] = '';
            }
        }

        return $out;
    }
}

if (!function_exists('tazrim_popup_campaign_validate_fields_against_schema')) {
    /**
     * @param array<int,array<string,mixed>> $fieldDefs
     * @param array<string,string> $values
     * @return array{ok:bool,values?:array<string,string>,error?:string}
     */
    function tazrim_popup_campaign_validate_fields_against_schema(array $fieldDefs, array $values): array
    {
        $out = [];
        foreach ($fieldDefs as $f) {
            if (!is_array($f)) {
                return ['ok' => false, 'error' => 'הגדרת שדה שגויה'];
            }
            $name = isset($f['name']) ? (string) $f['name'] : '';
            $type = isset($f['type']) ? (string) $f['type'] : 'text';
            $required = !empty($f['required']);
            $maxLen = isset($f['maxLength']) ? (int) $f['maxLength'] : ($type === 'textarea' ? 8000 : 2000);
            if ($maxLen < 1) {
                $maxLen = 2000;
            }
            $raw = array_key_exists($name, $values) ? trim((string) $values[$name]) : '';
            if ($type === 'checkbox') {
                $raw = (isset($values[$name]) && (string) $values[$name] !== '' && (string) $values[$name] !== '0') ? '1' : '';
            }
            if ($required && $raw === '') {
                return ['ok' => false, 'error' => 'שדה חובה: ' . $name];
            }
            if ($raw !== '' && function_exists('mb_strlen')) {
                if (mb_strlen($raw, 'UTF-8') > $maxLen) {
                    return ['ok' => false, 'error' => 'שדה ארוך מדי: ' . $name];
                }
            } elseif ($raw !== '' && strlen($raw) > $maxLen) {
                return ['ok' => false, 'error' => 'שדה ארוך מדי: ' . $name];
            }
            if ($raw !== '' && $type === 'number') {
                if (!is_numeric(str_replace([',', ' '], '', $raw))) {
                    return ['ok' => false, 'error' => 'מספר לא תקין: ' . $name];
                }
            }
            if ($raw !== '' && $type === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'אימייל לא תקין: ' . $name];
            }
            $out[$name] = $raw;
        }

        return ['ok' => true, 'values' => $out];
    }
}

if (!function_exists('tazrim_popup_campaign_process_form_schema_submit')) {
    /**
     * @return array{status:string, acked?:bool, message?:string}
     */
    function tazrim_popup_campaign_process_form_schema_submit(
        mysqli $conn,
        int $user_id,
        ?int $home_id,
        int $campaign_id,
        array $schema,
        array $body
    ): array {
        $err = tazrim_popup_campaign_validate_form_schema_shape($schema);
        if ($err !== null) {
            return ['status' => 'error', 'message' => $err];
        }
        $handler = (string) $schema['handler'];
        /** @var array<int,array<string,mixed>> $fieldDefs */
        $fieldDefs = $schema['fields'];

        if ($handler === 'bank_balance' && $fieldDefs === []) {
            $fieldDefs = [
                ['name' => 'bank_balance', 'type' => 'text', 'required' => true, 'maxLength' => 40],
            ];
        }

        $valuesIn = tazrim_popup_campaign_extract_form_values($body);
        $chk = tazrim_popup_campaign_validate_fields_against_schema($fieldDefs, $valuesIn);
        if (!$chk['ok']) {
            return ['status' => 'error', 'message' => $chk['error'] ?? 'שגיאת ולידציה'];
        }
        /** @var array<string,string> $clean */
        $clean = $chk['values'] ?? [];

        $crow = selectOne('popup_campaigns', ['id' => $campaign_id]);
        $policy = isset($crow['ack_policy']) ? (string) $crow['ack_policy'] : 'each_user';
        if (!in_array($policy, ['each_user', 'one_per_home', 'primary_only'], true)) {
            $policy = 'each_user';
        }

        if ($handler === 'bank_balance') {
            $merged = $body;
            $merged['bank_balance'] = $clean['bank_balance'] ?? '';

            return tazrim_popup_action_save_bank_balance($conn, $user_id, $home_id, $campaign_id, $merged);
        }

        if ($handler === 'submission_store') {
            $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
            if ($json === false || strlen($json) > 2000000) {
                return ['status' => 'error', 'message' => 'נתונים גדולים מדי'];
            }
            $uid = (int) $user_id;
            $cid = (int) $campaign_id;
            $hid = $home_id !== null && $home_id > 0 ? (int) $home_id : 'NULL';
            $jsonEsc = mysqli_real_escape_string($conn, $json);

            mysqli_begin_transaction($conn);
            try {
                $ins = "INSERT INTO `popup_campaign_form_submissions` (`campaign_id`, `user_id`, `home_id`, `payload_json`)
                        VALUES ({$cid}, {$uid}, {$hid}, '{$jsonEsc}')";
                if (!mysqli_query($conn, $ins)) {
                    throw new RuntimeException(mysqli_error($conn));
                }
                if (!tazrim_popup_campaign_insert_ack_with_policy($conn, $user_id, $home_id, $campaign_id, $policy)) {
                    throw new RuntimeException('ack');
                }
                mysqli_commit($conn);
            } catch (Throwable $e) {
                mysqli_rollback($conn);

                return ['status' => 'error', 'message' => 'שגיאת שמירה'];
            }

            return ['status' => 'ok', 'acked' => true];
        }

        return ['status' => 'error', 'message' => 'handler לא ידוע'];
    }
}

<?php
/**
 * פעולות פופאפ קמפיין — רישום מרכזי (whitelist).
 *
 * כל פעולה חדשה: מימוש כאן + תיעוד ב-popup_html_guide.md (שמות שדות ב-HTML).
 * ה-HTML ב-body_html לא מגדיר לאן נשמרים נתונים — רק שמות שדות; היעד נקבע בקוד ה-handler.
 */

if (!function_exists('tazrim_popup_action_save_bank_balance')) {
    /**
     * @return array{status:string, acked?:bool, message?:string}
     */
    function tazrim_popup_action_save_bank_balance(mysqli $conn, int $uid, ?int $home_id, int $campaign_id, array $body): array
    {
        if ($home_id === null || $home_id <= 0) {
            return ['status' => 'error', 'message' => 'נדרש בית פעיל לעדכון יתרה.'];
        }
        $bank_raw = isset($body['bank_balance']) ? trim((string) $body['bank_balance']) : '';
        $parsed = tazrim_parse_bank_balance_input($bank_raw);
        if ($parsed === null) {
            return ['status' => 'error', 'message' => 'נא להזין יתרת בנק תקינה.'];
        }

        $today = date('Y-m-d');
        $crow = selectOne('popup_campaigns', ['id' => $campaign_id]);
        $policy = isset($crow['ack_policy']) ? (string) $crow['ack_policy'] : 'each_user';
        if (!in_array($policy, ['each_user', 'one_per_home', 'primary_only'], true)) {
            $policy = 'each_user';
        }

        mysqli_begin_transaction($conn);
        try {
            tazrim_apply_user_bank_balance_target($conn, $home_id, $parsed, $today);
            if (!tazrim_popup_campaign_insert_ack_with_policy($conn, $uid, $home_id, $campaign_id, $policy)) {
                throw new RuntimeException('ack');
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);

            return ['status' => 'error', 'message' => 'שגיאת שמירה'];
        }

        return ['status' => 'ok', 'acked' => true];
    }
}

if (!function_exists('tazrim_popup_campaign_action_handlers')) {
    /**
     * @return array<string, callable(mysqli,int,?int,int,array): array>
     */
    function tazrim_popup_campaign_action_handlers(): array
    {
        return [
            'save_bank_balance' => 'tazrim_popup_action_save_bank_balance',
        ];
    }
}

if (!function_exists('tazrim_popup_campaign_run_action')) {
    /**
     * מריץ פעולה רשומה בלבד.
     *
     * @return array{status:string, acked?:bool, message?:string}
     */
    function tazrim_popup_campaign_run_action(
        mysqli $conn,
        int $user_id,
        ?int $home_id,
        int $campaign_id,
        string $action,
        array $body
    ): array {
        $action = trim($action);
        if ($action === '' || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $action)) {
            return ['status' => 'error', 'message' => 'פעולה לא תקינה'];
        }
        $handlers = tazrim_popup_campaign_action_handlers();
        if (!isset($handlers[$action])) {
            return ['status' => 'error', 'message' => 'פעולה לא נתמכת'];
        }
        $cb = $handlers[$action];
        if (!is_callable($cb)) {
            return ['status' => 'error', 'message' => 'פעולה לא זמינה'];
        }

        return $cb($conn, $user_id, $home_id, $campaign_id, $body);
    }
}

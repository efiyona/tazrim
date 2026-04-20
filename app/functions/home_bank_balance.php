<?php
/**
 * יתרת בנק: ledger ממומש (מתנועות) + יישור ידני, וחישוב תצוגה (כולל הוצאות עתידיות).
 */

if (!function_exists('tazrim_ledger_effect_for_transaction')) {
    /**
     * תרומה ל־ledger ממומש לפעולה בודדת (0 אם התאריך עתידי).
     */
    function tazrim_ledger_effect_for_transaction(string $type, float $amount, string $transaction_date, string $today): float
    {
        if ($transaction_date > $today) {
            return 0.0;
        }
        if ($type === 'income') {
            return $amount;
        }
        if ($type === 'expense') {
            return -$amount;
        }
        return 0.0;
    }
}

if (!function_exists('tazrim_home_ledger_plain_from_db')) {
    /**
     * חישוב ledger ממומש ישירות מהטבלה (ללא שימוש ב-cache).
     */
    function tazrim_home_ledger_plain_from_db(mysqli $conn, int $home_id, string $today): float
    {
        $today_esc = mysqli_real_escape_string($conn, $today);
        $hid = (int) $home_id;
        $sql = "SELECT 
            COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date <= '$today_esc' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date <= '$today_esc' THEN amount ELSE 0 END), 0) AS ledger
            FROM transactions WHERE home_id = $hid";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return 0.0;
        }
        $row = mysqli_fetch_assoc($res);
        return (float) ($row['ledger'] ?? 0);
    }
}

if (!function_exists('tazrim_home_future_expenses_sum')) {
    /**
     * סכום הוצאות עם transaction_date > היום (גלובלי לבית).
     */
    function tazrim_home_future_expenses_sum(mysqli $conn, int $home_id, string $today): float
    {
        $today_esc = mysqli_real_escape_string($conn, $today);
        $hid = (int) $home_id;
        $sql = "SELECT COALESCE(SUM(amount), 0) AS s FROM transactions 
                WHERE home_id = $hid AND type = 'expense' AND transaction_date > '$today_esc'";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return 0.0;
        }
        $row = mysqli_fetch_assoc($res);
        return (float) ($row['s'] ?? 0);
    }
}

if (!function_exists('tazrim_recompute_home_ledger_cached_from_db')) {
    /**
     * מסנכרן את bank_balance_ledger_cached לפי SUM ממומש (לא משנה adjustment).
     *
     * @param string|null $today תאריך "היום" לחישוב ממומש (ברירת מחדל: היום לפי שרת)
     */
    function tazrim_recompute_home_ledger_cached_from_db(mysqli $conn, int $home_id, ?string $today = null): void
    {
        $today = $today ?? date('Y-m-d');
        $plain = tazrim_home_ledger_plain_from_db($conn, $home_id, $today);
        $enc = encryptBalance($plain);
        if ($enc === null) {
            $enc = encryptBalance(0.0);
        }
        $enc_esc = mysqli_real_escape_string($conn, $enc);
        $hid = (int) $home_id;
        mysqli_query($conn, "UPDATE homes SET bank_balance_ledger_cached = '$enc_esc' WHERE id = $hid LIMIT 1");
    }
}

if (!function_exists('tazrim_recompute_home_ledger_cached_from_db_all_homes')) {
    function tazrim_recompute_home_ledger_cached_from_db_all_homes(mysqli $conn): void
    {
        $res = mysqli_query($conn, 'SELECT id FROM homes');
        if (!$res) {
            return;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                tazrim_recompute_home_ledger_cached_from_db($conn, $id);
            }
        }
    }
}

if (!function_exists('tazrim_adjust_home_ledger_cached_by_delta')) {
    function tazrim_adjust_home_ledger_cached_by_delta(mysqli $conn, int $home_id, float $delta): void
    {
        if (abs($delta) < 1e-12) {
            return;
        }
        $home = selectOne('homes', ['id' => $home_id]);
        if (!$home) {
            return;
        }
        $cur = isset($home['bank_balance_ledger_cached']) ? (float) $home['bank_balance_ledger_cached'] : 0.0;
        $new = $cur + $delta;
        $enc = encryptBalance($new);
        if ($enc === null) {
            $enc = encryptBalance(0.0);
        }
        $enc_esc = mysqli_real_escape_string($conn, $enc);
        $hid = (int) $home_id;
        mysqli_query($conn, "UPDATE homes SET bank_balance_ledger_cached = '$enc_esc' WHERE id = $hid LIMIT 1");
    }
}

if (!function_exists('tazrim_set_home_manual_adjustment_plain')) {
    function tazrim_set_home_manual_adjustment_plain(mysqli $conn, int $home_id, float $plain): void
    {
        $enc = encryptBalance($plain);
        if ($enc === null) {
            $enc = encryptBalance(0.0);
        }
        $enc_esc = mysqli_real_escape_string($conn, $enc);
        $hid = (int) $home_id;
        mysqli_query($conn, "UPDATE homes SET bank_balance_manual_adjustment = '$enc_esc' WHERE id = $hid LIMIT 1");
    }
}

if (!function_exists('tazrim_reset_home_bank_balance_fields')) {
    function tazrim_reset_home_bank_balance_fields(mysqli $conn, int $home_id): void
    {
        $z = encryptBalance(0.0);
        if ($z === null) {
            $z = '';
        }
        $z_esc = mysqli_real_escape_string($conn, $z);
        $hid = (int) $home_id;
        mysqli_query(
            $conn,
            "UPDATE homes SET bank_balance_ledger_cached = '$z_esc', bank_balance_manual_adjustment = '$z_esc' WHERE id = $hid LIMIT 1"
        );
    }
}

if (!function_exists('tazrim_apply_user_bank_balance_target')) {
    /**
     * משתמש מזין את היתרה שרוצה לראות (תצוגה); מעדכן adjustment בלבד.
     * display = ledger_cached + adj - future  =>  adj = target - ledger_cached + future
     *
     * חשוב: ledger חייב להיות זהה לערך ב־bank_balance_ledger_cached (אחרי פענוח), לא רק SUM נפרד —
     * אחרת cache מפולג/NULL יגרום לערך שמוצג אחרי שמירה להיות שונה מהמטרה.
     */
    function tazrim_apply_user_bank_balance_target(mysqli $conn, int $home_id, float $target_display, string $today): void
    {
        tazrim_recompute_home_ledger_cached_from_db($conn, $home_id, $today);
        $home = selectOne('homes', ['id' => $home_id]);
        if (!$home) {
            return;
        }
        $ledger = isset($home['bank_balance_ledger_cached']) ? (float) $home['bank_balance_ledger_cached'] : 0.0;
        $future = tazrim_home_future_expenses_sum($conn, $home_id, $today);
        $adj = $target_display - $ledger + $future;
        tazrim_set_home_manual_adjustment_plain($conn, $home_id, $adj);
    }
}

if (!function_exists('tazrim_home_display_bank_balance')) {
    /**
     * @return array{ledger_dec: float, adjustment_dec: float, future_expenses_sum: float, display: float}
     */
    function tazrim_home_display_bank_balance(mysqli $conn, int $home_id, string $today_il): array
    {
        $home = selectOne('homes', ['id' => $home_id]);
        $ledger_dec = isset($home['bank_balance_ledger_cached']) ? (float) $home['bank_balance_ledger_cached'] : 0.0;
        $adjustment_dec = isset($home['bank_balance_manual_adjustment']) ? (float) $home['bank_balance_manual_adjustment'] : 0.0;
        $future = tazrim_home_future_expenses_sum($conn, $home_id, $today_il);
        $display = $ledger_dec + $adjustment_dec - $future;
        return [
            'ledger_dec' => $ledger_dec,
            'adjustment_dec' => $adjustment_dec,
            'future_expenses_sum' => $future,
            'display' => $display,
        ];
    }
}

if (!function_exists('tazrim_after_transaction_row_change')) {
    /**
     * עדכון ledger לפי שינוי בשורה (לפני/אחרי מחיקה — העבר old בלבד; אחרי עדכון — old+new).
     */
    function tazrim_after_transaction_row_change(mysqli $conn, int $home_id, ?array $oldRow, ?array $newRow, string $today): void
    {
        $effOld = 0.0;
        if ($oldRow !== null) {
            $effOld = tazrim_ledger_effect_for_transaction(
                (string) ($oldRow['type'] ?? ''),
                (float) ($oldRow['amount'] ?? 0),
                (string) ($oldRow['transaction_date'] ?? ''),
                $today
            );
        }
        $effNew = 0.0;
        if ($newRow !== null) {
            $effNew = tazrim_ledger_effect_for_transaction(
                (string) ($newRow['type'] ?? ''),
                (float) ($newRow['amount'] ?? 0),
                (string) ($newRow['transaction_date'] ?? ''),
                $today
            );
        }
        $delta = $effNew - $effOld;
        tazrim_adjust_home_ledger_cached_by_delta($conn, $home_id, $delta);
    }
}

if (!function_exists('tazrim_migrate_single_home_from_initial_balance')) {
    /**
     * מיגרציה מ־initial_balance גולמי (מוצפן) לשדות החדשים.
     */
    function tazrim_migrate_single_home_from_initial_balance(mysqli $conn, int $home_id, ?string $raw_initial_balance, string $today): bool
    {
        $old_initial = decryptBalance($raw_initial_balance);
        $ledger_plain = tazrim_home_ledger_plain_from_db($conn, $home_id, $today);
        $adj_plain = $old_initial;

        $le = encryptBalance($ledger_plain);
        $ae = encryptBalance($adj_plain);
        if ($le === null) {
            $le = encryptBalance(0.0);
        }
        if ($ae === null) {
            $ae = encryptBalance(0.0);
        }
        $le_esc = mysqli_real_escape_string($conn, $le);
        $ae_esc = mysqli_real_escape_string($conn, $ae);
        $show = (abs($old_initial) > 1e-9) ? 1 : 0;
        $hid = (int) $home_id;
        $ok = mysqli_query(
            $conn,
            "UPDATE homes SET bank_balance_ledger_cached = '$le_esc', bank_balance_manual_adjustment = '$ae_esc', show_bank_balance = $show WHERE id = $hid LIMIT 1"
        );
        if (!$ok || mysqli_errno($conn)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('tazrim_run_homes_bank_balance_data_migration')) {
    /**
     * מריץ העתקה מ־initial_balance לשדות החדשים ומוחק את העמודה רק אם כל ה־UPDATE הצליחו.
     *
     * @return array{ok:bool,message:string,dropped?:bool,homes_ok?:int,homes_fail?:int}
     */
    function tazrim_run_homes_bank_balance_data_migration(mysqli $conn): array
    {
        $rOld = @mysqli_query($conn, "SHOW COLUMNS FROM `homes` LIKE 'initial_balance'");
        if (!$rOld || mysqli_num_rows($rOld) === 0) {
            return ['ok' => true, 'message' => 'אין עמודת initial_balance — כבר הומר או לא קיים.', 'dropped' => false];
        }
        @set_time_limit(0);
        $today = date('Y-m-d');
        $res = mysqli_query($conn, 'SELECT id, initial_balance FROM homes');
        if (!$res) {
            return ['ok' => false, 'message' => 'SELECT מ־homes נכשל: ' . mysqli_error($conn)];
        }
        $nOk = 0;
        $nFail = 0;
        while ($row = mysqli_fetch_assoc($res)) {
            $hid = (int) ($row['id'] ?? 0);
            if ($hid <= 0) {
                continue;
            }
            if (tazrim_migrate_single_home_from_initial_balance($conn, $hid, $row['initial_balance'] ?? null, $today)) {
                $nOk++;
            } else {
                $nFail++;
            }
        }
        if ($nFail > 0) {
            return [
                'ok' => false,
                'message' => "עדכון נכשל ב־{$nFail} בתים (הצליחו {$nOk}). בדוק error_log / הרשאות / אורך VARCHAR.",
                'dropped' => false,
                'homes_ok' => $nOk,
                'homes_fail' => $nFail,
            ];
        }
        $drop = mysqli_query($conn, 'ALTER TABLE `homes` DROP COLUMN `initial_balance`');
        if (!$drop) {
            return [
                'ok' => false,
                'message' => 'DROP initial_balance נכשל: ' . mysqli_error($conn),
                'dropped' => false,
                'homes_ok' => $nOk,
            ];
        }
        return [
            'ok' => true,
            'message' => "הומרו {$nOk} בתים ונמחקה עמודת initial_balance.",
            'dropped' => true,
            'homes_ok' => $nOk,
        ];
    }
}

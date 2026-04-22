<?php
declare(strict_types=1);

/**
 * שליחת מייל המוני מפאנל הניהול — רזולוציית נמענים, רישום DB, PHPMailer.
 */

if (!function_exists('tazrim_admin_mass_email_tables_ok')) {
    function tazrim_admin_mass_email_tables_ok(mysqli $conn): bool
    {
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'admin_email_broadcasts'");
        if (!($r instanceof mysqli_result) || mysqli_num_rows($r) === 0) {
            return false;
        }
        mysqli_free_result($r);
        $r2 = @mysqli_query($conn, "SHOW TABLES LIKE 'admin_email_broadcast_logs'");
        if (!($r2 instanceof mysqli_result) || mysqli_num_rows($r2) === 0) {
            return false;
        }
        mysqli_free_result($r2);

        return true;
    }
}

if (!function_exists('tazrim_admin_mass_email_resolve_recipients')) {
    /**
     * @param 'all_users'|'all_homes'|'homes'|'users' $targetType
     * @param list<int> $ids home_ids או user_ids לפי הסוג
     * @return array{ok:bool, rows?:list<array{email:string,user_id:?int,home_id:?int}>, error?:string, count?:int}
     */
    function tazrim_admin_mass_email_resolve_recipients(mysqli $conn, string $targetType, array $ids): array
    {
        $allowed = ['all_users', 'all_homes', 'homes', 'users'];
        if (!in_array($targetType, $allowed, true)) {
            return ['ok' => false, 'error' => 'invalid_target'];
        }

        $byEmail = [];

        $addRow = static function (string $email, ?int $uid, ?int $hid) use (&$byEmail): void {
            $e = trim($email);
            if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $k = strtolower($e);
            if (!isset($byEmail[$k])) {
                $byEmail[$k] = ['email' => $e, 'user_id' => $uid, 'home_id' => $hid];
            }
        };

        if ($targetType === 'all_users') {
            $sql = "SELECT id, email, home_id FROM users WHERE email IS NOT NULL AND TRIM(email) <> ''";
            $res = mysqli_query($conn, $sql);
            if (!$res) {
                return ['ok' => false, 'error' => 'db_query_failed'];
            }
            while ($row = mysqli_fetch_assoc($res)) {
                $addRow((string) ($row['email'] ?? ''), (int) ($row['id'] ?? 0) ?: null, isset($row['home_id']) ? (int) $row['home_id'] : null);
            }
            mysqli_free_result($res);
        } elseif ($targetType === 'all_homes') {
            $sql = "SELECT id, email, home_id FROM users WHERE home_id IS NOT NULL AND home_id > 0 AND email IS NOT NULL AND TRIM(email) <> ''";
            $res = mysqli_query($conn, $sql);
            if (!$res) {
                return ['ok' => false, 'error' => 'db_query_failed'];
            }
            while ($row = mysqli_fetch_assoc($res)) {
                $addRow((string) ($row['email'] ?? ''), (int) ($row['id'] ?? 0) ?: null, (int) ($row['home_id'] ?? 0) ?: null);
            }
            mysqli_free_result($res);
        } elseif ($targetType === 'homes') {
            $hids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $x): bool => $x > 0)));
            if ($hids === []) {
                return ['ok' => false, 'error' => 'no_home_ids'];
            }
            if (count($hids) > 500) {
                return ['ok' => false, 'error' => 'too_many_homes'];
            }
            $ph = implode(',', array_fill(0, count($hids), '?'));
            $types = str_repeat('i', count($hids));
            $sql = "SELECT id, email, home_id FROM users WHERE home_id IN ({$ph}) AND email IS NOT NULL AND TRIM(email) <> ''";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return ['ok' => false, 'error' => 'db_prepare_failed'];
            }
            $stmt->bind_param($types, ...$hids);
            if (!$stmt->execute()) {
                $stmt->close();

                return ['ok' => false, 'error' => 'db_execute_failed'];
            }
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $addRow((string) ($row['email'] ?? ''), (int) ($row['id'] ?? 0) ?: null, (int) ($row['home_id'] ?? 0) ?: null);
            }
            $stmt->close();
        } else {
            $uids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $x): bool => $x > 0)));
            if ($uids === []) {
                return ['ok' => false, 'error' => 'no_user_ids'];
            }
            if (count($uids) > 500) {
                return ['ok' => false, 'error' => 'too_many_users'];
            }
            $ph = implode(',', array_fill(0, count($uids), '?'));
            $types = str_repeat('i', count($uids));
            $sql = "SELECT id, email, home_id FROM users WHERE id IN ({$ph}) AND email IS NOT NULL AND TRIM(email) <> ''";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return ['ok' => false, 'error' => 'db_prepare_failed'];
            }
            $stmt->bind_param($types, ...$uids);
            if (!$stmt->execute()) {
                $stmt->close();

                return ['ok' => false, 'error' => 'db_execute_failed'];
            }
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $addRow((string) ($row['email'] ?? ''), (int) ($row['id'] ?? 0) ?: null, isset($row['home_id']) ? (int) $row['home_id'] : null);
            }
            $stmt->close();
        }

        $rows = array_values($byEmail);
        $n = count($rows);
        if ($n === 0) {
            return ['ok' => false, 'error' => 'no_valid_recipients', 'count' => 0];
        }
        if ($n > 200) {
            return ['ok' => false, 'error' => 'too_many_recipients', 'count' => $n];
        }

        return ['ok' => true, 'rows' => $rows, 'count' => $n];
    }
}

if (!function_exists('tazrim_admin_mass_email_prepare_html')) {
    function tazrim_admin_mass_email_prepare_html(string $html): string
    {
        if (!function_exists('admin_ai_agent_mail_sanitize_html')) {
            require_once dirname(__DIR__) . '/features/ai_chat/services/agent_send_mail.php';
        }
        $html = admin_ai_agent_mail_sanitize_html($html);
        $pub = admin_ai_chat_resolve_public_base_url();
        if ($pub !== '') {
            $html = admin_ai_agent_mail_absolutize_root_paths($html, $pub);
        }
        $wrapped = admin_ai_agent_mail_wrap_branded_layout($html);

        return $wrapped !== '' ? $wrapped : $html;
    }
}

if (!function_exists('tazrim_admin_mass_email_send_one')) {
    /**
     * @return array{ok:bool, detail?:string}
     */
    function tazrim_admin_mass_email_send_one(string $toEmail, string $subject, string $htmlBody, string $altBody): array
    {
        if (!defined('ROOT_PATH')) {
            return ['ok' => false, 'detail' => 'ROOT_PATH_missing'];
        }
        if (!defined('MAIL_HOST')) {
            $sec = ROOT_PATH . '/secrets.php';
            if (is_file($sec)) {
                require_once $sec;
            }
        }
        if (!defined('MAIL_HOST') || !defined('MAIL_USERNAME') || !defined('MAIL_PASSWORD') || (string) MAIL_HOST === '') {
            return ['ok' => false, 'detail' => 'mail_not_configured'];
        }

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            require_once ROOT_PATH . '/vendor/autoload.php';
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            $fromAddress = defined('MAIL_FROM_ADDRESS') && trim((string) constant('MAIL_FROM_ADDRESS')) !== ''
                ? trim((string) constant('MAIL_FROM_ADDRESS'))
                : trim((string) MAIL_USERNAME);
            $fromName = defined('MAIL_FROM_NAME') && trim((string) constant('MAIL_FROM_NAME')) !== ''
                ? trim((string) constant('MAIL_FROM_NAME'))
                : 'התזרים';
            $mail->setFrom($fromAddress, $fromName);
            $mail->Subject = $subject;
            $mail->clearAddresses();
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody !== '' ? $altBody : trim(strip_tags($htmlBody));
            $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return ['ok' => false, 'detail' => $mail->ErrorInfo ?: $e->getMessage()];
        }

        return ['ok' => true];
    }
}

if (!function_exists('tazrim_admin_mass_email_run_broadcast')) {
    /**
     * יוצר רשומת broadcast + לוגים, ושולח לכל נמען. מניח שטבלאות קיימות.
     *
     * @param list<array{email:string,user_id:?int,home_id:?int}> $rows
     * @return array{ok:bool, broadcast_id?:int, message?:string, error?:string}
     */
    function tazrim_admin_mass_email_run_broadcast(
        mysqli $conn,
        int $adminUserId,
        string $targetType,
        ?string $targetJson,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $rows
    ): array {
        $subject = trim($subject);
        if ($subject === '' || (function_exists('mb_strlen') ? mb_strlen($subject, 'UTF-8') : strlen($subject)) > 500) {
            return ['ok' => false, 'error' => 'invalid_subject'];
        }
        $htmlBody = tazrim_admin_mass_email_prepare_html($htmlBody);
        $textBody = trim($textBody);
        if (strlen($htmlBody) > 500000) {
            $htmlBody = function_exists('mb_substr') ? mb_substr($htmlBody, 0, 500000, 'UTF-8') : substr($htmlBody, 0, 500000);
        }

        $total = count($rows);
        $tj = $targetJson;
        $stmt = $conn->prepare(
            'INSERT INTO admin_email_broadcasts (admin_user_id, target_type, target_json, subject, html_body, text_body, status, recipient_total, sent_ok, sent_fail) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'db_prepare_failed'];
        }
        $status = 'pending';
        $z = 0;
        $types = 'i' . str_repeat('s', 6) . 'iii';
        $stmt->bind_param(
            $types,
            $adminUserId,
            $targetType,
            $tj,
            $subject,
            $htmlBody,
            $textBody,
            $status,
            $total,
            $z,
            $z
        );
        if (!$stmt->execute()) {
            $stmt->close();

            return ['ok' => false, 'error' => 'db_insert_broadcast'];
        }
        $bid = (int) $stmt->insert_id;
        $stmt->close();

        @mysqli_query($conn, "UPDATE admin_email_broadcasts SET status='sending', started_at=NOW() WHERE id={$bid}");

        $ok = 0;
        $fail = 0;
        $firstErr = '';

        foreach ($rows as $row) {
            $em = (string) ($row['email'] ?? '');
            $uid = isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null;
            $hid = isset($row['home_id']) && $row['home_id'] !== null ? (int) $row['home_id'] : null;
            $emEsc = mysqli_real_escape_string($conn, $em);
            $uidSql = $uid !== null && $uid > 0 ? (string) $uid : 'NULL';
            $hidSql = $hid !== null && $hid > 0 ? (string) $hid : 'NULL';
            $insSql = "INSERT INTO admin_email_broadcast_logs (broadcast_id, recipient_email, user_id, home_id, status) VALUES ({$bid}, '{$emEsc}', {$uidSql}, {$hidSql}, 'pending')";
            if (!@mysqli_query($conn, $insSql)) {
                $fail++;
                if ($firstErr === '') {
                    $firstErr = 'log_insert_failed';
                }
                continue;
            }
            $logId = (int) mysqli_insert_id($conn);

            $send = tazrim_admin_mass_email_send_one($em, $subject, $htmlBody, $textBody);
            if ($send['ok']) {
                $ok++;
                @mysqli_query($conn, "UPDATE admin_email_broadcast_logs SET status='sent', error_message=NULL, detail=NULL WHERE id={$logId}");
            } else {
                $fail++;
                $detail = (string) ($send['detail'] ?? 'send_failed');
                if ($firstErr === '') {
                    $firstErr = $detail;
                }
                $dShort = function_exists('mb_substr') ? mb_substr($detail, 0, 1900, 'UTF-8') : substr($detail, 0, 1900);
                $dEsc = mysqli_real_escape_string($conn, $dShort);
                @mysqli_query(
                    $conn,
                    "UPDATE admin_email_broadcast_logs SET status='failed', error_message='{$dEsc}', detail='{$dEsc}' WHERE id={$logId}"
                );
            }
        }

        $sum = $fail === 0 ? null : ($firstErr !== '' ? (function_exists('mb_substr') ? mb_substr($firstErr, 0, 1900, 'UTF-8') : substr($firstErr, 0, 1900)) : 'partial_failures');
        $final = $fail === $total ? 'failed' : 'completed';
        $sumSql = $sum !== null ? "'" . mysqli_real_escape_string($conn, $sum) . "'" : 'NULL';
        @mysqli_query(
            $conn,
            "UPDATE admin_email_broadcasts SET status='{$final}', sent_ok={$ok}, sent_fail={$fail}, error_summary={$sumSql}, completed_at=NOW() WHERE id={$bid}"
        );

        return [
            'ok' => true,
            'broadcast_id' => $bid,
            'message' => "נשלחו {$ok} מיילים, נכשלו {$fail}.",
        ];
    }
}

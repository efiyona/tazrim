<?php
declare(strict_types=1);

/**
 * שליחת מייל מתוך סוכן הפאנל — מתוקן ומאובטח.
 */

if (!function_exists('admin_ai_chat_resolve_public_base_url')) {
    /**
     * בסיס ציבורי לקישורים במייל / מחוץ לאפליקציה — זהה לרוח של path.php (BASE_URL = SITE_URL).
     * תמיד מסתיים ב־/ (או מחרוזת ריקה אם אין הגדרה).
     */
    function admin_ai_chat_resolve_public_base_url(): string
    {
        if (!defined('BASE_URL')) {
            return '';
        }
        $raw = trim((string) BASE_URL);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            return rtrim($raw, '/') . '/';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return rtrim($raw, '/') . '/';
        }
        $path = '/' . ltrim($raw, '/');

        return $scheme . '://' . $host . rtrim($path, '/') . '/';
    }
}

if (!function_exists('admin_ai_agent_mail_absolutize_root_paths')) {
    /**
     * הופך href="/..." ו-src="/..." לכתובות מלאות מול APP base — נדרש במייל כשהאתר ב-subpath.
     *
     * @param non-empty-string $publicBase
     */
    function admin_ai_agent_mail_absolutize_root_paths(string $html, string $publicBase): string
    {
        $publicBase = rtrim($publicBase, '/') . '/';
        if ($publicBase === '/' || !preg_match('#^https?://#i', $publicBase)) {
            return $html;
        }

        foreach (['href', 'src'] as $attr) {
            $attrQ = preg_quote($attr, '/');
            $html = (string) preg_replace_callback(
                '/\b' . $attrQ . '\s*=\s*(["\'])\/(?!\/)([^"\']*)\1/iu',
                static function (array $m) use ($publicBase, $attr): string {
                    $q = $m[1];
                    $tail = (string) ($m[2] ?? '');
                    $full = $publicBase . ltrim($tail, '/');
                    $full = (string) preg_replace('#([^:])//+#', '$1/', $full);

                    return $attr . '=' . $q . htmlspecialchars($full, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $q;
                },
                $html
            );
        }

        return $html;
    }
}

if (!function_exists('admin_ai_agent_mail_sanitize_html')) {
    function admin_ai_agent_mail_sanitize_html(string $html): string
    {
        $out = preg_replace('/<\s*script\b[^>]*>[\s\S]*?<\s*\/\s*script\s*>/iu', '', $html);
        $out = preg_replace('/<\s*iframe\b[^>]*>[\s\S]*?<\s*\/\s*iframe\s*>/iu', '', (string) $out);
        $out = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', (string) $out);

        return (string) $out;
    }
}

if (!function_exists('admin_ai_agent_collect_send_mail_recipients')) {
    /**
     * מאחד user_ids, home_ids ו-emails לרשימת כתובות ייחודיות.
     *
     * @param array<string, mixed> $recipients
     * @return array{ok:bool, emails:list<string>, error?:string, count:int}
     */
    function admin_ai_agent_collect_send_mail_recipients(mysqli $conn, array $recipients): array
    {
        $set = [];
        $emailsIn = isset($recipients['emails']) && is_array($recipients['emails']) ? $recipients['emails'] : [];
        foreach ($emailsIn as $e) {
            $e = trim((string) $e);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $set[strtolower($e)] = $e;
            }
        }

        $uids = isset($recipients['user_ids']) && is_array($recipients['user_ids']) ? $recipients['user_ids'] : [];
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $x): bool => $x > 0)));
        if (count($uids) > 500) {
            return ['ok' => false, 'emails' => [], 'error' => 'too_many_user_ids', 'count' => 0];
        }
        if ($uids !== []) {
            $ph = implode(',', array_fill(0, count($uids), '?'));
            $types = str_repeat('i', count($uids));
            $sql = "SELECT DISTINCT id, email FROM users WHERE id IN ({$ph}) AND email IS NOT NULL AND TRIM(email) <> ''";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // הערה: שימוש בפירוק מערך כאן דורש PHP 8.1 ומעלה. אם יש שגיאה, יש להשתמש ב-call_user_func_array
                $stmt->bind_param($types, ...$uids);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $e = trim((string) ($row['email'] ?? ''));
                        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                            $set[strtolower($e)] = $e;
                        }
                    }
                }
                $stmt->close();
            }
        }

        $hids = isset($recipients['home_ids']) && is_array($recipients['home_ids']) ? $recipients['home_ids'] : [];
        $hids = array_values(array_unique(array_filter(array_map('intval', $hids), static fn (int $x): bool => $x > 0)));
        if (count($hids) > 500) {
            return ['ok' => false, 'emails' => [], 'error' => 'too_many_home_ids', 'count' => 0];
        }
        if ($hids !== []) {
            $ph = implode(',', array_fill(0, count($hids), '?'));
            $types = str_repeat('i', count($hids));
            $sql = "SELECT DISTINCT email FROM users WHERE home_id IN ({$ph}) AND email IS NOT NULL AND TRIM(email) <> ''";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // הערה: שימוש בפירוק מערך כאן דורש PHP 8.1 ומעלה
                $stmt->bind_param($types, ...$hids);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $e = trim((string) ($row['email'] ?? ''));
                        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                            $set[strtolower($e)] = $e;
                        }
                    }
                }
                $stmt->close();
            }
        }

        $list = array_values($set);
        $n = count($list);
        if ($n === 0) {
            return ['ok' => false, 'emails' => [], 'error' => 'no_valid_recipients', 'count' => 0];
        }
        if ($n > 200) {
            return ['ok' => false, 'emails' => [], 'error' => 'too_many_recipients', 'count' => $n];
        }

        return ['ok' => true, 'emails' => $list, 'count' => $n];
    }
}

if (!function_exists('admin_ai_agent_send_mail_execute')) {
    /**
     * @param array<string, mixed> $payload action send_mail + subject + html_body + text_body? + recipients
     * @return array{ok:bool, message?:string, detail?:string, recipients?:int}
     */
    function admin_ai_agent_send_mail_execute(mysqli $conn, int $homeId, int $userId, int $chatId, array $payload): array
    {
        if (!defined('ROOT_PATH')) {
            return ['ok' => false, 'message' => 'ROOT_PATH_missing'];
        }
        if (!defined('MAIL_HOST')) {
            $sec = ROOT_PATH . '/secrets.php';
            if (is_file($sec)) {
                require_once $sec;
            }
        }
        
        // תיקון מס' 1: וידוא שכל הקבועים הוגדרו כדי למנוע קריסת Fatal Error
        if (!defined('MAIL_HOST') || !defined('MAIL_USERNAME') || !defined('MAIL_PASSWORD') || (string) MAIL_HOST === '') {
            return ['ok' => false, 'message' => 'mail_not_configured'];
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        if ($subject === '' || (function_exists('mb_strlen') ? mb_strlen($subject, 'UTF-8') : strlen($subject)) > 200) {
            return ['ok' => false, 'message' => 'invalid_subject'];
        }

        $html = (string) ($payload['html_body'] ?? '');
        $text = trim((string) ($payload['text_body'] ?? ''));
        if ($html === '' && $text === '') {
            return ['ok' => false, 'message' => 'missing_body'];
        }
        
        // תיקון: שימוש ב-mb_substr כדי למנוע חיתוך אותיות עבריות (שיבושי קידוד)
        if (strlen($html) > 200000) {
            $html = function_exists('mb_substr') ? mb_substr($html, 0, 200000, 'UTF-8') : substr($html, 0, 200000);
        }
        if (strlen($text) > 100000) {
            $text = function_exists('mb_substr') ? mb_substr($text, 0, 100000, 'UTF-8') : substr($text, 0, 100000);
        }
        
        if ($html !== '') {
            $html = admin_ai_agent_mail_sanitize_html($html);
            $pub = admin_ai_chat_resolve_public_base_url();
            if ($pub !== '') {
                $html = admin_ai_agent_mail_absolutize_root_paths($html, $pub);
            }
        }

        $rec = isset($payload['recipients']) && is_array($payload['recipients']) ? $payload['recipients'] : [];
        $col = admin_ai_agent_collect_send_mail_recipients($conn, $rec);
        if (!$col['ok']) {
            return ['ok' => false, 'message' => (string) ($col['error'] ?? 'recipients_failed')];
        }
        $emails = $col['emails'];

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
            // שים לב: אם אתה משתמש בפורט 587, ייתכן שתצטרך לשנות כאן ל-ENCRYPTION_STARTTLS
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom('support@trofaplus.com', 'התזרים');
            $mail->Subject = $subject;

            if (empty($emails)) {
                return ['ok' => false, 'message' => 'no_valid_recipients'];
            }
            
            // תיקון מס' 2: פתרון בעיית הפרטיות. שמים את המייל של המערכת ב-To ואת כל השאר ב-Bcc.
            $mail->addAddress('support@trofaplus.com', 'התזרים'); // הנמען הראשי הגלוי
            
            foreach ($emails as $bcc) {
                $mail->addBCC($bcc); // כל המשתמשים יקבלו בעותק נסתר
            }

            if ($html !== '') {
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->AltBody = $text !== '' ? $text : trim(strip_tags($html));
            } else {
                $mail->isHTML(false);
                $mail->Body = $text;
            }

            $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return ['ok' => false, 'message' => 'send_failed', 'detail' => $mail->ErrorInfo ?: $e->getMessage()];
        }

        if (function_exists('admin_ai_agent_exec_log')) {
            admin_ai_agent_exec_log(
                $conn,
                $homeId,
                $userId,
                'Admin AI Agent SEND_MAIL chat=' . $chatId . ' recipients=' . ($col['count'] ?? 0)
            );
        }

        return [
            'ok' => true,
            'message' => 'המייל נשלח ל-' . (string) ($col['count'] ?? 0) . ' נמענים בהצלחה',
            'recipients' => (int) ($col['count'] ?? 0),
        ];
    }
}
<?php
declare(strict_types=1);

/**
 * סידור עבודה בסוכן המשתמש — קטלוג למודל + ביצוע CRUD מאושר (רק החשבון עם work_schedule_enabled).
 */

if (!function_exists('ai_chat_user_work_schedule_db_enabled')) {
    function ai_chat_user_work_schedule_db_enabled(mysqli $conn, int $userId): bool
    {
        $u = selectOne('users', ['id' => $userId]);

        return $u && !empty($u['work_schedule_enabled']);
    }
}

if (!function_exists('ai_chat_ws_agent_job')) {
    function ai_chat_ws_agent_job(mysqli $conn, int $userId, int $jobId): ?array
    {
        $q = mysqli_prepare($conn, 'SELECT * FROM `user_work_jobs` WHERE `id` = ? AND `user_id` = ? LIMIT 1');
        if (!$q) {
            return null;
        }
        mysqli_stmt_bind_param($q, 'ii', $jobId, $userId);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);

        return $row ?: null;
    }
}

if (!function_exists('ai_chat_ws_agent_shift_type')) {
    function ai_chat_ws_agent_shift_type(mysqli $conn, int $userId, int $typeId): ?array
    {
        $q = mysqli_prepare(
            $conn,
            'SELECT t.* FROM `user_work_shift_types` t
             INNER JOIN `user_work_jobs` j ON j.id = t.job_id
             WHERE t.id = ? AND j.user_id = ? LIMIT 1'
        );
        if (!$q) {
            return null;
        }
        mysqli_stmt_bind_param($q, 'ii', $typeId, $userId);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);

        return $row ?: null;
    }
}

if (!function_exists('ai_chat_ws_agent_shift')) {
    function ai_chat_ws_agent_shift(mysqli $conn, int $userId, int $shiftId): ?array
    {
        $q = mysqli_prepare($conn, 'SELECT * FROM `user_work_shifts` WHERE `id` = ? AND `user_id` = ? LIMIT 1');
        if (!$q) {
            return null;
        }
        mysqli_stmt_bind_param($q, 'ii', $shiftId, $userId);
        mysqli_stmt_execute($q);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
        mysqli_stmt_close($q);

        return $row ?: null;
    }
}

if (!function_exists('ai_chat_work_schedule_catalog_for_prompt')) {
    /**
     * בלוק טקסט פנימי ל־Gemini (מיפוי עבודות, סוגי משמרות, משמרות בחודש הנוכחי בלוח ההצגה בסשן).
     */
    function ai_chat_work_schedule_catalog_for_prompt(mysqli $conn, int $userId): string
    {
        if (!ai_chat_user_work_schedule_db_enabled($conn, $userId)) {
            return '';
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $m = (int) ($_SESSION['view_month'] ?? date('n'));
        $y = (int) ($_SESSION['view_year'] ?? date('Y'));
        if ($m < 1 || $m > 12) {
            $m = (int) date('n');
        }
        if ($y < 2000 || $y > 2100) {
            $y = (int) date('Y');
        }

        $lines = [];
        $lines[] = '### מיפוי סידור עבודה (לשימושך בלבד בתוך [[ACTION]] — אסור להציג למשתמש)';
        $lines[] = '';

        $q = mysqli_prepare($conn, 'SELECT id, title FROM `user_work_jobs` WHERE `user_id` = ? ORDER BY `title` ASC');
        if (!$q) {
            return '';
        }
        mysqli_stmt_bind_param($q, 'i', $userId);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        $jobs = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $jobs[] = $row;
        }
        mysqli_stmt_close($q);
        if ($jobs === []) {
            $lines[] = '(אין עבודות מוגדרות — המשתמש צריך להגדיר עבודות בפרופיל/הגדרות.)';

            return implode("\n", $lines);
        }

        $lines[] = '**עבודות (`job_id` → כותרת):**';
        foreach ($jobs as $j) {
            $lines[] = '- `' . (int) $j['id'] . '` → ' . (string) $j['title'];
        }

        $lines[] = '';
        $lines[] = '**סוגי משמרת (`shift_type_id` → שם, job_id, שעות ברירת מחדל):**';
        $tSt = mysqli_prepare(
            $conn,
            'SELECT t.id, t.job_id, t.name, t.default_start_time, t.default_end_time
             FROM `user_work_shift_types` t
             INNER JOIN `user_work_jobs` j ON j.id = t.job_id
             WHERE j.user_id = ?
             ORDER BY j.title ASC, t.sort_order ASC, t.id ASC'
        );
        if (!$tSt) {
            return implode("\n", $lines);
        }
        mysqli_stmt_bind_param($tSt, 'i', $userId);
        mysqli_stmt_execute($tSt);
        $tr = mysqli_stmt_get_result($tSt);
        $typeCount = 0;
        while ($t = mysqli_fetch_assoc($tr)) {
            $ds = substr((string) ($t['default_start_time'] ?? ''), 0, 5);
            $de = substr((string) ($t['default_end_time'] ?? ''), 0, 5);
            $lines[] = '- `' . (int) $t['id'] . '` → ' . ($t['name'] ?? '')
                . ' (job `' . (int) $t['job_id'] . '`, ברירות ' . ($ds ?: '?') . '–' . ($de ?: '?') . ')';
            ++$typeCount;
        }
        mysqli_stmt_close($tSt);
        if ($typeCount === 0) {
            $lines[] = '(אין סוגי משמרות מוגדרים — אפשר shift_type_id=0.)';
        }

        $lines[] = '';
        $lines[] = '**משמרות החודש ' . sprintf('%04d-%02d', $y, $m) . ' (`shift_id` — רק משמרות שמוצגות בלוח ההקשר הנוכחי):**';

        $start = sprintf('%04d-%02d-01 00:00:00', $y, $m);
        $mEnd = $m + 1;
        $yEnd = $y;
        if ($mEnd > 12) {
            $mEnd = 1;
            ++$yEnd;
        }
        $end = sprintf('%04d-%02d-01 00:00:00', $yEnd, $mEnd);

        $sSt = mysqli_prepare(
            $conn,
            'SELECT s.id AS shift_id, s.job_id, s.shift_type_id, s.starts_at, s.ends_at, s.note,
                    j.title AS job_title
             FROM `user_work_shifts` s
             INNER JOIN `user_work_jobs` j ON j.id = s.job_id
             WHERE s.user_id = ? AND s.starts_at >= ? AND s.starts_at < ?
             ORDER BY s.starts_at ASC, s.id ASC
             LIMIT 100'
        );
        if (!$sSt) {
            return implode("\n", $lines);
        }
        mysqli_stmt_bind_param($sSt, 'iss', $userId, $start, $end);
        mysqli_stmt_execute($sSt);
        $sr = mysqli_stmt_get_result($sSt);
        $n = 0;
        while ($s = mysqli_fetch_assoc($sr)) {
            $snippet = mb_substr(trim((string) ($s['note'] ?? '')), 0, 60);
            if ($snippet !== '') {
                $snippet = ' הערה: ' . $snippet . (mb_strlen(trim((string) ($s['note'] ?? ''))) > 60 ? '…' : '');
            }
            $lines[] = '- shift_id `' . (int) $s['shift_id'] . '` | job `' . (int) $s['job_id'] . '` (' . ($s['job_title'] ?? '')
                . ') | ' . ($s['starts_at'] ?? '') . ' → ' . ($s['ends_at'] ?? '')
                . ' | סוג `' . (($s['shift_type_id'] ?? null) === null || (int) $s['shift_type_id'] === 0 ? '0' : (int) $s['shift_type_id']) . '`' . $snippet;
            ++$n;
        }
        mysqli_stmt_close($sSt);
        if ($n === 0) {
            $lines[] = '(אין משמרות בטווח זה בלוח)';
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('ai_chat_dispatch_work_schedule_action')) {
    /**
     * @param array<string,mixed> $action
     * @return array{ok:bool,message?:string}
     */
    function ai_chat_dispatch_work_schedule_action(mysqli $conn, int $userId, array $action): array
    {
        if (!ai_chat_user_work_schedule_db_enabled($conn, $userId)) {
            return ['ok' => false, 'message' => 'סידור עבודה אינו פעיל לחשבון זה.'];
        }

        $kind = strtolower(trim((string) ($action['kind'] ?? '')));

        if ($kind === 'create_work_shift') {
            $jobId = (int) ($action['job_id'] ?? 0);
            if ($jobId < 1 || !ai_chat_ws_agent_job($conn, $userId, $jobId)) {
                return ['ok' => false, 'message' => 'עבודה לא נמצאה.'];
            }
            $typeId = (int) ($action['shift_type_id'] ?? 0);
            if ($typeId > 0) {
                $trow = ai_chat_ws_agent_shift_type($conn, $userId, $typeId);
                if (!$trow || (int) $trow['job_id'] !== $jobId) {
                    return ['ok' => false, 'message' => 'סוג משמרת לא תקין לעבודה זו.'];
                }
            }

            $startsAt = trim((string) ($action['starts_at'] ?? ''));
            $endsAt = trim((string) ($action['ends_at'] ?? ''));
            if ($startsAt === '' || $endsAt === '') {
                return ['ok' => false, 'message' => 'חסרים תאריכי התחלה/סיום.'];
            }
            $ts1 = strtotime($startsAt);
            $ts2 = strtotime($endsAt);
            if ($ts1 === false || $ts2 === false) {
                return ['ok' => false, 'message' => 'פורמט תאריכים לא תקין.'];
            }
            if ($ts2 <= $ts1) {
                return ['ok' => false, 'message' => 'שעת סיום חייבת להיות אחרי שעת התחלה.'];
            }
            $note = trim((string) ($action['note'] ?? ''));
            if (function_exists('mb_strlen') ? mb_strlen($note) > 500 : strlen($note) > 500) {
                $note = function_exists('mb_substr') ? mb_substr($note, 0, 500) : substr($note, 0, 500);
            }
            $noteDb = $note === '' ? '' : $note;
            $s = date('Y-m-d H:i:s', $ts1);
            $e = date('Y-m-d H:i:s', $ts2);
            if ($typeId > 0) {
                $st = $conn->prepare(
                    'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`shift_type_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?,?)'
                );
                $st->bind_param('iiisss', $userId, $jobId, $typeId, $s, $e, $noteDb);
            } else {
                $st = $conn->prepare(
                    'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?)'
                );
                $st->bind_param('iisss', $userId, $jobId, $s, $e, $noteDb);
            }
            if (!$st || !$st->execute()) {
                return ['ok' => false, 'message' => 'לא ניתן ליצור את המשמרת.'];
            }
            $st->close();

            return ['ok' => true, 'message' => 'נוספה משמרת חדשה לסידור.'];
        }

        if ($kind === 'update_work_shift') {
            $id = (int) ($action['shift_id'] ?? 0);
            $row = $id > 0 ? ai_chat_ws_agent_shift($conn, $userId, $id) : null;
            if (!$row) {
                return ['ok' => false, 'message' => 'משמרת לא נמצאה.'];
            }
            $jobId = (int) ($action['job_id'] ?? 0);
            if ($jobId < 1 || !ai_chat_ws_agent_job($conn, $userId, $jobId)) {
                return ['ok' => false, 'message' => 'עבודה לא נמצאה.'];
            }
            $typeId = (int) ($action['shift_type_id'] ?? 0);
            if ($typeId > 0) {
                $trow = ai_chat_ws_agent_shift_type($conn, $userId, $typeId);
                if (!$trow || (int) $trow['job_id'] !== $jobId) {
                    return ['ok' => false, 'message' => 'סוג משמרת לא תקין לעבודה זו.'];
                }
            }
            $startsAt = trim((string) ($action['starts_at'] ?? ''));
            $endsAt = trim((string) ($action['ends_at'] ?? ''));
            $ts1 = strtotime($startsAt);
            $ts2 = strtotime($endsAt);
            if ($startsAt === '' || $endsAt === '' || $ts1 === false || $ts2 === false) {
                return ['ok' => false, 'message' => 'פורמט תאריכים לא תקין.'];
            }
            if ($ts2 <= $ts1) {
                return ['ok' => false, 'message' => 'שעת סיום חייבת להיות אחרי שעת התחלה.'];
            }
            $note = trim((string) ($action['note'] ?? ''));
            if (function_exists('mb_strlen') ? mb_strlen($note) > 500 : strlen($note) > 500) {
                $note = function_exists('mb_substr') ? mb_substr($note, 0, 500) : substr($note, 0, 500);
            }
            $noteDb = $note === '' ? '' : $note;
            $s = date('Y-m-d H:i:s', $ts1);
            $e = date('Y-m-d H:i:s', $ts2);
            if ($typeId > 0) {
                $st = $conn->prepare(
                    'UPDATE `user_work_shifts` SET `job_id`=?, `shift_type_id`=?, `starts_at`=?, `ends_at`=?, `note`=? WHERE `id`=? AND `user_id`=?'
                );
                $st->bind_param('iisssii', $jobId, $typeId, $s, $e, $noteDb, $id, $userId);
            } else {
                $st = $conn->prepare(
                    'UPDATE `user_work_shifts` SET `job_id`=?, `shift_type_id`=NULL, `starts_at`=?, `ends_at`=?, `note`=? WHERE `id`=? AND `user_id`=?'
                );
                $st->bind_param('issiii', $jobId, $s, $e, $noteDb, $id, $userId);
            }
            if (!$st || !$st->execute()) {
                return ['ok' => false, 'message' => 'לא ניתן לעדכן את המשמרת.'];
            }
            $st->close();

            return ['ok' => true, 'message' => 'המשמרת עודכנה.'];
        }

        if ($kind === 'delete_work_shift') {
            $id = (int) ($action['shift_id'] ?? 0);
            if ($id < 1 || !ai_chat_ws_agent_shift($conn, $userId, $id)) {
                return ['ok' => false, 'message' => 'משמרת לא נמצאה.'];
            }
            if (!function_exists('delete')) {
                require_once ROOT_PATH . '/app/database/db.php';
            }
            delete('user_work_shifts', $id);

            return ['ok' => true, 'message' => 'המשמרת נמחקה מהסידור.'];
        }

        return ['ok' => false, 'message' => 'פעולת סידור לא נתמכת'];
    }
}

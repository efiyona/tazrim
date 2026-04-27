<?php
/**
 * API JSON — סידור עבודה (עבודות, סוגי משמרות, משמרות).
 * POST: action=... + פרמטרים; דורש סשן + work_schedule_enabled.
 */
require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';

header('Content-Type: application/json; charset=utf-8');

$uid = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($uid < 1) {
    echo json_encode(['ok' => false, 'message' => 'נדרשת התחברות.']);
    exit;
}

$u = selectOne('users', ['id' => $uid]);
if (!$u || empty($u['work_schedule_enabled'])) {
    echo json_encode(['ok' => false, 'message' => 'התכונה אינה פעילה לחשבון זה.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$action = preg_replace('/[^a-z0-9_]/', '', $action);

function ws_json($ok, $data = null, $message = null): void
{
    $o = ['ok' => (bool) $ok];
    if ($message !== null) {
        $o['message'] = $message;
    }
    if ($data !== null) {
        $o['data'] = $data;
    }
    echo json_encode($o, JSON_UNESCAPED_UNICODE);
    exit;
}

function ws_job_for_user(mysqli $conn, int $userId, int $jobId): ?array
{
    $q = mysqli_prepare($conn, 'SELECT * FROM `user_work_jobs` WHERE `id` = ? AND `user_id` = ? LIMIT 1');
    if (!$q) {
        return null;
    }
    mysqli_stmt_bind_param($q, 'ii', $jobId, $userId);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($q);
    return $row ?: null;
}

function ws_shift_type_for_user(mysqli $conn, int $userId, int $typeId): ?array
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
    $res = mysqli_stmt_get_result($q);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($q);
    return $row ?: null;
}

function ws_shift_for_user(mysqli $conn, int $userId, int $shiftId): ?array
{
    $q = mysqli_prepare(
        $conn,
        'SELECT * FROM `user_work_shifts` WHERE `id` = ? AND `user_id` = ? LIMIT 1'
    );
    if (!$q) {
        return null;
    }
    mysqli_stmt_bind_param($q, 'ii', $shiftId, $userId);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($q);
    return $row ?: null;
}

function ws_sanitize_color(string $c): string
{
    $c = trim($c);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) {
        return $c;
    }
    return '#5B8DEF';
}

function ws_sanitize_icon_preset(string $p): string
{
    $allowed = ['', 'morning', 'evening', 'mid', 'night'];
    $p = trim($p);
    return in_array($p, $allowed, true) ? $p : '';
}

/** אייקון Font Awesome בסגנון fa-xxx (ללא fa-solid — נוסיף ב־UI). */
function ws_sanitize_icon_class(string $c): string
{
    $c = trim($c);
    if ($c === '') {
        return '';
    }
    if (strlen($c) > 64) {
        return '';
    }
    if (!preg_match('/^fa-[a-z0-9-]+$/', $c)) {
        return '';
    }

    return $c;
}

/** HH:MM או ריק → null אם ריק */
function ws_parse_time_or_null(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return null;
    }
    if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $t, $m)) {
        return null;
    }

    return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
}

$payday = static function (int $d): int {
    if ($d < 1) {
        return 1;
    }
    if ($d > 31) {
        return 31;
    }
    return $d;
};

switch ($action) {

    case 'list_jobs': {
        $q = mysqli_prepare(
            $conn,
            'SELECT * FROM `user_work_jobs` WHERE `user_id` = ? ORDER BY `sort_order` ASC, `id` ASC'
        );
        mysqli_stmt_bind_param($q, 'i', $uid);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        $rows = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($q);
        ws_json(true, $rows);
    }
    // no break
    // phpcs:ignore
    case 'create_job': {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120) {
            ws_json(false, null, 'נא להזין שם עבודה (עד 120 תווים).');
        }
        $color = ws_sanitize_color((string) ($_POST['color'] ?? '#5B8DEF'));
        $pd = $payday((int) ($_POST['payday_day_of_month'] ?? 1));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $id = create('user_work_jobs', [
            'user_id' => $uid,
            'title' => $title,
            'color' => $color,
            'payday_day_of_month' => $pd,
            'sort_order' => $sort,
        ]);
        ws_json(true, ['id' => (int) $id]);
    }

    case 'update_job': {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1 || !ws_job_for_user($conn, $uid, $id)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120) {
            ws_json(false, null, 'שם עבודה לא תקין.');
        }
        $color = ws_sanitize_color((string) ($_POST['color'] ?? '#5B8DEF'));
        $pd = $payday((int) ($_POST['payday_day_of_month'] ?? 1));
        update('user_work_jobs', $id, [
            'title' => $title,
            'color' => $color,
            'payday_day_of_month' => $pd,
        ]);
        ws_json(true, ['id' => $id]);
    }

    case 'delete_job': {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1 || !ws_job_for_user($conn, $uid, $id)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $conn->begin_transaction();
        try {
            delete('user_work_jobs', $id);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            ws_json(false, null, 'מחיקה נכשלה.');
        }
        ws_json(true, ['id' => $id]);
    }

    case 'list_shift_types': {
        $jobId = (int) ($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
        if ($jobId < 1 || !ws_job_for_user($conn, $uid, $jobId)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $q = mysqli_prepare(
            $conn,
            'SELECT * FROM `user_work_shift_types` WHERE `job_id` = ? ORDER BY `sort_order` ASC, `id` ASC'
        );
        mysqli_stmt_bind_param($q, 'i', $jobId);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        $rows = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($q);
        ws_json(true, $rows);
    }

    case 'create_shift_type': {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId < 1 || !ws_job_for_user($conn, $uid, $jobId)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            ws_json(false, null, 'שם סוג משמרת לא תקין.');
        }
        $icon = ws_sanitize_icon_preset((string) ($_POST['icon_preset'] ?? ''));
        $iconClass = ws_sanitize_icon_class((string) ($_POST['icon_class'] ?? ''));
        $dst = ws_parse_time_or_null((string) ($_POST['default_start_time'] ?? ''));
        $den = ws_parse_time_or_null((string) ($_POST['default_end_time'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($dst === null && $den !== null) {
            ws_json(false, null, 'אם מוגדרת שעת סיום, יש להגדיר גם שעת התחלה.');
        }
        if ($dst === null && $den === null) {
            $st = $conn->prepare(
                'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`sort_order`) VALUES (?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($st, 'isssi', $jobId, $name, $icon, $iconClass, $sort);
        } elseif ($dst !== null && $den === null) {
            $st = $conn->prepare(
                'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`default_start_time`,`sort_order`) VALUES (?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($st, 'issssi', $jobId, $name, $icon, $iconClass, $dst, $sort);
        } else {
            $st = $conn->prepare(
                'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`default_start_time`,`default_end_time`,`sort_order`) VALUES (?,?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($st, 'isssssi', $jobId, $name, $icon, $iconClass, $dst, $den, $sort);
        }
        mysqli_stmt_execute($st);
        $newId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($st);
        ws_json(true, ['id' => $newId]);
    }

    case 'update_shift_type': {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            ws_json(false, null, 'סוג לא נמצא.');
        }
        $t = ws_shift_type_for_user($conn, $uid, $id);
        if (!$t) {
            ws_json(false, null, 'סוג לא נמצא.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            ws_json(false, null, 'שם סוג לא תקין.');
        }
        $icon = ws_sanitize_icon_preset((string) ($_POST['icon_preset'] ?? ''));
        $iconClass = ws_sanitize_icon_class((string) ($_POST['icon_class'] ?? ''));
        $dst = ws_parse_time_or_null((string) ($_POST['default_start_time'] ?? ''));
        $den = ws_parse_time_or_null((string) ($_POST['default_end_time'] ?? ''));
        if ($dst === null && $den !== null) {
            ws_json(false, null, 'אם מוגדרת שעת סיום, יש להגדיר גם שעת התחלה.');
        }
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $iconEsc = mysqli_real_escape_string($conn, $icon);
        $icEsc = mysqli_real_escape_string($conn, $iconClass);
        $dstSql = $dst === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $dst) . "'";
        $denSql = $den === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $den) . "'";
        $sql = "UPDATE `user_work_shift_types` SET `name`='{$nameEsc}', `icon_preset`='{$iconEsc}', `icon_class`='{$icEsc}', `default_start_time`={$dstSql}, `default_end_time`={$denSql}, `sort_order`={$sort} WHERE `id`=" . (int) $id . ' LIMIT 1';
        if (!mysqli_query($conn, $sql)) {
            ws_json(false, null, 'עדכון נכשל.');
        }
        ws_json(true, ['id' => $id]);
    }

    case 'delete_shift_type': {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1 || !ws_shift_type_for_user($conn, $uid, $id)) {
            ws_json(false, null, 'סוג לא נמצא.');
        }
        @mysqli_query($conn, 'UPDATE `user_work_shifts` SET `shift_type_id` = NULL WHERE `shift_type_id` = ' . (int) $id);
        delete('user_work_shift_types', $id);
        ws_json(true, ['id' => $id]);
    }

    case 'list_shifts': {
        $y = (int) ($_POST['year'] ?? $_GET['year'] ?? 0);
        $m = (int) ($_POST['month'] ?? $_GET['month'] ?? 0);
        if ($y < 1970 || $y > 2100 || $m < 1 || $m > 12) {
            ws_json(false, null, 'תאריך לא תקין.');
        }
        $start = sprintf('%04d-%02d-01 00:00:00', $y, $m);
        $mEnd = $m + 1;
        $yEnd = $y;
        if ($mEnd > 12) {
            $mEnd = 1;
            $yEnd++;
        }
        $end = sprintf('%04d-%02d-01 00:00:00', $yEnd, $mEnd);

        $q = mysqli_prepare(
            $conn,
            'SELECT s.*, j.title AS job_title, j.color AS job_color, t.name AS type_name, t.icon_preset,
                    t.icon_class AS type_icon_class, t.default_start_time AS type_default_start_time,
                    t.default_end_time AS type_default_end_time
             FROM `user_work_shifts` s
             INNER JOIN `user_work_jobs` j ON j.id = s.job_id
             LEFT JOIN `user_work_shift_types` t ON t.id = s.shift_type_id
             WHERE s.user_id = ? AND s.starts_at >= ? AND s.starts_at < ?
             ORDER BY s.starts_at ASC, s.id ASC'
        );
        mysqli_stmt_bind_param($q, 'iss', $uid, $start, $end);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        $rows = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($q);
        ws_json(true, $rows);
    }

    case 'create_shift': {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId < 1) {
            ws_json(false, null, 'נא לבחור עבודה.');
        }
        if (!ws_job_for_user($conn, $uid, $jobId)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $typeId = (int) ($_POST['shift_type_id'] ?? 0);
        if ($typeId > 0) {
            $trow = ws_shift_type_for_user($conn, $uid, $typeId);
            if (!$trow || (int) $trow['job_id'] !== $jobId) {
                ws_json(false, null, 'סוג משמרת לא תקין לעבודה זו.');
            }
        } else {
            $typeId = 0; // will store NULL
        }

        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $endsAt = trim((string) ($_POST['ends_at'] ?? ''));
        if ($startsAt === '' || $endsAt === '') {
            ws_json(false, null, 'נא להזין תאריך ושעות.');
        }
        $ts1 = strtotime($startsAt);
        $ts2 = strtotime($endsAt);
        if ($ts1 === false || $ts2 === false) {
            ws_json(false, null, 'פורמט תאריך לא תקין.');
        }
        if ($ts2 <= $ts1) {
            ws_json(false, null, 'שעת סיום חייבת להיות אחרי שעת התחלה.');
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $noteDb = $note === '' ? '' : $note;

        $s = date('Y-m-d H:i:s', $ts1);
        $e = date('Y-m-d H:i:s', $ts2);
        if ($typeId > 0) {
            $st = $conn->prepare(
                'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`shift_type_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($st, 'iiisss', $uid, $jobId, $typeId, $s, $e, $noteDb);
        } else {
            $st = $conn->prepare(
                'INSERT INTO `user_work_shifts` (`user_id`,`job_id`,`starts_at`,`ends_at`,`note`) VALUES (?,?,?,?,?)'
            );
            mysqli_stmt_bind_param($st, 'iisss', $uid, $jobId, $s, $e, $noteDb);
        }
        mysqli_stmt_execute($st);
        $newId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($st);
        ws_json(true, ['id' => $newId]);
    }

    case 'update_shift': {
        $id = (int) ($_POST['id'] ?? 0);
        $s = $id > 0 ? ws_shift_for_user($conn, $uid, $id) : null;
        if (!$s) {
            ws_json(false, null, 'משמרת לא נמצאה.');
        }
        $jobId = (int) ($_POST['job_id'] ?? 0);
        if ($jobId < 1) {
            ws_json(false, null, 'נא לבחור עבודה.');
        }
        if (!ws_job_for_user($conn, $uid, $jobId)) {
            ws_json(false, null, 'עבודה לא נמצאה.');
        }
        $typeId = (int) ($_POST['shift_type_id'] ?? 0);
        if ($typeId > 0) {
            $trow = ws_shift_type_for_user($conn, $uid, $typeId);
            if (!$trow || (int) $trow['job_id'] !== $jobId) {
                ws_json(false, null, 'סוג משמרת לא תקין לעבודה זו.');
            }
        } else {
            $typeId = 0;
        }

        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $endsAt = trim((string) ($_POST['ends_at'] ?? ''));
        $ts1 = strtotime($startsAt);
        $ts2 = strtotime($endsAt);
        if ($ts1 === false || $ts2 === false) {
            ws_json(false, null, 'פורמט תאריך לא תקין.');
        }
        if ($ts2 <= $ts1) {
            ws_json(false, null, 'שעת סיום חייבת להיות אחרי שעת התחלה.');
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $noteDb = $note === '' ? '' : $note;

        $s = date('Y-m-d H:i:s', $ts1);
        $e = date('Y-m-d H:i:s', $ts2);
        if ($typeId > 0) {
            $st = $conn->prepare(
                'UPDATE `user_work_shifts` SET `job_id`=?, `shift_type_id`=?, `starts_at`=?, `ends_at`=?, `note`=? WHERE `id`=? AND `user_id`=?'
            );
            mysqli_stmt_bind_param($st, 'iisssii', $jobId, $typeId, $s, $e, $noteDb, $id, $uid);
        } else {
            $st = $conn->prepare(
                'UPDATE `user_work_shifts` SET `job_id`=?, `shift_type_id`=NULL, `starts_at`=?, `ends_at`=?, `note`=? WHERE `id`=? AND `user_id`=?'
            );
            mysqli_stmt_bind_param($st, 'isssii', $jobId, $s, $e, $noteDb, $id, $uid);
        }
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        ws_json(true, ['id' => $id]);
    }

    case 'delete_shift': {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1 || !ws_shift_for_user($conn, $uid, $id)) {
            ws_json(false, null, 'משמרת לא נמצאה.');
        }
        delete('user_work_shifts', $id);
        ws_json(true, ['id' => $id]);
    }

    case 'wizard_setup': {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120) {
            ws_json(false, null, 'נא להזין שם עבודה.');
        }
        $color = ws_sanitize_color((string) ($_POST['color'] ?? '#5B8DEF'));
        $pd = $payday((int) ($_POST['payday_day_of_month'] ?? 1));
        $typesJson = (string) ($_POST['types'] ?? '[]');
        $types = json_decode($typesJson, true);
        if (!is_array($types)) {
            $types = [];
        }
        $conn->begin_transaction();
        try {
            $jobId = create('user_work_jobs', [
                'user_id' => $uid,
                'title' => $title,
                'color' => $color,
                'payday_day_of_month' => $pd,
                'sort_order' => 0,
            ]);
            $so = 0;
            foreach ($types as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $n = trim((string) ($t['name'] ?? ''));
                if ($n === '' || mb_strlen($n) > 80) {
                    continue;
                }
                $so++;
                $ip = ws_sanitize_icon_preset((string) ($t['icon_preset'] ?? ''));
                $ic = ws_sanitize_icon_class((string) ($t['icon_class'] ?? ''));
                $dst = ws_parse_time_or_null((string) ($t['default_start_time'] ?? ''));
                $den = ws_parse_time_or_null((string) ($t['default_end_time'] ?? ''));
                if ($dst === null && $den !== null) {
                    $conn->rollback();
                    ws_json(false, null, 'שעות ברירת מחדל: אם יש סיום חייבת להיות התחלה.');
                }
                if ($dst === null && $den === null) {
                    $st = $conn->prepare(
                        'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`sort_order`) VALUES (?,?,?,?,?)'
                    );
                    mysqli_stmt_bind_param($st, 'isssi', $jobId, $n, $ip, $ic, $so);
                } elseif ($dst !== null && $den === null) {
                    $st = $conn->prepare(
                        'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`default_start_time`,`sort_order`) VALUES (?,?,?,?,?,?)'
                    );
                    mysqli_stmt_bind_param($st, 'issssi', $jobId, $n, $ip, $ic, $dst, $so);
                } else {
                    $st = $conn->prepare(
                        'INSERT INTO `user_work_shift_types` (`job_id`,`name`,`icon_preset`,`icon_class`,`default_start_time`,`default_end_time`,`sort_order`) VALUES (?,?,?,?,?,?,?)'
                    );
                    mysqli_stmt_bind_param($st, 'isssssi', $jobId, $n, $ip, $ic, $dst, $den, $so);
                }
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
            $conn->commit();
            ws_json(true, ['job_id' => (int) $jobId]);
        } catch (Throwable $e) {
            $conn->rollback();
            ws_json(false, null, 'שמירה נכשלה. נסו שוב.');
        }
    }

    default:
        ws_json(false, null, 'פעולה לא ידועה.');
}

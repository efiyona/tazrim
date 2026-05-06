<?php
/**
 * חילוץ משמרות מצילומי סידור — Gemini (multipart).
 */
require_once dirname(__DIR__, 2) . '/path.php';
include ROOT_PATH . '/app/database/db.php';
require_once ROOT_PATH . '/app/functions/user_gemini_key.php';

/** @var mysqli $conn */

function schedule_ai_job_for_user(mysqli $conn, int $userId, int $jobId): ?array
{
    $q = mysqli_prepare($conn, 'SELECT id, title FROM `user_work_jobs` WHERE id = ? AND user_id = ? LIMIT 1');
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

function schedule_ai_shift_types(mysqli $conn, int $jobId): array
{
    $q = mysqli_prepare(
        $conn,
        'SELECT id, name, default_start_time, default_end_time, icon_preset FROM `user_work_shift_types` WHERE job_id = ? ORDER BY sort_order ASC, id ASC'
    );
    if (!$q) {
        return [];
    }
    mysqli_stmt_bind_param($q, 'i', $jobId);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($q);
    return $rows;
}

function schedule_build_schema(): array
{
    // weekday: 0–6 כמו JS Date.getDay() (0 = ראשון … 6 = שבת)
    return [
        'type' => 'object',
        'properties' => [
            'is_work_schedule' => ['type' => 'boolean'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'shifts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string'],
                        'start_time' => ['type' => 'string'],
                        'end_time' => ['type' => 'string'],
                        'note' => ['type' => 'string', 'nullable' => true],
                        'weekday' => ['type' => 'integer', 'nullable' => true, 'minimum' => 0, 'maximum' => 6],
                    ],
                    'required' => ['date', 'start_time', 'end_time'],
                ],
            ],
        ],
        'required' => ['is_work_schedule', 'shifts', 'warnings'],
    ];
}

/**
 * נרמול מבנה $_FILES לשדה מרובה: לפעמים PHP מחזיר name/type אחד כמחרוזת במקום מערך.
 *
 * @return array<int, array{tmp_name: string, size: int, error: int, original_name: string}>
 */
function schedule_normalize_files_array(?array $files): array
{
    if (!is_array($files) || !isset($files['name'])) {
        return [];
    }
    if (is_string($files['name'])) {
        $err = (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($files['tmp_name'] ?? '');
        $size = (int) ($files['size'] ?? 0);
        $orig = trim((string) $files['name']);
        if ($orig === '') {
            $orig = 'קובץ';
        }
        return [[
            'tmp_name' => $tmp,
            'size' => $size,
            'error' => $err,
            'original_name' => $orig,
        ]];
    }
    if (!is_array($files['name'])) {
        return [];
    }
    $out = [];
    $n = count($files['name']);
    for ($i = 0; $i < $n; $i++) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        $size = (int) ($files['size'][$i] ?? 0);
        $orig = trim((string) ($files['name'][$i] ?? ''));
        if ($orig === '') {
            $orig = 'קובץ ' . ($i + 1);
        }
        $out[] = ['tmp_name' => $tmp, 'size' => $size, 'error' => $err, 'original_name' => $orig];
    }
    return $out;
}

function schedule_upload_error_message_he(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'הקובץ חורג מהמגבלה שהוגדרה בשרת.';
        case UPLOAD_ERR_PARTIAL:
            return 'ההעלאה נקטעה באמצע.';
        case UPLOAD_ERR_NO_FILE:
            return 'לא הועלה קובץ.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'חסרה תיקייה זמנית בשרת.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'כתיבה לדיסק נכשלה.';
        case UPLOAD_ERR_EXTENSION:
            return 'ההעלאה נחסמה על ידי הרחבת PHP.';
        default:
            return 'שגיאת העלאה (קוד ' . $code . ').';
    }
}

/** @return array<int, array{mime_type: string, base64: string}> */
function schedule_collect_uploaded_images_with_report(): array
{
    $allowedLabels = 'JPEG או PNG או WEBP בלבד';
    $allowed = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP'];
    $maxFiles = 8;
    $maxBytesPerFile = 6 * 1024 * 1024;
    $maxMbHuman = '6 מגה־בייט';

    $normalized = schedule_normalize_files_array($_FILES['schedule_images'] ?? null);
    $report = [];
    $images = [];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($normalized === []) {
        return [
            'images' => [],
            'report' => [[
                'status' => 'no_valid_slot',
                'message' => 'לא זוהו קבצים שהועלו כראוי. ודאו שבחרתם קובץ אחד לפחות.',
            ]],
        ];
    }

    $slots = array_slice($normalized, 0, $maxFiles);
    $skippedOverLimit = max(0, count($normalized) - $maxFiles);

    foreach ($slots as $idx => $slot) {
        $n = $idx + 1;
        $label = $slot['original_name'] ?? ('קובץ ' . $n);
        $err = (int) ($slot['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $report[] = [
                'status' => 'upload_err',
                'slot' => $n,
                'file_label' => $label,
                'message' => 'קובץ ' . $n . ' (' . $label . '): ' . schedule_upload_error_message_he($err),
            ];
            continue;
        }
        $tmp = (string) ($slot['tmp_name'] ?? '');
        $size = (int) ($slot['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp)) {
            $report[] = [
                'status' => 'missing_tmp',
                'slot' => $n,
                'file_label' => $label,
                'message' => 'קובץ ' . $n . ' (' . $label . '): לא נמצא קובץ זמני בשרת אחרי ההעלאה.',
            ];
            continue;
        }
        if ($size <= 0) {
            $report[] = [
                'status' => 'empty_file',
                'slot' => $n,
                'file_label' => $label,
                'message' => 'קובץ ' . $n . ' (' . $label . '): הקובץ ריק (גודל 0 בתים).',
            ];
            continue;
        }
        if ($size > $maxBytesPerFile) {
            $kb = (int) round($size / 1024);
            $report[] = [
                'status' => 'too_large',
                'slot' => $n,
                'file_label' => $label,
                'message' => 'קובץ ' . $n . ' (' . $label . '): גודל ' . $kb . ' ק״ב — חורג מהמותר (' . $maxMbHuman . ' לקובץ).',
            ];
            continue;
        }
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($mime === '' || !isset($allowed[$mime])) {
            $mimeDisp = $mime !== '' ? $mime : 'לא זוהה';
            $report[] = [
                'status' => 'mime_rejected',
                'slot' => $n,
                'file_label' => $label,
                'detected_mime' => $mime,
                'message' => 'קובץ ' . $n . ' (' . $label . '): סוג MIME שזוהה: ' . $mimeDisp . '. מותרים ' . $allowedLabels . '.',
            ];
            continue;
        }
        $bin = @file_get_contents($tmp);
        if (!is_string($bin) || $bin === '') {
            $report[] = [
                'status' => 'read_failed',
                'slot' => $n,
                'file_label' => $label,
                'message' => 'קובץ ' . $n . ' (' . $label . '): לא ניתן לקרוא את תוכן הקובץ מהשרת.',
            ];
            continue;
        }
        $images[] = [
            'mime_type' => $mime,
            'base64' => base64_encode($bin),
        ];
        $report[] = [
            'status' => 'accepted',
            'slot' => $n,
            'file_label' => $label,
            'message' => 'קובץ ' . $n . ' (' . $label . '): התקבל כ' . $allowed[$mime] . '.',
        ];
    }

    if ($skippedOverLimit > 0) {
        $report[] = [
            'status' => 'skipped_limit',
            'message' => 'דולגו ' . $skippedOverLimit . ' קבצים — מקסימום ' . $maxFiles . ' קבצים בבקשה.',
        ];
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    return ['images' => $images, 'report' => $report];
}

function schedule_call_gemini(
    array $images,
    array $orderedApiKeys,
    int $year,
    int $month,
    string $monthNameHe,
    string $jobTitle,
    array $shiftTypeHints
): array {
    $models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    $schema = schedule_build_schema();
    $typeHintLines = [];
    foreach ($shiftTypeHints as $t) {
        if (!is_array($t)) {
            continue;
        }
        $n = trim((string) ($t['name'] ?? ''));
        if ($n === '') {
            continue;
        }
        $ds = $t['default_start_time'] ?? null;
        $de = $t['default_end_time'] ?? null;
        $dsS = $ds ? substr((string) $ds, 0, 5) : '';
        $deS = $de ? substr((string) $de, 0, 5) : '';
        if ($dsS !== '' && $deS !== '') {
            $typeHintLines[] = '- ' . $n . ': ' . $dsS . '–' . $deS . ' (local wall clock; if overnight, end_time is smaller than start_time)';
        } else {
            $typeHintLines[] = '- ' . $n;
        }
    }
    $hintsBlock = $typeHintLines === [] ? '(no predefined shift types with hours)' : implode("\n", $typeHintLines);

    $jobEnc = json_encode($jobTitle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    if (!is_string($jobEnc)) {
        $jobEnc = '""';
    }
    $prompt = "You are given one or more images of a MONTHLY WORK SHIFT SCHEDULE / roster.\n"
        . "Calendar context chosen by user: Gregorian month {$month}/{$year} ({$monthNameHe}). Job title hint: {$jobEnc}.\n\n"
        . "Predefined shift TYPE labels & default hours for this job (hints only):\n{$hintsBlock}\n\n"
        . "Rules:\n"
        . "1) Extract EVERY work shift occurrence visible across all images. Merge overlapping pages.\n"
        . "2) For each shift output: date (YYYY-MM-DD in Gregorian calendar), start_time & end_time as HH:MM 24-hour format.\n"
        . "3) If ONLY day-of-month is shown without month/year, use the user's month/year context above.\n"
        . "4) Overnight shifts (e.g. 22:00–06:00): keep chronological meaning — start_time AFTER end_time on the clock means ends next calendar day; still output the START date.\n"
        . "5) Optional note: short Hebrew or text from image (department, remarks).\n"
        . "6) For EVERY shift row: output \"weekday\" as integer 0–6 matching JavaScript getDay(): 0=Sunday, 1=Monday … 6=Saturday, copied from COLUMN HEADERS / row labels ON THE IMAGE when visible. "
        . "Use it for calendar alignment; if weekday text is unclear, omit weekday for that row.\n"
        . "7) If images do NOT depict a shift schedule/roster/grid, set is_work_schedule=false and shifts=[].\n"
        . "Return JSON only per schema.\n";

    $parts = [['text' => $prompt]];
    foreach ($images as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $img['mime_type'],
                'data' => $img['base64'],
            ],
        ];
    }

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => $parts,
        ]],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
        ],
    ];

    $retryable = [429, 500, 503];

    foreach ($models as $model) {
        $gr = tazrim_user_gemini_v1beta_generate_content_with_key_rotation(
            $orderedApiKeys,
            $model,
            $payload,
            80,
            false,
            3,
            $retryable
        );
        $raw = $gr['raw'];
        if (!$gr['ok'] || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            continue;
        }
        $json = json_decode($text, true);
        if (is_array($json)) {
            return ['ok' => true, 'data' => $json];
        }
    }

    return ['ok' => false];
}

function schedule_js_weekday_from_ymd(string $ymd): int
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $ymd) {
        return -1;
    }

    /** @var int sunday = 0 (כמו JS) */
    return (int) $dt->format('w');
}

/**
 * מתוך אותו חודש קלנדרינגלי: מתאריך AI ויום בשבוע שחולץ, מוצא תאריך מתאים (המרחק המינימלי בימים בחודש).
 */
function schedule_best_same_month_date_for_weekday(string $ymd, int $targetWeekdayJs): ?string
{
    if ($targetWeekdayJs < 0 || $targetWeekdayJs > 6) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $ymd) {
        return null;
    }
    $y = (int) $dt->format('Y');
    $m = (int) $dt->format('n');
    $origDay = (int) $dt->format('j');
    $daysInMonth = (int) $dt->modify('last day of this month')->format('j');
    $best = null;
    $bestDist = 9999;
    for ($d = 1; $d <= $daysInMonth; ++$d) {
        try {
            $cd = sprintf('%04d-%02d-%02d', $y, $m, $d);
            $tst = DateTimeImmutable::createFromFormat('Y-m-d', $cd);
            if (!$tst instanceof DateTimeImmutable || $tst->format('Y-m-d') !== $cd) {
                continue;
            }
            if ((int) $tst->format('w') !== $targetWeekdayJs) {
                continue;
            }
            $dist = abs($d - $origDay);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $cd;
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    return $best;
}

/**
 * מתאימה תאריכים לימי השבוע שחולצו מתמונה; מוסיפה warnings.
 *
 * @return array{rows: array<int, array>, warnings: array<int, string>}
 */
function schedule_align_dates_with_calendar_weekdays(array $rows, array $baseWarnings): array
{
    $extra = [];
    $out = [];

    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = isset($row['date']) ? (string) $row['date'] : '';
        $wRaw = $row['weekday'] ?? null;

        unset($row['weekday']);

        if ($wRaw === null || $wRaw === '') {
            $out[] = $row;

            continue;
        }

        if (!is_numeric($wRaw)) {
            $out[] = $row;

            continue;
        }

        $w = (int) $wRaw;
        if ($w < 0 || $w > 6 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $out[] = $row;

            continue;
        }

        $actual = schedule_js_weekday_from_ymd($date);

        if ($actual < 0) {
            $out[] = $row;

            continue;
        }

        if ($actual === $w) {
            $out[] = $row;

            continue;
        }

        $fixed = schedule_best_same_month_date_for_weekday($date, $w);
        if ($fixed !== null && $fixed !== $date) {
            $human = '(' . ($i + 1) . ') ' . $date . ' ← ' . $fixed;
            $extra[] =
                $human . ': תוקן התאריך מול לוח השנה כדי שהיום בשבוע יתאים לכותרת/עמודה בתמונה.';

            $row['date'] = $fixed;

            $out[] = $row;

            continue;
        }

        $extra[] = '(' . ($i + 1) . ') תאריך ' . $date . ' לא תואם ליום בשבוע שזוהה בתמונה — נא לאמת ידנית.';

        $out[] = $row;
    }

    return ['rows' => $out, 'warnings' => array_values(array_merge($baseWarnings, $extra))];
}

function schedule_normalize_shift_row(array $row): ?array
{
    $date = trim((string) ($row['date'] ?? ''));
    $start = trim((string) ($row['start_time'] ?? ''));
    $end = trim((string) ($row['end_time'] ?? ''));
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dm)) {
        return null;
    }
    $y = (int) $dm[1];
    $mo = (int) $dm[2];
    $day = (int) $dm[3];
    if (!checkdate($mo, $day, $y)) {
        return null;
    }
    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
        return null;
    }
    [$sh, $sm] = array_map('intval', explode(':', $start));
    [$eh, $em] = array_map('intval', explode(':', $end));
    if ($sh < 0 || $sh > 23 || $sm < 0 || $sm > 59 || $eh < 0 || $eh > 23 || $em < 0 || $em > 59) {
        return null;
    }
    $start = sprintf('%02d:%02d', $sh, $sm);
    $end = sprintf('%02d:%02d', $eh, $em);
    $note = trim((string) ($row['note'] ?? ''));
    if (mb_strlen($note) > 500) {
        $note = mb_substr($note, 0, 500);
    }

    $wd = null;
    if (array_key_exists('weekday', $row) && $row['weekday'] !== null && $row['weekday'] !== '') {
        if (is_numeric($row['weekday'])) {
            $wi = (int) $row['weekday'];
            if ($wi >= 0 && $wi <= 6) {
                $wd = $wi;
            }
        }
    }

    $o = [
        'date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'note' => $note,
    ];

    if ($wd !== null) {
        $o['weekday'] = $wd;
    }

    return $o;
}

function schedule_normalize_all(array $shifts): array
{
    $out = [];
    foreach ($shifts as $row) {
        if (!is_array($row)) {
            continue;
        }
        $n = schedule_normalize_shift_row($row);
        if ($n === null) {
            continue;
        }
        $out[] = $n;
    }

    return $out;
}

/**
 * כל משמרות החילוץ מתיישבות בחודש היעד שהמשתמש בחר (יום בחודש עם קיטוע למספר ימים בחודש).
 *
 * @param array<int, array<string, mixed>> $rows
 *
 * @return array<int, array<string, mixed>>
 */
function schedule_remap_shifts_to_target_month(array $rows, int $ty, int $tm): array
{
    if ($ty < 2000 || $ty > 2100 || $tm < 1 || $tm > 12) {
        return $rows;
    }
    $anchor = sprintf('%04d-%02d-01', $ty, $tm);
    $base = DateTimeImmutable::createFromFormat('Y-m-d', $anchor);
    if (!$base instanceof DateTimeImmutable || $base->format('Y-m-d') !== $anchor) {
        return $rows;
    }
    $last = (int) $base->modify('last day of this month')->format('j');
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['date'])) {
            $out[] = $row;
            continue;
        }
        $dateStr = (string) $row['date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $out[] = $row;
            continue;
        }
        $p = explode('-', $dateStr);
        $d = isset($p[2]) ? (int) $p[2] : 1;
        $d = min(max(1, $d), $last);
        while ($d > 0 && !checkdate($tm, $d, $ty)) {
            --$d;
        }
        if ($d < 1) {
            $d = 1;
        }
        $row['date'] = sprintf('%04d-%02d-%02d', $ty, $tm, $d);
        $out[] = $row;
    }

    return $out;
}

function schedule_strip_weekday_from_rows(array $rows): array
{
    return array_values(array_map(static function ($r) {
        if (!is_array($r)) {
            return $r;
        }

        unset($r['weekday']);

        return $r;
    }, $rows));
}

// ——— ביצוע ———

header('Content-Type: application/json; charset=utf-8');

$uid = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($uid < 1) {
    echo json_encode(['status' => 'error', 'message' => 'נדרשת התחברות.']);
    exit;
}

$u = selectOne('users', ['id' => $uid]);
if (!$u || empty($u['work_schedule_enabled'])) {
    echo json_encode(['status' => 'error', 'message' => 'התכונה אינה פעילה לחשבון זה.']);
    exit;
}

if (!isset($_FILES['schedule_images'])) {
    echo json_encode([
        'status' => 'error',
        'code' => 'upload_validation_failed',
        'message' => 'לא הועלו קבצים — לא נשלח שדה הקבצים לשרת.',
        'upload_report' => [[
            'status' => 'no_field',
            'message' => 'לא נמצא שדה הקבצים (schedule_images). רעננו את העמוד ובחרו שוב תמונות.',
        ]],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$jobId = (int) ($_POST['job_id'] ?? 0);
$y = (int) ($_POST['year'] ?? 0);
$m = (int) ($_POST['month'] ?? 0);
if ($jobId < 1 || !schedule_ai_job_for_user($conn, $uid, $jobId)) {
    echo json_encode(['status' => 'error', 'message' => 'עבודה לא תקינה.']);
    exit;
}
if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
    echo json_encode(['status' => 'error', 'message' => 'חודש/שנה לא תקינים.']);
    exit;
}

$hebrewMonths = [
    1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
    5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
    9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר',
];
$mNameHe = $hebrewMonths[$m] ?? (string) $m;

$uploadBundle = schedule_collect_uploaded_images_with_report();
$images = $uploadBundle['images'];
$uploadReportFull = $uploadBundle['report'];
if (count($images) === 0) {
    echo json_encode([
        'status' => 'error',
        'code' => 'upload_validation_failed',
        'message' => 'לא נקלטה אף תמונה תקינה. לכל קובץ מופיע הסבר למטה.',
        'upload_report' => $uploadReportFull,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$keys = tazrim_user_gemini_plain_keys_ordered($conn, $uid);
if ($keys === []) {
    echo json_encode([
        'status' => 'error',
        'code' => 'gemini_key_missing',
        'message' => 'נדרש מפתח Gemini אישי בהגדרות החשבון.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$job = schedule_ai_job_for_user($conn, $uid, $jobId);
$jobTitle = $job ? trim((string) $job['title']) : '';

$hints = schedule_ai_shift_types($conn, $jobId);

$ai = schedule_call_gemini($images, $keys, $y, $m, $mNameHe, $jobTitle, $hints);
if (!$ai['ok']) {
    echo json_encode(['status' => 'error', 'message' => 'שירות הבינה עמוס כרגע, נסו שוב בעוד רגע.']);
    exit;
}

$data = $ai['data'];
$isSchedule = !empty($data['is_work_schedule']);
$warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];
$rawShifts = is_array($data['shifts'] ?? null) ? $data['shifts'] : [];

if (!$isSchedule) {
    echo json_encode([
        'status' => 'error',
        'message' => 'לא זוהה סידור עבודה או לוח משמרות בתמונות. צלמו ברור מהמסך או הגדילו טקסט.',
        'source_mode' => 'images',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$shifts = schedule_normalize_all($rawShifts);
if (count($shifts) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'לא נמצאו משמרות תקינות בחילוץ. נסו תמונה אחרת או הרחיבו בסידור.',
        'warnings' => $warnings,
        'source_mode' => 'images',
        'upload_report' => $uploadReportFull,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$shifts = schedule_remap_shifts_to_target_month($shifts, $y, $m);

$aligned = schedule_align_dates_with_calendar_weekdays($shifts, $warnings);
$shifts = $aligned['rows'];
$warnings = $aligned['warnings'];

$shifts = schedule_strip_weekday_from_rows($shifts);

if (count($shifts) > 125) {
    $shifts = array_slice($shifts, 0, 125);
}

$uploadIssues = [];
foreach ($uploadReportFull as $ur) {
    if (!is_array($ur)) {
        continue;
    }
    $st = (string) ($ur['status'] ?? '');
    if ($st !== 'accepted') {
        $uploadIssues[] = $ur;
    }
}

$successPayload = [
    'status' => 'success',
    'shifts' => $shifts,
    'warnings' => $warnings,
    'source_mode' => 'images',
    'images_count' => count($images),
    'year' => $y,
    'month' => $m,
];

if ($uploadIssues !== []) {
    $successPayload['upload_report'] = $uploadIssues;
}

echo json_encode($successPayload, JSON_UNESCAPED_UNICODE);

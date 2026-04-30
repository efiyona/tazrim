<?php
$banner = $GLOBALS['tazrim_banner'] ?? null;
$banner_type = is_array($banner) ? (string) ($banner['type'] ?? 'info') : 'info';
$banner_text = is_array($banner) ? (string) ($banner['text'] ?? '') : '';
$banner_href = is_array($banner) ? (string) ($banner['href'] ?? '') : '';
$banner_link_text = is_array($banner) ? (string) ($banner['link_text'] ?? '') : '';
$is_shift_banner = false;
$shift_job_title = '';
$shift_type_name = '';
$shift_when = '';
$shift_color = '';
$shift_start_ts = null;

/**
 * Show only for users with the monthly work schedule feature enabled.
 */
$uid = (int) ($_SESSION['id'] ?? 0);
$work_enabled = false;
if ($uid > 0 && function_exists('selectOne')) {
    $u = selectOne('users', ['id' => $uid]);
    $work_enabled = !empty($u['work_schedule_enabled']);
}
if (!$work_enabled) {
    return;
}

if (!in_array($banner_type, ['info', 'success', 'warn', 'error'], true)) {
    $banner_type = 'info';
}

// Auto-generate banner content based on next shift (only if not explicitly set by the page).
if ($banner_text === '' && isset($conn) && $conn instanceof mysqli && $uid > 0) {
    try {
        $sql = 'SELECT s.starts_at, s.ends_at, s.note, j.title AS job_title, j.color AS job_color, t.name AS type_name
                FROM `user_work_shifts` s
                INNER JOIN `user_work_jobs` j ON j.id = s.job_id
                LEFT JOIN `user_work_shift_types` t ON t.id = s.shift_type_id
                WHERE s.user_id = ? AND s.starts_at >= NOW()
                ORDER BY s.starts_at ASC
                LIMIT 1';
        $st = mysqli_prepare($conn, $sql);
        if ($st) {
            mysqli_stmt_bind_param($st, 'i', $uid);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($st);

            $banner_href = $banner_href !== '' ? $banner_href : (defined('BASE_URL') ? (BASE_URL . 'pages/work_schedule.php') : 'pages/work_schedule.php');
            $banner_link_text = $banner_link_text !== '' ? $banner_link_text : 'לסידור';

            if ($row) {
                $jobTitle = trim((string) ($row['job_title'] ?? ''));
                $jobColor = trim((string) ($row['job_color'] ?? ''));
                $typeName = trim((string) ($row['type_name'] ?? ''));
                $startsAt = (string) ($row['starts_at'] ?? '');
                $endsAt = (string) ($row['ends_at'] ?? '');

                $startTs = $startsAt !== '' ? strtotime($startsAt) : false;
                $endTs = $endsAt !== '' ? strtotime($endsAt) : false;
                $dateStr = '';
                if ($startTs) {
                    $curY = (int) date('Y');
                    $y = (int) date('Y', $startTs);
                    $dateStr = ($y === $curY) ? date('j.n', $startTs) : date('j.n.y', $startTs);
                }
                $startTime = $startTs ? date('H:i', $startTs) : '';
                $endTime = $endTs ? date('H:i', $endTs) : '';

                $when = trim($dateStr . ' ' . ($startTime !== '' ? ($startTime . ($endTime !== '' ? '–' . $endTime : '')) : ''));
                $whenHuman = '';
                if ($startTs) {
                    $shift_start_ts = $startTs;
                    $todayStart = strtotime(date('Y-m-d 00:00:00'));
                    $startDay = strtotime(date('Y-m-d 00:00:00', $startTs));
                    $days = (int) floor(($startDay - $todayStart) / 86400);
                    if ($days <= 0) {
                        $whenHuman = 'היום';
                    } elseif ($days === 1) {
                        $whenHuman = 'מחר';
                    } else {
                        $whenHuman = 'בעוד ' . $days . ' ימים';
                    }
                }
                $when = trim(($whenHuman !== '' ? ($whenHuman . ' • ') : '') . $when);

                $is_shift_banner = true;
                $shift_job_title = $jobTitle;
                $shift_type_name = $typeName;
                $shift_when = $when;
                $shift_color = $jobColor;

                $banner_type = 'info';
                $banner_text = 'משמרת קרובה';
            } else {
                // No upcoming shifts -> do not show the banner at all.
                return;
            }
        }
    } catch (Throwable $e) {
        // If DB/table isn't available, do not risk showing misleading info.
        return;
    }
}

// If the page didn't explicitly set a banner message, we only show when we have an upcoming shift.
if ($banner_text === '') {
    return;
}

$banner_role = ($banner_type === 'error' || $banner_type === 'warn') ? 'alert' : 'status';
$banner_icon = 'fa-circle-info';
if ($banner_type === 'success') {
    $banner_icon = 'fa-circle-check';
} elseif ($banner_type === 'warn') {
    $banner_icon = 'fa-triangle-exclamation';
} elseif ($banner_type === 'error') {
    $banner_icon = 'fa-circle-xmark';
}
?>

<style>
/* =========================================
   Banner Alert (scoped to banner partial)
========================================= */
.tazrim-banner {
    position: relative;
    top: auto;
    margin-top: 12px;
    background-color: rgba(35, 114, 39, 0.08);
    border: 1px solid rgba(41, 182, 105, 0.45);
    box-shadow: 0 6px 18px rgba(41, 182, 105, 0.08);
    border-radius: 100px;
    z-index: 10;
}

.tazrim-banner::before { display: none; }

.tazrim-banner__content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.tazrim-banner__shiftline {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    overflow: hidden;
}

.tazrim-banner__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--tazrim-shift-c, var(--main));
    flex-shrink: 0;
}

.tazrim-banner__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(41, 182, 105, 0.12);
    border: 1px solid rgba(41, 182, 105, 0.22);
    font-weight: 900;
    font-size: 0.8rem;
    color: #1a1d24;
    white-space: nowrap;
    flex-shrink: 0;
}

.tazrim-banner__badge--job {
    background: rgba(255, 255, 255, 0.65);
    border-color: rgba(41, 182, 105, 0.22);
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tazrim-banner__badge--job span:last-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tazrim-banner__badge i { color: var(--main); }

.tazrim-banner__cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 0.9rem;
    line-height: 1;
    white-space: nowrap;
    flex-shrink: 0;
    width: auto;
    margin-top: 0;
    text-decoration: none;
    color: var(--white);
}

.tazrim-banner__cta-btn,
.tazrim-banner__cta-btn span,
.tazrim-banner__cta-btn i {
    color: #fff !important;
}

.tazrim-banner__icon {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: rgba(41, 182, 105, 0.14);
    border: 1px solid rgba(41, 182, 105, 0.35);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tazrim-banner__icon i {
    color: var(--main);
    font-size: 1.05rem;
}

.tazrim-banner__text { font-weight: 700; color: var(--text); }
.tazrim-banner__link { font-weight: 800; color: var(--main); text-decoration: underline; }
.tazrim-banner__link:hover { opacity: 0.85; }

/* Variants */
.tazrim-banner--info {
    background-color: rgba(35, 114, 39, 0.08);
    border-color: rgba(41, 182, 105, 0.45);
}
.tazrim-banner--success {
    background-color: rgba(104, 211, 145, 0.12);
    border-color: rgba(104, 211, 145, 0.55);
}
.tazrim-banner--success .tazrim-banner__icon {
    background: rgba(104, 211, 145, 0.18);
    border-color: rgba(104, 211, 145, 0.45);
}
.tazrim-banner--success .tazrim-banner__icon i,
.tazrim-banner--success .tazrim-banner__link { color: var(--success); }

.tazrim-banner--warn {
    background-color: rgba(246, 173, 85, 0.14);
    border-color: rgba(246, 173, 85, 0.6);
}
.tazrim-banner--warn .tazrim-banner__icon {
    background: rgba(246, 173, 85, 0.18);
    border-color: rgba(246, 173, 85, 0.5);
}
.tazrim-banner--warn .tazrim-banner__icon i,
.tazrim-banner--warn .tazrim-banner__link { color: var(--notice); }

.tazrim-banner--error {
    background-color: rgba(245, 101, 101, 0.12);
    border-color: rgba(245, 101, 101, 0.55);
}
.tazrim-banner--error .tazrim-banner__icon {
    background: rgba(245, 101, 101, 0.16);
    border-color: rgba(245, 101, 101, 0.45);
}
.tazrim-banner--error .tazrim-banner__icon i,
.tazrim-banner--error .tazrim-banner__link { color: var(--error); }

@media (max-width: 560px) {
    .tazrim-banner__content { flex-wrap: nowrap; overflow: hidden; }
    .tazrim-banner__badge { padding: 5px 8px; }
    .tazrim-banner__badge--job { max-width: 160px; }
    .tazrim-banner__cta-btn { padding: 9px 12px; font-size: 0.85rem; }
}
</style>

<section class="top-bar tazrim-banner tazrim-banner--<?php echo htmlspecialchars($banner_type, ENT_QUOTES, 'UTF-8'); ?>" id="tazrimTopBanner" role="<?php echo $banner_role; ?>" aria-live="polite">
    <div class="header-right">
        <div class="tazrim-banner__content">
            <span class="tazrim-banner__icon" aria-hidden="true">
                <i class="fa-solid <?php echo $banner_icon; ?>"></i>
            </span>
            <?php if ($is_shift_banner): ?>
                <div class="tazrim-banner__shiftline" role="group" aria-label="משמרת קרובה" style="--tazrim-shift-c: <?php echo htmlspecialchars($shift_color !== '' ? $shift_color : '#29b669', ENT_QUOTES, 'UTF-8'); ?>;">
                    <span class="tazrim-banner__badge tazrim-banner__badge--job" aria-label="עבודה">
                        <span class="tazrim-banner__dot" aria-hidden="true"></span>
                        <span><?php echo htmlspecialchars($shift_job_title !== '' ? $shift_job_title : 'עבודה', ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                    <?php if ($shift_type_name !== ''): ?>
                        <span class="tazrim-banner__badge" aria-label="סוג משמרת">
                            <i class="fa-solid fa-clock" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($shift_type_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($shift_when !== ''): ?>
                        <span class="tazrim-banner__badge" aria-label="מתי המשמרת">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <span><?php echo htmlspecialchars($shift_when, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <span class="tazrim-banner__text"><?php echo htmlspecialchars($banner_text, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($banner_href !== ''): ?>
                    <a class="tazrim-banner__link" href="<?php echo htmlspecialchars($banner_href, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($banner_link_text, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-left">
        <?php if ($is_shift_banner && $banner_href !== ''): ?>
            <a class="btn-primary tazrim-banner__cta-btn" href="<?php echo htmlspecialchars($banner_href, ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo htmlspecialchars($banner_link_text, ENT_QUOTES, 'UTF-8'); ?></span>
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>
</section>
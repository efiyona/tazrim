<?php
require_once '../path.php';
include ROOT_PATH . '/app/database/db.php';
include ROOT_PATH . '/assets/includes/auth_check.php';

$home_id = (int) ($_SESSION['home_id'] ?? 0);
$home_data = $home_id > 0 ? selectOne('homes', ['id' => $home_id]) : null;
if (!$home_data) {
    $home_data = ['name' => ''];
}

$uid = (int) ($_SESSION['id'] ?? 0);
$u = $uid > 0 ? selectOne('users', ['id' => $uid]) : null;
$work_enabled = $u && !empty($u['work_schedule_enabled']);

$work_palette = ['#5B8DEF', '#E85D75', '#2BB673', '#F5A524', '#9B6BFF', '#00B8D4', '#FF7B54', '#6B7C93'];
$work_palette_first = $work_palette[0];

$job_count = 0;
$needs_wizard = false;
if ($work_enabled) {
    $jc = @mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c FROM `user_work_jobs` WHERE `user_id` = ' . (int) $uid
    );
    if ($jc) {
        $row = mysqli_fetch_assoc($jc);
        $job_count = (int) ($row['c'] ?? 0);
    }
    $needs_wizard = ($job_count === 0);
}

if (isset($_GET['m']) && isset($_GET['y'])) {
    $cal_m = (int) $_GET['m'];
    $cal_y = (int) $_GET['y'];
    if ($cal_m < 1 || $cal_m > 12) {
        $cal_m = (int) date('m');
    }
    if ($cal_y < 2000 || $cal_y > 2100) {
        $cal_y = (int) date('Y');
    }
    $_SESSION['view_month'] = $cal_m;
    $_SESSION['view_year'] = $cal_y;
} else {
    $cal_m = $_SESSION['view_month'] ?? (int) date('m');
    $cal_y = $_SESSION['view_year'] ?? (int) date('Y');
}

$hebrew_months = [
    1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
    5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
    9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר',
];
$GLOBALS['tazrim_work_schedule_hide_fab'] = $work_enabled && $needs_wizard;

$today_m = (int) date('m');
$today_y = (int) date('Y');
$is_current_cal_month = ($cal_m === $today_m && $cal_y === $today_y);

$prev_m = $cal_m - 1;
$prev_y = $cal_y;
if ($prev_m < 1) {
    $prev_m = 12;
    $prev_y--;
}
$next_m = $cal_m + 1;
$next_y = $cal_y;
if ($next_m > 12) {
    $next_m = 1;
    $next_y++;
}

$ws_api = BASE_URL . 'app/ajax/work_schedule.php';
$page_url = BASE_URL . 'pages/work_schedule.php';
$account_work_url = BASE_URL . 'pages/settings/user_profile.php#work-account-jobs';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include ROOT_PATH . '/assets/includes/setup_meta_data.php'; ?>
    <title>סידור עבודה | התזרים</title>
</head>
<body class="bg-gray">
<div class="sidebar-overlay" id="overlay"></div>
<div class="dashboard-container">
    <?php include ROOT_PATH . '/assets/includes/sidebar_bavbar.php'; ?>
    <div class="content-wrapper">
        <div class="work-schedule-page" id="work-schedule-root">
            <?php if (!$work_enabled): ?>
                <div class="work-schedule-blocked">
                    <h1 class="section-title">סידור עבודה</h1>
                    <p>התכונה אינה פעילה לחשבון שלכם. לפרטים נא לפנות לתמיכה.</p>
                    <a class="btn-primary" href="<?php echo htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8'); ?>">לדף הראשי</a>
                </div>
            <?php elseif ($needs_wizard): ?>
                <div class="work-welcome-card shopping-welcome-card" id="work-wizard">
                    <div class="shopping-stepper-dots work-wizard-dots">
                        <div class="dot active" id="work-dot-1"></div>
                        <div class="dot" id="work-dot-2"></div>
                    </div>
                    <div class="work-wizard-step active" id="work-step-1">
                        <div class="shopping-wizard-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></div>
                        <h1 class="work-welcome-title">סידור עבודה</h1>
                        <p class="work-welcome-text">רשמו את המשמרות שלכם — כמה מקורות הכנסה, צבע לכל אחד, וסוגי משמרות. אפשר לערוך בהמשך.</p>
                        <button type="button" class="btn-welcome" onclick="workWizardStep(2)">בואו נתחיל <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                    </div>
                    <div class="work-wizard-step" id="work-step-2" hidden>
                        <h2 class="work-wizard-h2">הגדרת עבודה ראשונה</h2>
                        <p class="work-wizard-hint">שם המקום, צבע בלוח, יום שכר בחודש, וסוגי משמרות.</p>
                        <label class="work-field-label" for="wiz-job-title">שם העבודה</label>
                        <input type="text" id="wiz-job-title" class="work-input" placeholder="למשל: שיפוצים" autocomplete="off" />
                        <span class="work-field-label">צבע בלוח</span>
                        <div class="work-palette work-palette--centered" id="wiz-palette" role="list">
                            <?php
                            foreach ($work_palette as $i => $hex) {
                                $sel = $i === 0 ? ' work-palette-swatch--selected' : '';
                                echo '<button type="button" class="work-palette-swatch' . $sel . '" data-color="' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '" style="background:' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . '" aria-pressed="' . ($i === 0 ? 'true' : 'false') . '" aria-label="צבע"></button>';
                            }
                            ?>
                        </div>
                        <input type="hidden" id="wiz-color" value="<?php echo htmlspecialchars($work_palette_first, ENT_QUOTES, 'UTF-8'); ?>" />
                        <label class="work-field-label" for="wiz-payday">יום קבלת שכר בחודש (1–31)</label>
                        <input type="number" id="wiz-payday" class="work-input" min="1" max="31" value="10" />
                        <span class="work-field-label">סוגי משמרות <span class="work-optional">(אופציונלי)</span></span>
                        <p class="work-wizard-hint" style="margin-top:0">אפשר להוסיף סוגים עם שם, שעות ברירת מחדל ואייקון — או להשאיר ריק ולהגדיר בהמשך.</p>
                        <div id="wiz-types-wrap"></div>
                        <button type="button" class="btn-primary work-add-type-btn" id="wiz-add-type"><i class="fa-solid fa-plus" aria-hidden="true"></i> הוספת סוג משמרת</button>
                        <div id="wiz-msg" class="work-modal-msg" style="display:none"></div>
                        <button type="button" class="btn-welcome" id="wizard-finish-btn">שמירה והתחלה</button>
                    </div>
                </div>
            <?php else: ?>
                <div id="work-main-calendar">
                    <div class="page-header-actions page-header-actions--home flex-between work-cal-header" style="margin-bottom:18px">
                        <div class="page-header-actions__title-wrap">
                            <h1 class="section-title" style="margin-bottom:0">סידור עבודה</h1>
                        </div>
                        <div class="home-month-nav shopping-tabs-bar<?php echo !$is_current_cal_month ? ' home-month-nav--has-today' : ''; ?>" id="work-month-nav" aria-label="ניווט בין חודשים">
                            <div class="shopping-store-tabs">
                                <a id="work-month-prev" href="<?php echo htmlspecialchars($page_url . '?m=' . $prev_m . '&y=' . $prev_y, ENT_QUOTES, 'UTF-8'); ?>" data-m="<?php echo (int) $prev_m; ?>" data-y="<?php echo (int) $prev_y; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש קודם">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    <span data-role="month-label"><?php echo htmlspecialchars($hebrew_months[$prev_m], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                                <div class="home-month-nav__center-cell">
                                    <span class="shopping-tab-chip active home-month-nav__current" id="work-month-current" aria-current="page">
                                        <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                                        <span data-role="current-label"><?php echo htmlspecialchars($hebrew_months[$cal_m] . ' ' . $cal_y, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <a id="work-month-today" href="<?php echo htmlspecialchars($page_url . '?m=' . $today_m . '&y=' . $today_y, ENT_QUOTES, 'UTF-8'); ?>" data-m="<?php echo (int) $today_m; ?>" data-y="<?php echo (int) $today_y; ?>" class="shopping-tab-chip shopping-tab-add home-month-nav__today" title="חזרה לחודש הנוכחי"<?php echo $is_current_cal_month ? ' hidden' : ''; ?>>
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                        <span>היום</span>
                                    </a>
                                </div>
                                <a id="work-month-next" href="<?php echo htmlspecialchars($page_url . '?m=' . $next_m . '&y=' . $next_y, ENT_QUOTES, 'UTF-8'); ?>" data-m="<?php echo (int) $next_m; ?>" data-y="<?php echo (int) $next_y; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש הבא">
                                    <span data-role="month-label"><?php echo htmlspecialchars($hebrew_months[$next_m], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="work-cal-legend" id="work-cal-legend" aria-label="מקרא עבודות"></div>
                    <div class="work-cal-grid work-cal-grid--modern" id="work-cal-grid" role="grid" aria-label="לוח שנה"></div>
                    <div id="work-day-sheet-wrap" class="work-day-sheet-wrap" aria-hidden="true">
                        <button type="button" class="work-day-sheet__backdrop" id="work-day-sheet-backdrop" aria-label="סגור"></button>
                        <div id="work-day-sheet" class="work-day-sheet" role="dialog" aria-modal="true" aria-labelledby="work-day-sheet-title">
                            <div class="work-day-sheet__handle" aria-hidden="true"></div>
                            <button type="button" class="work-day-sheet__close" id="work-day-sheet-close" aria-label="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            <div id="work-day-sheet-title" class="work-day-sheet__title" role="heading" aria-level="2"></div>
                            <div id="work-day-sheet-list" class="work-day-sheet__list"></div>
                        </div>
                    </div>
                    <div class="work-manage-cta">
                        <a class="btn-primary work-manage-cta__btn" href="<?php echo htmlspecialchars($account_work_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-solid fa-briefcase" aria-hidden="true"></i> ניהול עבודות וסוגי משמרת
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </main>
</div>

<?php if ($work_enabled && !$needs_wizard): ?>
<div id="work-shift-quick-modal" class="modal work-shift-modal" style="display:none" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="work-shift-modal-title">
    <div class="modal-content work-shift-modal__content" role="document">
        <div class="modal-header">
            <h3 id="work-shift-modal-title">הוספת משמרת</h3>
            <button type="button" class="close-modal-btn" id="work-shift-modal-close" aria-label="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ws-modal-mode" value="add" />
            <input type="hidden" id="ws-modal-shift-id" value="" />
            <input type="hidden" id="ws-modal-day" value="" />
            <input type="hidden" id="ws-modal-job" value="" />
            <input type="hidden" id="ws-modal-type" value="0" />
            <span class="work-field-label">עבודה <span class="work-req">*</span></span>
            <div id="ws-modal-job-chips" class="work-store-chip-wrap" role="listbox" aria-label="בחירת עבודה"></div>
            <span class="work-field-label">סוג משמרת</span>
            <div id="ws-modal-type-chips" class="work-store-chip-wrap" role="listbox" aria-label="בחירת סוג משמרת"></div>
            <div class="work-time-fields" role="group" aria-label="שעות משמרת">
                <div class="work-time-field">
                    <label class="work-field-label work-field-label--inline" for="ws-modal-start-time">התחלה</label>
                    <input type="time" id="ws-modal-start-time" class="work-input work-input--time" step="60" />
                </div>
                <div class="work-time-field">
                    <label class="work-field-label work-field-label--inline" for="ws-modal-end-time">סיום</label>
                    <input type="time" id="ws-modal-end-time" class="work-input work-input--time" step="60" />
                </div>
            </div>
            <p class="work-field-hint work-field-hint--subtle" id="ws-modal-overnight-hint" style="display:none">המשמרת מסתיימת ביום המחרת</p>
            <label class="work-field-label" for="ws-modal-note">הערה (אופציונלי)</label>
            <input type="text" id="ws-modal-note" class="work-input" maxlength="500" />
            <div class="work-modal-actions">
                <button type="button" class="btn-primary" id="ws-modal-save">שמור</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
var TAZRIM_WORK_SCHEDULE = {
  api: <?php echo json_encode($ws_api, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
  pageUrl: <?php echo json_encode($page_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
  accountWorkUrl: <?php echo json_encode($account_work_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
  year: <?php echo (int) $cal_y; ?>,
  month: <?php echo (int) $cal_m; ?>
};

function workWizardStep(n) {
  document.querySelectorAll('.work-wizard-step').forEach(function (s) { s.hidden = true; s.classList.remove('active'); });
  var t = document.getElementById('work-step-' + n);
  if (t) { t.hidden = false; t.classList.add('active'); }
  document.querySelectorAll('.work-wizard-dots .dot').forEach(function (d, i) { d.classList.toggle('active', i + 1 === n); });
}
(function () {
  var pal = document.getElementById('wiz-palette');
  if (pal) {
    pal.addEventListener('click', function (e) {
      var b = e.target.closest('.work-palette-swatch');
      if (!b) return;
      var c = b.getAttribute('data-color');
      document.querySelectorAll('#wiz-palette .work-palette-swatch').forEach(function (x) { x.classList.remove('work-palette-swatch--selected'); x.setAttribute('aria-pressed', 'false'); });
      b.classList.add('work-palette-swatch--selected'); b.setAttribute('aria-pressed', 'true');
      var h = document.getElementById('wiz-color');
      if (h && c) h.value = c;
    });
  }
})();
</script>
<?php if ($work_enabled): ?>
<?php
$ws_js_path = ROOT_PATH . '/assets/js/work_schedule.js';
$ws_js_ver = is_file($ws_js_path) ? filemtime($ws_js_path) : time();
$ws_js_url = BASE_URL . 'assets/js/work_schedule.js?v=' . $ws_js_ver;
?>
<script src="<?php echo htmlspecialchars($ws_js_url, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
</body>
</html>

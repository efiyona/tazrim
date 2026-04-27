<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'לוח בקרה — ניהול מערכת';
$registry = tazrim_admin_registry();
global $conn;

$stats = [
    'users_total' => 0,
    'homes_total' => 0,
    'reports_pending' => 0,
    'landing_visits' => 0,
];
$statQueries = [
    'users_total' => 'SELECT COUNT(*) AS c FROM `users`',
    'homes_total' => 'SELECT COUNT(*) AS c FROM `homes`',
    'reports_pending' => "SELECT COUNT(*) AS c FROM `feedback_reports` WHERE `status` IN ('new', 'in_review')",
];
foreach ($statQueries as $key => $sql) {
    $res = mysqli_query($conn, $sql);
    $stats[$key] = $res ? (int) mysqli_fetch_assoc($res)['c'] : 0;
}
$lvRes = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `landing_page_events`');
$stats['landing_visits'] = $lvRes ? (int) mysqli_fetch_assoc($lvRes)['c'] : 0;

$latestUsers = [];
$usersRes = mysqli_query($conn, "SELECT id, first_name, last_name, email, role FROM `users` ORDER BY `id` DESC LIMIT 5");
if ($usersRes) {
    while ($row = mysqli_fetch_assoc($usersRes)) {
        $latestUsers[] = $row;
    }
}

$latestHomes = [];
$homesRes = mysqli_query($conn, "SELECT id, name, join_code FROM `homes` ORDER BY `id` DESC LIMIT 5");
if ($homesRes) {
    while ($row = mysqli_fetch_assoc($homesRes)) {
        $latestHomes[] = $row;
    }
}

$pendingReports = [];
$reportsRes = mysqli_query($conn, "SELECT id, title, kind, status, context_screen FROM `feedback_reports` WHERE `status` IN ('new', 'in_review') ORDER BY `created_at` DESC, `id` DESC LIMIT 6");
if ($reportsRes) {
    while ($row = mysqli_fetch_assoc($reportsRes)) {
        $pendingReports[] = $row;
    }
}

$latestRegisteredUser = null;
$latestUserRes = mysqli_query($conn, "SELECT id, first_name, last_name, email, created_at FROM `users` ORDER BY `created_at` DESC LIMIT 1");
if ($latestUserRes && mysqli_num_rows($latestUserRes) > 0) {
    $latestRegisteredUser = mysqli_fetch_assoc($latestUserRes);
}

$admin_nav_context = 'dashboard';

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full min-w-0 max-w-full">
    <section class="admin-dashboard-hero">
        <div>
            <p class="admin-dashboard-hero__eyebrow">ניהול מערכת</p>
            <h1 class="admin-dashboard-hero__title">דשבורד ניהול</h1>
            <p class="admin-dashboard-hero__subtitle">מבט מהיר על משתמשים, בתים ודיווחי משתמשים שממתינים לטיפול.</p>
        </div>
        <a class="admin-dashboard-hero__cta" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=feedback_reports', ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-bug"></i>
            מעבר לדיווחים
        </a>
    </section>

    <section class="admin-kpi-grid" id="admin-dashboard-kpi">
        <a class="admin-kpi-card" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=users', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="admin-kpi-card__icon"><i class="fa-solid fa-users"></i></span>
            <div class="admin-kpi-card__label">משתמשים רשומים</div>
            <div class="admin-kpi-card__value"><span class="js-admin-kpi-counter" data-kpi-target="<?php echo (int) $stats['users_total']; ?>">0</span></div>
        </a>
        <a class="admin-kpi-card" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=homes', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="admin-kpi-card__icon"><i class="fa-solid fa-house-user"></i></span>
            <div class="admin-kpi-card__label">בתים פעילים</div>
            <div class="admin-kpi-card__value"><span class="js-admin-kpi-counter" data-kpi-target="<?php echo (int) $stats['homes_total']; ?>">0</span></div>
        </a>
        <a class="admin-kpi-card admin-kpi-card--alert" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=feedback_reports', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="admin-kpi-card__icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div class="admin-kpi-card__label">דיווחים ממתינים</div>
            <div class="admin-kpi-card__value"><span class="js-admin-kpi-counter" data-kpi-target="<?php echo (int) $stats['reports_pending']; ?>">0</span></div>
        </a>
        <a class="admin-kpi-card" href="<?php echo htmlspecialchars(BASE_URL . 'admin/landing_events.php', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="admin-kpi-card__icon"><i class="fa-solid fa-door-open"></i></span>
            <div class="admin-kpi-card__label">ביקורים בדף נחיתה</div>
            <div class="admin-kpi-card__value"><span class="js-admin-kpi-counter" data-kpi-target="<?php echo (int) $stats['landing_visits']; ?>">0</span></div>
        </a>

    </section>

    <script>
    (function () {
        function formatCount(n) {
            return n.toLocaleString('en-US');
        }
        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }
        var nodes = document.querySelectorAll('#admin-dashboard-kpi .js-admin-kpi-counter');
        var duration = 950;
        nodes.forEach(function (el, i) {
            var raw = el.getAttribute('data-kpi-target') || '0';
            var target = parseInt(raw, 10);
            if (isNaN(target) || target < 0) {
                target = 0;
            }
            var delay = i * 70;
            window.setTimeout(function () {
                var t0 = performance.now();
                function tick(now) {
                    var p = Math.min(1, (now - t0) / duration);
                    var v = Math.round(target * easeOutCubic(p));
                    el.textContent = formatCount(v);
                    if (p < 1) {
                        requestAnimationFrame(tick);
                    }
                }
                requestAnimationFrame(tick);
            }, delay);
        });
    })();
    </script>

    <section class="admin-dashboard-grid">
        <article class="admin-dashboard-panel admin-dashboard-panel--wide">
            <div class="admin-dashboard-panel__head">
                <div>
                    <p class="admin-dashboard-panel__eyebrow">מעקב מיידי</p>
                    <h2 class="admin-dashboard-panel__title">דיווחי משתמשים ממתינים</h2>
                </div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=feedback_reports', ENT_QUOTES, 'UTF-8'); ?>" class="admin-dashboard-panel__link">לכל הדיווחים</a>
            </div>
            <?php if ($pendingReports === []): ?>
                <div class="admin-empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    אין כרגע דיווחים שממתינים לטיפול.
                </div>
            <?php else: ?>
                <div class="admin-dashboard-list">
                    <?php foreach ($pendingReports as $report): ?>
                        <a class="admin-dashboard-list__item" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=feedback_reports&id=' . urlencode((string) $report['id']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="admin-dashboard-list__main">
                                <strong><?php echo htmlspecialchars($report['title'] ?: ('דיווח #' . $report['id']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($report['context_screen'] ?: 'ללא הקשר מסך', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="admin-dashboard-list__meta">
                                <span class="admin-badge admin-badge--<?php echo $report['status'] === 'new' ? 'new' : 'review'; ?>"><?php echo $report['status'] === 'new' ? 'חדש' : 'בטיפול'; ?></span>
                                <span><?php echo htmlspecialchars($report['kind'] === 'bug' ? 'באג' : 'רעיון', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="admin-dashboard-panel">
            <div class="admin-dashboard-panel__head">
                <div>
                    <p class="admin-dashboard-panel__eyebrow">משתמשים</p>
                    <h2 class="admin-dashboard-panel__title">נרשמו לאחרונה</h2>
                </div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=users', ENT_QUOTES, 'UTF-8'); ?>" class="admin-dashboard-panel__link">ניהול משתמשים</a>
            </div>
            <div class="admin-dashboard-list">
                <?php foreach ($latestUsers as $user): ?>
                    <a class="admin-dashboard-list__item" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=users&id=' . urlencode((string) $user['id']), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="admin-dashboard-list__main">
                            <strong><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ('משתמש #' . $user['id']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="admin-dashboard-list__meta">
                            <span><?php echo htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="admin-dashboard-panel">
            <div class="admin-dashboard-panel__head">
                <div>
                    <p class="admin-dashboard-panel__eyebrow">בתים</p>
                    <h2 class="admin-dashboard-panel__title">בתים אחרונים</h2>
                </div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=homes', ENT_QUOTES, 'UTF-8'); ?>" class="admin-dashboard-panel__link">ניהול בתים</a>
            </div>
            <div class="admin-dashboard-list">
                <?php foreach ($latestHomes as $home): ?>
                    <a class="admin-dashboard-list__item" href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=homes&id=' . urlencode((string) $home['id']), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="admin-dashboard-list__main">
                            <strong><?php echo htmlspecialchars($home['name'] ?: ('בית #' . $home['id']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>קוד הצטרפות: <?php echo htmlspecialchars($home['join_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="admin-dashboard-list__meta">
                            <span>#<?php echo (int) $home['id']; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>
    </section>


<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

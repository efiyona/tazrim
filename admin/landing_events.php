<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'כניסות לדף נחיתה';
$admin_nav_context = 'landing_events';
global $conn;

$pagePath = trim((string) ($_GET['page_path'] ?? ''));
$queryFilter = trim((string) ($_GET['q'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));

$p = (int) ($_GET['p'] ?? 1);
if ($p < 1) {
    $p = 1;
}
$perPage = 50;
$offset = ($p - 1) * $perPage;

$where = [];
$params = [];
$types = '';

if ($pagePath !== '') {
    $where[] = '`page_path` LIKE ?';
    $params[] = '%' . $pagePath . '%';
    $types .= 's';
}
if ($queryFilter !== '') {
    $where[] = '`query_string` LIKE ?';
    $params[] = '%' . $queryFilter . '%';
    $types .= 's';
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'DATE(`created_at`) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'DATE(`created_at`) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$countSql = "SELECT COUNT(*) AS c FROM `landing_page_events` $whereSql";
$stmt = $conn->prepare($countSql);
if ($stmt) {
    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $total = (int) ($row['c'] ?? 0);
    $stmt->close();
}

$rows = [];
$listSql = "SELECT `id`,`created_at`,`page_path`,`referer`,`user_agent`,`query_string`
            FROM `landing_page_events`
            $whereSql
            ORDER BY `id` DESC
            LIMIT ? OFFSET ?";
$stmt2 = $conn->prepare($listSql);
if ($stmt2) {
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$perPage, $offset]);
    $stmt2->bind_param($types2, ...$params2);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2) {
        while ($r = $res2->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt2->close();
}

$pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
if ($p > $pages) {
    $p = $pages;
}

function admin_qs(array $overrides = []): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>

<main class="admin-page-main w-full min-w-0 max-w-full" id="admin-landing-events">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800">כניסות לדף נחיתה</h2>
        <p class="text-sm text-gray-600 mt-1">שורה אחת לכל טעינת דף תחת <code class="text-xs bg-gray-100 px-1 rounded">/landing</code> (בקשת GET).</p>
    </div>

    <section class="bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6 w-full min-w-0 mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" autocomplete="off">
            <div>
                <label class="block text-sm font-semibold text-gray-800 mb-1">page_path</label>
                <input type="text" name="page_path" value="<?php echo htmlspecialchars($pagePath, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400" placeholder="חלק מהנתיב">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-800 mb-1">מחרוזת שאילתה (UTM וכו')</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($queryFilter, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400" placeholder="utm_source">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-800 mb-1">מתאריך</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-800 mb-1">עד תאריך</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            </div>
            <div class="md:col-span-4 flex gap-2 justify-start">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2.5 px-4 shadow-sm transition-colors">
                    <i class="fa-solid fa-filter"></i>
                    סינון
                </button>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/landing_events.php', ENT_QUOTES, 'UTF-8'); ?>"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2.5 px-4 transition-colors">
                    איפוס
                </a>
                <div class="text-sm text-gray-600 ms-auto self-center">
                    סה&quot;כ: <strong><?php echo number_format($total); ?></strong>
                </div>
            </div>
        </form>
    </section>

    <section class="bg-white rounded-lg shadow border border-gray-100 w-full min-w-0 overflow-hidden">
        <?php if ($rows === []): ?>
            <div class="admin-empty-state p-8">
                <i class="fa-solid fa-circle-info"></i>
                אין רשומות להצגה לפי הסינון.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">id</th>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">created_at</th>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">page_path</th>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">query_string</th>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">referer</th>
                            <th class="text-right px-4 py-3 font-bold whitespace-nowrap">user_agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($rows as $r): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-900"><?php echo (int) ($r['id'] ?? 0); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars((string) ($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="<?php echo htmlspecialchars((string) ($r['page_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['page_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-3 text-gray-600 text-xs max-w-sm truncate" title="<?php echo htmlspecialchars((string) ($r['query_string'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['query_string'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-3 text-gray-600 text-xs max-w-sm truncate" title="<?php echo htmlspecialchars((string) ($r['referer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['referer'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="px-4 py-3 text-gray-600 text-xs max-w-md truncate" title="<?php echo htmlspecialchars((string) ($r['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($r['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between gap-3 p-4 border-t border-gray-100">
                <div class="text-sm text-gray-600">
                    עמוד <strong><?php echo (int) $p; ?></strong> מתוך <strong><?php echo (int) $pages; ?></strong>
                </div>
                <div class="flex gap-2">
                    <?php if ($p > 1): ?>
                        <a class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold"
                           href="<?php echo htmlspecialchars(BASE_URL . 'admin/landing_events.php?' . admin_qs(['p' => $p - 1]), ENT_QUOTES, 'UTF-8'); ?>">הקודם</a>
                    <?php endif; ?>
                    <?php if ($p < $pages): ?>
                        <a class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-semibold"
                           href="<?php echo htmlspecialchars(BASE_URL . 'admin/landing_events.php?' . admin_qs(['p' => $p + 1]), ENT_QUOTES, 'UTF-8'); ?>">הבא</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

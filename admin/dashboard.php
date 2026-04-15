<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'לוח בקרה — ניהול מערכת';
$registry = tazrim_admin_registry();
$stats = [];
global $conn;
foreach ($registry as $key => $cfg) {
    $t = $cfg['table'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
        continue;
    }
    $esc = mysqli_real_escape_string($conn, $t);
    $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$esc`");
    $stats[$key] = $q ? (int) mysqli_fetch_assoc($q)['c'] : 0;
}

$admin_nav_context = 'dashboard';

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full min-w-0 max-w-full">
    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 sm:mb-6">סיכום טבלאות</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($registry as $key => $cfg): ?>
            <a class="block bg-white rounded-lg shadow border border-gray-100 p-5 hover:shadow-md transition-shadow duration-200 group"
               href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=' . urlencode($key), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="text-sm font-semibold text-gray-500 mb-1"><?php echo htmlspecialchars($cfg['label'] ?? $key, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-3xl font-extrabold text-blue-600 tabular-nums"><?php echo (int) ($stats[$key] ?? 0); ?></div>
                <div class="text-xs text-gray-400 mt-3 group-hover:text-blue-500">ניהול רשומות ←</div>
            </a>
        <?php endforeach; ?>
    </div>
</main>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

<?php
/**
 * פתיחת מעטפת Tailwind: סיידבר, מובייל, שורת עליון, תוכן (ללא Alpine — תואם CSP ללא unsafe-eval).
 * דורש לפני כן: $admin_nav_context (אופציונלי), init.php.
 */
$u = tazrim_admin_current_user_row();
$registry = tazrim_admin_registry();
$navItems = tazrim_admin_nav_items();
$sidebarMetrics = tazrim_admin_sidebar_metrics();
$navCtx = $admin_nav_context ?? null;
if ($navCtx === null) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'dashboard.php') {
        $navCtx = 'dashboard';
    } elseif ($script === 'push_broadcast.php') {
        $navCtx = 'push_broadcast';
    } elseif ($script === 'popup_campaigns.php' || $script === 'popup_campaign_edit.php') {
        $navCtx = 'popup_campaigns';
    } else {
        $navCtx = '';
    }
}

$fn = isset($u['first_name']) ? trim((string) $u['first_name']) : '';
$ln = isset($u['last_name']) ? trim((string) $u['last_name']) : '';
$a1 = $fn !== '' ? mb_substr($fn, 0, 1, 'UTF-8') : 'מ';
$a2 = $ln !== '' ? mb_substr($ln, 0, 1, 'UTF-8') : 'נ';
$initials = $a1 . $a2;

$base = rtrim(BASE_URL, '/') . '/';
$isTableContext = isset($registry[$navCtx]) && !empty($registry[$navCtx]['table']);
$searchPlaceholder = $isTableContext
    ? 'חיפוש ב' . ($registry[$navCtx]['label'] ?? 'טבלה') . '...'
    : 'חיפוש בפאנל...';

function admin_nav_link_class(bool $active): string
{
    $cls = 'mb-1 px-2 py-2 rounded-lg flex items-center gap-3 font-medium transition-colors ';
    if ($active) {
        return $cls . 'text-blue-700 bg-gray-200';
    }
    return $cls . 'text-gray-700 hover:text-blue-600 hover:bg-gray-200';
}
?>
<div class="admin-tw-shell h-[100dvh] min-h-0 flex overflow-hidden min-w-0 max-w-[100vw]">

    <div class="md:hidden">
        <div
            id="admin-mobile-nav-overlay"
            class="fixed inset-0 z-30 bg-gray-600 transition-opacity ease-linear duration-300 opacity-0 pointer-events-none"
            aria-hidden="true"
        ></div>

        <div
            id="admin-mobile-nav-drawer"
            class="fixed inset-y-0 right-0 flex flex-col z-40 max-w-xs w-full bg-white shadow-xl transform ease-in-out duration-300 translate-x-full"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between px-4 py-3 h-16 border-b border-gray-100">
                <div class="text-xl font-bold tracking-tight text-gray-800">התזרים</div>
                <button type="button" id="admin-mobile-nav-close-btn" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600" aria-label="סגור תפריט">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="px-3 py-3 flex-1 overflow-y-auto">
                <ul class="space-y-0.5">
                    <?php foreach ($navItems as $item): ?>
                        <?php $isActive = tazrim_admin_nav_item_is_active($item, $navCtx); ?>
                        <?php if (($item['type'] ?? 'link') === 'group'): ?>
                            <li class="admin-nav-group">
                                <button type="button" class="<?php echo admin_nav_link_class($isActive); ?> w-full justify-between admin-nav-toggle"
                                    data-nav-label="<?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>">
                                    <span class="flex items-center gap-3 min-w-0">
                                        <i class="fa-solid <?php echo htmlspecialchars($item['icon'] ?? 'fa-folder-tree', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                        <span class="truncate"><?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-xs transition-transform <?php echo $isActive ? 'rotate-180' : ''; ?>"></i>
                                </button>
                                <ul class="mt-1 me-3 border-r border-gray-200 pr-2 admin-nav-group-children <?php echo $isActive ? '' : 'hidden'; ?>">
                                    <?php foreach (($item['children'] ?? []) as $child): ?>
                                        <li class="admin-nav-leaf">
                                            <a href="<?php echo htmlspecialchars($child['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"
                                               class="<?php echo admin_nav_link_class($navCtx === ($child['key'] ?? '')); ?> admin-nav-link admin-mobile-nav-close-link"
                                               data-nav-label="<?php echo htmlspecialchars($child['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-solid <?php echo htmlspecialchars($child['icon'] ?? 'fa-table', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                                <?php echo htmlspecialchars($child['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($item['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>"
                                   class="<?php echo admin_nav_link_class($isActive); ?> admin-nav-link admin-mobile-nav-close-link"
                                   data-nav-label="<?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fa-solid <?php echo htmlspecialchars($item['icon'] ?? 'fa-table', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                    <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <aside class="bg-white w-64 min-h-screen overflow-y-auto hidden md:block shadow relative z-30 shrink-0" aria-label="תפריט ניהול">
        <div class="flex items-center px-6 py-3 h-16 border-b border-gray-100">
            <div class="text-2xl font-bold tracking-tight text-gray-800">התזרים</div>
            <span class="mr-2 text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">ניהול</span>
        </div>
        <div class="px-3 py-3">
            <ul class="space-y-0.5">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = tazrim_admin_nav_item_is_active($item, $navCtx); ?>
                        <?php if (($item['type'] ?? 'link') === 'group'): ?>
                        <li class="admin-nav-group">
                            <button type="button" class="<?php echo admin_nav_link_class($isActive); ?> w-full justify-between admin-nav-toggle"
                                data-nav-label="<?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>">
                                <span class="flex items-center gap-3 min-w-0">
                                    <i class="fa-solid <?php echo htmlspecialchars($item['icon'] ?? 'fa-folder-tree', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                    <span class="truncate"><?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                                <i class="fa-solid fa-chevron-down text-xs transition-transform <?php echo $isActive ? 'rotate-180' : ''; ?>"></i>
                            </button>
                            <ul class="mt-1 me-3 border-r border-gray-200 pr-2 admin-nav-group-children <?php echo $isActive ? '' : 'hidden'; ?>">
                                <?php foreach (($item['children'] ?? []) as $child): ?>
                                    <li class="admin-nav-leaf">
                                        <a href="<?php echo htmlspecialchars($child['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === ($child['key'] ?? '')); ?> admin-nav-link" data-nav-label="<?php echo htmlspecialchars($child['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fa-solid <?php echo htmlspecialchars($child['icon'] ?? 'fa-table', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                            <?php echo htmlspecialchars($child['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($isActive); ?> admin-nav-link" data-nav-label="<?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid <?php echo htmlspecialchars($item['icon'] ?? 'fa-table', ENT_QUOTES, 'UTF-8'); ?> w-6 text-center opacity-50"></i>
                                <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <div class="bg-slate-900 text-white mb-6 p-4 rounded-2xl mt-8 shadow-lg">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold leading-tight">תמונת מצב מהירה</h2>
                        <p class="text-xs text-slate-300 mt-1">נתונים חשובים בלי לעזוב את התפריט.</p>
                    </div>
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/10 text-lg">
                        <i class="fa-solid fa-chart-simple"></i>
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-2 mt-4">
                    <div class="admin-sidebar-stat">
                        <span>משתמשים</span>
                        <strong><?php echo number_format((int) ($sidebarMetrics['users_total'] ?? 0)); ?></strong>
                    </div>
                    <div class="admin-sidebar-stat">
                        <span>בתים</span>
                        <strong><?php echo number_format((int) ($sidebarMetrics['homes_total'] ?? 0)); ?></strong>
                    </div>
                    <div class="admin-sidebar-stat admin-sidebar-stat--pending">
                        <span>דיווחים ממתינים</span>
                        <strong><?php echo number_format((int) ($sidebarMetrics['pending_reports'] ?? 0)); ?></strong>
                    </div>
                </div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>"
                   class="inline-flex items-center justify-center gap-2 w-full mt-4 text-center bg-white text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-white/50 font-semibold py-2 px-4 rounded-xl text-sm">
                    <i class="fa-solid fa-arrow-left-long"></i>
                    מעבר לדשבורד
                </a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-0 min-w-0 relative z-0 overflow-hidden">
        <div class="px-2 sm:px-4 md:px-8 py-2 min-h-16 flex justify-between items-center gap-2 shadow-sm bg-white shrink-0">
            <div class="flex items-center min-w-0 flex-1 gap-2">
                <input
                    type="search"
                    id="admin-topbar-search"
                    class="bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:bg-white border border-transparent focus:border-gray-300 rounded-lg py-2 px-4 w-full max-w-md hidden md:block placeholder-gray-500 ms-0"
                    placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                    data-context="<?php echo $isTableContext ? 'table' : 'panel'; ?>"
                    data-table-label="<?php echo htmlspecialchars($registry[$navCtx]['label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    autocomplete="off"
                >
                <button
                    type="button"
                    id="admin-mobile-nav-open-btn"
                    class="p-2 rounded-full hover:bg-gray-200 cursor-pointer md:hidden text-gray-600 shrink-0"
                    aria-label="פתח תפריט"
                    aria-expanded="false"
                    aria-controls="admin-mobile-nav-drawer"
                >
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="text-base sm:text-lg font-bold tracking-tight text-gray-800 md:hidden min-w-0 flex-1 truncate leading-tight">התזרים · ניהול</div>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <a href="<?php echo htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8'); ?>"
                   class="inline-flex text-gray-500 p-2 rounded-full hover:text-blue-600 hover:bg-gray-200"
                   title="האתר">
                    <i class="fa-solid fa-house"></i>
                </a>
                <div class="relative min-w-0" id="admin-profile-menu-root">
                    <button
                        type="button"
                        id="admin-profile-menu-btn"
                        class="cursor-pointer font-bold w-9 h-9 sm:w-10 sm:h-10 bg-blue-200 text-blue-700 flex items-center justify-center rounded-full hover:bg-blue-300 text-sm shrink-0"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="admin-profile-menu-panel"
                    >
                        <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <div
                        id="admin-profile-menu-panel"
                        class="hidden absolute top-full mt-2 end-0 w-[min(13rem,calc(100vw-1.5rem))] bg-white py-2 shadow-lg border border-gray-100 rounded-lg z-[60] text-right"
                        role="menu"
                        aria-hidden="true"
                    >
                        <?php if ($u && !empty($u['email'])): ?>
                            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 truncate"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/settings/user_profile.php', ENT_QUOTES, 'UTF-8'); ?>" class="block px-4 py-2 text-gray-600 hover:bg-gray-100 hover:text-blue-600" role="menuitem">החשבון שלי</a>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'logout.php', ENT_QUOTES, 'UTF-8'); ?>" class="block px-4 py-2 text-gray-600 hover:bg-gray-100 hover:text-red-600" role="menuitem">התנתקות</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden min-h-0 min-w-0 overscroll-y-contain">
            <div class="max-w-6xl mx-auto px-3 sm:px-4 md:px-8 py-4 sm:py-6 md:py-8 w-full min-w-0 box-border">

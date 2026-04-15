<?php
/**
 * פתיחת מעטפת Tailwind + Alpine: סיידבר, מובייל, שורת עליון, תוכן.
 * דורש לפני כן: $admin_nav_context (אופציונלי), init.php.
 */
$u = tazrim_admin_current_user_row();
$registry = tazrim_admin_registry();
$navCtx = $admin_nav_context ?? null;
if ($navCtx === null) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'dashboard.php') {
        $navCtx = 'dashboard';
    } elseif ($script === 'push_broadcast.php') {
        $navCtx = 'push_broadcast';
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

function admin_nav_link_class(bool $active): string
{
    $cls = 'mb-1 px-2 py-2 rounded-lg flex items-center gap-3 font-medium transition-colors ';
    if ($active) {
        return $cls . 'text-blue-700 bg-gray-200';
    }
    return $cls . 'text-gray-700 hover:text-blue-600 hover:bg-gray-200';
}
?>
<div class="admin-tw-shell h-[100dvh] min-h-0 flex overflow-hidden min-w-0 max-w-[100vw]" x-data="{ sidemenu: false }" x-cloak @keydown.window.escape="sidemenu = false">

    <div class="md:hidden">
        <div
            @click="sidemenu = false"
            class="fixed inset-0 z-30 bg-gray-600 transition-opacity ease-linear duration-300"
            :class="sidemenu ? 'opacity-75 pointer-events-auto' : 'opacity-0 pointer-events-none'"
            aria-hidden="true"
        ></div>

        <div
            class="fixed inset-y-0 right-0 flex flex-col z-40 max-w-xs w-full bg-white shadow-xl transform ease-in-out duration-300"
            :class="sidemenu ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="flex items-center justify-between px-4 py-3 h-16 border-b border-gray-100">
                <div class="text-xl font-bold tracking-tight text-gray-800">התזרים</div>
                <button type="button" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600" @click="sidemenu = false" aria-label="סגור תפריט">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="px-3 py-3 flex-1 overflow-y-auto">
                <ul class="space-y-0.5">
                    <li>
                        <a href="<?php echo htmlspecialchars($base . 'admin/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === 'dashboard'); ?>"
                           @click="sidemenu = false">
                            <i class="fa-solid fa-gauge-high w-6 text-center opacity-50"></i>
                            לוח בקרה
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($base . 'admin/push_broadcast.php', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === 'push_broadcast'); ?>"
                           @click="sidemenu = false">
                            <i class="fa-solid fa-bullhorn w-6 text-center opacity-50"></i>
                            שידור פוש
                        </a>
                    </li>
                    <?php foreach ($registry as $key => $cfg): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($base . 'admin/table.php?t=' . urlencode($key), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === $key); ?>"
                               @click="sidemenu = false">
                                <i class="fa-solid fa-table w-6 text-center opacity-50"></i>
                                <?php echo htmlspecialchars($cfg['label'] ?? $key, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
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
                <li>
                    <a href="<?php echo htmlspecialchars($base . 'admin/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === 'dashboard'); ?>">
                        <i class="fa-solid fa-gauge-high w-6 text-center opacity-50"></i>
                        לוח בקרה
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($base . 'admin/push_broadcast.php', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === 'push_broadcast'); ?>">
                        <i class="fa-solid fa-bullhorn w-6 text-center opacity-50"></i>
                        שידור פוש
                    </a>
                </li>
                <?php foreach ($registry as $key => $cfg): ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($base . 'admin/table.php?t=' . urlencode($key), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo admin_nav_link_class($navCtx === $key); ?>">
                            <i class="fa-solid fa-table w-6 text-center opacity-50"></i>
                            <?php echo htmlspecialchars($cfg['label'] ?? $key, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="bg-amber-100 border border-amber-200 mb-6 p-4 rounded-lg mt-8">
                <h2 class="text-gray-800 text-sm font-bold leading-tight">מערכת התזרים</h2>
                <p class="text-gray-700 text-sm mt-1 mb-3">ניהול טבלאות ונתוני המערכת.</p>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8'); ?>"
                   class="inline-flex items-center justify-center w-full text-center bg-blue-500 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 text-white font-semibold py-2 px-4 rounded-lg text-sm">
                    חזרה לאתר
                </a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-0 min-w-0 relative z-0 overflow-hidden">
        <div class="px-2 sm:px-4 md:px-8 py-2 min-h-16 flex justify-between items-center gap-2 shadow-sm bg-white shrink-0">
            <div class="flex items-center min-w-0 flex-1 gap-2">
                <input
                    type="search"
                    class="bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:bg-white border border-transparent focus:border-gray-300 rounded-lg py-2 px-4 w-full max-w-md hidden md:block placeholder-gray-500 ms-0"
                    placeholder="חיפוש…"
                    disabled
                    aria-disabled="true"
                    title="בקרוב"
                >
                <button
                    type="button"
                    class="p-2 rounded-full hover:bg-gray-200 cursor-pointer md:hidden text-gray-600 shrink-0"
                    @click="sidemenu = !sidemenu"
                    aria-label="פתח תפריט"
                >
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div class="text-base sm:text-lg font-bold tracking-tight text-gray-800 md:hidden min-w-0 flex-1 truncate leading-tight">התזרים · ניהול</div>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <a href="<?php echo htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8'); ?>"
                   class="hidden sm:inline-flex text-gray-500 p-2 rounded-full hover:text-blue-600 hover:bg-gray-200"
                   title="האתר">
                    <i class="fa-solid fa-house"></i>
                </a>
                <div class="relative min-w-0" x-data="{ open: false }" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="cursor-pointer font-bold w-9 h-9 sm:w-10 sm:h-10 bg-blue-200 text-blue-700 flex items-center justify-center rounded-full hover:bg-blue-300 text-sm shrink-0"
                        aria-expanded="false"
                        :aria-expanded="open"
                    >
                        <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <div
                        x-show="open"
                        x-transition
                        class="absolute top-full mt-2 end-0 w-[min(13rem,calc(100vw-1.5rem))] bg-white py-2 shadow-lg border border-gray-100 rounded-lg z-[60] text-right"
                    >
                        <?php if ($u && !empty($u['email'])): ?>
                            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 truncate"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/settings/user_profile.php', ENT_QUOTES, 'UTF-8'); ?>" class="block px-4 py-2 text-gray-600 hover:bg-gray-100 hover:text-blue-600">החשבון שלי</a>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="block px-4 py-2 text-gray-600 hover:bg-gray-100 hover:text-blue-600">חזרה לאתר</a>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'logout.php', ENT_QUOTES, 'UTF-8'); ?>" class="block px-4 py-2 text-gray-600 hover:bg-gray-100 hover:text-red-600">התנתקות</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto overflow-x-hidden min-h-0 min-w-0 overscroll-y-contain">
            <div class="max-w-6xl mx-auto px-3 sm:px-4 md:px-8 py-4 sm:py-6 md:py-8 w-full min-w-0 box-border">

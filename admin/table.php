<?php
require_once dirname(__FILE__) . '/includes/init.php';

$tableKey = isset($_GET['t']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['t']) : '';
if ($tableKey === '' || $tableKey !== ($_GET['t'] ?? '')) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$config = tazrim_admin_get_table_config($tableKey);
if (!$config) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

$listOnly = !empty($config['list_only']);
$openCreate = isset($_GET['create']) && $_GET['create'] === '1' && !$listOnly;
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($listOnly && $editId > 0) {
    header('Location: ' . BASE_URL . 'admin/table.php?t=' . urlencode($tableKey));
    exit;
}

$pageTitle = ($config['label'] ?? $tableKey) . ' — ניהול מערכת';
$csrf = tazrim_admin_csrf_token();
$allowDelete = !isset($config['allow_delete']) || $config['allow_delete'] !== false;

$admin_nav_context = $tableKey;

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full min-w-0 max-w-full" id="admin-app"
    data-table-key="<?php echo htmlspecialchars($tableKey, ENT_QUOTES, 'UTF-8'); ?>"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-base-url="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>"
    data-allow-delete="<?php echo $allowDelete ? '1' : '0'; ?>"
    data-edit-id="<?php echo $editId > 0 ? (int) $editId : ''; ?>"
    data-list-only="<?php echo $listOnly ? '1' : '0'; ?>"
    data-bulk-delete="<?php echo (!$listOnly && $allowDelete) ? '1' : '0'; ?>"
    data-modal-open="<?php echo ($editId > 0 || $openCreate) ? '1' : '0'; ?>"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 w-full min-w-0">
        <h2 class="text-lg sm:text-xl font-bold text-gray-800 min-w-0 break-words"><?php echo htmlspecialchars($config['label'] ?? $tableKey, ENT_QUOTES, 'UTF-8'); ?></h2>
    </div>

    <div id="admin-flash" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-semibold border" role="status"></div>

    <?php if (!$listOnly && $allowDelete): ?>
    <div id="admin-bulk-bar" class="hidden mb-3 flex flex-wrap items-center gap-3" role="region" aria-label="מחיקה מרובה">
        <button type="button" id="admin-bulk-delete-btn" disabled
            class="inline-flex items-center gap-2 rounded-lg bg-red-100 hover:bg-red-200 text-red-800 font-semibold py-2 px-4 text-sm disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
            מחק נבחרים (<span id="admin-bulk-count">0</span>)
        </button>
    </div>
    <?php endif; ?>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4 w-full min-w-0">
        <div class="admin-pagination flex items-center gap-2 sm:gap-3 flex-wrap text-sm text-gray-600 min-w-0" id="admin-pagination-top"></div>
        <?php if (!$listOnly): ?>
        <div class="shrink-0 w-full sm:w-auto">
            <a class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2.5 sm:py-2 px-4 shadow-sm transition-colors text-center"
               href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=' . urlencode($tableKey) . '&create=1', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                רשומה חדשה
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($listOnly): ?>
        <p class="text-sm text-gray-600 mb-4">מצב צפייה בלבד — אין עריכה או מחיקה מכאן.</p>
    <?php endif; ?>

    <div class="admin-table-wrap bg-white rounded-lg shadow border border-gray-100 overflow-x-auto mb-0">
        <table class="admin-data-table" id="admin-data-table">
            <thead><tr id="admin-thead-row"></tr></thead>
            <tbody id="admin-tbody"></tbody>
        </table>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4 mb-8">
        <div class="admin-pagination flex items-center gap-2 sm:gap-3 flex-wrap text-sm text-gray-600 min-w-0 w-full sm:w-auto" id="admin-pagination-bottom"></div>
    </div>

    <?php if (!$listOnly): ?>
    <section class="admin-modal <?php echo ($editId > 0 || $openCreate) ? '' : 'hidden'; ?>" id="admin-entity-modal" aria-hidden="<?php echo ($editId > 0 || $openCreate) ? 'false' : 'true'; ?>">
        <div class="admin-modal__backdrop"></div>
        <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-entity-modal-title">
            <div class="admin-modal__header">
                <div>
                    <p class="admin-modal__eyebrow"><?php echo htmlspecialchars($config['label'] ?? $tableKey, ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="admin-modal__title" id="admin-entity-modal-title">טוען...</h2>
                </div>
                <button type="button" class="admin-modal__close" id="admin-modal-close-btn" aria-label="סגור חלון">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form id="admin-entity-form" autocomplete="off" class="admin-modal__form">
                <div id="admin-form-fields"></div>
                <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 shadow-sm transition-colors" id="admin-save-btn">שמירה</button>
                    <button type="button" class="inline-flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 text-red-800 font-semibold py-2 px-5 transition-colors hidden" id="admin-delete-btn">מחיקה</button>
                    <button type="button" class="inline-flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-5 transition-colors" id="admin-cancel-btn">ביטול</button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>
</main>
<script src="<?php echo htmlspecialchars(tazrim_admin_asset_href('admin/assets/js/admin.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(tazrim_admin_asset_href('admin/assets/js/admin_fk_lookup.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

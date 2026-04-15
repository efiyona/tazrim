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

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($listOnly && $editId > 0) {
    header('Location: ' . BASE_URL . 'admin/table.php?t=' . urlencode($tableKey));
    exit;
}
$row = null;
$sqlTable = $config['table'];

if ($editId > 0) {
    $row = selectOne($sqlTable, ['id' => $editId]);
    if (!$row) {
        header('Location: ' . BASE_URL . 'admin/table.php?t=' . urlencode($tableKey));
        exit;
    }
}

$pageTitle = ($config['label'] ?? $tableKey) . ' — ניהול מערכת';
$csrf = tazrim_admin_csrf_token();
$allowDelete = !isset($config['allow_delete']) || $config['allow_delete'] !== false;

$hasFkLookup = false;
foreach (($config['fields'] ?? []) as $_fd) {
    if (($_fd['type'] ?? '') === 'fk_lookup') {
        $hasFkLookup = true;
        break;
    }
}

function tazrim_admin_field_value(array $fieldDef, $row, string $name)
{
    if (($fieldDef['type'] ?? '') === 'password_new') {
        return '';
    }
    if ($row && array_key_exists($name, $row)) {
        $v = $row[$name];
        if ($v === null) {
            return '';
        }
        if (($fieldDef['type'] ?? '') === 'checkbox') {
            return (int) $v ? '1' : '0';
        }
        return (string) $v;
    }
    if (($fieldDef['type'] ?? '') === 'checkbox') {
        return '1';
    }
    return '';
}

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
               href="<?php echo htmlspecialchars(BASE_URL . 'admin/table.php?t=' . urlencode($tableKey), ENT_QUOTES, 'UTF-8'); ?>">+ רשומה חדשה</a>
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
    <section class="bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6 mt-6 sm:mt-8 w-full min-w-0">
        <h2 class="text-lg font-bold text-gray-800 mb-4"><?php echo $editId > 0 ? 'עריכת רשומה #' . (int) $editId : 'הוספת רשומה'; ?></h2>
        <form id="admin-entity-form" autocomplete="off">
            <?php foreach (($config['fields'] ?? []) as $name => $fieldDef):
                $type = $fieldDef['type'] ?? 'text';
                $label = $fieldDef['label'] ?? $name;
                $val = tazrim_admin_field_value($fieldDef, $row ?? [], $name);
            ?>
                <div class="admin-field mb-4 <?php echo $type === 'checkbox' ? 'admin-field--checkbox' : ''; ?>">
                    <?php if ($type === 'checkbox'): ?>
                        <label>
                            <input type="checkbox" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="1" <?php echo $val === '1' ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php elseif ($type === 'enum'): ?>
                        <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                        <select id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php
                            $opts = isset($fieldDef['enum_options']) && is_array($fieldDef['enum_options']) ? $fieldDef['enum_options'] : [];
                            foreach ($opts as $ov => $olabel):
                            ?>
                                <option value="<?php echo htmlspecialchars((string) $ov, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) $val === (string) $ov ? 'selected' : ''; ?>><?php echo htmlspecialchars($olabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'password_new'): ?>
                        <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="password" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="" autocomplete="new-password" placeholder="<?php echo $editId > 0 ? 'השאר ריק אם אין שינוי' : ''; ?>">
                    <?php elseif ($type === 'fk_lookup'):
                        $fkCfg = $fieldDef['fk'] ?? [];
                        $fkOpt = !empty($fkCfg['optional']);
                        $sid = $val !== '' ? (int) $val : 0;
                        $initialFkLabel = ($sid > 0 && $fkCfg) ? tazrim_admin_fk_lookup_resolve_label($fkCfg, $sid) : '';
                        ?>
                        <div class="admin-fk-lookup"
                            data-entity="<?php echo htmlspecialchars($tableKey, ENT_QUOTES, 'UTF-8'); ?>"
                            data-field="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                            data-optional="<?php echo $fkOpt ? '1' : '0'; ?>"
                        >
                            <label for="fk_search_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                            <input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" class="admin-fk-value">
                            <div class="admin-fk-lookup__controls">
                                <input type="search" id="fk_search_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" class="admin-fk-search" placeholder="חיפוש ובחירה מהרשימה" value="<?php echo htmlspecialchars($initialFkLabel, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" data-locked-label="<?php echo htmlspecialchars($initialFkLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($fkOpt): ?>
                                    <button type="button" class="admin-fk-clear">ללא</button>
                                <?php endif; ?>
                            </div>
                            <ul class="admin-fk-results hidden" role="listbox"></ul>
                        </div>
                    <?php else: ?>
                        <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php if ($type === 'textarea'): ?>
                            <?php
                            $taRows = isset($fieldDef['rows']) ? max(4, min(50, (int) $fieldDef['rows'])) : 12;
                            ?>
                            <textarea id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" rows="<?php echo (int) $taRows; ?>"><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <?php elseif ($type === 'number' || $type === 'balance'): ?>
                            <input type="number" step="<?php echo $type === 'balance' ? 'any' : '1'; ?>" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php elseif ($type === 'datetime'): ?>
                            <input type="datetime-local" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                            <input type="text" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($sqlTable === 'homes' && $name === 'join_code') ? 'maxlength="4"' : ''; ?>>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-5 shadow-sm transition-colors" id="admin-save-btn">שמירה</button>
                <?php if ($editId > 0 && $allowDelete): ?>
                    <button type="button" class="inline-flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 text-red-800 font-semibold py-2 px-5 transition-colors" id="admin-delete-btn">מחיקה</button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>
</main>
<script src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/admin/assets/js/admin.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if (!$listOnly && $hasFkLookup): ?>
<script src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/admin/assets/js/admin_fk_lookup.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

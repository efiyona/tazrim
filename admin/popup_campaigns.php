<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'פופאפים למשתמשים';
$admin_nav_context = 'popup_campaigns';
$csrf = tazrim_admin_csrf_token();

global $conn;

$rows = [];
$res = mysqli_query(
    $conn,
    'SELECT `id`, `title`, `target_scope`, `status`, `is_active`, `sort_order`, `starts_at`, `ends_at`, `created_at`
     FROM `popup_campaigns` ORDER BY `sort_order` ASC, `id` DESC'
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full max-w-5xl min-w-0 mx-auto" id="admin-popup-campaigns-list"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-base-url="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800">פופאפים למשתמשים</h2>
            <p class="text-sm text-gray-600 mt-1">הודעות חוסמות עם אישור קריאה — לפי כל הבתים, בתים נבחרים או משתמשים.</p>
        </div>
        <a href="<?php echo htmlspecialchars(BASE_URL . 'admin/popup_campaign_edit.php', ENT_QUOTES, 'UTF-8'); ?>"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 shadow-sm transition-colors">
            <i class="fa-solid fa-plus"></i>
            קמפיין חדש
        </a>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-100 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-700 font-semibold border-b border-gray-100">
                <tr>
                    <th class="text-right py-3 px-4">#</th>
                    <th class="text-right py-3 px-4">כותרת</th>
                    <th class="text-right py-3 px-4">יעד</th>
                    <th class="text-right py-3 px-4">סטטוס</th>
                    <th class="text-right py-3 px-4">פעיל</th>
                    <th class="text-right py-3 px-4">סדר</th>
                    <th class="text-right py-3 px-4">תזמון</th>
                    <th class="text-right py-3 px-4">פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="8" class="py-10 text-center text-gray-500">אין קמפיינים עדיין.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $tid = (int) $r['id'];
                        $scope = (string) $r['target_scope'];
                        $scopeLabel = $scope === 'all' ? 'כל הבתים' : ($scope === 'homes' ? 'בתים' : 'משתמשים');
                        $st = (string) $r['status'];
                        $active = (int) $r['is_active'] === 1;
                        $s1 = $r['starts_at'] ? htmlspecialchars((string) $r['starts_at'], ENT_QUOTES, 'UTF-8') : '—';
                        $s2 = $r['ends_at'] ? htmlspecialchars((string) $r['ends_at'], ENT_QUOTES, 'UTF-8') : '—';
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50/80">
                            <td class="py-3 px-4 font-mono text-gray-600"><?php echo $tid; ?></td>
                            <td class="py-3 px-4 font-medium text-gray-900"><?php echo htmlspecialchars((string) $r['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-3 px-4">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?php echo $st === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'; ?>">
                                    <?php echo $st === 'published' ? 'מפורסם' : 'טיוטה'; ?>
                                </span>
                            </td>
                            <td class="py-3 px-4"><?php echo $active ? 'כן' : 'לא'; ?></td>
                            <td class="py-3 px-4"><?php echo (int) $r['sort_order']; ?></td>
                            <td class="py-3 px-4 text-xs text-gray-600 whitespace-nowrap"><?php echo $s1; ?> → <?php echo $s2; ?></td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <a class="text-blue-600 hover:underline font-medium" href="<?php echo htmlspecialchars(BASE_URL . 'admin/popup_campaign_edit.php?id=' . $tid, ENT_QUOTES, 'UTF-8'); ?>">עריכה</a>
                                <button type="button" class="text-emerald-700 hover:underline font-medium mr-3 js-dup-campaign" data-id="<?php echo $tid; ?>">שכפול</button>
                                <button type="button" class="text-red-600 hover:underline font-medium mr-3 js-del-campaign" data-id="<?php echo $tid; ?>">מחיקה</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
(function () {
    var root = document.getElementById('admin-popup-campaigns-list');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf');
    var base = root.getAttribute('data-base-url') || '/';
    function apiUrl(path) {
        return base.replace(/\/?$/, '/') + path.replace(/^\//, '');
    }
    function showErr(msg) {
        var m = msg || 'אירעה שגיאה.';
        if (typeof window.tazrimAlert === 'function') {
            window.tazrimAlert({ title: 'שגיאה', message: m });
        } else {
            console.error(m);
        }
    }
    function runDup(id) {
        fetch(apiUrl('admin/ajax/popup_campaign_duplicate.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrf, id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'ok' && data.id) {
                    window.location.href = apiUrl('admin/popup_campaign_edit.php?id=' + data.id);
                } else {
                    showErr(data.message);
                }
            })
            .catch(function () { showErr('שגיאת תקשורת.'); });
    }
    root.querySelectorAll('.js-dup-campaign').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!id) return;
            if (typeof window.tazrimConfirm !== 'function') {
                showErr('לא ניתן להציג אישור. רעננו את הדף.');
                return;
            }
            window.tazrimConfirm({
                title: 'שכפול קמפיין',
                message: 'לשכפל קמפיין זה? ייווצר עותק חדש כטיוטה.',
                confirmText: 'שכפול',
                cancelText: 'ביטול',
                danger: false
            }).then(function (ok) {
                if (ok) runDup(id);
            });
        });
    });
    function runDel(id) {
        fetch(apiUrl('admin/ajax/popup_campaign_delete.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrf, id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'ok') location.reload();
                else showErr(data.message);
            })
            .catch(function () { showErr('שגיאת תקשורת.'); });
    }
    root.querySelectorAll('.js-del-campaign').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-id'), 10);
            if (!id) return;
            if (typeof window.tazrimConfirm !== 'function') {
                showErr('לא ניתן להציג אישור. רעננו את הדף.');
                return;
            }
            window.tazrimConfirm({
                title: 'מחיקת קמפיין',
                message: 'למחוק את הקמפיין הזה? לא ניתן לבטל.',
                confirmText: 'מחיקה',
                cancelText: 'ביטול',
                danger: true
            }).then(function (ok) {
                if (ok) runDel(id);
            });
        });
    });
})();
</script>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';

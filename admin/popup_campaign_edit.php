<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'עריכת פופאפ';
$admin_nav_context = 'popup_campaigns';
$csrf = tazrim_admin_csrf_token();
$pushLinkOptions = tazrim_admin_push_link_options();

global $conn;

/**
 * @return string ערך לשדה datetime-local
 */
function tazrim_admin_popup_dt_to_local(?string $db): string
{
    if ($db === null || $db === '') {
        return '';
    }
    $ts = strtotime($db);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $ts);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$campaign = [
    'title' => '',
    'body_html' => '',
    'target_scope' => 'all',
    'ack_policy' => 'each_user',
    'status' => 'draft',
    'is_active' => 1,
    'sort_order' => 0,
    'starts_at' => '',
    'ends_at' => '',
    'form_schema' => '',
];

$home_chips = [];
$user_chips = [];

if ($id > 0) {
    $row = selectOne('popup_campaigns', ['id' => $id]);
    if (!$row) {
        header('Location: ' . BASE_URL . 'admin/popup_campaigns.php');
        exit;
    }
    $campaign['title'] = (string) $row['title'];
    $campaign['body_html'] = (string) $row['body_html'];
    $campaign['target_scope'] = (string) $row['target_scope'];
    $ap = isset($row['ack_policy']) ? (string) $row['ack_policy'] : 'each_user';
    $campaign['ack_policy'] = in_array($ap, ['each_user', 'one_per_home', 'primary_only'], true) ? $ap : 'each_user';
    $campaign['status'] = (string) $row['status'];
    $campaign['is_active'] = (int) $row['is_active'];
    $campaign['sort_order'] = (int) $row['sort_order'];
    $campaign['starts_at'] = tazrim_admin_popup_dt_to_local(isset($row['starts_at']) ? (string) $row['starts_at'] : null);
    $campaign['ends_at'] = tazrim_admin_popup_dt_to_local(isset($row['ends_at']) ? (string) $row['ends_at'] : null);
    $campaign['form_schema'] = isset($row['form_schema']) ? (string) $row['form_schema'] : '';

    $rq = mysqli_query($conn, 'SELECT `home_id` FROM `popup_campaign_homes` WHERE `campaign_id` = ' . (int) $id);
    if ($rq) {
        while ($r = mysqli_fetch_assoc($rq)) {
            $hid = (int) $r['home_id'];
            $h = selectOne('homes', ['id' => $hid]);
            $label = $h ? ($h['name'] . ' (#' . $hid . ')') : '#' . $hid;
            $home_chips[] = ['id' => $hid, 'label' => $label];
        }
    }

    $rq = mysqli_query($conn, 'SELECT `user_id` FROM `popup_campaign_users` WHERE `campaign_id` = ' . (int) $id);
    if ($rq) {
        while ($r = mysqli_fetch_assoc($rq)) {
            $uid = (int) $r['user_id'];
            $u = selectOne('users', ['id' => $uid]);
            if ($u) {
                $fn = trim((string) ($u['first_name'] ?? ''));
                $ln = trim((string) ($u['last_name'] ?? ''));
                $em = trim((string) ($u['email'] ?? ''));
                $nm = trim($fn . ' ' . $ln);
                $label = $nm !== '' ? ($nm . ' · ' . $em) : $em;
            } else {
                $label = '#' . $uid;
            }
            $user_chips[] = ['id' => $uid, 'label' => $label];
        }
    }
}

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';

$listHref = BASE_URL . 'admin/popup_campaigns.php';
?>
<main class="admin-page-main w-full max-w-2xl min-w-0 mx-auto" id="admin-popup-campaign-edit"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-base-url="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>"
    data-id="<?php echo (int) $id; ?>"
>
    <a href="<?php echo htmlspecialchars($listHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-sm text-blue-600 hover:underline mb-3 inline-block">← חזרה לרשימה</a>

    <section class="mb-6 rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50/90 to-white p-4 sm:p-5 shadow-sm" aria-label="יצירת תוכן עם AI">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900">יצירה עם AI</h3>
                <p class="text-xs text-gray-500 mt-0.5">מילוי אוטומטי של כותרת ותוכן HTML לפי ההנחיות שלכם (Gemini).</p>
            </div>
            <button type="button" id="pc_ai_btn"
                class="inline-flex items-center justify-center gap-2 shrink-0 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold py-2.5 px-4 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                בקשת תוכן מ-AI
            </button>
        </div>
        <div id="pc_ai_status" class="hidden mb-3 rounded-lg border border-violet-100 bg-violet-50/60 px-3 py-2.5 text-sm text-violet-800 flex items-center gap-2">
            <i class="fa-solid fa-spinner fa-spin text-violet-500" aria-hidden="true"></i>
            <span id="pc_ai_status_text">מנתח את ההנחיות…</span>
        </div>
        <div id="pc_ai_questions" class="hidden mb-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 space-y-4"></div>
        <label for="pc_ai_instructions" class="block text-sm font-semibold text-gray-800 mb-1">הוראות לבינה</label>
        <p class="text-xs text-amber-800/90 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-2">בכל הרצה השדות <strong>כותרת</strong> ו־<strong>תוכן (HTML)</strong> מתרוקנים ומוחלפים בתוצאה החדשה בלבד.</p>
        <textarea id="pc_ai_instructions" rows="4" placeholder="למשל: פופאפ להשקת עוזר צ'אט — להסביר איפה נמצא הכפתור, מה הוא נותן, בטון קליל. להדגיש שזה אופציונלי."
            class="w-full rounded-lg border border-violet-100 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 resize-y min-h-[96px]"></textarea>
        <div class="mt-4 pt-4 border-t border-violet-100">
            <label for="pc_ai_link_preset" class="block text-sm font-semibold text-gray-800 mb-1">כפתור קישור בתוכן (אופציונלי)</label>
            <p class="text-xs text-gray-500 mb-2">אם נבחר עמוד או קישור — ה-AI יוסיף כפתור מעוגל ירוק (#29b669) ב-HTML. כמו בשידור פוש: עמוד מהרשימה או נתיב מותאם.</p>
            <div class="space-y-3">
                <select id="pc_ai_link_preset"
                    class="w-full rounded-lg border border-violet-100 bg-white px-3 py-2 text-sm text-gray-900 focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
                    <option value="">ללא כפתור קישור</option>
                    <option value="custom">קישור מותאם אישית</option>
                    <?php foreach ($pushLinkOptions as $url => $label): ?>
                        <option value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="pc_ai_link" value=""
                    class="w-full rounded-lg border border-violet-100 bg-gray-100 px-3 py-2 text-sm text-gray-900 focus:ring-2 focus:ring-violet-300 focus:border-violet-400"
                    placeholder="/ או נתיב באתר (כשמופעל «קישור מותאם»)" readonly>
            </div>
        </div>
    </section>

    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800"><?php echo $id > 0 ? 'עריכת קמפיין #' . (int) $id : 'קמפיין פופאפ חדש'; ?></h2>
        <p class="text-sm text-gray-600 mt-1">תוכן HTML מוצג למשתמשים כפי שהוזן — השתמשו בזהירות. ניתן להגדיר למי נדרש אישור &quot;קראתי&quot; (ראו למטה).</p>
    </div>

    <div id="pc-flash" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-semibold border" role="status"></div>

    <form id="pc-form" class="space-y-6 bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6">
        <div>
            <label for="pc_title" class="block font-semibold text-gray-800 mb-2">כותרת</label>
            <input type="text" id="pc_title" required
                value="<?php echo htmlspecialchars($campaign['title'], ENT_QUOTES, 'UTF-8'); ?>"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400">
        </div>

        <div>
            <label for="pc_body" class="block font-semibold text-gray-800 mb-2">תוכן (HTML)</label>
            <textarea id="pc_body" name="body_html" rows="14" required
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 font-mono text-sm focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 resize-y"><?php echo htmlspecialchars($campaign['body_html'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div>
            <label for="pc_form_schema" class="block font-semibold text-gray-800 mb-2">סכמת טופס (JSON, אופציונלי)</label>
            <p class="text-xs text-gray-600 mb-2">מגדירה איזה שדות נשמרים ולאן (`submission_store` או `bank_balance`). ב־HTML: `data-tazrim-popup-action="submit"` ושמות שדות תואמים. ריק = קמפיין מידע בלבד או שימוש ב־save_bank_balance ללא סכמה (מצב ישן).</p>
            <textarea id="pc_form_schema" rows="8"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 font-mono text-xs focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 resize-y"
                placeholder='{"handler":"submission_store","fields":[{"name":"feedback","type":"textarea","required":false,"maxLength":2000}]}'><?php echo htmlspecialchars($campaign['form_schema'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <fieldset class="border border-gray-100 rounded-lg p-4 bg-gray-50">
            <legend class="text-sm font-bold text-gray-800 px-1">יעד</legend>
            <div class="space-y-3 mt-2">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_target" value="all" class="mt-1" <?php echo $campaign['target_scope'] === 'all' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">כל הבתים</span></span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_target" value="homes" class="mt-1" <?php echo $campaign['target_scope'] === 'homes' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">בתים נבחרים</span></span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_target" value="users" class="mt-1" <?php echo $campaign['target_scope'] === 'users' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">משתמשים נבחרים</span></span>
                </label>
            </div>
        </fieldset>

        <fieldset class="border border-gray-100 rounded-lg p-4 bg-gray-50">
            <legend class="text-sm font-bold text-gray-800 px-1">מדיניות אישור (&quot;קראתי&quot;)</legend>
            <p class="text-xs text-gray-600 mb-3">מגדירה מי רואה את ההודעה ומי סוגר אותה — בנוסף ליעד (כל הבתים / בתים נבחרים / משתמשים נבחרים).</p>
            <div class="space-y-3 mt-2">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_ack_policy" value="each_user" class="mt-1" <?php echo $campaign['ack_policy'] === 'each_user' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">כל משתמש בנפרד</span><span class="block text-xs text-gray-600 mt-0.5">כל משתמש ביעד חייב לאשר בעצמו.</span></span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_ack_policy" value="one_per_home" class="mt-1" <?php echo $campaign['ack_policy'] === 'one_per_home' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">אישור אחד לכל בית</span><span class="block text-xs text-gray-600 mt-0.5">ההודעה מוצגת לכולם בבית; אישור של המשתמש הראשון מכל בית מספיק לסגור את ההודעה לכל משתמשי אותו הבית.</span></span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="pc_ack_policy" value="primary_only" class="mt-1" <?php echo $campaign['ack_policy'] === 'primary_only' ? 'checked' : ''; ?>>
                    <span><span class="font-semibold text-gray-900">רק אב בית (משתמש ראשי)</span><span class="block text-xs text-gray-600 mt-0.5">רק משתמש ראשי הבית בבתים שנכללו ביעד רואה את ההודעה ומאשר בשם הבית.</span></span>
                </label>
            </div>
        </fieldset>

        <div id="pc_homes_panel" class="hidden border border-amber-100 rounded-lg p-4 bg-amber-50/50">
            <label class="block font-semibold text-gray-800 mb-2">בחירת בתים</label>
            <div class="relative">
                <input type="search" id="pc_home_search" class="w-full rounded-lg border border-gray-200 px-3 py-2" placeholder="חיפוש בית…" autocomplete="off">
                <ul id="pc_home_suggestions" class="hidden absolute z-40 left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1" role="listbox"></ul>
            </div>
            <div id="pc_selected_homes" class="flex flex-wrap gap-2 mt-4 min-h-[2rem]"></div>
        </div>

        <div id="pc_users_panel" class="hidden border border-amber-100 rounded-lg p-4 bg-amber-50/50">
            <label class="block font-semibold text-gray-800 mb-2">בחירת משתמשים</label>
            <div class="relative">
                <input type="search" id="pc_user_search" class="w-full rounded-lg border border-gray-200 px-3 py-2" placeholder="חיפוש לפי שם או מייל…" autocomplete="off">
                <ul id="pc_user_suggestions" class="hidden absolute z-40 left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1" role="listbox"></ul>
            </div>
            <div id="pc_selected_users" class="flex flex-wrap gap-2 mt-4 min-h-[2rem]"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="pc_status" class="block font-semibold text-gray-800 mb-2">סטטוס</label>
                <select id="pc_status" class="w-full rounded-lg border border-gray-200 px-3 py-2">
                    <option value="draft" <?php echo $campaign['status'] === 'draft' ? 'selected' : ''; ?>>טיוטה</option>
                    <option value="published" <?php echo $campaign['status'] === 'published' ? 'selected' : ''; ?>>מפורסם</option>
                </select>
            </div>
            <div>
                <label class="flex items-center gap-2 mt-8 cursor-pointer">
                    <input type="checkbox" id="pc_active" <?php echo !empty($campaign['is_active']) ? 'checked' : ''; ?>>
                    <span class="font-semibold text-gray-800">פעיל</span>
                </label>
            </div>
        </div>

        <div>
            <label for="pc_sort" class="block font-semibold text-gray-800 mb-2">סדר הצגה (נמוך קודם)</label>
            <input type="number" id="pc_sort" value="<?php echo (int) $campaign['sort_order']; ?>" class="w-full sm:w-40 rounded-lg border border-gray-200 px-3 py-2">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="pc_starts" class="block font-semibold text-gray-800 mb-2">התחלה (אופציונלי)</label>
                <input type="datetime-local" id="pc_starts" value="<?php echo htmlspecialchars($campaign['starts_at'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2">
            </div>
            <div>
                <label for="pc_ends" class="block font-semibold text-gray-800 mb-2">סיום (אופציונלי)</label>
                <input type="datetime-local" id="pc_ends" value="<?php echo htmlspecialchars($campaign['ends_at'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2">
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" id="pc_submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 shadow-sm transition-colors disabled:opacity-50">
                <i class="fa-solid fa-floppy-disk"></i>
                שמירה
            </button>
            <button type="button" id="pc_preview_btn" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-800 font-semibold py-3 px-6">
                <i class="fa-solid fa-eye"></i>
                תצוגה מקדימה
            </button>
        </div>
    </form>

    <?php if ($id > 0): ?>
    <section class="mt-10 bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-2">מי קרא</h3>
        <p class="text-sm text-gray-600 mb-4">אישורי משתמשים ואישורי בית (כשמדיניות הקמפיין היא «אחד לכל בית»).</p>
        <button type="button" id="pc_load_reads" class="rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-900 font-semibold py-2 px-4 text-sm mb-4">רענון רשימה</button>
        <div id="pc_reads_table_wrap" class="overflow-x-auto text-sm">
            <p class="text-gray-500">לחצו &quot;רענון רשימה&quot; לטעינה.</p>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- תצוגה מקדימה — כמו tazrim-app-dialog / פופאפ באפליקציה -->
<div id="pc_preview_overlay" class="modal tazrim-app-dialog tazrim-app-dialog--alert tazrim-system-popup" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="pc_preview_title" aria-hidden="true">
    <div class="modal-content tazrim-app-dialog__content">
        <div class="tazrim-app-dialog__hero">
            <div class="tazrim-app-dialog__icon-wrap tazrim-app-dialog__icon-wrap--main" aria-hidden="true">
                <i class="fa-solid fa-bell"></i>
            </div>
        </div>
        <div class="modal-body tazrim-app-dialog__body">
            <h3 id="pc_preview_title" class="tazrim-app-dialog__title"></h3>
            <div id="pc_preview_body" class="tazrim-system-popup__html"></div>
            <div class="tazrim-app-dialog__actions">
                <button type="button" id="pc_preview_close" class="btn-primary tazrim-app-dialog__btn tazrim-app-dialog__btn--ok">סגירת תצוגה מקדימה</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('admin-popup-campaign-edit');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf');
    var base = root.getAttribute('data-base-url') || '/';
    var campaignId = parseInt(root.getAttribute('data-id'), 10) || 0;

    var INITIAL_HOMES = <?php echo json_encode($home_chips, JSON_UNESCAPED_UNICODE); ?>;
    var INITIAL_USERS = <?php echo json_encode($user_chips, JSON_UNESCAPED_UNICODE); ?>;

    function apiUrl(path) {
        return base.replace(/\/?$/, '/') + path.replace(/^\//, '');
    }

    var form = document.getElementById('pc-form');
    var flash = document.getElementById('pc-flash');
    var btn = document.getElementById('pc_submit');
    var homesPanel = document.getElementById('pc_homes_panel');
    var usersPanel = document.getElementById('pc_users_panel');
    var homeSearch = document.getElementById('pc_home_search');
    var userSearch = document.getElementById('pc_user_search');
    var homeSug = document.getElementById('pc_home_suggestions');
    var userSug = document.getElementById('pc_user_suggestions');
    var selHomes = document.getElementById('pc_selected_homes');
    var selUsers = document.getElementById('pc_selected_users');

    /** @type {Map<number, string>} */
    var selectedHomes = new Map();
    /** @type {Map<number, string>} */
    var selectedUsers = new Map();

    INITIAL_HOMES.forEach(function (x) { selectedHomes.set(x.id, x.label); });
    INITIAL_USERS.forEach(function (x) { selectedUsers.set(x.id, x.label); });

    function showFlash(ok, msg) {
        if (!flash) return;
        flash.textContent = msg;
        flash.classList.remove('hidden');
        flash.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-semibold border ' + (ok ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200');
    }

    function getTarget() {
        var r = form.querySelector('input[name="pc_target"]:checked');
        return r ? r.value : 'all';
    }

    function getAckPolicy() {
        var r = form.querySelector('input[name="pc_ack_policy"]:checked');
        return r ? r.value : 'each_user';
    }

    function syncPanels() {
        var t = getTarget();
        homesPanel.classList.toggle('hidden', t !== 'homes');
        usersPanel.classList.toggle('hidden', t !== 'users');
    }

    form.querySelectorAll('input[name="pc_target"]').forEach(function (el) {
        el.addEventListener('change', syncPanels);
    });

    function renderChips(container, map) {
        container.innerHTML = '';
        map.forEach(function (label, id) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 rounded-full bg-blue-100 text-blue-900 text-sm font-medium py-1.5 px-3';
            var lbl = document.createElement('span');
            lbl.textContent = label;
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'text-blue-700 hover:text-red-600 font-bold leading-none';
            rm.setAttribute('data-id', String(id));
            rm.setAttribute('aria-label', 'הסרה');
            rm.textContent = '×';
            rm.addEventListener('click', function () {
                map.delete(id);
                renderChips(container, map);
            });
            chip.appendChild(lbl);
            chip.appendChild(rm);
            container.appendChild(chip);
        });
    }

    renderChips(selHomes, selectedHomes);
    renderChips(selUsers, selectedUsers);
    syncPanels();

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function setupSearch(searchInput, suggestions, fetchPath, map, container) {
        function run() {
            var q = (searchInput.value || '').trim();
            fetch(apiUrl(fetchPath) + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status !== 'ok' || !data.items) {
                        suggestions.classList.add('hidden');
                        return;
                    }
                    suggestions.innerHTML = '';
                    data.items.forEach(function (item) {
                        var li = document.createElement('li');
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'w-full text-right px-3 py-2 text-sm text-gray-800 hover:bg-gray-100';
                        b.textContent = item.label;
                        b.addEventListener('click', function () {
                            if (!map.has(item.id)) {
                                map.set(item.id, item.label);
                                renderChips(container, map);
                            }
                            searchInput.value = '';
                            suggestions.classList.add('hidden');
                            suggestions.innerHTML = '';
                        });
                        li.appendChild(b);
                        suggestions.appendChild(li);
                    });
                    suggestions.classList.toggle('hidden', data.items.length === 0);
                })
                .catch(function () { suggestions.classList.add('hidden'); });
        }
        var d = debounce(run, 280);
        searchInput.addEventListener('input', d);
        searchInput.addEventListener('focus', run);
    }

    setupSearch(homeSearch, homeSug, 'admin/ajax/homes_list.php', selectedHomes, selHomes);
    setupSearch(userSearch, userSug, 'admin/ajax/users_list.php', selectedUsers, selUsers);

    document.addEventListener('click', function (e) {
        if (homesPanel && !homesPanel.contains(e.target)) homeSug.classList.add('hidden');
        if (usersPanel && !usersPanel.contains(e.target)) userSug.classList.add('hidden');
    });

    var prevOverlay = document.getElementById('pc_preview_overlay');
    var prevTitle = document.getElementById('pc_preview_title');
    var prevBody = document.getElementById('pc_preview_body');
    function setPreviewOpen(open) {
        if (!prevOverlay) return;
        prevOverlay.style.display = open ? 'block' : 'none';
        prevOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('no-scroll', !!open);
    }
    document.getElementById('pc_preview_btn').addEventListener('click', function () {
        prevTitle.textContent = (document.getElementById('pc_title').value || '').trim() || 'תצוגה מקדימה';
        prevBody.innerHTML = document.getElementById('pc_body').value || '';
        setPreviewOpen(true);
    });
    document.getElementById('pc_preview_close').addEventListener('click', function () {
        setPreviewOpen(false);
    });
    if (prevOverlay) {
        prevOverlay.addEventListener('click', function (e) {
            if (e.target === prevOverlay) {
                setPreviewOpen(false);
            }
        });
    }

    var pcAiLinkPreset = document.getElementById('pc_ai_link_preset');
    var pcAiLink = document.getElementById('pc_ai_link');
    function syncPcAiLink() {
        if (!pcAiLinkPreset || !pcAiLink) return;
        var v = pcAiLinkPreset.value;
        var noBtn = v === '';
        var isCustom = v === 'custom';
        if (noBtn) {
            pcAiLink.readOnly = true;
            pcAiLink.value = '';
            pcAiLink.classList.add('bg-gray-100');
            return;
        }
        pcAiLink.classList.toggle('bg-gray-100', !isCustom);
        pcAiLink.readOnly = !isCustom;
        if (!isCustom) {
            pcAiLink.value = v;
        } else if (!pcAiLink.value || pcAiLink.value === '') {
            pcAiLink.value = '/';
        }
    }
    if (pcAiLinkPreset) {
        pcAiLinkPreset.addEventListener('change', syncPcAiLink);
        syncPcAiLink();
    }

    var pcAiBtn = document.getElementById('pc_ai_btn');
    var pcAiInstructions = document.getElementById('pc_ai_instructions');
    var pcTitle = document.getElementById('pc_title');
    var pcBody = document.getElementById('pc_body');
    var pcAiStatus = document.getElementById('pc_ai_status');
    var pcAiStatusText = document.getElementById('pc_ai_status_text');
    var pcAiQuestionsEl = document.getElementById('pc_ai_questions');
    var pcAiBtnOrigHtml = pcAiBtn ? pcAiBtn.innerHTML : '';

    function pcAiSetBusy(busy, statusMsg) {
        pcAiBtn.disabled = busy;
        pcAiBtn.innerHTML = busy
            ? '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> AI עובד...'
            : pcAiBtnOrigHtml;
        if (busy && statusMsg) {
            pcAiStatusText.textContent = statusMsg;
            pcAiStatus.classList.remove('hidden');
        } else if (!busy) {
            pcAiStatus.classList.add('hidden');
        }
    }

    function pcAiGetCtaHref() {
        if (!pcAiLinkPreset || !pcAiLink) return '';
        var pv = pcAiLinkPreset.value;
        if (pv === '') return '';
        var href = (pv === 'custom' ? (pcAiLink.value || '') : pv).trim();
        if (pv === 'custom' && !href) return null;
        return href;
    }

    function pcAiCallSSE(payload) {
        pcAiQuestionsEl.classList.add('hidden');
        pcAiQuestionsEl.innerHTML = '';
        pcAiSetBusy(true, 'מנתח את ההנחיות ומעצב תוכן…');

        fetch(apiUrl('admin/ajax/popup_campaign_ai_generate.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                return response.text().then(function (t) {
                    var d; try { d = JSON.parse(t); } catch(e) { d = {}; }
                    pcAiSetBusy(false);
                    showFlash(false, d.message || 'שגיאה ' + response.status);
                });
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            function read() {
                reader.read().then(function (result) {
                    if (result.done) {
                        pcAiSetBusy(false);
                        return;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    var lines = buffer.split('\n');
                    buffer = lines.pop();
                    var currentEvent = '';
                    for (var i = 0; i < lines.length; i++) {
                        var line = lines[i];
                        if (line.indexOf('event: ') === 0) {
                            currentEvent = line.slice(7).trim();
                        } else if (line.indexOf('data: ') === 0) {
                            var jsonStr = line.slice(6);
                            var data;
                            try { data = JSON.parse(jsonStr); } catch(e) { continue; }
                            pcAiHandleEvent(currentEvent, data, payload);
                            currentEvent = '';
                        }
                    }
                    read();
                }).catch(function () {
                    pcAiSetBusy(false);
                    showFlash(false, 'שגיאת תקשורת.');
                });
            }
            read();
        }).catch(function () {
            pcAiSetBusy(false);
            showFlash(false, 'שגיאת תקשורת.');
        });
    }

    function pcAiHandleEvent(event, data, originalPayload) {
        if (event === 'thinking') {
            pcAiStatusText.textContent = data.hint || 'חושב…';
            pcAiStatus.classList.remove('hidden');
        } else if (event === 'questions') {
            pcAiSetBusy(false);
            pcAiRenderQuestions(data.questions || [], originalPayload);
        } else if (event === 'done') {
            pcAiSetBusy(false);
            if (data.status === 'ok') {
                pcTitle.value = data.title || '';
                pcBody.value = data.body_html || '';
                pcAiQuestionsEl.classList.add('hidden');
                showFlash(true, 'הכותרת וה-HTML עודכנו לפי ה-AI.');
            } else if (data.status === 'questions') {
                /* handled by questions event */
            } else {
                showFlash(false, data.message || 'שגיאה');
            }
        }
    }

    function pcAiRenderQuestions(questions, originalPayload) {
        pcAiQuestionsEl.innerHTML = '';
        pcAiQuestionsEl.classList.remove('hidden');

        var header = document.createElement('div');
        header.className = 'flex items-center gap-2 mb-2';
        header.innerHTML = '<i class="fa-solid fa-comments text-amber-600" aria-hidden="true"></i>'
            + '<span class="text-sm font-bold text-gray-900">ל-AI יש כמה שאלות לפני היצירה:</span>';
        pcAiQuestionsEl.appendChild(header);

        var answersMap = {};
        questions.forEach(function (q) {
            var wrap = document.createElement('div');
            wrap.className = 'bg-white rounded-lg border border-amber-100 p-3';

            var qText = document.createElement('p');
            qText.className = 'text-sm font-semibold text-gray-800 mb-2';
            qText.textContent = q.text;
            wrap.appendChild(qText);

            var selectedBtn = null;
            answersMap[q.id] = '';

            if (q.options && q.options.length > 0) {
                var optionsWrap = document.createElement('div');
                optionsWrap.className = 'flex flex-wrap gap-2 mb-2';
                q.options.forEach(function (opt) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-violet-400 hover:bg-violet-50 transition-colors';
                    btn.textContent = opt;
                    btn.addEventListener('click', function () {
                        if (selectedBtn) {
                            selectedBtn.classList.remove('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                            selectedBtn.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                        }
                        btn.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
                        btn.classList.add('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                        selectedBtn = btn;
                        answersMap[q.id] = opt;
                        customInput.value = '';
                    });
                    optionsWrap.appendChild(btn);
                });
                wrap.appendChild(optionsWrap);
            }

            var customInput = document.createElement('input');
            customInput.type = 'text';
            customInput.placeholder = 'או תשובה מותאמת אישית…';
            customInput.className = 'w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-violet-300 focus:border-violet-400';
            customInput.addEventListener('input', function () {
                var v = customInput.value.trim();
                if (v) {
                    if (selectedBtn) {
                        selectedBtn.classList.remove('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                        selectedBtn.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                        selectedBtn = null;
                    }
                    answersMap[q.id] = v;
                }
            });
            wrap.appendChild(customInput);
            pcAiQuestionsEl.appendChild(wrap);
        });

        var submitWrap = document.createElement('div');
        submitWrap.className = 'flex justify-end pt-2';
        var submitBtn = document.createElement('button');
        submitBtn.type = 'button';
        submitBtn.className = 'inline-flex items-center gap-2 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold py-2.5 px-5 shadow-sm transition-colors';
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane" aria-hidden="true"></i> שליחת תשובות וייצור תוכן';
        submitBtn.addEventListener('click', function () {
            var answers = [];
            var allAnswered = true;
            questions.forEach(function (q) {
                var v = (answersMap[q.id] || '').trim();
                if (!v) allAnswered = false;
                answers.push({ id: q.id, value: v });
            });
            if (!allAnswered) {
                showFlash(false, 'נא לענות על כל השאלות.');
                return;
            }
            pcAiQuestionsEl.classList.add('hidden');
            var ctaHref = pcAiGetCtaHref();
            pcAiCallSSE({
                csrf_token: csrf,
                phase: 'answer',
                instructions: (pcAiInstructions.value || '').trim(),
                original_instructions: originalPayload.instructions || '',
                cta_href: ctaHref || '',
                answers: answers,
                prev_questions: questions
            });
        });
        submitWrap.appendChild(submitBtn);
        pcAiQuestionsEl.appendChild(submitWrap);

        pcAiQuestionsEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (pcAiBtn && pcAiInstructions && pcTitle && pcBody) {
        pcAiBtn.addEventListener('click', function () {
            var hint = (pcAiInstructions.value || '').trim();
            if (!hint) {
                showFlash(false, 'נא למלא הוראות לבינה.');
                return;
            }
            var ctaHref = pcAiGetCtaHref();
            if (ctaHref === null) {
                showFlash(false, 'נא להזין נתיב לקישור או לבחור «ללא כפתור קישור».');
                return;
            }
            pcAiCallSSE({
                csrf_token: csrf,
                phase: 'generate',
                instructions: hint,
                cta_href: ctaHref
            });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var target = getTarget();
        var title = (document.getElementById('pc_title').value || '').trim();
        var bodyHtml = document.getElementById('pc_body').value || '';
        if (!title) {
            showFlash(false, 'נא למלא כותרת.');
            return;
        }
        if (target === 'homes' && selectedHomes.size === 0) {
            showFlash(false, 'נא לבחור לפחות בית אחד.');
            return;
        }
        if (target === 'users' && selectedUsers.size === 0) {
            showFlash(false, 'נא לבחור לפחות משתמש אחד.');
            return;
        }
        var payload = {
            csrf_token: csrf,
            id: campaignId,
            title: title,
            body_html: bodyHtml,
            target_scope: target,
            ack_policy: getAckPolicy(),
            form_schema: (document.getElementById('pc_form_schema') && document.getElementById('pc_form_schema').value) ? document.getElementById('pc_form_schema').value.trim() : '',
            status: document.getElementById('pc_status').value,
            is_active: document.getElementById('pc_active').checked ? 1 : 0,
            sort_order: parseInt(document.getElementById('pc_sort').value, 10) || 0,
            starts_at: document.getElementById('pc_starts').value || '',
            ends_at: document.getElementById('pc_ends').value || '',
            home_ids: Array.from(selectedHomes.keys()),
            user_ids: Array.from(selectedUsers.keys())
        };
        btn.disabled = true;
        fetch(apiUrl('admin/ajax/popup_campaign_save.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    showFlash(true, data.message || 'נשמר.');
                    if (data.id && !campaignId) {
                        window.location.href = apiUrl('admin/popup_campaign_edit.php?id=' + data.id);
                    }
                } else {
                    showFlash(false, data.message || 'שגיאה');
                }
            })
            .catch(function () { showFlash(false, 'שגיאת תקשורת'); })
            .finally(function () { btn.disabled = false; });
    });

    var loadReadsBtn = document.getElementById('pc_load_reads');
    var readsWrap = document.getElementById('pc_reads_table_wrap');
    if (loadReadsBtn && readsWrap && campaignId) {
        function escHtml(s) {
            if (s === null || s === undefined) return '';
            var d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }
        function loadReads() {
            fetch(apiUrl('admin/ajax/popup_campaign_reads.php') + '?campaign_id=' + campaignId, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status !== 'ok') {
                        readsWrap.innerHTML = '<p class="text-red-600">' + escHtml(data.message || 'שגיאה') + '</p>';
                        return;
                    }
                    var rows = data.reads || [];
                    if (rows.length === 0) {
                        readsWrap.innerHTML = '<p class="text-gray-500">אין קריאות עדיין.</p>';
                        return;
                    }
                    var t = '<table class="min-w-full border border-gray-100"><thead class="bg-gray-50"><tr><th class="text-right p-2 border-b">סוג</th><th class="text-right p-2 border-b">פרטים</th><th class="text-right p-2 border-b">מייל</th><th class="text-right p-2 border-b">זמן</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        var kind = r.kind === 'home' ? 'בית (אחד לכל בית)' : 'משתמש';
                        var name = (r.first_name + ' ' + r.last_name).trim();
                        var detail;
                        if (r.kind === 'home') {
                            detail = escHtml(r.home_name || '') + ' (בית #' + (r.home_id || '') + ')';
                            if (r.read_by_user_id) {
                                detail += ' — אישר: ' + escHtml(name) + ' (#' + r.read_by_user_id + ')';
                            }
                        } else {
                            detail = escHtml(name) + ' (#' + r.user_id + ')';
                        }
                        t += '<tr class="border-b border-gray-50"><td class="p-2">' + escHtml(kind) + '</td><td class="p-2">' + detail + '</td><td class="p-2">' + escHtml(r.email || '') + '</td><td class="p-2">' + escHtml(r.read_at_label || r.read_at) + '</td></tr>';
                    });
                    t += '</tbody></table><p class="mt-2 text-gray-600">סה&quot;כ: ' + rows.length + '</p>';
                    readsWrap.innerHTML = t;
                })
                .catch(function () { readsWrap.innerHTML = '<p class="text-red-600">שגיאת רשת</p>'; });
        }
        loadReadsBtn.addEventListener('click', loadReads);
    }
})();
</script>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';

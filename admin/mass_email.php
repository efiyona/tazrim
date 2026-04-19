<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'שליחת מיילים';
$admin_nav_context = 'mass_email';
$csrf = tazrim_admin_csrf_token();

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full max-w-3xl min-w-0 mx-auto" id="admin-mass-email"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-base-url="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800">שליחת מיילים למשתמשים</h2>
        <p class="text-sm text-gray-600 mt-1">שליחה דרך SMTP (כמו בשאר המערכת). עד 200 נמענים לשליחה. יש ליצור טבלאות במסד לפני שימוש — ראו מיגרציה <code class="text-xs bg-gray-100 px-1 rounded">docs/database/migrations/20260419_admin_email_broadcasts.sql</code>.</p>
    </div>

    <section class="mb-6 rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50/90 to-white p-4 sm:p-5 shadow-sm" aria-label="יצירת תוכן עם AI">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900">יצירה עם AI</h3>
                <p class="text-xs text-gray-500 mt-0.5">מילוי נושא וגוף HTML לפי הנחיות (Gemini).</p>
            </div>
            <button type="button" id="me_ai_btn"
                class="inline-flex items-center justify-center gap-2 shrink-0 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold py-2.5 px-4 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                בקשת תוכן מ-AI
            </button>
        </div>
        <div id="me_ai_status" class="hidden mb-3 rounded-lg border border-violet-100 bg-violet-50/60 px-3 py-2.5 text-sm text-violet-800 flex items-center gap-2">
            <i class="fa-solid fa-spinner fa-spin text-violet-500" aria-hidden="true"></i>
            <span id="me_ai_status_text">מנתח…</span>
        </div>
        <div id="me_ai_questions" class="hidden mb-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 space-y-4"></div>
        <label for="me_ai_instructions" class="block text-sm font-semibold text-gray-800 mb-1">הוראות לבינה</label>
        <p class="text-xs text-amber-800/90 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-2">בכל הרצה השדות <strong>נושא</strong> ו־<strong>HTML</strong> מוחלפים בתוצאה החדשה.</p>
        <textarea id="me_ai_instructions" rows="3" placeholder="למשל: מייל על פיצ'ר ייצוא לאקסל — טון חגיגי, כפתור לדוחות."
            class="w-full rounded-lg border border-violet-100 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 resize-y min-h-[72px]"></textarea>
    </section>

    <div id="me-flash" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-semibold border" role="status"></div>

    <section class="bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6 w-full min-w-0 mb-8">
        <form id="mass-email-form" autocomplete="off">
            <fieldset class="mb-6 border border-gray-100 rounded-lg p-4 bg-gray-50">
                <legend class="text-sm font-bold text-gray-800 px-1">יעד שליחה</legend>
                <div class="space-y-3 mt-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="me_target" value="all_users" class="mt-1" checked>
                        <span>
                            <span class="font-semibold text-gray-900">כל המשתמשים</span>
                            <span class="block text-sm text-gray-600">כל כתובות המייל התקפות בטבלת משתמשים</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="me_target" value="all_homes" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">כל הבתים</span>
                            <span class="block text-sm text-gray-600">כל משתמש עם בית משויך (home_id)</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="me_target" value="homes" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">בתים נבחרים</span>
                            <span class="block text-sm text-gray-600">כל המשתמשים באותם בתים</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="me_target" value="users" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">משתמשים נבחרים</span>
                            <span class="block text-sm text-gray-600">לפי חשבון משתמש</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div id="me_homes_panel" class="hidden mb-6 border border-amber-100 rounded-lg p-4 bg-amber-50/50">
                <label class="block font-semibold text-gray-800 mb-2">בחירת בתים</label>
                <div class="relative">
                    <input type="search" id="me_home_search" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400" placeholder="חיפוש בית…" autocomplete="off">
                    <ul id="me_home_suggestions" class="hidden absolute z-40 left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1" role="listbox"></ul>
                </div>
                <div id="me_selected_homes" class="flex flex-wrap gap-2 mt-4 min-h-[2rem]"></div>
                <p id="me_homes_empty" class="text-sm text-amber-800 mt-2 hidden">לא נבחרו בתים.</p>
            </div>

            <div id="me_users_panel" class="hidden mb-6 border border-sky-100 rounded-lg p-4 bg-sky-50/50">
                <label class="block font-semibold text-gray-800 mb-2">בחירת משתמשים</label>
                <div class="relative">
                    <input type="search" id="me_user_search" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400" placeholder="חיפוש לפי שם / מייל / מזהה…" autocomplete="off">
                    <ul id="me_user_suggestions" class="hidden absolute z-40 left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1" role="listbox"></ul>
                </div>
                <div id="me_selected_users" class="flex flex-wrap gap-2 mt-4 min-h-[2rem]"></div>
                <p id="me_users_empty" class="text-sm text-sky-900 mt-2 hidden">לא נבחרו משתמשים.</p>
            </div>

            <div class="mb-4">
                <label for="me_subject" class="block font-semibold text-gray-800 mb-2">נושא</label>
                <input type="text" id="me_subject" required maxlength="500"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
            </div>
            <div class="mb-4">
                <label for="me_html" class="block font-semibold text-gray-800 mb-2">גוף HTML</label>
                <textarea id="me_html" required rows="10"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 font-mono text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 resize-y min-h-[160px]"></textarea>
            </div>
            <div class="mb-6">
                <label for="me_text" class="block font-semibold text-gray-800 mb-2">טקסט גלוי (אופציונלי — Alt)</label>
                <textarea id="me_text" rows="4"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 resize-y"></textarea>
            </div>
            <button type="submit" id="me_submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-6 shadow-sm transition-colors w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-paper-plane"></i>
                שליחת מיילים
            </button>
        </form>
    </section>

    <section class="bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6 w-full min-w-0" aria-label="היסטוריית שליחות">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <h3 class="text-lg font-bold text-gray-800">היסטוריית שליחות</h3>
            <button type="button" id="me_hist_refresh" class="text-sm font-semibold text-blue-600 hover:text-blue-800">רענון</button>
        </div>
        <div id="me_hist_wrap" class="overflow-x-auto">
            <p id="me_hist_loading" class="text-sm text-gray-500">טוען…</p>
            <table id="me_hist_table" class="hidden min-w-full text-sm text-right border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-600">
                        <th class="py-2 px-2">מזהה</th>
                        <th class="py-2 px-2">מאת</th>
                        <th class="py-2 px-2">יעד</th>
                        <th class="py-2 px-2">נושא</th>
                        <th class="py-2 px-2">סטטוס</th>
                        <th class="py-2 px-2">נשלחו / נכשלו</th>
                        <th class="py-2 px-2">תאריך</th>
                        <th class="py-2 px-2"></th>
                    </tr>
                </thead>
                <tbody id="me_hist_body"></tbody>
            </table>
            <p id="me_hist_empty" class="hidden text-sm text-gray-500">אין שליחות רשומות או טבלאות לא אותחלו.</p>
        </div>
        <div id="me_hist_detail" class="hidden mt-4 border border-gray-100 rounded-lg p-3 bg-gray-50 text-xs"></div>
    </section>
</main>
<script>
(function () {
    var root = document.getElementById('admin-mass-email');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf');
    var base = root.getAttribute('data-base-url') || '/';
    var form = document.getElementById('mass-email-form');
    var btn = document.getElementById('me_submit');
    var flash = document.getElementById('me-flash');

    function apiUrl(path) {
        return base.replace(/\/?$/, '/') + path.replace(/^\//, '');
    }

    function showFlash(ok, msg) {
        if (!flash) return;
        flash.textContent = msg;
        flash.classList.remove('hidden');
        flash.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-semibold border ' +
            (ok ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200');
    }

    var selectedHomes = new Map();
    var selectedUsers = new Map();

    function getTarget() {
        var r = form.querySelector('input[name="me_target"]:checked');
        return r ? r.value : 'all_users';
    }

    function syncPanels() {
        var t = getTarget();
        document.getElementById('me_homes_panel').classList.toggle('hidden', t !== 'homes');
        document.getElementById('me_users_panel').classList.toggle('hidden', t !== 'users');
    }

    form.querySelectorAll('input[name="me_target"]').forEach(function (el) {
        el.addEventListener('change', syncPanels);
    });
    syncPanels();

    function renderHomes() {
        var w = document.getElementById('me_selected_homes');
        w.innerHTML = '';
        selectedHomes.forEach(function (label, id) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 rounded-full bg-blue-100 text-blue-900 text-sm font-medium py-1.5 px-3';
            chip.innerHTML = '<span>' + label + '</span><button type="button" class="text-blue-700 hover:text-red-600 font-bold" data-rm-home="' + id + '">×</button>';
            w.appendChild(chip);
        });
        w.querySelectorAll('[data-rm-home]').forEach(function (b) {
            b.addEventListener('click', function () {
                selectedHomes.delete(parseInt(b.getAttribute('data-rm-home'), 10));
                renderHomes();
            });
        });
    }

    function renderUsers() {
        var w = document.getElementById('me_selected_users');
        w.innerHTML = '';
        selectedUsers.forEach(function (label, id) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 rounded-full bg-sky-100 text-sky-900 text-sm font-medium py-1.5 px-3';
            chip.innerHTML = '<span>' + label + '</span><button type="button" class="text-sky-700 hover:text-red-600 font-bold" data-rm-user="' + id + '">×</button>';
            w.appendChild(chip);
        });
        w.querySelectorAll('[data-rm-user]').forEach(function (b) {
            b.addEventListener('click', function () {
                selectedUsers.delete(parseInt(b.getAttribute('data-rm-user'), 10));
                renderUsers();
            });
        });
    }

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    var homeSearch = document.getElementById('me_home_search');
    var homeSug = document.getElementById('me_home_suggestions');
    function runHomeFetch() {
        var q = (homeSearch.value || '').trim();
        fetch(apiUrl('admin/ajax/homes_list.php') + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'ok' || !data.items) { homeSug.classList.add('hidden'); return; }
                homeSug.innerHTML = '';
                data.items.forEach(function (item) {
                    var li = document.createElement('li');
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'w-full text-right px-3 py-2 text-sm text-gray-800 hover:bg-gray-100';
                    b.textContent = item.label;
                    b.addEventListener('click', function () {
                        if (!selectedHomes.has(item.id)) selectedHomes.set(item.id, item.label);
                        renderHomes();
                        homeSearch.value = '';
                        homeSug.classList.add('hidden');
                    });
                    li.appendChild(b);
                    homeSug.appendChild(li);
                });
                homeSug.classList.toggle('hidden', data.items.length === 0);
            }).catch(function () { homeSug.classList.add('hidden'); });
    }
    homeSearch.addEventListener('input', debounce(runHomeFetch, 280));
    homeSearch.addEventListener('focus', runHomeFetch);

    var userSearch = document.getElementById('me_user_search');
    var userSug = document.getElementById('me_user_suggestions');
    function runUserFetch() {
        var q = (userSearch.value || '').trim();
        fetch(apiUrl('admin/ajax/users_list.php') + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'ok' || !data.items) { userSug.classList.add('hidden'); return; }
                userSug.innerHTML = '';
                data.items.forEach(function (item) {
                    var li = document.createElement('li');
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'w-full text-right px-3 py-2 text-sm text-gray-800 hover:bg-gray-100';
                    b.textContent = item.label;
                    b.addEventListener('click', function () {
                        if (!selectedUsers.has(item.id)) selectedUsers.set(item.id, item.label);
                        renderUsers();
                        userSearch.value = '';
                        userSug.classList.add('hidden');
                    });
                    li.appendChild(b);
                    userSug.appendChild(li);
                });
                userSug.classList.toggle('hidden', data.items.length === 0);
            }).catch(function () { userSug.classList.add('hidden'); });
    }
    userSearch.addEventListener('input', debounce(runUserFetch, 280));
    userSearch.addEventListener('focus', runUserFetch);

    document.addEventListener('click', function (e) {
        var hp = document.getElementById('me_homes_panel');
        var up = document.getElementById('me_users_panel');
        if (hp && !hp.contains(e.target)) homeSug.classList.add('hidden');
        if (up && !up.contains(e.target)) userSug.classList.add('hidden');
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var target = getTarget();
        var subject = (document.getElementById('me_subject').value || '').trim();
        var html = document.getElementById('me_html').value || '';
        var text = (document.getElementById('me_text').value || '').trim();
        if (!subject) { showFlash(false, 'נא למלא נושא.'); return; }
        if (!html.trim()) { showFlash(false, 'נא למלא HTML.'); return; }
        var ids = [];
        if (target === 'homes') {
            if (selectedHomes.size === 0) {
                document.getElementById('me_homes_empty').classList.remove('hidden');
                showFlash(false, 'נא לבחור בתים.');
                return;
            }
            document.getElementById('me_homes_empty').classList.add('hidden');
            ids = Array.from(selectedHomes.keys());
        } else if (target === 'users') {
            if (selectedUsers.size === 0) {
                document.getElementById('me_users_empty').classList.remove('hidden');
                showFlash(false, 'נא לבחור משתמשים.');
                return;
            }
            document.getElementById('me_users_empty').classList.add('hidden');
            ids = Array.from(selectedUsers.keys());
        }
        var nLabel = target === 'all_users' ? 'כל המשתמשים' : target === 'all_homes' ? 'כל הבתים' : (target === 'homes' ? ids.length + ' בתים' : ids.length + ' משתמשים');
        var confirmMsg = 'לשלוח מייל ל־' + nLabel + '? לא ניתן לבטל לאחר האישור.';

        function doSend() {
            btn.disabled = true;
            fetch(apiUrl('admin/ajax/mass_email_send.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrf,
                    target_type: target,
                    ids: ids,
                    subject: subject,
                    html_body: html,
                    text_body: text
                })
            }).then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'ok') {
                    showFlash(true, data.message || 'נשלח.');
                    loadHistory();
                } else {
                    showFlash(false, data.message || 'שגיאה');
                }
            }).catch(function () { showFlash(false, 'שגיאת תקשורת'); })
            .finally(function () { btn.disabled = false; });
        }

        if (typeof window.tazrimConfirm === 'function') {
            window.tazrimConfirm({ title: 'אישור שליחה', message: confirmMsg, danger: true, confirmText: 'שליחה', cancelText: 'ביטול' })
                .then(function (ok) { if (ok) doSend(); });
        } else if (window.confirm(confirmMsg)) {
            doSend();
        }
    });

    /* --- History --- */
    function loadHistory() {
        var loading = document.getElementById('me_hist_loading');
        var table = document.getElementById('me_hist_table');
        var body = document.getElementById('me_hist_body');
        var empty = document.getElementById('me_hist_empty');
        loading.classList.remove('hidden');
        table.classList.add('hidden');
        empty.classList.add('hidden');
        fetch(apiUrl('admin/ajax/mass_email_list.php') + '?page=1&per_page=40', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading.classList.add('hidden');
                if (data.status !== 'ok') { empty.classList.remove('hidden'); return; }
                if (data.tables_missing) {
                    empty.textContent = 'טבלאות המייל לא קיימות במסד — הריצו את קובץ המיגרציה.';
                    empty.classList.remove('hidden');
                    return;
                }
                body.innerHTML = '';
                var items = data.items || [];
                if (items.length === 0) {
                    empty.textContent = 'אין שליחות עדיין.';
                    empty.classList.remove('hidden');
                    return;
                }
                table.classList.remove('hidden');
                items.forEach(function (row) {
                    var tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-100 hover:bg-gray-50';
                    var tgt = ({ all_users: 'כולם', all_homes: 'כל הבתים', homes: 'בתים', users: 'משתמשים' })[row.target_type] || row.target_type;
                    tr.innerHTML =
                        '<td class="py-2 px-2">' + row.id + '</td>' +
                        '<td class="py-2 px-2 max-w-[120px] truncate" title="' + escapeAttr(row.admin_name || '') + '">' + escapeHtml(row.admin_name || '—') + '</td>' +
                        '<td class="py-2 px-2">' + escapeHtml(tgt) + '</td>' +
                        '<td class="py-2 px-2 max-w-[200px] truncate" title="' + escapeAttr(row.subject) + '">' + escapeHtml(row.subject) + '</td>' +
                        '<td class="py-2 px-2">' + escapeHtml(row.status) + '</td>' +
                        '<td class="py-2 px-2">' + row.sent_ok + ' / ' + row.sent_fail + '</td>' +
                        '<td class="py-2 px-2 whitespace-nowrap">' + escapeHtml((row.created_at || '').replace('T', ' ').slice(0, 16)) + '</td>' +
                        '<td class="py-2 px-2"><button type="button" class="text-blue-600 hover:underline text-xs me-log-btn" data-bid="' + row.id + '">לוג</button></td>';
                    body.appendChild(tr);
                });
                body.querySelectorAll('.me-log-btn').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var bid = b.getAttribute('data-bid');
                        loadLogDetail(bid);
                    });
                });
            }).catch(function () {
                loading.classList.add('hidden');
                empty.classList.remove('hidden');
            });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function loadLogDetail(bid) {
        var box = document.getElementById('me_hist_detail');
        box.classList.remove('hidden');
        box.textContent = 'טוען לוג…';
        fetch(apiUrl('admin/ajax/mass_email_list.php') + '?broadcast_id=' + encodeURIComponent(bid) + '&per_page=200', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'ok' || !data.items) {
                    box.textContent = 'לא ניתן לטעון לוג.';
                    return;
                }
                var lines = data.items.map(function (x) {
                    return x.recipient_email + ' — ' + x.status + (x.error_message ? ' — ' + x.error_message : '');
                });
                box.innerHTML = '<div class="font-bold mb-2">נמענים (' + data.items.length + ')</div><pre class="whitespace-pre-wrap break-words text-gray-800 max-h-64 overflow-y-auto">' + escapeHtml(lines.join('\n')) + '</pre>';
            });
    }

    document.getElementById('me_hist_refresh').addEventListener('click', loadHistory);
    loadHistory();

    /* --- AI (SSE) --- */
    var meAiBtn = document.getElementById('me_ai_btn');
    var meAiInstructions = document.getElementById('me_ai_instructions');
    var meAiStatus = document.getElementById('me_ai_status');
    var meAiStatusText = document.getElementById('me_ai_status_text');
    var meAiQuestionsEl = document.getElementById('me_ai_questions');
    var meSubject = document.getElementById('me_subject');
    var meHtml = document.getElementById('me_html');
    var meText = document.getElementById('me_text');
    var meAiBtnOrigHtml = meAiBtn ? meAiBtn.innerHTML : '';

    function meAiSetBusy(busy, statusMsg) {
        meAiBtn.disabled = busy;
        meAiBtn.innerHTML = busy ? '<i class="fa-solid fa-spinner fa-spin"></i> AI…' : meAiBtnOrigHtml;
        if (busy && statusMsg) {
            meAiStatusText.textContent = statusMsg;
            meAiStatus.classList.remove('hidden');
        } else if (!busy) {
            meAiStatus.classList.add('hidden');
        }
    }

    function meAiCallSSE(payload) {
        meAiQuestionsEl.classList.add('hidden');
        meAiQuestionsEl.innerHTML = '';
        meAiSetBusy(true, 'מנסח…');
        fetch(apiUrl('admin/ajax/mass_email_ai_generate.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                return response.text().then(function (t) {
                    var d; try { d = JSON.parse(t); } catch (e) { d = {}; }
                    meAiSetBusy(false);
                    showFlash(false, d.message || 'שגיאה ' + response.status);
                });
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            function read() {
                reader.read().then(function (result) {
                    if (result.done) { meAiSetBusy(false); return; }
                    buffer += decoder.decode(result.value, { stream: true });
                    var lines = buffer.split('\n');
                    buffer = lines.pop();
                    var currentEvent = '';
                    for (var i = 0; i < lines.length; i++) {
                        var line = lines[i];
                        if (line.indexOf('event: ') === 0) currentEvent = line.slice(7).trim();
                        else if (line.indexOf('data: ') === 0) {
                            var dat;
                            try { dat = JSON.parse(line.slice(6)); } catch (e) { continue; }
                            if (currentEvent === 'thinking') meAiStatusText.textContent = dat.hint || '…';
                            else if (currentEvent === 'questions') {
                                meAiSetBusy(false);
                                meAiRenderQuestions(dat.questions || [], payload);
                                return;
                            } else if (currentEvent === 'done') {
                                meAiSetBusy(false);
                                if (dat.status === 'ok') {
                                    meSubject.value = dat.subject || '';
                                    meHtml.value = dat.html_body || '';
                                    if (dat.text_body) meText.value = dat.text_body;
                                    meAiQuestionsEl.classList.add('hidden');
                                    showFlash(true, 'הנושא וה-HTML עודכנו.');
                                } else if (dat.status === 'questions' && dat.questions && dat.questions.length) {
                                    meAiRenderQuestions(dat.questions, payload);
                                } else if (dat.status !== 'questions') {
                                    showFlash(false, dat.message || 'שגיאה');
                                }
                                return;
                            }
                            currentEvent = '';
                        }
                    }
                    read();
                }).catch(function () { meAiSetBusy(false); showFlash(false, 'שגיאת תקשורת'); });
            }
            read();
        }).catch(function () { meAiSetBusy(false); showFlash(false, 'שגיאת תקשורת'); });
    }

    function meAiRenderQuestions(questions, originalPayload) {
        meAiQuestionsEl.innerHTML = '';
        meAiQuestionsEl.classList.remove('hidden');
        var header = document.createElement('div');
        header.className = 'text-sm font-bold text-gray-900 mb-2';
        header.textContent = 'שאלות מה-AI:';
        meAiQuestionsEl.appendChild(header);
        var answersMap = {};
        questions.forEach(function (q) {
            var wrap = document.createElement('div');
            wrap.className = 'bg-white rounded-lg border border-amber-100 p-3 mb-2';
            var qt = document.createElement('p');
            qt.className = 'text-sm font-semibold mb-2';
            qt.textContent = q.text;
            wrap.appendChild(qt);
            answersMap[q.id] = '';
            var selectedBtn = null;
            if (q.options && q.options.length) {
                var ow = document.createElement('div');
                ow.className = 'flex flex-wrap gap-2 mb-2';
                q.options.forEach(function (opt) {
                    var ob = document.createElement('button');
                    ob.type = 'button';
                    ob.className = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm';
                    ob.textContent = opt;
                    ob.addEventListener('click', function () {
                        if (selectedBtn) selectedBtn.className = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm';
                        ob.className = 'rounded-full border border-violet-500 bg-violet-50 px-3 py-1.5 text-sm font-semibold';
                        selectedBtn = ob;
                        answersMap[q.id] = opt;
                    });
                    ow.appendChild(ob);
                });
                wrap.appendChild(ow);
            }
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.placeholder = 'או תשובה חופשית…';
            inp.className = 'w-full rounded border px-2 py-1 text-sm';
            inp.addEventListener('input', function () {
                if (inp.value.trim()) {
                    if (selectedBtn) { selectedBtn.className = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm'; selectedBtn = null; }
                    answersMap[q.id] = inp.value.trim();
                }
            });
            wrap.appendChild(inp);
            meAiQuestionsEl.appendChild(wrap);
        });
        var sb = document.createElement('button');
        sb.type = 'button';
        sb.className = 'mt-2 rounded-lg bg-violet-600 text-white text-sm font-semibold py-2 px-4';
        sb.textContent = 'שליחת תשובות והמשך';
        sb.addEventListener('click', function () {
            var answers = [];
            var ok = true;
            questions.forEach(function (q) {
                var v = (answersMap[q.id] || '').trim();
                if (!v) ok = false;
                answers.push({ id: q.id, value: v });
            });
            if (!ok) { showFlash(false, 'נא לענות על כל השאלות.'); return; }
            meAiQuestionsEl.classList.add('hidden');
            meAiCallSSE({
                csrf_token: csrf,
                phase: 'answer',
                instructions: (meAiInstructions.value || '').trim(),
                original_instructions: originalPayload.instructions || '',
                answers: answers,
                prev_questions: questions
            });
        });
        meAiQuestionsEl.appendChild(sb);
    }

    if (meAiBtn && meAiInstructions) {
        meAiBtn.addEventListener('click', function () {
            var hint = (meAiInstructions.value || '').trim();
            if (!hint) { showFlash(false, 'נא למלא הוראות.'); return; }
            meAiCallSSE({ csrf_token: csrf, phase: 'generate', instructions: hint });
        });
    }
})();
</script>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

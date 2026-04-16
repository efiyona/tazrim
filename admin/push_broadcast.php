<?php
require_once dirname(__FILE__) . '/includes/init.php';

$pageTitle = 'שידור פוש גלובלי';
$admin_nav_context = 'push_broadcast';
$csrf = tazrim_admin_csrf_token();
$pushLinkOptions = tazrim_admin_push_link_options();

require dirname(__FILE__) . '/includes/partials/head.php';
require dirname(__FILE__) . '/includes/partials/layout_shell_start.php';
?>
<main class="admin-page-main w-full max-w-2xl min-w-0 mx-auto" id="admin-push-broadcast"
    data-csrf="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>"
    data-base-url="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/', ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800">הודעת מערכת — שידור פוש</h2>
        <p class="text-sm text-gray-600 mt-1">שליחה לפי העדפת מערכת (notify_system) ל־Push, או התראה בפעמון באפליקציה, או שניהם. ניתן לשלוח לכל הבתים או רק לבתים שתבחרו.</p>
    </div>

    <section class="mb-6 rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50/90 to-white p-4 sm:p-5 shadow-sm" aria-label="יצירת תוכן עם AI">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900">יצירה עם AI</h3>
                <p class="text-xs text-gray-500 mt-0.5">מילוי אוטומטי של כותרת ותוכן לפי הנחיות שלכם (Gemini).</p>
            </div>
            <button type="button" id="pb_ai_btn"
                class="inline-flex items-center justify-center gap-2 shrink-0 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold py-2.5 px-4 shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                בקשת תוכן מ-AI
            </button>
        </div>
        <div id="pb_ai_status" class="hidden mb-3 rounded-lg border border-violet-100 bg-violet-50/60 px-3 py-2.5 text-sm text-violet-800 flex items-center gap-2">
            <i class="fa-solid fa-spinner fa-spin text-violet-500" aria-hidden="true"></i>
            <span id="pb_ai_status_text">מנתח את ההנחיות…</span>
        </div>
        <div id="pb_ai_questions" class="hidden mb-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 space-y-4"></div>
        <label for="pb_ai_instructions" class="block text-sm font-semibold text-gray-800 mb-1">הוראות לבינה</label>
        <p class="text-xs text-amber-800/90 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-2">בכל הרצה השדות <strong>כותרת</strong> ו־<strong>תוכן</strong> מוחלפים בתוצאה החדשה.</p>
        <textarea id="pb_ai_instructions" rows="3" placeholder="למשל: עדכון על מעבר לתשלום חודשי — טון עדין, להדגיש יתרונות. או: תזכורת להזנת הוצאות של החודש."
            class="w-full rounded-lg border border-violet-100 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-violet-300 focus:border-violet-400 resize-y min-h-[72px]"></textarea>
    </section>

    <div id="push-flash" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-semibold border" role="status"></div>

    <section class="bg-white rounded-lg shadow border border-gray-100 p-4 sm:p-6 w-full min-w-0">
        <form id="push-broadcast-form" autocomplete="off">
            <fieldset class="mb-6 border border-gray-100 rounded-lg p-4 bg-gray-50">
                <legend class="text-sm font-bold text-gray-800 px-1">יעד שידור</legend>
                <div class="space-y-3 mt-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="pb_target" value="all" class="mt-1" checked>
                        <span>
                            <span class="font-semibold text-gray-900">כל המשתמשים</span>
                            <span class="block text-sm text-gray-600">כל הבתים במערכת</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="pb_target" value="homes" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">בתים נבחרים</span>
                            <span class="block text-sm text-gray-600">רק בית או מספר בתים שתבחרו מהרשימה</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="mb-6 border border-gray-100 rounded-lg p-4 bg-gray-50">
                <legend class="text-sm font-bold text-gray-800 px-1">סוג התראה</legend>
                <div class="space-y-3 mt-2">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="pb_delivery" value="push" class="mt-1" checked>
                        <span>
                            <span class="font-semibold text-gray-900">התראת Push בלבד</span>
                            <span class="block text-sm text-gray-600">מכשיר / דפדפן, לפי מנוי והעדפת notify_system</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="pb_delivery" value="bell" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">פעמון באפליקציה בלבד</span>
                            <span class="block text-sm text-gray-600">התראה ברשימת הפעמון (ללא Push)</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="pb_delivery" value="both" class="mt-1">
                        <span>
                            <span class="font-semibold text-gray-900">גם Push וגם פעמון</span>
                            <span class="block text-sm text-gray-600">שני הערוצים לכל בית שנבחר</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div id="pb_homes_panel" class="hidden mb-6 border border-amber-100 rounded-lg p-4 bg-amber-50/50">
                <label class="block font-semibold text-gray-800 mb-2">בחירת בתים</label>
                <p class="text-sm text-gray-600 mb-3">חפשו לפי שם וקוד הצטרפות, לחצו על תוצאה כדי להוסיף. אפשר להוסיף מספר בתים.</p>
                <div class="relative">
                    <input type="search" id="pb_home_search" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400"
                        placeholder="חיפוש בית…" autocomplete="off">
                    <ul id="pb_home_suggestions" class="hidden absolute z-40 left-0 right-0 top-full mt-1 max-h-48 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1" role="listbox"></ul>
                </div>
                <div id="pb_selected_homes" class="flex flex-wrap gap-2 mt-4 min-h-[2rem]"></div>
                <p id="pb_homes_empty" class="text-sm text-amber-800 mt-2 hidden">לא נבחרו בתים — הוסיפו לפחות בית אחד לפני השידור.</p>
            </div>

            <div class="mb-4">
                <label for="pb_title" class="block font-semibold text-gray-800 mb-2">כותרת ההודעה</label>
                <input type="text" id="pb_title" name="title" required
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400"
                    placeholder="למשל: עדכון במערכת">
            </div>
            <div class="mb-4">
                <label for="pb_body" class="block font-semibold text-gray-800 mb-2">תוכן ההודעה</label>
                <textarea id="pb_body" name="body" required rows="4"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400 resize-y min-h-[100px]"
                    placeholder="תוכן ההתראה…"></textarea>
            </div>
            <div class="mb-6">
                <label for="pb_link" class="block font-semibold text-gray-800 mb-2">קישור בלחיצה (אופציונלי)</label>
                <div class="space-y-3">
                    <select id="pb_link_preset" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400">
                        <option value="custom">קישור מותאם אישית</option>
                        <?php foreach ($pushLinkOptions as $url => $label): ?>
                            <option value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="pb_link" name="link" value="/"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-300 focus:border-blue-400"
                        placeholder="/ או נתיב באתר">
                    <p class="text-xs text-gray-500">אפשר לבחור עמוד קיים מהרשימה או להשאיר "קישור מותאם אישית" ולהזין נתיב ידני.</p>
                </div>
            </div>
            <button type="submit" id="pb_submit"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-6 shadow-sm transition-colors w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-paper-plane"></i>
                שליחת התראה
            </button>
        </form>
    </section>
</main>
<script>
(function () {
    var root = document.getElementById('admin-push-broadcast');
    if (!root) return;
    var csrf = root.getAttribute('data-csrf');
    var base = root.getAttribute('data-base-url') || '/';
    var form = document.getElementById('push-broadcast-form');
    var btn = document.getElementById('pb_submit');
    var flash = document.getElementById('push-flash');
    var linkPreset = document.getElementById('pb_link_preset');
    var linkInput = document.getElementById('pb_link');
    var homesPanel = document.getElementById('pb_homes_panel');
    var homeSearch = document.getElementById('pb_home_search');
    var suggestions = document.getElementById('pb_home_suggestions');
    var selectedWrap = document.getElementById('pb_selected_homes');
    var homesEmptyHint = document.getElementById('pb_homes_empty');
    var targetRadios = form.querySelectorAll('input[name="pb_target"]');

    /** @type {Map<number, string>} */
    var selectedHomes = new Map();

    function apiUrl(path) {
        return base.replace(/\/?$/, '/') + path.replace(/^\//, '');
    }

    function showFlash(ok, msg) {
        if (!flash) return;
        flash.textContent = msg;
        flash.classList.remove('hidden');
        flash.className =
            'mb-4 px-4 py-3 rounded-lg text-sm font-semibold border ' +
            (ok
                ? 'bg-green-100 text-green-800 border-green-200'
                : 'bg-red-100 text-red-800 border-red-200');
    }

    function getTarget() {
        var r = form.querySelector('input[name="pb_target"]:checked');
        return r ? r.value : 'all';
    }

    function getDelivery() {
        var r = form.querySelector('input[name="pb_delivery"]:checked');
        return r ? r.value : 'push';
    }

    function syncHomesPanel() {
        var show = getTarget() === 'homes';
        homesPanel.classList.toggle('hidden', !show);
        if (!show) {
            homeSearch.value = '';
            suggestions.classList.add('hidden');
            suggestions.innerHTML = '';
        }
    }

    function syncLinkInput() {
        if (!linkPreset || !linkInput) return;
        var isCustom = linkPreset.value === 'custom';
        linkInput.readOnly = !isCustom;
        linkInput.classList.toggle('bg-gray-100', !isCustom);
        if (!isCustom) {
            linkInput.value = linkPreset.value;
        }
    }

    targetRadios.forEach(function (el) {
        el.addEventListener('change', syncHomesPanel);
    });
    if (linkPreset) {
        linkPreset.addEventListener('change', syncLinkInput);
    }

    function renderSelected() {
        selectedWrap.innerHTML = '';
        selectedHomes.forEach(function (label, id) {
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
                selectedHomes.delete(id);
                renderSelected();
            });
            chip.appendChild(lbl);
            chip.appendChild(rm);
            selectedWrap.appendChild(chip);
        });
    }

    function debounce(fn, ms) {
        var t = null;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(null, args);
            }, ms);
        };
    }

    function runHomeFetch() {
        var q = (homeSearch.value || '').trim();
        fetch(apiUrl('admin/ajax/homes_list.php') + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
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
                        if (!selectedHomes.has(item.id)) {
                            selectedHomes.set(item.id, item.label);
                            renderSelected();
                        }
                        homeSearch.value = '';
                        suggestions.classList.add('hidden');
                        suggestions.innerHTML = '';
                    });
                    li.appendChild(b);
                    suggestions.appendChild(li);
                });
                suggestions.classList.toggle('hidden', data.items.length === 0);
            })
            .catch(function () {
                suggestions.classList.add('hidden');
            });
    }

    var debouncedFetch = debounce(runHomeFetch, 280);
    homeSearch.addEventListener('input', debouncedFetch);
    homeSearch.addEventListener('focus', runHomeFetch);

    document.addEventListener('click', function (e) {
        if (!homesPanel.contains(e.target)) {
            suggestions.classList.add('hidden');
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var target = getTarget();
        var title = (document.getElementById('pb_title').value || '').trim();
        var body = (document.getElementById('pb_body').value || '').trim();
        var link = (linkInput.value || '').trim() || '/';
        if (!title || !body) {
            showFlash(false, 'נא למלא כותרת ותוכן.');
            return;
        }
        if (target === 'homes') {
            if (selectedHomes.size === 0) {
                homesEmptyHint.classList.remove('hidden');
                showFlash(false, 'נא לבחור לפחות בית אחד.');
                return;
            }
            homesEmptyHint.classList.add('hidden');
        }
        var delivery = getDelivery();
        var deliveryLabel =
            delivery === 'bell' ? 'התראות פעמון בלבד' : delivery === 'both' ? 'Push ופעמון' : 'התראת Push בלבד';
        var confirmMsg =
            target === 'all'
                ? 'לשלוח (' + deliveryLabel + ') לכל הבתים במערכת? לא ניתן לבטל.'
                : 'לשלוח (' + deliveryLabel + ') ל-' + selectedHomes.size + ' בתים נבחרים? לא ניתן לבטל.';

        function doSendPush() {
            var payload = {
                csrf_token: csrf,
                title: title,
                body: body,
                link: link,
                delivery: delivery,
                target: target === 'homes' ? 'homes' : 'all'
            };
            if (target === 'homes') {
                payload.home_ids = Array.from(selectedHomes.keys());
            }
            btn.disabled = true;
            fetch(apiUrl('admin/ajax/push_broadcast.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (data.status === 'ok') {
                        showFlash(true, data.message || 'נשלח.');
                        form.reset();
                        if (linkPreset) linkPreset.value = 'custom';
                        if (linkInput) linkInput.value = '/';
                        syncLinkInput();
                        form.querySelector('input[name="pb_target"][value="all"]').checked = true;
                        var dPush = form.querySelector('input[name="pb_delivery"][value="push"]');
                        if (dPush) dPush.checked = true;
                        selectedHomes.clear();
                        renderSelected();
                        syncHomesPanel();
                    } else {
                        showFlash(false, data.message || 'שגיאה');
                    }
                })
                .catch(function () {
                    showFlash(false, 'שגיאת תקשורת');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        }

        if (typeof window.tazrimConfirm === 'function') {
            window.tazrimConfirm({
                title: 'אישור שידור',
                message: confirmMsg,
                danger: true,
                confirmText: 'שליחה',
                cancelText: 'ביטול'
            }).then(function (ok) {
                if (ok) doSendPush();
            });
        } else if (window.confirm(confirmMsg)) {
            doSendPush();
        }
    });
    syncLinkInput();

    /* ── AI generate (SSE with questions) ── */
    var pbAiBtn = document.getElementById('pb_ai_btn');
    var pbAiInstructions = document.getElementById('pb_ai_instructions');
    var pbAiStatus = document.getElementById('pb_ai_status');
    var pbAiStatusText = document.getElementById('pb_ai_status_text');
    var pbAiQuestionsEl = document.getElementById('pb_ai_questions');
    var pbTitle = document.getElementById('pb_title');
    var pbBody = document.getElementById('pb_body');
    var pbAiBtnOrigHtml = pbAiBtn ? pbAiBtn.innerHTML : '';

    function pbAiSetBusy(busy, statusMsg) {
        pbAiBtn.disabled = busy;
        pbAiBtn.innerHTML = busy
            ? '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> AI עובד...'
            : pbAiBtnOrigHtml;
        if (busy && statusMsg) {
            pbAiStatusText.textContent = statusMsg;
            pbAiStatus.classList.remove('hidden');
        } else if (!busy) {
            pbAiStatus.classList.add('hidden');
        }
    }

    function pbAiCallSSE(payload) {
        pbAiQuestionsEl.classList.add('hidden');
        pbAiQuestionsEl.innerHTML = '';
        pbAiSetBusy(true, 'מנתח את ההנחיות ומנסח הודעה…');

        fetch(apiUrl('admin/ajax/push_broadcast_ai_generate.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                return response.text().then(function (t) {
                    var d; try { d = JSON.parse(t); } catch(e) { d = {}; }
                    pbAiSetBusy(false);
                    showFlash(false, d.message || 'שגיאה ' + response.status);
                });
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            function read() {
                reader.read().then(function (result) {
                    if (result.done) { pbAiSetBusy(false); return; }
                    buffer += decoder.decode(result.value, { stream: true });
                    var lines = buffer.split('\n');
                    buffer = lines.pop();
                    var currentEvent = '';
                    for (var i = 0; i < lines.length; i++) {
                        var line = lines[i];
                        if (line.indexOf('event: ') === 0) {
                            currentEvent = line.slice(7).trim();
                        } else if (line.indexOf('data: ') === 0) {
                            var data;
                            try { data = JSON.parse(line.slice(6)); } catch(e) { continue; }
                            pbAiHandleEvent(currentEvent, data, payload);
                            currentEvent = '';
                        }
                    }
                    read();
                }).catch(function () { pbAiSetBusy(false); showFlash(false, 'שגיאת תקשורת.'); });
            }
            read();
        }).catch(function () { pbAiSetBusy(false); showFlash(false, 'שגיאת תקשורת.'); });
    }

    function pbAiHandleEvent(event, data, originalPayload) {
        if (event === 'thinking') {
            pbAiStatusText.textContent = data.hint || 'חושב…';
            pbAiStatus.classList.remove('hidden');
        } else if (event === 'questions') {
            pbAiSetBusy(false);
            pbAiRenderQuestions(data.questions || [], originalPayload);
        } else if (event === 'done') {
            pbAiSetBusy(false);
            if (data.status === 'ok') {
                pbTitle.value = data.title || '';
                pbBody.value = data.body || '';
                pbAiQuestionsEl.classList.add('hidden');
                showFlash(true, 'הכותרת והתוכן עודכנו לפי ה-AI.');
            } else if (data.status !== 'questions') {
                showFlash(false, data.message || 'שגיאה');
            }
        }
    }

    function pbAiRenderQuestions(questions, originalPayload) {
        pbAiQuestionsEl.innerHTML = '';
        pbAiQuestionsEl.classList.remove('hidden');

        var header = document.createElement('div');
        header.className = 'flex items-center gap-2 mb-2';
        header.innerHTML = '<i class="fa-solid fa-comments text-amber-600" aria-hidden="true"></i>'
            + '<span class="text-sm font-bold text-gray-900">ל-AI יש כמה שאלות לפני היצירה:</span>';
        pbAiQuestionsEl.appendChild(header);

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
                    var optBtn = document.createElement('button');
                    optBtn.type = 'button';
                    optBtn.className = 'rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-violet-400 hover:bg-violet-50 transition-colors';
                    optBtn.textContent = opt;
                    optBtn.addEventListener('click', function () {
                        if (selectedBtn) {
                            selectedBtn.classList.remove('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                            selectedBtn.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                        }
                        optBtn.classList.remove('border-gray-200', 'bg-white', 'text-gray-700');
                        optBtn.classList.add('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                        selectedBtn = optBtn;
                        answersMap[q.id] = opt;
                        customInput.value = '';
                    });
                    optionsWrap.appendChild(optBtn);
                });
                wrap.appendChild(optionsWrap);
            }

            var customInput = document.createElement('input');
            customInput.type = 'text';
            customInput.placeholder = 'או תשובה מותאמת אישית…';
            customInput.className = 'w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-violet-300 focus:border-violet-400';
            customInput.addEventListener('input', function () {
                if (customInput.value.trim()) {
                    if (selectedBtn) {
                        selectedBtn.classList.remove('border-violet-500', 'bg-violet-100', 'text-violet-800', 'font-semibold');
                        selectedBtn.classList.add('border-gray-200', 'bg-white', 'text-gray-700');
                        selectedBtn = null;
                    }
                    answersMap[q.id] = customInput.value.trim();
                }
            });
            wrap.appendChild(customInput);
            pbAiQuestionsEl.appendChild(wrap);
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
            pbAiQuestionsEl.classList.add('hidden');
            pbAiCallSSE({
                csrf_token: csrf,
                phase: 'answer',
                instructions: (pbAiInstructions.value || '').trim(),
                original_instructions: originalPayload.instructions || '',
                answers: answers,
                prev_questions: questions
            });
        });
        submitWrap.appendChild(submitBtn);
        pbAiQuestionsEl.appendChild(submitWrap);

        pbAiQuestionsEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (pbAiBtn && pbAiInstructions && pbTitle && pbBody) {
        pbAiBtn.addEventListener('click', function () {
            var hint = (pbAiInstructions.value || '').trim();
            if (!hint) {
                showFlash(false, 'נא למלא הוראות לבינה.');
                return;
            }
            pbAiCallSSE({
                csrf_token: csrf,
                phase: 'generate',
                instructions: hint
            });
        });
    }
})();
</script>
<?php
require dirname(__FILE__) . '/includes/partials/layout_shell_end.php';
require dirname(__FILE__) . '/includes/partials/footer.php';
?>

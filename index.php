<?php 
require('path.php'); 

include(ROOT_PATH . '/app/controllers/users.php'); 
include(ROOT_PATH . '/assets/includes/auth_check.php'); 

$home_id = $_SESSION['home_id'];

$home_data = selectOne('homes', ['id' => $home_id]);
if (!$home_data) {
    $home_data = ['name' => ''];
}

include(ROOT_PATH . '/assets/includes/process_recurring.php');

// --- ניהול חודש ושנה עם זיכרון בסשן ואבטחה ---
if (isset($_GET['m']) && isset($_GET['y'])) {
    $selected_month = (int)$_GET['m'];
    $selected_year = (int)$_GET['y'];
    
    if ($selected_month < 1 || $selected_month > 12) { $selected_month = (int)date('m'); }
    if ($selected_year < 2000 || $selected_year > 2100) { $selected_year = (int)date('Y'); }

    $_SESSION['view_month'] = $selected_month;
    $_SESSION['view_year'] = $selected_year;
} else {
    $selected_month = $_SESSION['view_month'] ?? (int)date('m');
    $selected_year = $_SESSION['view_year'] ?? (int)date('Y');
}

$prev_month = $selected_month - 1;
$prev_year = $selected_year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month == 13) { $next_month = 1; $next_year++; }

$hebrew_months = [
    1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל', 
    5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט', 
    9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
];
$month_name = $hebrew_months[$selected_month];

require_once ROOT_PATH . '/app/includes/render_home_dashboard_core.php';
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>התזרים | דף הבית</title>
</head>
<body class="bg-gray">
    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">
    
                <?php 
                $is_current_month = ($selected_month == date('m') && $selected_year == date('Y')); 
                ?>

                <div class="page-header-actions page-header-actions--home flex-between" style="margin-bottom: 25px;">
                    <div class="page-header-actions__title-wrap">
                        <h1 class="section-title" style="margin-bottom: 0;">נתוני החודש</h1>
                    </div>
                    
                    <div class="home-month-nav shopping-tabs-bar<?php echo !$is_current_month ? ' home-month-nav--has-today' : ''; ?>" aria-label="ניווט בין חודשים">
                        <div class="shopping-store-tabs">
                            <a href="?m=<?php echo $prev_month; ?>&y=<?php echo $prev_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש קודם">
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                <span><?php echo $hebrew_months[$prev_month]; ?></span>
                            </a>
                            <div class="home-month-nav__center-cell">
                                <span class="shopping-tab-chip active home-month-nav__current" aria-current="page">
                                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                                    <span><?php echo $month_name . ' ' . $selected_year; ?></span>
                                </span>
                                <?php if (!$is_current_month): ?>
                                    <a href="<?php echo '?m=' . date('m') . '&y=' . date('Y'); ?>" class="shopping-tab-chip shopping-tab-add home-month-nav__today" title="חזרה לחודש הנוכחי">
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                        <span>היום</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <a href="?m=<?php echo $next_month; ?>&y=<?php echo $next_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש הבא">
                                <span><?php echo $hebrew_months[$next_month]; ?></span>
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php echo tazrim_render_home_dashboard_core($conn, $home_id, $selected_month, $selected_year); ?>
            </div>
        </main>

    </div>

    <div id="category-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="selected-cat-name"></h3>
                <button type="button" onclick="closeCatDetails()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <div id="cat-details-content"></div>
            </div>
        </div>
    </div>

    <?php 
    $all_cats_query = "SELECT id, name, type, icon FROM categories WHERE home_id = $home_id AND is_active = 1";
    $all_cats_result = mysqli_query($conn, $all_cats_query);
    $categories_array = [];
    while($cat = mysqli_fetch_assoc($all_cats_result)) {
        $categories_array[] = $cat;
    }
    ?>

    <div id="add-transaction-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3>הוספת פעולה חדשה</h3>
                <button type="button" onclick="closeAddModal()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <form id="add-transaction-form" class="form-fields-pill">
                    
                    <div class="modern-toggle">
                        <input type="radio" name="type" id="type-expense" value="expense" checked onchange="filterCategories()">
                        <label for="type-expense" class="toggle-option expense">הוצאה</label>
                        
                        <input type="radio" name="type" id="type-income" value="income" onchange="filterCategories()">
                        <label for="type-income" class="toggle-option income">הכנסה</label>
                    </div>

                    <div class="input-group">
                        <label>תיאור הפעולה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" name="description" id="trans-desc" required placeholder="למשל: קניות בסופר" pattern=".*\S+.*" title="לא ניתן להזין רק רווחים">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>סכום</label>
                        <div class="input-with-icon input-with-icon--currency">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <input type="number" name="amount" id="trans-amount" step="0.01" min="0.01" required placeholder="0.00" style="font-size: 1.2rem; font-weight: 800;">
                            <input type="hidden" name="currency_code" id="trans-currency-code" value="ILS">
                            <button type="button" id="trans-currency-toggle" class="currency-toggle-btn" onclick="toggleCurrencyField('trans-currency-code', 'trans-currency-toggle')" aria-label="החלף מטבע" title="לחיצה מחליפה בין שקל לדולר">
                                <i class="fa-solid fa-shekel-sign" aria-hidden="true"></i>
                                <span class="currency-toggle-btn__tooltip">לחיצה מחליפה בין שקל לדולר</span>
                            </button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>קטגוריה</label>
                        <div id="category-grid-container"></div>
                        <input type="hidden" name="category_id" id="selected-category-id" required>
                    </div>

                    <div class="input-group">
                        <label>תאריך</label>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-calendar-days"></i>
                            <input type="date" name="transaction_date" id="trans-date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="input-group" style="margin-top: 20px; text-align: right;">
                        <label class="checkbox-container" style="font-size: 0.95rem; font-weight: 600;">
                            <input type="checkbox" name="is_recurring" id="trans-recurring" value="1">
                            הגדר כפעולה קבועה (תחזור אוטומטית כל חודש)
                        </label>
                    </div>

                    <div id="add-trans-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="submit-trans-btn" style="margin-top: 5px;">
                        <i class="fa-solid fa-plus"></i> הוסף פעולה
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="edit-transaction-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3>עריכת פעולה</h3>
                <button type="button" onclick="closeEditTransModal()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <form id="edit-transaction-form" class="form-fields-pill">
                    <input type="hidden" name="transaction_id" id="edit-trans-id">
                    <input type="hidden" id="edit-trans-type">

                    <div class="input-group">
                        <label>תיאור הפעולה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" name="description" id="edit-trans-desc" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>סכום (₪)</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-shekel-sign"></i>
                            <input type="number" name="amount" id="edit-trans-amount" step="0.01" min="0.01" required style="font-size: 1.2rem; font-weight: 800;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>קטגוריה</label>
                        <div id="edit-category-grid-container"></div>
                        <input type="hidden" name="category_id" id="edit-selected-category-id" required>
                    </div>

                    <div id="edit-trans-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="submit-edit-trans-btn" style="margin-top: 5px;">
                        <i class="fa-solid fa-save"></i> שמור שינויים
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php include(ROOT_PATH . '/assets/includes/global_info_modal.php'); ?>
</body>

<script>
    (function () {
        if (typeof window.tazrimMessageFromAjaxText === 'function') return;
        window.tazrimMessageFromAjaxText = function (text) {
            if (!text || typeof text !== 'string') return 'אירעה שגיאה. נסו שוב או רעננו את הדף.';
            var t = text.trim();
            if (!t) return 'אירעה שגיאה. נסו שוב או רעננו את הדף.';
            if (t.charAt(0) === '{' || t.charAt(0) === '[') {
                try {
                    var o = JSON.parse(t);
                    if (o && typeof o.message === 'string' && o.message.length) return o.message;
                } catch (e) {}
            }
            return 'אירעה שגיאה. נסו שוב או רעננו את הדף.';
        };
    })();

    const currentMonth = <?php echo $selected_month; ?>;
    const currentYear = <?php echo $selected_year; ?>;

    let recentOffset = 4;
    let pendingOffset = 4;

    /** מקור אחרון לפתיחת עריכה — main | category-details */
    window.transactionActionSource = 'main';
    /** פירוט קטגוריה פתוח (לרענון תוכן אחרי שינוי) */
    window.categoryDetailsContext = null;

    document.addEventListener('click', function(e) {
        const recentBtn = e.target.closest('#loadMoreBtn');
        if (recentBtn) {
            e.preventDefault();
            if (recentBtn.getAttribute('data-state') === 'expanded') {
                collapseTransactions('recent', 'recent-transactions-list', recentBtn);
            } else {
                loadTransactions('recent', recentOffset, 'recent-transactions-list', recentBtn);
            }
            return;
        }
        const pendBtn = e.target.closest('#loadMorePendingBtn');
        if (pendBtn) {
            e.preventDefault();
            if (pendBtn.getAttribute('data-state') === 'expanded') {
                collapseTransactions('pending', 'pending-transactions-list', pendBtn);
            } else {
                loadTransactions('pending', pendingOffset, 'pending-transactions-list', pendBtn);
            }
        }
    });

    function loadTransactions(status, offset, containerId, btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> רגע…';
        btn.disabled = true;
        
        const url = `app/ajax/fetch_transactions.php?offset=${offset}&status=${status}&m=${currentMonth}&y=${currentYear}`;
        
        fetch(url)
            .then(res => res.text().then(t => ({ ok: res.ok, t })))
            .then(({ ok, t }) => {
                if (!ok) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    var errMsg1 = tazrimMessageFromAjaxText(t);
                    if (typeof tazrimAlert === 'function') tazrimAlert({ title: 'לא ניתן לטעון', message: errMsg1 });
                    else if (window.alert) window.alert(errMsg1);
                    return;
                }
                const html = t;
                if (html.trim() === "" || html.trim() === "NO_MORE") {
                    setButtonToExpanded(btn);
                } else {
                    const wrappedHtml = `<div class="ajax-loaded-${status}">${html}</div>`;
                    document.getElementById(containerId).insertAdjacentHTML('beforeend', wrappedHtml);
                    
                    if (status === 'recent') recentOffset += 4;
                    if (status === 'pending') pendingOffset += 4;

                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    if (tempDiv.querySelectorAll('.transaction-item').length < 4) {
                        setButtonToExpanded(btn);
                    }
                }
            })
            .catch(err => {
                console.error('Error fetching transactions:', err);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    function setButtonToExpanded(btn) {
        btn.innerText = 'סגור';
        btn.disabled = false;
        btn.setAttribute('data-state', 'expanded');
        btn.style.backgroundColor = 'var(--gray)';
    }

    function collapseTransactions(status, containerId, btn) {
        const loadedItems = document.querySelectorAll(`.ajax-loaded-${status}`);
        loadedItems.forEach(item => item.remove());
        
        if (status === 'recent') recentOffset = 4;
        if (status === 'pending') pendingOffset = 4;
        
        btn.innerHTML = status === 'pending' ? 'עוד ממתינות' : 'עוד אחרונות';
        btn.removeAttribute('data-state');
        btn.style.backgroundColor = ''; 
        
        document.getElementById(containerId).parentElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function getDetailsRequestUrl(ctx) {
        if (!ctx) return null;
        if (ctx.mode === 'type' && ctx.type) {
            return `app/ajax/fetch_category_details.php?mode=type&trans_type=${encodeURIComponent(ctx.type)}&m=${currentMonth}&y=${currentYear}`;
        }
        if (ctx.id) {
            return `app/ajax/fetch_category_details.php?cat_id=${ctx.id}&m=${currentMonth}&y=${currentYear}`;
        }
        return null;
    }

    function loadCategoryDetails(catId, catName) {
        const modal = document.getElementById('category-details-modal');
        const content = document.getElementById('cat-details-content');
        const title = document.getElementById('selected-cat-name');

        window.categoryDetailsContext = { mode: 'category', id: catId, name: catName };
        modal.style.display = 'block';
        title.innerText = 'פירוט הוצאות: ' + catName;
        content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> רגע…</div>';

        fetch(getDetailsRequestUrl(window.categoryDetailsContext))
            .then(response => response.text().then(t => ({ ok: response.ok, t })))
            .then(({ ok, t }) => {
                if (!ok) {
                    const msg = typeof tazrimMessageFromAjaxText === 'function' ? tazrimMessageFromAjaxText(t) : 'הפעולה נכשלה';
                    content.innerHTML = '<p style="text-align:center;padding:24px;color:var(--error);font-weight:700;">' + msg + '</p>';
                    return;
                }
                content.innerHTML = t;
            });
    }

    function loadTypeDetails(type) {
        const modal = document.getElementById('category-details-modal');
        const content = document.getElementById('cat-details-content');
        const title = document.getElementById('selected-cat-name');
        const normalizedType = type === 'income' ? 'income' : 'expense';
        const typeLabel = normalizedType === 'income' ? 'הכנסות' : 'הוצאות';

        window.categoryDetailsContext = { mode: 'type', type: normalizedType, name: typeLabel };
        modal.style.display = 'block';
        title.innerText = `פירוט ${typeLabel}`;
        content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> רגע…</div>';

        fetch(getDetailsRequestUrl(window.categoryDetailsContext))
            .then(response => response.text().then(t => ({ ok: response.ok, t })))
            .then(({ ok, t }) => {
                if (!ok) {
                    const msg = typeof tazrimMessageFromAjaxText === 'function' ? tazrimMessageFromAjaxText(t) : 'הפעולה נכשלה';
                    content.innerHTML = '<p style="text-align:center;padding:24px;color:var(--error);font-weight:700;">' + msg + '</p>';
                    return;
                }
                content.innerHTML = t;
            });
    }

    function closeCatDetails() {
        window.categoryDetailsContext = null;
        document.getElementById('category-details-modal').style.display = 'none';
    }

    function refreshOpenCategoryDetailsIfAny() {
        const modal = document.getElementById('category-details-modal');
        if (!modal || modal.style.display !== 'block') return;
        const ctx = window.categoryDetailsContext;
        if (!ctx) return;
        const url = getDetailsRequestUrl(ctx);
        if (!url) return;
        const content = document.getElementById('cat-details-content');
        content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> רגע…</div>';
        return fetch(url)
            .then(r => r.text().then(t => ({ ok: r.ok, t })))
            .then(({ ok, t }) => {
                if (!ok) {
                    const msg = typeof tazrimMessageFromAjaxText === 'function' ? tazrimMessageFromAjaxText(t) : 'הפעולה נכשלה';
                    content.innerHTML = '<p style="text-align:center;padding:24px;color:var(--error);font-weight:700;">' + msg + '</p>';
                    return;
                }
                content.innerHTML = t;
            });
    }

    function refreshHomeDashboardCore() {
        return fetch(`app/ajax/home_dashboard_core.php?m=${currentMonth}&y=${currentYear}`)
            .then(r => r.text().then(t => {
                var data = null;
                try { data = JSON.parse(t); } catch (e) { /* ignore */ }
                if (!r.ok) {
                    var msg2 = (data && data.message) || tazrimMessageFromAjaxText(t);
                    if (typeof tazrimAlert === 'function') tazrimAlert({ title: 'שגיאה', message: msg2 });
                    else if (window.alert) window.alert(msg2);
                    return;
                }
                if (!data || !data.ok) {
                    window.location.reload();
                    return;
                }
                const el = document.getElementById('home-dashboard-core');
                if (el) el.outerHTML = data.html;
                recentOffset = 4;
                pendingOffset = 4;
            }))
            .catch(function () { window.location.reload(); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.kpi-balance-toggle-btn');
        if (!btn) return;
        var core = document.getElementById('home-dashboard-core');
        if (!core || !core.contains(btn)) return;
        e.preventDefault();
        var card = btn.closest('.kpi-card--balance');
        if (!card) return;
        var amountEl = card.querySelector('.kpi-bank-amount-display');
        var labelEl = card.querySelector('.kpi-bank-label-toggle');
        if (!amountEl || !labelEl) return;
        var pressed = btn.getAttribute('aria-pressed') === 'true';
        var next = !pressed;
        btn.setAttribute('aria-pressed', next ? 'true' : 'false');
        var est = amountEl.getAttribute('data-estimated') || '';
        var raw = amountEl.getAttribute('data-raw') || '';
        var estPos = amountEl.getAttribute('data-est-pos') === '1';
        var rawPos = amountEl.getAttribute('data-raw-pos') === '1';
        var icon = btn.querySelector('i');
        if (next) {
            amountEl.textContent = raw;
            labelEl.textContent = labelEl.getAttribute('data-label-raw') || 'יתרה בבנק (כעת)';
            amountEl.classList.remove('success-text', 'error-text');
            amountEl.classList.add(rawPos ? 'success-text' : 'error-text');
            if (icon) icon.className = 'fa-solid fa-eye-slash';
        } else {
            amountEl.textContent = est;
            labelEl.textContent = labelEl.getAttribute('data-label-estimated') || 'יתרה בחשבון';
            amountEl.classList.remove('success-text', 'error-text');
            amountEl.classList.add(estPos ? 'success-text' : 'error-text');
            if (icon) icon.className = 'fa-solid fa-eye';
        }
    });

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('category-details-modal');
        if (modal && event.target === modal) {
            window.categoryDetailsContext = null;
            modal.style.display = 'none';
        }
    });

</script>
<script>
    const addModal = document.getElementById('add-transaction-modal');
    const floatingBtn = document.querySelector('.floating-btn');
    const addForm = document.getElementById('add-transaction-form');
    
    const allCategories = <?php echo json_encode($categories_array); ?>;

    // === מנוע ה-Custom Select (בורר הקטגוריות החדש) ===
    function buildCustomSelect(containerId, hiddenInputId, type, selectedId = null) {
        const container = document.getElementById(containerId);
        const hiddenInput = document.getElementById(hiddenInputId);
        
        container.innerHTML = '';
        
        const filteredCats = allCategories.filter(cat => cat.type === type);
        
        if (filteredCats.length === 0) {
            container.innerHTML = '<div style="color:var(--error); font-size:0.9rem; padding: 10px;">לא נמצאו קטגוריות</div>';
            hiddenInput.value = '';
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';

        let selectedCat = filteredCats.find(cat => cat.id == selectedId);
        let triggerHTML = '';

        if (!selectedCat) {
            hiddenInput.value = '';
            triggerHTML = `
                <div class="selected-cat-info" style="color: #888;">
                    <i class="fa-solid fa-list-ul" style="color: #ccc;"></i> <span>בחירת קטגוריה...</span>
                </div>
                <i class="fa-solid fa-chevron-down" style="color: #ccc; font-size: 0.9rem;"></i>
            `;
        } else {
            hiddenInput.value = selectedCat.id;
            const iconClassInit = selectedCat.icon ? selectedCat.icon : 'fa-tag';
            triggerHTML = `
                <div class="selected-cat-info">
                    <i class="fa-solid ${iconClassInit}" style="color: var(--main);"></i> <span>${selectedCat.name}</span>
                </div>
                <i class="fa-solid fa-chevron-down" style="color: #ccc; font-size: 0.9rem;"></i>
            `;
        }
        
        let optionsHTML = '';
        filteredCats.forEach(cat => {
            const iconClass = cat.icon ? cat.icon : 'fa-tag';
            optionsHTML += `
                <div class="custom-option" data-value="${cat.id}" data-name="${cat.name}" data-icon="${iconClass}">
                    <i class="fa-solid ${iconClass}"></i> <span>${cat.name}</span>
                </div>
            `;
        });

        wrapper.innerHTML = `
            <div class="custom-select-trigger">
                ${triggerHTML}
            </div>
            <div class="custom-select-options">
                ${optionsHTML}
            </div>
        `;
        
        container.appendChild(wrapper);

        const trigger = wrapper.querySelector('.custom-select-trigger');
        const options = wrapper.querySelectorAll('.custom-option');
        
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.custom-select-wrapper').forEach(w => {
                if(w !== wrapper) w.classList.remove('open');
            });
            wrapper.classList.toggle('open');
        });

        options.forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const val = this.getAttribute('data-value');
                const name = this.getAttribute('data-name');
                const icon = this.getAttribute('data-icon');
                
                hiddenInput.value = val;
                wrapper.querySelector('.selected-cat-info').innerHTML = `<i class="fa-solid ${icon}" style="color: var(--main);"></i> <span style="color: var(--text);">${name}</span>`;
                wrapper.classList.remove('open');
            });
        });
    }

    document.addEventListener('click', function() {
        document.querySelectorAll('.custom-select-wrapper').forEach(w => {
            w.classList.remove('open');
        });
    });

    // 1. התיקון כאן: הגנה מקריסה במקרה שהכפתור הישן כבר לא קיים במסך
    if (floatingBtn) {
        floatingBtn.addEventListener('click', () => {
            addModal.style.display = 'block';
            resetAddForm(); 
        });
    }

    function closeAddModal() {
        addModal.style.display = 'none';
        resetAddForm();
    }

    function syncCurrencyToggle(inputId, buttonId) {
        const hiddenInput = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        if (!hiddenInput || !button) {
            return;
        }

        const icon = button.querySelector('i');
        const currencyCode = hiddenInput.value === 'USD' ? 'USD' : 'ILS';
        hiddenInput.value = currencyCode;
        if (icon) {
            icon.className = currencyCode === 'USD' ? 'fa-solid fa-dollar-sign' : 'fa-solid fa-shekel-sign';
        }
        button.setAttribute('aria-label', currencyCode === 'USD' ? 'מטבע נוכחי דולר, לחץ להחלפה לשקל' : 'מטבע נוכחי שקל, לחץ להחלפה לדולר');
    }

    function toggleCurrencyField(inputId, buttonId) {
        const hiddenInput = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        if (!hiddenInput || !button) {
            return;
        }

        hiddenInput.value = hiddenInput.value === 'USD' ? 'ILS' : 'USD';
        syncCurrencyToggle(inputId, buttonId);
        button.classList.add('tooltip-visible');
        window.setTimeout(() => button.classList.remove('tooltip-visible'), 1200);
    }

    function resetAddForm() {
        addForm.reset();
        document.getElementById('type-expense').checked = true; 
        document.getElementById('trans-date').value = "<?php echo date('Y-m-d'); ?>"; 
        document.getElementById('trans-currency-code').value = 'ILS';
        syncCurrencyToggle('trans-currency-code', 'trans-currency-toggle');
        document.getElementById('selected-category-id').value = ""; 
        document.getElementById('add-trans-msg').style.display = 'none';
        
        const submitBtn = document.getElementById('submit-trans-btn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
        
        filterCategories(); 
    }

    function filterCategories() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        buildCustomSelect('category-grid-container', 'selected-category-id', selectedType);
    }

    addForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        const submitBtn = document.getElementById('submit-trans-btn');
        const msgBox = document.getElementById('add-trans-msg');
        
        const selectedCatId = document.getElementById('selected-category-id').value;
        if (!selectedCatId) {
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#fee2e2';
            msgBox.style.color = 'var(--error)';
            msgBox.innerText = 'נא לבחור קטגוריה מהרשימה.';
            return; 
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר נתונים...';
        msgBox.style.display = 'none';

        const formData = new FormData(addForm);

        fetch('app/ajax/add_transaction.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = 'var(--sub_main-light)';
                msgBox.style.color = 'var(--main)';
                msgBox.innerText = 'הפעולה נוספה בהצלחה!';
                
                setTimeout(() => {
                    closeAddModal();
                    closeCatDetails();
                    refreshHomeDashboardCore();
                }, 500);
            } else {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = data.message || 'אירעה שגיאה בשמירת הנתונים.';
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#fee2e2';
            msgBox.style.color = 'var(--error)';
            msgBox.innerText = 'שגיאת תקשורת. אנא נסה שוב.';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == addModal) {
            closeAddModal();
        }
    });

    syncCurrencyToggle('trans-currency-code', 'trans-currency-toggle');
</script>

<script>
    // === לוגיקת עריכת פעולה ===
    const editModal = document.getElementById('edit-transaction-modal');
    const editForm = document.getElementById('edit-transaction-form');

    function openEditTransModal(id, amount, categoryId, desc, type, source) {
        window.transactionActionSource = source || 'main';
        document.getElementById('edit-trans-id').value = id;
        document.getElementById('edit-trans-amount').value = amount;
        document.getElementById('edit-trans-desc').value = desc;
        document.getElementById('edit-trans-type').value = type;

        buildCustomSelect('edit-category-grid-container', 'edit-selected-category-id', type, categoryId);

        editModal.style.display = 'block';
    }

    function closeEditTransModal() {
        editModal.style.display = 'none';
        document.getElementById('edit-trans-msg').style.display = 'none';
    }

    function deleteTransaction(id, source) {
        const src = source || window.transactionActionSource || 'main';
        tazrimConfirm({
            title: 'מחיקת פעולה',
            message: 'האם אתה בטוח שברצונך למחוק פעולה זו? התקציב ויתרת הבנק יעודכנו בהתאם.',
            confirmText: 'מחק',
            cancelText: 'ביטול',
            danger: true
        }).then(function(ok) {
            if (!ok) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('app/ajax/delete_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    tazrimAlert({
                        title: 'שגיאה במחיקה',
                        message: data.message || 'אירעה שגיאה.'
                    });
                    return;
                }
                closeEditTransModal();
                if (src === 'category-details') {
                    refreshHomeDashboardCore()
                        .then(() => refreshOpenCategoryDetailsIfAny());
                } else {
                    closeCatDetails();
                    refreshHomeDashboardCore();
                }
            })
            .catch(err => {
                console.error('Error:', err);
                tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת.' });
            });
        });
    }

    editForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        // 2. התיקון כאן: חובה להגדיר את המשתנים בתוך פונקציית הלחיצה!
        const submitBtn = document.getElementById('submit-edit-trans-btn');
        const msgBox = document.getElementById('edit-trans-msg');

        const selectedCatId = document.getElementById('edit-selected-category-id').value;
        if (!selectedCatId) {
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#fee2e2';
            msgBox.style.color = 'var(--error)';
            msgBox.innerText = 'נא לבחור קטגוריה מהרשימה.';
            return; 
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר...';
        msgBox.style.display = 'none';

        fetch('app/ajax/edit_transaction.php', {
            method: 'POST',
            body: new FormData(editForm)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = 'var(--sub_main-light)';
                msgBox.style.color = 'var(--main)';
                msgBox.innerText = 'הפעולה עודכנה בהצלחה!';
                const src = window.transactionActionSource || 'main';
                setTimeout(() => {
                    closeEditTransModal();
                    if (src === 'category-details') {
                        refreshHomeDashboardCore()
                            .then(() => refreshOpenCategoryDetailsIfAny());
                    } else {
                        closeCatDetails();
                        refreshHomeDashboardCore();
                    }
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';
                }, 500);
            } else {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = data.message;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';
            }
        })
        .catch(err => {
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = '#fee2e2';
            msgBox.style.color = 'var(--error)';
            msgBox.innerText = 'שגיאת תקשורת.';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == editModal) {
            closeEditTransModal();
        }
    });
</script>
</html>
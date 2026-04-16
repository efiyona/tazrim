<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

$home_id = $_SESSION['home_id'];
$home_data = selectOne('homes', ['id' => $home_id]);

// בדיקה האם הבית הזה כבר עבר אשף התקנה של רשימת קניות (האם יש לו חנויות)
$check_cats_query = "SELECT COUNT(*) as count FROM shopping_categories WHERE home_id = $home_id";
$check_cats_result = mysqli_query($conn, $check_cats_query);
$cats_count = mysqli_fetch_assoc($check_cats_result)['count'];

$is_setup_needed = ($cats_count == 0);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>רשימת קניות | התזרים</title>
    <?php /* עיצוב אשף + דף קניות: user.css (.shopping-*) */ ?>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">

            <?php if ($is_setup_needed): ?>
                <div class="shopping-welcome-card">
                    <div class="shopping-stepper-dots">
                        <div class="dot active" id="dot-1"></div>
                        <div class="dot" id="dot-2"></div>
                    </div>

                    <div class="shopping-wizard-step active" id="step-1">
                        <div class="shopping-wizard-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                        <h1 style="font-weight: 800; margin-bottom: 10px; font-size: 2rem;">רשימת קניות חכמה</h1>
                        <p style="color: #666; line-height: 1.6; margin-bottom: 10px; font-size: 1.1rem;">במקום פתקים בוואטסאפ – רשימה משותפת לכל הבית שמתעדכנת בזמן אמת, מחולקת לפי חנויות.</p>
                        <button type="button" class="btn-welcome" onclick="nextStep(2)">בואו נגדיר חנויות <i class="fa-solid fa-arrow-left"></i></button>
                    </div>

                    <div class="shopping-wizard-step" id="step-2">
                        <h2 style="font-weight: 800; margin-bottom: 5px;">איפה אתם קונים?</h2>
                        <p style="color: #666; font-size: 0.95rem;">בחרו חנויות (ניתן לערוך בהמשך בניהול הבית).</p>
                        
                        <div class="shopping-wizard-chip-grid" id="stores-grid">
                            <div class="store-option" data-icon="fa-cart-shopping" data-name="סופרמרקט">
                                <i class="fa-solid fa-cart-shopping"></i>
                                <span>סופרמרקט</span>
                            </div>
                            <div class="store-option" data-icon="fa-leaf" data-name="ירקנייה">
                                <i class="fa-solid fa-leaf"></i>
                                <span>ירקנייה</span>
                            </div>
                            <div class="store-option" data-icon="fa-medkit" data-name="פארם">
                                <i class="fa-solid fa-medkit"></i>
                                <span>פארם</span>
                            </div>
                            <div class="store-option" data-icon="fa-drumstick-bite" data-name="קצבייה">
                                <i class="fa-solid fa-drumstick-bite"></i>
                                <span>קצבייה</span>
                            </div>
                            <div class="store-option" data-icon="fa-bread-slice" data-name="מאפייה">
                                <i class="fa-solid fa-bread-slice"></i>
                                <span>מאפייה</span>
                            </div>
                            <div class="store-option" data-icon="fa-store" data-name="מכולת">
                                <i class="fa-solid fa-store"></i>
                                <span>מכולת השכונה</span>
                            </div>
                            <div class="store-option" data-icon="fa-plug" data-name="אלקטרוניקה">
                                <i class="fa-solid fa-plug"></i>
                                <span>אלקטרוניקה</span>
                            </div>
                            <div class="store-option" data-icon="fa-box" data-name="שונות">
                                <i class="fa-solid fa-box"></i>
                                <span>שונות</span>
                            </div>
                            
                            <div class="custom-store-wrapper shopping-wizard-custom-row">
                                <input type="text" id="custom-store-name" class="custom-store-input" placeholder="חנות ספציפית (למשל: מקס סטוק)">
                                <button type="button" class="btn-add-custom" onclick="addCustomStore()">הוסף</button>
                            </div>
                        </div>

                        <div id="setup-msg" style="margin-top: 15px; display: none; padding: 10px; border-radius: 8px; font-weight: 700;"></div>
                        <button type="button" id="finish-setup-btn" class="btn-welcome" onclick="saveStores()"><i class="fa-solid fa-check"></i> יצירת רשימה!</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="page-header-actions" style="margin-bottom: 20px;">
                    <h1 class="section-title" style="margin-bottom: 0;">רשימת קניות</h1>
                    <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 5px;">מה חסר בבית?</p>
                </div>

                <div id="shopping-tabs-bar" class="shopping-tabs-bar" style="display: none;" aria-label="חנויות">
                    <div class="shopping-store-tabs" id="shopping-store-tabs"></div>
                </div>

                <div id="shopping-lists-container">
                    <div style="text-align:center; padding: 40px; color:#888;">
                        <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>רגע…
                    </div>
                </div>

                <div id="shopping-page-store-modal" class="modal shopping-page-store-modal" style="display: none;" aria-hidden="true">
                    <div class="modal-content shopping-page-store-modal__content">
                        <div class="modal-header shopping-page-store-modal__header">
                            <h3 id="shopping-page-store-modal-title">חנות חדשה</h3>
                            <button type="button" class="close-modal-btn" onclick="closeShoppingPageStoreModal()" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="shopping-page-store-id" value="">
                            <label class="shopping-field-label" for="shopping-page-store-name">שם החנות</label>
                            <input type="text" id="shopping-page-store-name" class="shopping-modal-input" placeholder="למשל: שופרסל" autocomplete="off">
                            <div id="shopping-page-store-icon-block">
                                <span class="shopping-field-label">אייקון</span>
                                <div id="shopping-page-store-icon-grid" class="shopping-icon-grid"></div>
                                <input type="hidden" id="shopping-page-store-icon" value="fa-cart-shopping">
                            </div>
                            <div id="shopping-page-store-msg" class="shopping-modal-msg" style="display: none;"></div>
                            <div id="shopping-page-store-actions-row" class="shopping-page-store-actions-row">
                                <button type="button" class="btn-primary shopping-modal-submit" id="shopping-page-store-save" onclick="submitShoppingPageStoreForm()">שמור</button>
                                <button type="button" class="shopping-modal-delete-btn" id="shopping-page-store-delete-btn" onclick="shoppingPageStoreDeleteFromModal()" aria-label="מחיקת חנות">מחיקה <i class="fa-solid fa-trash-alt" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            </div>
        </main>
    </div>

    <?php if ($is_setup_needed): ?>
    <script>
        // --- לוגיקת האשף (Wizard) ---
        function nextStep(stepNum) {
            document.querySelectorAll('.shopping-wizard-step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            document.querySelectorAll('.shopping-stepper-dots .dot').forEach(d => d.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        // בחירה והסרה של חנויות בלחיצה
        $(document).on('click', '.store-option', function() {
            $(this).toggleClass('selected');
        });

        // הוספת חנות אישית
        function addCustomStore() {
            const nameInput = $('#custom-store-name');
            const name = nameInput.val().trim();
            if (name === '') return;

            const newStore = `
                <div class="store-option selected" data-icon="fa-shop" data-name="${name}">
                    <i class="fa-solid fa-shop"></i>
                    <span>${name}</span>
                </div>
            `;
            
            // מכניס את החנות החדשה לפני תיבת ההקלדה האישית
            $('.custom-store-wrapper').before(newStore);
            nameInput.val('');
        }

        // אפשרות להוסיף חנות אישית בלחיצה על Enter
        $('#custom-store-name').on('keypress', function(e) {
            if(e.which === 13) {
                e.preventDefault();
                addCustomStore();
            }
        });

        // שמירת הנתונים בסוף
        function saveStores() {
            const selectedStores = [];
            $('.store-option.selected').each(function() {
                selectedStores.push(JSON.stringify({
                    name: $(this).data('name'),
                    icon: $(this).data('icon')
                }));
            });

            const msgBox = $('#setup-msg');
            if (selectedStores.length === 0) {
                if (typeof tazrimAlert === 'function') {
                    tazrimAlert({ title: 'בחירה נדרשת', message: 'אנא בחרו לפחות חנות אחת כדי להתחיל.' });
                } else {
                    msgBox.html('אנא בחרו לפחות חנות אחת כדי להתחיל.').css({'display': 'block', 'background': '#fee2e2', 'color': 'var(--error)'});
                }
                return;
            }

            const btn = $('#finish-setup-btn');
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> רגע…');
            msgBox.hide();

            $.post('../app/ajax/setup_shopping_categories.php', { categories: selectedStores }, function(response) {
                const res = (typeof response === 'object' && response !== null) ? response : (function(){ try { return JSON.parse(response); } catch(e){ return {}; } })();
                if(res.status === 'success') {
                    // מרענן את הדף, מה שיגרום לו לטעון את המערכת הרגילה!
                    window.location.reload();
                } else {
                    const m = res.message || 'אירעה שגיאה.';
                    if (typeof tazrimAlert === 'function') tazrimAlert({ title: 'שגיאה', message: m });
                    else msgBox.html(m).css({'display': 'block', 'background': '#fee2e2', 'color': 'var(--error)'});
                    btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> יצירת רשימה!');
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> יצירת רשימה!');
                if (typeof tazrimAlert === 'function') tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
            });
        }
    </script>
    <?php else: ?>
    <script>
        window.shoppingSelectedStoreId = window.shoppingSelectedStoreId || null;

        const SHOPPING_STORE_ICONS = [
            'fa-cart-shopping', 'fa-store', 'fa-leaf', 'fa-basket-shopping', 'fa-shop', 'fa-bag-shopping',
            'fa-medkit', 'fa-drumstick-bite', 'fa-bread-slice', 'fa-plug', 'fa-box', 'fa-tag'
        ];

        function shoppingEscapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function shoppingReadStorePanelMeta(storeId) {
            const $lbl = $('#shopping-panel-' + storeId + ' .category-title-label');
            const raw = $lbl.clone();
            raw.find('i').remove();
            const name = (raw.text() || '').trim();
            const parts = (($lbl.find('i').attr('class') || '') + '').split(/\s+/);
            let icon = 'fa-cart-shopping';
            for (let p = 0; p < parts.length; p += 1) {
                if (
                    parts[p].indexOf('fa-') === 0 &&
                    parts[p] !== 'fa-solid' &&
                    parts[p] !== 'fa-regular' &&
                    parts[p] !== 'fa-light' &&
                    parts[p] !== 'fa-brands'
                ) {
                    icon = parts[p];
                    break;
                }
            }
            return { name: name, icon: icon };
        }

        function openShoppingStoreEditFromHeader(storeId, ev) {
            if (ev) ev.stopPropagation();
            const meta = shoppingReadStorePanelMeta(storeId);
            openShoppingPageRenameStoreModal(storeId, meta.name, meta.icon);
        }

        function shoppingPageStoreDeleteFromModal() {
            const sid = $('#shopping-page-store-id').val();
            if (!sid) return;
            tazrimConfirm({
                title: 'מחיקת חנות',
                message:
                    'האם למחוק את החנות ואת כל המוצרים המשויכים אליה? (ניתן לנהל חנויות גם בניהול הבית.)',
                confirmText: 'מחק',
                cancelText: 'ביטול',
                danger: true,
            }).then(function (ok) {
                if (!ok) return;
                $.post('../app/ajax/delete_shopping_store.php', { id: sid }, function (raw) {
                    const res = typeof raw === 'object' && raw !== null ? raw : JSON.parse(raw);
                    if (res.status === 'success') {
                        closeShoppingPageStoreModal();
                        loadShoppingLists();
                        if (typeof updatePlusMenuUI === 'function') updatePlusMenuUI();
                    } else {
                        tazrimAlert({ title: 'שגיאה', message: res.message || 'מחיקה נכשלה' });
                    }
                }).fail(function () {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
                });
            });
        }

        function selectShoppingStoreTab(storeId) {
            window.shoppingSelectedStoreId = storeId;
            $('#shopping-store-tabs .shopping-tab-chip').removeClass('active');
            $('#shopping-store-tabs .shopping-tab-chip[data-store-id="' + storeId + '"]').addClass('active');
            $('.shopping-panel').removeClass('shopping-panel--active');
            const $p = $('#shopping-panel-' + storeId);
            if ($p.length) $p.addClass('shopping-panel--active');
        }

        function shoppingAppendTabChip(storeId, name, icon) {
            const ic = shoppingEscapeHtml(icon || 'fa-cart-shopping');
            const nm = shoppingEscapeHtml(name);
            const html =
                '<button type="button" class="shopping-tab-chip" data-store-id="' +
                storeId +
                '"><i class="fa-solid ' +
                ic +
                '"></i><span>' +
                nm +
                '</span></button>';
            $('#shopping-store-tabs').append(html);
        }

        function shoppingRemoveTabChip(storeId) {
            $('#shopping-store-tabs .shopping-tab-chip[data-store-id="' + storeId + '"]').remove();
        }

        function buildShoppingTabsFromCategories(cats) {
            let h =
                '<button type="button" class="shopping-tab-chip shopping-tab-add" id="shopping-tab-add" title="חנות חדשה">' +
                '<i class="fa-solid fa-plus"></i><span>חנות</span></button>';
            (cats || []).forEach(function (cat) {
                const id = cat.id;
                const ic = shoppingEscapeHtml(cat.icon || 'fa-cart-shopping');
                const nm = shoppingEscapeHtml(cat.name || '');
                h +=
                    '<button type="button" class="shopping-tab-chip" data-store-id="' +
                    id +
                    '"><i class="fa-solid ' +
                    ic +
                    '"></i><span>' +
                    nm +
                    '</span></button>';
            });
            $('#shopping-store-tabs').html(h);
        }

        function shoppingTryBodyScrollLock() {
            const t = document.getElementById('tazrim-app-dialog');
            const tOpen = t && window.getComputedStyle(t).display !== 'none';
            const m = $('#shopping-page-store-modal');
            const mOpen = m.length && m.is(':visible');
            document.body.classList.toggle('no-scroll', !!(tOpen || mOpen));
        }

        function initShoppingPageIconGrid() {
            const grid = document.getElementById('shopping-page-store-icon-grid');
            if (!grid) return;
            const cur = $('#shopping-page-store-icon').val() || 'fa-cart-shopping';
            let h = '';
            SHOPPING_STORE_ICONS.forEach(function (ic) {
                const cls = ic === cur ? 'selected' : '';
                h +=
                    '<button type="button" class="' +
                    cls +
                    '" data-icon="' +
                    ic +
                    '"><i class="fa-solid ' +
                    ic +
                    '"></i></button>';
            });
            grid.innerHTML = h;
            $(grid)
                .off('click')
                .on('click', 'button', function () {
                    const icon = $(this).data('icon');
                    $('#shopping-page-store-icon').val(icon);
                    $(grid).find('button').removeClass('selected');
                    $(this).addClass('selected');
                });
        }

        function closeShoppingPageStoreModal() {
            $('#shopping-page-store-modal').hide();
            $('#shopping-page-store-msg').hide().text('');
            $('#shopping-page-store-actions-row').removeClass('shopping-page-store-actions-row--edit');
            shoppingTryBodyScrollLock();
        }

        function openShoppingPageAddStoreModal() {
            $('#shopping-page-store-modal-title').text('חנות חדשה');
            $('#shopping-page-store-id').val('');
            $('#shopping-page-store-name').val('');
            $('#shopping-page-store-icon').val('fa-cart-shopping');
            $('#shopping-page-store-icon-block').show();
            $('#shopping-page-store-actions-row').removeClass('shopping-page-store-actions-row--edit');
            initShoppingPageIconGrid();
            $('#shopping-page-store-modal').show();
            shoppingTryBodyScrollLock();
            setTimeout(function () {
                $('#shopping-page-store-name').trigger('focus');
            }, 80);
        }

        function openShoppingPageRenameStoreModal(storeId, name, icon) {
            $('#shopping-page-store-modal-title').text('עריכת חנות');
            $('#shopping-page-store-id').val(String(storeId));
            $('#shopping-page-store-name').val(name || '');
            $('#shopping-page-store-icon').val(icon || 'fa-cart-shopping');
            $('#shopping-page-store-icon-block').hide();
            $('#shopping-page-store-actions-row').addClass('shopping-page-store-actions-row--edit');
            $('#shopping-page-store-modal').show();
            shoppingTryBodyScrollLock();
            setTimeout(function () {
                $('#shopping-page-store-name').trigger('focus');
            }, 80);
        }

        function submitShoppingPageStoreForm() {
            const storeId = $('#shopping-page-store-id').val();
            const storeName = ($('#shopping-page-store-name').val() || '').trim();
            const storeIcon = $('#shopping-page-store-icon').val() || 'fa-cart-shopping';
            const msg = $('#shopping-page-store-msg');
            msg.hide().text('');
            if (!storeName) {
                msg.css({ display: 'block', background: '#fee2e2', color: 'var(--error)' }).text('הזינו שם חנות');
                return;
            }
            const $btn = $('#shopping-page-store-save');
            $btn.prop('disabled', true);
            const payload = { store_name: storeName, store_icon: storeIcon };
            if (storeId) payload.store_id = storeId;
            $.post('../app/ajax/save_shopping_store.php', payload, function (raw) {
                let res = raw;
                if (typeof raw === 'string') {
                    try {
                        res = JSON.parse(raw);
                    } catch (e) {
                        res = {};
                    }
                }
                $btn.prop('disabled', false);
                if (res.status !== 'success') {
                    tazrimAlert({ title: 'שגיאה', message: res.message || 'שמירה נכשלה' });
                    return;
                }
                closeShoppingPageStoreModal();
                if (storeId) {
                    const sid = parseInt(storeId, 10);
                    $('#shopping-panel-' + sid + ' .category-title-label').html(
                        '<i class="fa-solid ' + shoppingEscapeHtml(storeIcon) + '"></i> ' + shoppingEscapeHtml(storeName)
                    );
                    const $tab = $('#shopping-store-tabs .shopping-tab-chip[data-store-id="' + sid + '"] span');
                    if ($tab.length) $tab.text(storeName);
                } else if (res.new_store_id) {
                    const nid = parseInt(res.new_store_id, 10);
                    $('#shopping-tabs-bar').show();
                    openEmptyCategory(nid, storeName, storeIcon, null);
                    selectShoppingStoreTab(nid);
                } else {
                    loadShoppingLists();
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
            });
        }

        function clearEntireList() {
            tazrimConfirm({
                title: 'ניקוי הרשימה',
                message: 'האם למחוק את כל המוצרים ברשימה?',
                confirmText: 'מחק הכל',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;
                const plusWrapper = document.querySelector('.detached-plus-wrapper');
                if (plusWrapper) plusWrapper.classList.remove('open');

                $.post('../app/ajax/clear_shopping_list.php', function(response) {
                    $('.category-block').fadeOut(400, function() {
                        loadShoppingLists();
                    });
                });
            });
        }

        function clearCurrentShoppingList() {
            const selectedStoreId = window.shoppingSelectedStoreId;
            if (!selectedStoreId) {
                tazrimAlert({ title: 'אין רשימה פעילה', message: 'בחרו קודם רשימה כדי למחוק את המוצרים שבה.' });
                return;
            }
            clearShoppingCategory(selectedStoreId);
        }

        function clearShoppingCategory(categoryId, event) {
            if (event) event.stopPropagation();
            tazrimConfirm({
                title: 'ניקוי רשימת החנות',
                message: 'האם למחוק את כל המוצרים בחנות זו בלבד?',
                confirmText: 'נקה',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;
                $.post('../app/ajax/clear_shopping_category.php', { category_id: categoryId }, function(response) {
                    try {
                        const res = (typeof response === 'object' && response !== null) ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            loadShoppingLists(categoryId);
                            if (typeof updatePlusMenuUI === 'function') updatePlusMenuUI();
                        } else {
                            tazrimAlert({ title: 'שגיאה', message: res.message || 'ניקוי נכשל' });
                        }
                    } catch (e) {
                        tazrimAlert({ title: 'שגיאה', message: 'תשובת שרת לא תקינה' });
                    }
                });
            });
        }

        function renderShoppingDeleteMenu() {
            const plusWrapper = document.querySelector('.detached-plus-wrapper');
            if (!plusWrapper) return;
            let submenu = plusWrapper.querySelector('.submenu-popup-container');
            if (!submenu) {
                submenu = document.createElement('div');
                submenu.className = 'submenu-popup-container';
                plusWrapper.appendChild(submenu);
            }
            const hasSelectedStore = !!window.shoppingSelectedStoreId;

            submenu.innerHTML = `
                <a href="javascript:void(0);" onclick="clearCurrentShoppingList()" class="submenu-action-btn danger-solid-btn ${hasSelectedStore ? '' : 'submenu-action-btn--disabled'}">
                    <i class="fa-solid fa-trash"></i> מחיקת הרשימה
                </a>
                <a href="javascript:void(0);" onclick="clearEntireList()" class="submenu-action-btn danger-solid-btn">
                    <i class="fa-solid fa-trash-can"></i> מחיקת כל הרשימות
                </a>
            `;
            plusWrapper.style.display = 'flex';
        }

        function updatePlusMenuUI() {
            renderShoppingDeleteMenu();
        }

        // --- מערכת רשימת הקניות הרגילה ---
        $(document).ready(function() {
            $('#fabToggle').on('click', function(e) { e.stopPropagation(); $('#fabMenu').fadeToggle(200); });
            $(document).on('click', function() { $('#fabMenu').fadeOut(200); });
            $('#fabMenu').on('click', function(e) { e.stopPropagation(); });

            $(document).on('click', '.category-header', function () {
                const $arrow = $(this).find('.toggle-arrow');
                if (!$arrow.length) return;
                const $list = $(this).next('.category-items-list');
                $list.slideToggle(300, function () {
                    if ($list.is(':visible')) $arrow.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    else $arrow.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                });
            });

            $(document).on('click', '#shopping-store-tabs .shopping-tab-add', function (e) {
                e.preventDefault();
                openShoppingPageAddStoreModal();
            });

            $(document).on('click', '#shopping-store-tabs .shopping-tab-chip:not(.shopping-tab-add)', function (e) {
                e.preventDefault();
                const sid = $(this).data('store-id');
                if (sid) selectShoppingStoreTab(sid);
            });

            $(document).on('click', '#shopping-page-store-modal', function (e) {
                if (e.target === this) closeShoppingPageStoreModal();
            });

            // חסימת תווים שאינם מספרים (השדה הפך חזרה ל-number כדי שיהיה אנטר במקלדת)
            $(document).on('input', '.item-qty-input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            $(document).on('blur', '.item-qty-input', function() {
                if (this.value === '' || parseInt(this.value) === 0) {
                    this.value = '1';
                    saveItemToDB($(this).closest('.item-row'));
                }
            });

            $(document).on('keydown', '.item-row input', function(e) {
                const $currentRow = $(this).closest('.item-row');
                if (e.key !== 'Enter') return;
                if ($currentRow.hasClass('ghost-row')) return;

                e.preventDefault();
                if ($(this).hasClass('item-input')) {
                    const $nextRow = $currentRow.next('.item-row');
                    if ($nextRow.length && !$nextRow.hasClass('ghost-row')) {
                        $nextRow.find('.item-input').focus();
                    } else {
                        const $ghost = $currentRow.closest('.category-items-list').find('.ghost-row').first().find('.item-input');
                        if ($ghost.length) $ghost.focus();
                    }
                } else if ($(this).hasClass('item-qty-input')) {
                    const $nextRow = $currentRow.next('.item-row');
                    if ($nextRow.length && !$nextRow.hasClass('ghost-row')) {
                        $nextRow.find('.item-input').focus();
                    } else {
                        const $ghost = $currentRow.closest('.category-items-list').find('.ghost-row').first().find('.item-input');
                        if ($ghost.length) $ghost.focus();
                    }
                }
            });

            // שורת רפאים: אנטר מוסיף מוצר מתחת לשורה הריקה (לא יוצא מהשדה בכל תו)
            $(document).on('keydown', '.ghost-row input', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const $ghost = $(this).closest('.ghost-row');
                const catId = $ghost.data('cat-id');
                const name = $ghost.find('.item-input').val().trim();
                if (!name) return;

                const newRowHtml = buildItemRowHtml({ id: 'new', item_name: name, quantity: '1' }, catId, false);
                $(newRowHtml).insertAfter($ghost).hide().css('opacity', 0).slideDown(220, function() {
                    $(this).animate({ opacity: 1 }, 180);
                });

                $ghost.find('.item-input').val('');
                $ghost.find('.item-qty-input').val('1');

                const $newRow = $ghost.next('.item-row');
                saveItemToDB($newRow);

                setTimeout(function() {
                    $ghost.find('.item-input').focus();
                }, 50);

                updateCategoryHeaderActions(catId);
                updatePlusMenuUI();
            });

            $(document).on('focus', '.active-row .item-input', function() {
                const $row = $(this).closest('.active-row');
                if (!$row.hasClass('pending-delete')) $row.find('.item-checkbox i').removeClass('fa-regular').addClass('fa-solid').css('color', 'var(--main)');
            });

            $(document).on('blur', '.active-row .item-input', function() {
                const $row = $(this).closest('.active-row');
                setTimeout(() => {
                    if ($row.find('.item-input:focus').length === 0 && !$row.hasClass('pending-delete')) {
                        $row.find('.item-checkbox i').removeClass('fa-solid').addClass('fa-regular').css('color', '');
                    }
                }, 10);
            });

            let typingTimer;
            $(document).on('input', '.active-row input', function() {
                clearTimeout(typingTimer);
                const $row = $(this).closest('.active-row');
                typingTimer = setTimeout(function() { saveItemToDB($row); }, 500);
            });

            // מחיקה עם השהיה (ללא העלמת חנות ריקה!)
            $(document).on('click', '.item-checkbox', function() {
                const $row = $(this).closest('.item-row');
                if ($row.hasClass('ghost-row')) return;

                const itemId = $row.data('item-id') || 'new'; 
                const $icon = $(this).find('i');

                if ($row.hasClass('pending-delete')) {
                    clearTimeout($row.data('deleteTimer')); 
                    $row.removeClass('pending-delete');
                    $icon.removeClass('fa-rotate-left fa-solid').addClass('fa-regular fa-circle').css('color', ''); 
                    updateCategoryHeaderActions($row.data('cat-id'));
                } else {
                    $row.addClass('pending-delete');
                    updateCategoryHeaderActions($row.data('cat-id'));
                    $icon.removeClass('fa-circle fa-regular').addClass('fa-solid fa-rotate-left').css('color', ''); 
                    
                    const timer = setTimeout(function() {
                        $row.animate({ opacity: 0, marginRight: -12 }, 280, function() {
                            $(this).slideUp(220, function() {
                                $(this).remove();
                                updatePlusMenuUI();
                            });
                        });
                        if (itemId !== 'new') deleteItemFromDB(itemId);
                    }, 800);
                    $row.data('deleteTimer', timer);
                }
            });

            loadShoppingLists();
            
            // --- מנגנון סנכרון חי (Real-time Feel) ---
            // 1. סנכרון בכל פעם שהמשתמש חוזר לעמוד/פותח את הטלפון מחדש
            document.addEventListener("visibilitychange", function() {
                if (document.visibilityState === 'visible') {
                    silentSyncLists();
                }
            });
            // 2. סנכרון שקט כל כמה שניות אם העמוד פתוח (לא יפריע להקלדה)
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    silentSyncLists();
                }
            }, 4000);
        });

        // סנכרון שקט - מושך נתונים מהשרת ומשלים מה שחסר בלי להרוס את המסך
        function silentSyncLists() {
            $.get('../app/ajax/fetch_shopping_lists.php', function(response) {
                try {
                    const data = (typeof response === 'object' && response !== null) ? response : JSON.parse(response);
                    if(data.status !== 'success') return;

                    if (data.active_categories.length > 0) {
                        $('#empty-state-msg').remove();
                        $('#btnClearAllFAB').fadeIn();
                    }

                    data.active_categories.forEach(cat => {
                        let $catBlock = $(`#cat-block-${cat.id}`);
                        
                        // אם מישהו הוסיף מוצר לחנות שמוסתרת כרגע אצלי - ניצור אותה מחדש
                        if ($catBlock.length === 0) {
                            const inner = buildCategoryBlock(cat, true, true, { tabView: true });
                            const wrapped =
                                '<div class="shopping-panel shopping-panel--active" id="shopping-panel-' +
                                cat.id +
                                '" data-store-id="' +
                                cat.id +
                                '">' +
                                inner +
                                '</div>';
                            $('#shopping-lists-container').append(wrapped);
                            $('#shopping-tabs-bar').show();
                            shoppingEnsureAddTabOnly();
                            shoppingAppendTabChip(cat.id, cat.name, cat.icon || 'fa-cart-shopping');
                            selectShoppingStoreTab(cat.id);
                            $catBlock = $(`#cat-block-${cat.id}`);
                            // מחיקת החנות מתפריט ה"פלוס" אם היא הייתה שם
                            $(`.fab-menu-item[onclick*="${cat.id}"]`).remove();
                        }

                        // עוברים על המוצרים — הוספה/עדכון + הסרת שורות שנמחקו במקום אחר (אפליקציה/מכשיר אחר)
                        if (cat.items) {
                            const serverIds = new Set(cat.items.map(it => String(it.id)));
                            $catBlock.find('.item-row').each(function() {
                                const $row = $(this);
                                if ($row.hasClass('ghost-row')) return;
                                const rawId = $row.data('item-id');
                                if (!rawId || rawId === 'new') return;
                                if (!serverIds.has(String(rawId))) {
                                    $row.slideUp(220, function() {
                                        $(this).remove();
                                        updatePlusMenuUI();
                                        if (typeof updateCategoryHeaderActions === 'function') updateCategoryHeaderActions(cat.id);
                                    });
                                }
                            });
                            cat.items.forEach(item => {
                                let $existingItem = $catBlock.find(`.item-row[data-item-id="${item.id}"]`);
                                
                                if ($existingItem.length === 0) {
                                    const newItemHtml = buildItemRowHtml(item, cat.id, false);
                                    const $g = $catBlock.find('.ghost-row').first();
                                    if ($g.length) {
                                        $(newItemHtml).insertAfter($g).hide().slideDown(280);
                                    } else {
                                        $catBlock.find('.category-items-list').prepend($(newItemHtml).hide().slideDown(280));
                                    }
                                } else {
                                    // המוצר קיים. נעקן כמות/שם רק אם המשתמש לא מצוין עליו כרגע עם העכבר/מקלדת
                                    if ($existingItem.find('input:focus').length === 0 && !$existingItem.hasClass('pending-delete')) {
                                        $existingItem.find('.item-input').val(item.item_name);
                                        $existingItem.find('.item-qty-input').val(item.quantity);
                                    }
                                }
                            });
                            if (typeof updateCategoryHeaderActions === 'function') updateCategoryHeaderActions(cat.id);
                        }
                    });
                } catch (e) {}
            });
        }

        function loadShoppingLists(openCategoryId = null) {
            $.get('../app/ajax/fetch_shopping_lists.php', function (response) {
                try {
                    const data = typeof response === 'object' ? response : JSON.parse(response);
                    if (data.status !== 'success') return;

                    if (data.active_categories.length === 0) {
                        $('#shopping-tabs-bar').show();
                        shoppingEnsureAddTabOnly();
                        $('#shopping-lists-container').html(
                            '<div style="text-align:center; padding: 40px; color:#888;" id="empty-state-msg">' +
                                '<i class="fa-solid fa-basket-shopping fa-2x" style="margin-bottom: 10px; color:#ddd;"></i><br>' +
                                'הרשימה ריקה. אפשר להוסיף חנות חדשה מהלשוניות למעלה.</div>'
                        );
                        window.shoppingSelectedStoreId = null;
                    } else {
                        $('#shopping-tabs-bar').show();
                        buildShoppingTabsFromCategories(data.active_categories);
                        let panelsHtml = '';
                        data.active_categories.forEach(function (cat) {
                            panelsHtml +=
                                '<div class="shopping-panel" id="shopping-panel-' +
                                cat.id +
                                '" data-store-id="' +
                                cat.id +
                                '">' +
                                buildCategoryBlock(cat, true, true, { tabView: true }) +
                                '</div>';
                        });
                        $('#shopping-lists-container').html(panelsHtml);
                        let pick =
                            openCategoryId ||
                            window.shoppingSelectedStoreId ||
                            data.active_categories[0].id;
                        const exists = data.active_categories.some(function (c) {
                            return String(c.id) === String(pick);
                        });
                        if (!exists) pick = data.active_categories[0].id;
                        window.shoppingSelectedStoreId = pick;
                        selectShoppingStoreTab(pick);
                    }

                    const plusWrapper = document.querySelector('.detached-plus-wrapper');
                    if (plusWrapper) {
                        plusWrapper.classList.add('has-submenu');
                        const plusBtn = plusWrapper.querySelector('.plus-btn-detached');
                        if (plusBtn) {
                            plusBtn.onclick = function (e) {
                                e.preventDefault();
                                const popup = plusWrapper.querySelector('.submenu-popup-container');
                                if (typeof calculateAlignment === 'function') calculateAlignment(plusWrapper, popup);
                                plusWrapper.classList.toggle('open');
                            };
                        }
                        renderShoppingDeleteMenu();
                    }
                } catch (e) {
                    console.error('שגיאה בטעינת הרשימה:', e);
                }
            });
        }

// פונקציית עזר קטנה שסוגרת את תפריט הפלוס ומפעילה את הלוגיקה המקורית שלך
function handleEmptyCategoryClick(id, name, icon, element) {
    const plusWrapper = document.querySelector('.detached-plus-wrapper');
    if (plusWrapper) plusWrapper.classList.remove('open');
    
    // קורא לפונקציה המקורית שלך שעושה את ההוספה והפוקוס
    if (typeof openEmptyCategory === "function") {
        openEmptyCategory(id, name, icon, element);
    }
}

        // שינינו ל-type="number" אבל הסרנו את inputmode="numeric" והוספנו enterkeyhint
        function buildItemRowHtml(item, catId, isGhost = false) {
            const rowClass = isGhost ? 'ghost-row' : 'active-row';
            const iconClass = isGhost ? 'fa-solid fa-plus' : 'fa-regular fa-circle'; 
            const iconColor = isGhost ? 'color: #aaa;' : '';
            const placeholderName = isGhost ? 'placeholder="הקלד מוצר…"' : '';
            const idAttr = isGhost ? '' : `data-item-id="${item.id}"`;

            return `
                <div class="item-row ${rowClass}" data-cat-id="${catId}" ${idAttr}>
                    <div class="item-checkbox"><i class="${iconClass}" style="${iconColor}"></i></div>
                    <input type="text" enterkeyhint="next" class="item-input ${isGhost ? 'ghost-name' : ''}" value="${item.item_name}" ${placeholderName}>
                    <input type="number" enterkeyhint="next" class="item-qty-input ${isGhost ? 'ghost-qty' : ''}" value="${item.quantity}">
                </div>
            `;
        }

        function buildCategoryBlock(category, hasItems, isOpen = false, opts) {
            opts = opts || {};
            const tabView = opts.tabView === true;
            let itemsHtml = '';
            let activeItemsCount = 0;

            itemsHtml += buildItemRowHtml({ id: 'new', item_name: '', quantity: '1' }, category.id, true);

            if (hasItems && category.items) {
                category.items.forEach(function (item) {
                    itemsHtml += buildItemRowHtml(item, category.id, false);
                    activeItemsCount++;
                });
            }

            const displayStyle = tabView || isOpen ? '' : 'style="display: none;"';
            const arrowClass = isOpen ? 'fa-chevron-up' : 'fa-chevron-down';
            const arrowHtml = tabView
                ? ''
                : `<i class="fa-solid ${arrowClass} toggle-arrow"></i>`;

            let aiButtonHtml = '';
            if (activeItemsCount >= 3) {
                aiButtonHtml =
                    '<button type="button" class="btn-ai-sort" title="סידור חכם לפי מעברי הסופר" onclick="sortCategoryAI(' +
                    category.id +
                    ', event)">' +
                    '<i class="fa-solid fa-wand-magic-sparkles"></i></button>';
            }

            let clearCatHtml = '';
            if (activeItemsCount > 0) {
                clearCatHtml =
                    '<button type="button" class="btn-clear-category" title="נקה את רשימת החנות" onclick="clearShoppingCategory(' +
                    category.id +
                    ', event)">' +
                    '<i class="fa-solid fa-trash-alt"></i></button>';
            }

            let editStoreHtml = '';
            if (tabView) {
                editStoreHtml =
                    '<button type="button" class="btn-edit-store" title="עריכת חנות" aria-label="עריכת חנות" onclick="openShoppingStoreEditFromHeader(' +
                    category.id +
                    ', event)"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>';
            }

            const ic = shoppingEscapeHtml(String(category.icon || 'fa-cart-shopping').replace(/[^a-z0-9\-]/gi, ''));
            const nm = shoppingEscapeHtml(category.name || '');
            const headCls = 'category-header' + (tabView ? ' category-header--tabs' : '');
            const titleHtml =
                '<div class="category-title-cell">' +
                '<span class="category-title-label"><i class="fa-solid ' +
                ic +
                '"></i> ' +
                nm +
                '</span>' +
                '</div>';

            return (
                '<div class="category-block" id="cat-block-' +
                category.id +
                '">' +
                '<div class="' +
                headCls +
                '">' +
                titleHtml +
                '<div class="shopping-category-header-actions" dir="ltr">' +
                editStoreHtml +
                clearCatHtml +
                aiButtonHtml +
                arrowHtml +
                '</div></div>' +
                '<div class="category-items-list" ' +
                displayStyle +
                '>' +
                itemsHtml +
                '</div></div>'
            );
        }

        function sortCategoryAI(categoryId, event) {
            // עוצר את הלחיצה כדי לא לסגור את הקטגוריה בטעות
            event.stopPropagation();
            
            const $catBlock = $(`#cat-block-${categoryId}`);
            
            // 1. הזרקת שכבת הטעינה המגניבה לתוך הקטגוריה
            $catBlock.append(`
                <div class="ai-overlay" id="ai-overlay-${categoryId}">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>מסדר מדפים…</span>
                </div>
            `);

            // 2. איסוף המידע למשלוח לשרת (רק שמות ו-ID)
            let itemsData = [];
            $catBlock.find('.active-row').not('.pending-delete').each(function() {
                const id = $(this).data('item-id');
                const name = $(this).find('.item-input').val();
                if (id && id !== 'new' && name.trim() !== '') {
                    itemsData.push({ id: id, name: name });
                }
            });

            // 3. שליחה למוח של ה-AI בשרת
            $.post('../app/ajax/ai_sort_category.php', {
                category_id: categoryId,
                items: JSON.stringify(itemsData)
            }, function(response) {
                // הדפסה לקונסול כדי שתוכל לראות הכל מסודר
                console.log("AI Response Object:", response);
                
                try {
                    const res = (typeof response === 'string') ? JSON.parse(response) : response;
                    
                    // הקפצת התשובה הגולמית לבדיקה מהירה
                    if (res.debug_raw) {
                        console.log("Raw AI Text:", res.debug_raw);
                    }

                    if (res.status === 'success') {
                        $(`#ai-overlay-${categoryId} span`).text('סודר בהצלחה!');
                        $(`#ai-overlay-${categoryId} i`).removeClass('fa-wand-magic-sparkles').addClass('fa-check');
                        
                        setTimeout(() => {
                            loadShoppingLists(categoryId); 
                        }, 1000);
                    } else {
                        tazrimAlert({
                            title: 'שגיאת AI',
                            message: 'שגיאת AI: ' + (res.message || '') + (res.debug_raw ? '\n\nתשובת המודל:\n' + res.debug_raw : '')
                        });
                        $(`#ai-overlay-${categoryId}`).fadeOut(300, function() { $(this).remove(); });
                    }
                } catch (e) {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת פענוח בשרת. בדוק את ה-Console.' });
                    console.error("Parse Error:", e, response);
                    $(`#ai-overlay-${categoryId}`).fadeOut(300, function() { $(this).remove(); });
                }
            });
        }

        function shoppingEnsureAddTabOnly() {
            if (!$('#shopping-tab-add').length) {
                $('#shopping-store-tabs').html(
                    '<button type="button" class="shopping-tab-chip shopping-tab-add" id="shopping-tab-add" title="חנות חדשה">' +
                        '<i class="fa-solid fa-plus"></i><span>חנות</span></button>'
                );
            }
        }

        function openEmptyCategory(id, name, icon, btnElement) {
            if ($('#shopping-panel-' + id).length > 0 || $(`#cat-block-${id}`).length > 0) {
                $('#shopping-tabs-bar').show();
                selectShoppingStoreTab(id);
                $(`#cat-block-${id} .ghost-name`).trigger('focus');
                return;
            }
            const fakeCat = { id: id, name: name, icon: icon };
            const inner = buildCategoryBlock(fakeCat, false, true, { tabView: true });
            const wrapped =
                '<div class="shopping-panel" id="shopping-panel-' +
                id +
                '" data-store-id="' +
                id +
                '">' +
                inner +
                '</div>';
            $('#empty-state-msg').remove();
            $('#shopping-lists-container').append(wrapped);
            $('#shopping-tabs-bar').show();
            shoppingEnsureAddTabOnly();
            if (!$('#shopping-store-tabs .shopping-tab-chip[data-store-id="' + id + '"]').length) {
                shoppingAppendTabChip(id, name, icon);
            }
            selectShoppingStoreTab(id);
            $(`#cat-block-${id} .ghost-name`).trigger('focus');
            $(`#plus-cat-${id}`).remove();
            updatePlusMenuUI();
        }

        function saveItemToDB($row) {
            const catId = $row.data('cat-id');
            const itemId = $row.data('item-id') || 'new'; 
            const name = $row.find('.item-input').val();
            let qty = $row.find('.item-qty-input').val(); 
            
            if(name.trim() === '') return;
            if (qty === '' || parseInt(qty) === 0) qty = '1';

            $.post('../app/ajax/save_shopping_item.php', {
                item_id: itemId, category_id: catId, item_name: name, quantity: qty
            }, function(response) {
                try {
                    const res = JSON.parse(response);
                    if (res.status === 'success' && itemId === 'new') {
                        $row.data('item-id', res.new_id);
                        $row.attr('data-item-id', res.new_id); 
                    }
                } catch (e) {}
            });
        }

        function deleteItemFromDB(itemId) {
            if (itemId === 'new') return;
            $.post('../app/ajax/delete_shopping_item.php', { item_id: itemId });
        }

        function updateCategoryHeaderActions(categoryId) {
            const $catBlock = $(`#cat-block-${categoryId}`);
            const activeItemsCount = $catBlock.find('.active-row').not('.pending-delete').length;
            const $actions = $catBlock.find('.shopping-category-header-actions');
            if (!$actions.length) return;

            let $clearBtn = $actions.find('.btn-clear-category');
            if (activeItemsCount > 0) {
                if ($clearBtn.length === 0) {
                    $actions.prepend(`
                        <button type="button" class="btn-clear-category" title="נקה את רשימת החנות" onclick="clearShoppingCategory(${categoryId}, event)">
                            <i class="fa-solid fa-trash-alt"></i>
                        </button>
                    `);
                }
            } else {
                $clearBtn.remove();
            }

            let $aiBtn = $actions.find('.btn-ai-sort');
            if (activeItemsCount >= 3) {
                if ($aiBtn.length === 0) {
                    const aiHtml =
                        '<button type="button" class="btn-ai-sort" title="סידור חכם לפי מעברי הסופר" onclick="sortCategoryAI(' +
                        categoryId +
                        ', event)">' +
                        '<i class="fa-solid fa-wand-magic-sparkles"></i></button>';
                    const $arrow = $actions.find('.toggle-arrow');
                    const $clear = $actions.find('.btn-clear-category');
                    if ($arrow.length) $(aiHtml).insertBefore($arrow);
                    else if ($clear.length) $(aiHtml).insertAfter($clear);
                    else $actions.prepend(aiHtml);
                }
            } else {
                $aiBtn.remove();
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
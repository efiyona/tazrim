<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

$home_id = $_SESSION['home_id'];

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
    <?php if ($is_setup_needed): ?>
    <style>
        /* === עיצוב אשף ההתקנה של רשימת הקניות (בתוך ה-Content Wrapper) === */
        .welcome-card { background: white; width: 100%; max-width: 700px; margin: 20px auto; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        .step { display: none; }
        .step.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .stepper-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 30px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #ddd; }
        .dot.active { background: var(--main); width: 25px; border-radius: 10px; transition: 0.3s; }
        
        .welcome-icon { font-size: 4rem; color: var(--main); margin-bottom: 20px; }
        
        .cat-suggest-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; text-align: center; margin-bottom: 20px; margin-top: 25px; }
        
        .store-option { border: 2px solid #eee; background: #fafafa; border-radius: 15px; padding: 20px 10px; cursor: pointer; transition: 0.2s; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--text-light); }
        .store-option i { font-size: 1.8rem; }
        .store-option span { font-weight: 700; font-size: 0.95rem; }
        
        /* חנות נבחרת */
        .store-option.selected { border-color: var(--main); background: #f0fdf4; color: var(--main); box-shadow: 0 4px 10px rgba(35, 114, 39, 0.1); transform: translateY(-2px); }
        
        .custom-store-wrapper { grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 10px; }
        .custom-store-input { flex: 1; padding: 12px; border: 2px dashed #ccc; border-radius: 12px; font-family: inherit; font-size: 1rem; text-align: right; }
        .custom-store-input:focus { border-color: var(--main); outline: none; border-style: solid; }
        .btn-add-custom { background: var(--gray); color: var(--text); border: none; padding: 0 20px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-add-custom:hover { background: var(--main); color: white; }

        .btn-welcome { background: var(--main); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-welcome:hover { background: var(--main-dark); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(35,114,39,0.3); }
        .btn-welcome:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
    </style>
    <?php endif; ?>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

        <main class="content-wrapper">

            <?php if ($is_setup_needed): ?>
                <div class="welcome-card">
                    <div class="stepper-dots">
                        <div class="dot active" id="dot-1"></div>
                        <div class="dot" id="dot-2"></div>
                    </div>

                    <div class="step active" id="step-1">
                        <div class="welcome-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                        <h1 style="font-weight: 800; margin-bottom: 10px; font-size: 2rem;">רשימת קניות חכמה</h1>
                        <p style="color: #666; line-height: 1.6; margin-bottom: 10px; font-size: 1.1rem;">במקום פתקים בוואטסאפ – רשימה משותפת לכל הבית שמתעדכנת בזמן אמת, מחולקת לפי חנויות ומסתנכרנת ישירות למערכת.</p>
                        <button type="button" class="btn-welcome" onclick="nextStep(2)">בואו נגדיר חנויות <i class="fa-solid fa-arrow-left"></i></button>
                    </div>

                    <div class="step" id="step-2">
                        <h2 style="font-weight: 800; margin-bottom: 5px;">איפה אתם קונים?</h2>
                        <p style="color: #666; font-size: 0.95rem;">בחרו את החנויות שיופיעו ברשימה שלכם (תוכלו לערוך זאת תמיד בהמשך).</p>
                        
                        <div class="cat-suggest-grid" id="stores-grid">
                            <div class="store-option selected" data-icon="fa-cart-shopping" data-name="סופרמרקט">
                                <i class="fa-solid fa-cart-shopping"></i>
                                <span>סופרמרקט</span>
                            </div>
                            <div class="store-option selected" data-icon="fa-leaf" data-name="ירקנייה">
                                <i class="fa-solid fa-leaf"></i>
                                <span>ירקנייה</span>
                            </div>
                            <div class="store-option selected" data-icon="fa-medkit" data-name="פארם">
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
                            <div class="store-option selected" data-icon="fa-box" data-name="שונות">
                                <i class="fa-solid fa-box"></i>
                                <span>שונות</span>
                            </div>
                            
                            <div class="custom-store-wrapper">
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

                <div id="shopping-lists-container">
                    <div style="text-align:center; padding: 40px; color:#888;">
                        <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>טוען רשימות...
                    </div>
                </div>

                <div class="fabs-wrapper">
                    <button class="fab-btn fab-clear" id="btnClearAllFAB" title="נקה את כל הרשימה">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>

                    <div class="fab-container">
                        <div class="fab-menu" id="fabMenu"></div>
                        <button class="fab-btn" id="fabToggle">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <?php if ($is_setup_needed): ?>
    <script>
        // --- לוגיקת האשף (Wizard) ---
        function nextStep(stepNum) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            
            document.querySelectorAll('.dot').forEach(d => d.classList.remove('active'));
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
                msgBox.html('אנא בחרו לפחות חנות אחת כדי להתחיל.').css({'display': 'block', 'background': '#fee2e2', 'color': 'var(--error)'});
                return;
            }

            const btn = $('#finish-setup-btn');
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> מכין את הרשימות...');
            msgBox.hide();

            $.post('../app/ajax/setup_shopping_categories.php', { categories: selectedStores }, function(response) {
                if(response.status === 'success') {
                    // מרענן את הדף, מה שיגרום לו לטעון את המערכת הרגילה!
                    window.location.reload();
                } else {
                    msgBox.html(response.message).css({'display': 'block', 'background': '#fee2e2', 'color': 'var(--error)'});
                    btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> יצירת רשימה!');
                }
            });
        }
    </script>
    <?php else: ?>
    <script>
        // --- מערכת רשימת הקניות הרגילה ---
        $(document).ready(function() {
            $('#fabToggle').on('click', function(e) { e.stopPropagation(); $('#fabMenu').fadeToggle(200); });
            $(document).on('click', function() { $('#fabMenu').fadeOut(200); });
            $('#fabMenu').on('click', function(e) { e.stopPropagation(); });

            $(document).on('click', '.category-header', function() {
                let $list = $(this).next('.category-items-list');
                let $arrow = $(this).find('.toggle-arrow');
                $list.slideToggle(300, function() {
                    if ($list.is(':visible')) $arrow.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    else $arrow.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                });
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
                if (e.key === 'Enter') {
                    e.preventDefault(); 
                    if ($(this).hasClass('item-input')) $currentRow.find('.item-qty-input').focus().select();
                    else if ($(this).hasClass('item-qty-input')) {
                        const $nextRow = $currentRow.next('.item-row');
                        if ($nextRow.length) $nextRow.find('.item-input').focus();
                    }
                }
            });

            $(document).on('input', '.ghost-row input', function() {
                const $ghostRow = $(this).closest('.ghost-row');
                const catId = $ghostRow.data('cat-id');

                $ghostRow.removeClass('ghost-row').addClass('active-row');
                $ghostRow.find('.item-checkbox i').removeClass('fa-plus fa-regular').addClass('fa-solid fa-circle').css('color', 'var(--main)');
                $ghostRow.find('.item-input').removeAttr('placeholder');
                
                const newGhost = buildItemRowHtml({ id: 'new', item_name: '', quantity: '1' }, catId, true);
                $ghostRow.parent().append(newGhost);
            });

            $(document).on('focus', '.active-row input', function() {
                const $row = $(this).closest('.active-row');
                if (!$row.hasClass('pending-delete')) $row.find('.item-checkbox i').removeClass('fa-regular').addClass('fa-solid').css('color', 'var(--main)');
            });

            $(document).on('blur', '.active-row input', function() {
                const $row = $(this).closest('.active-row');
                setTimeout(() => {
                    if ($row.find('input:focus').length === 0 && !$row.hasClass('pending-delete')) {
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
                } else {
                    $row.addClass('pending-delete');
                    $icon.removeClass('fa-circle fa-regular').addClass('fa-solid fa-rotate-left').css('color', ''); 
                    
                    const timer = setTimeout(function() {
                        $row.slideUp(300, function() {
                            $(this).remove();
                            // הסרנו את הקוד שהעלים את החנות עצמה! עכשיו החנות תישאר גלויה וריקה.
                        });
                        if (itemId !== 'new') deleteItemFromDB(itemId);
                    }, 800);
                    $row.data('deleteTimer', timer);
                }
            });

            $('#btnClearAllFAB').on('click', function() {
                if(confirm('האם למחוק את כל המוצרים ברשימה?')) {
                    const btn = $(this);
                    btn.html('<i class="fa-solid fa-spinner fa-spin"></i>');
                    $.post('../app/ajax/clear_shopping_list.php', function(response) {
                        $('.category-block').fadeOut(400, function() {
                            loadShoppingLists(); 
                            btn.html('<i class="fa-solid fa-trash-can"></i>'); 
                        });
                    });
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
            // 2. סנכרון שקט כל 15 שניות אם העמוד פתוח (לא יפריע להקלדה)
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    silentSyncLists();
                }
            }, 15000);
        });

        // סנכרון שקט - מושך נתונים מהשרת ומשלים מה שחסר בלי להרוס את המסך
        function silentSyncLists() {
            $.get('../app/ajax/fetch_shopping_lists.php', function(response) {
                try {
                    const data = JSON.parse(response);
                    if(data.status !== 'success') return;

                    if (data.active_categories.length > 0) {
                        $('#empty-state-msg').remove();
                        $('#btnClearAllFAB').fadeIn();
                    }

                    data.active_categories.forEach(cat => {
                        let $catBlock = $(`#cat-block-${cat.id}`);
                        
                        // אם מישהו הוסיף מוצר לחנות שמוסתרת כרגע אצלי - ניצור אותה מחדש
                        if ($catBlock.length === 0) {
                            const newCatHtml = buildCategoryBlock(cat, true, true);
                            $('#shopping-lists-container').append(newCatHtml);
                            $catBlock = $(`#cat-block-${cat.id}`);
                            // מחיקת החנות מתפריט ה"פלוס" אם היא הייתה שם
                            $(`.fab-menu-item[onclick*="${cat.id}"]`).remove();
                        }

                        // עוברים על המוצרים ובודקים אם יש משהו חדש שצריך להזריק
                        if (cat.items) {
                            cat.items.forEach(item => {
                                let $existingItem = $catBlock.find(`.item-row[data-item-id="${item.id}"]`);
                                
                                if ($existingItem.length === 0) {
                                    // מוצר חדש לגמרי! נוסיף אותו רגע לפני השורת רפאים של אותה חנות
                                    const newItemHtml = buildItemRowHtml(item, cat.id, false);
                                    $(newItemHtml).insertBefore($catBlock.find('.ghost-row')).hide().slideDown(300);
                                } else {
                                    // המוצר קיים. נעקן כמות/שם רק אם המשתמש לא מצוין עליו כרגע עם העכבר/מקלדת
                                    if ($existingItem.find('input:focus').length === 0 && !$existingItem.hasClass('pending-delete')) {
                                        $existingItem.find('.item-input').val(item.item_name);
                                        $existingItem.find('.item-qty-input').val(item.quantity);
                                    }
                                }
                            });
                        }
                    });
                } catch (e) {}
            });
        }

        function loadShoppingLists() {
            $.get('../app/ajax/fetch_shopping_lists.php', function(response) {
                try {
                    const data = JSON.parse(response);
                    if(data.status !== 'success') return;

                    let listsHtml = ''; let fabHtml = '';

                    if (data.active_categories.length === 0) {
                        listsHtml = `<div style="text-align:center; padding: 40px; color:#888;" id="empty-state-msg">
                            <i class="fa-solid fa-basket-shopping fa-2x" style="margin-bottom: 10px; color:#ddd;"></i><br>
                            הרשימה ריקה! לחצו על ה- + למטה כדי להתחיל.
                        </div>`;
                        $('#btnClearAllFAB').hide(); 
                    } else {
                        data.active_categories.forEach(cat => {
                            listsHtml += buildCategoryBlock(cat, true, false);
                        });
                        $('#btnClearAllFAB').fadeIn(); 
                    }
                    $('#shopping-lists-container').html(listsHtml);

                    if (data.empty_categories.length === 0) {
                        fabHtml = `<div class="fab-menu-item" style="color:#aaa; cursor:default; justify-content:center;">אין חנויות מוגדרות</div>`;
                    } else {
                        data.empty_categories.forEach(cat => {
                            fabHtml += `<div class="fab-menu-item" onclick="openEmptyCategory(${cat.id}, '${cat.name}', '${cat.icon}', this)"><i class="fa-solid ${cat.icon}"></i> <span>${cat.name}</span></div>`;
                        });
                    }
                    $('#fabMenu').html(fabHtml);
                } catch (e) { console.error("שגיאה:", e); }
            });
        }

        // שינינו ל-type="number" אבל הסרנו את inputmode="numeric" והוספנו enterkeyhint
        function buildItemRowHtml(item, catId, isGhost = false) {
            const rowClass = isGhost ? 'ghost-row' : 'active-row';
            const iconClass = isGhost ? 'fa-solid fa-plus' : 'fa-regular fa-circle'; 
            const iconColor = isGhost ? 'color: #aaa;' : '';
            const placeholderName = isGhost ? 'placeholder="הקלד מוצר..."' : '';
            const idAttr = isGhost ? '' : `data-item-id="${item.id}"`;

            return `
                <div class="item-row ${rowClass}" data-cat-id="${catId}" ${idAttr}>
                    <div class="item-checkbox"><i class="${iconClass}" style="${iconColor}"></i></div>
                    <input type="text" enterkeyhint="next" class="item-input ${isGhost ? 'ghost-name' : ''}" value="${item.item_name}" ${placeholderName}>
                    <input type="number" enterkeyhint="next" class="item-qty-input ${isGhost ? 'ghost-qty' : ''}" value="${item.quantity}">
                </div>
            `;
        }

        function buildCategoryBlock(category, hasItems, isOpen = false) {
            let itemsHtml = '';
            if (hasItems && category.items) {
                category.items.forEach(item => { itemsHtml += buildItemRowHtml(item, category.id, false); });
            }
            itemsHtml += buildItemRowHtml({ id: 'new', item_name: '', quantity: '1' }, category.id, true);

            const displayStyle = isOpen ? '' : 'style="display: none;"';
            const arrowClass = isOpen ? 'fa-chevron-up' : 'fa-chevron-down';

            return `
                <div class="category-block" id="cat-block-${category.id}">
                    <div class="category-header">
                        <div><i class="fa-solid ${category.icon}"></i> ${category.name}</div>
                        <i class="fa-solid ${arrowClass} toggle-arrow"></i>
                    </div>
                    <div class="category-items-list" ${displayStyle}>${itemsHtml}</div>
                </div>
            `;
        }

        function openEmptyCategory(id, name, icon, btnElement) {
            if ($(`#cat-block-${id}`).length > 0) {
                $(`#cat-block-${id} .ghost-name`).focus();
                $('#fabMenu').fadeOut(200); return;
            }
            const fakeCat = { id: id, name: name, icon: icon };
            const newBlockHtml = buildCategoryBlock(fakeCat, false, true);
            
            $('#empty-state-msg').remove();
            $('#shopping-lists-container').append(newBlockHtml);
            $('#btnClearAllFAB').fadeIn(); 
            
            $(btnElement).remove();
            
            if($('#fabMenu').children().length === 0) {
                $('#fabMenu').html(`<div class="fab-menu-item" style="color:#aaa; cursor:default; justify-content:center;">אין חנויות מוגדרות</div>`);
            }
            $('#fabMenu').fadeOut(200);
            $(`#cat-block-${id} .ghost-name`).focus();
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
    </script>
    <?php endif; ?>
</body>
</html>
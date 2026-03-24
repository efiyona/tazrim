<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

$home_id = $_SESSION['home_id'];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>רשימת קניות | התזרים</title>
    <style>
        /* עיצוב כללי לדף הקניות */
        .shopping-header {
            margin-bottom: 20px;
        }
        
        /* עיצוב הבלוק של קטגוריה (חנות) */
        .category-block {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--main);
            cursor: pointer;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 10px;
        }

        /* שורות הפריטים ושורת הרפאים */
        .item-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }

        .item-checkbox {
            color: #ccc;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .item-input {
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            width: 100%;
        }

        .item-qty-input {
            border: none;
            background: #f9fafb;
            border-radius: 6px;
            padding: 4px 8px;
            width: 60px;
            text-align: center;
            font-family: inherit;
            outline: none;
        }

        /* שורת רפאים - שקיפות חלקית */
        .ghost-row {
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        
        .ghost-row:focus-within {
            opacity: 1;
        }

        /* הכפתור הצף (FAB) */
        .fab-container {
            position: fixed;
            bottom: 30px;
            left: 30px; /* בצד שמאל כמו בוואטסאפ (בעברית) */
            z-index: 1000;
        }

        .fab-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--main);
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fab-btn:active {
            transform: scale(0.95);
        }

        /* תפריט הכפתור הצף המעודכן */
        .fab-menu {
            position: absolute;
            bottom: 75px;
            left: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            padding: 8px;
            width: 180px;
            display: none;
            flex-direction: column;
            border: 1px solid #f0f0f0;
        }

        .fab-menu-item {
            padding: 12px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #444;
            border-radius: 10px;
            transition: all 0.2s;
            font-weight: 600;
        }

        .fab-menu-item i {
            font-size: 1.2rem;
            color: var(--main);
            width: 20px;
            text-align: center;
        }

        .fab-menu-item:hover {
            background: #f0fdf4;
            color: var(--main);
            transform: translateX(-5px);
        }

        /* === מנגנון מחיקה בהשהיה === */
        .item-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
            transition: all 0.3s ease;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }

        .item-checkbox {
            color: #ccc;
            font-size: 1.2rem;
            cursor: pointer;
            width: 30px;
            text-align: center;
            transition: 0.2s;
        }

        /* מצב המתנה למחיקה (המוצר מסומן) */
        .pending-delete {
            opacity: 0.4;
            transform: scale(0.98);
        }
        
        .pending-delete .item-input, 
        .pending-delete .item-qty-input {
            text-decoration: line-through;
            color: #aaa;
        }
        
        .pending-delete .item-checkbox {
            color: #ef4444; /* צבע אדום לכפתור הביטול */
        }
    </style>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

        <main class="content-wrapper">
            
            <div class="page-header-actions" style="margin-bottom: 20px;">
                <h1 class="section-title" style="margin-bottom: 0;">רשימת קניות</h1>
                <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 5px;">מה חסר בבית?</p>
            </div>

            <div id="shopping-lists-container">
                <div style="text-align:center; padding: 40px; color:#888;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>טוען רשימות...
                </div>
            </div>

            <div class="fab-container">
                <div class="fab-menu" id="fabMenu"></div>
                <button class="fab-btn" id="fabToggle">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>

        </main>

    </div>

</body>

<script>
    $(document).ready(function() {
        // --- פתיחה וסגירה של תפריט הכפתור הצף ---
        $('#fabToggle').on('click', function(e) {
            e.stopPropagation();
            $('#fabMenu').fadeToggle(200);
        });

        $(document).on('click', function() { $('#fabMenu').fadeOut(200); });
        $('#fabMenu').on('click', function(e) { e.stopPropagation(); });

        // --- פתיחה וסגירה של קטגוריה ---
        $(document).on('click', '.category-header', function() {
            let $list = $(this).next('.category-items-list');
            let $arrow = $(this).find('.toggle-arrow');
            $list.slideToggle(300, function() {
                if ($list.is(':visible')) {
                    $arrow.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                } else {
                    $arrow.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                }
            });
        });

        // --- לוגיקת מקלדת ו-Enter ---
        $(document).on('keydown', '.item-row input', function(e) {
            const $currentRow = $(this).closest('.item-row');
            if (e.key === 'Enter') {
                e.preventDefault(); 
                if ($(this).hasClass('item-input')) {
                    $currentRow.find('.item-qty-input').focus().select();
                } else if ($(this).hasClass('item-qty-input')) {
                    const $nextRow = $currentRow.next('.item-row');
                    if ($nextRow.length) $nextRow.find('.item-input').focus();
                }
            }
        });

        // --- יצירת שורת רפאים חדשה בהקלדה ---
        $(document).on('input', '.ghost-row input', function() {
            const $ghostRow = $(this).closest('.ghost-row');
            const catId = $ghostRow.data('cat-id');

            $ghostRow.removeClass('ghost-row').addClass('active-row');
            $ghostRow.find('.item-checkbox i').removeClass('fa-plus').addClass('fa-circle');
            
            const newGhost = buildItemRowHtml({ id: 'new', item_name: '', quantity: '1' }, catId, true);
            $ghostRow.parent().append(newGhost);
        });

        // --- שמירה אוטומטית ב-DB בהקלדה (עריכת כמות/שם) ---
        let typingTimer;
        $(document).on('input', '.active-row input', function() {
            clearTimeout(typingTimer);
            const $row = $(this).closest('.active-row');
            typingTimer = setTimeout(function() { saveItemToDB($row); }, 500);
        });

        // === חדש: מנגנון מחיקה עם השהיה (Undo) - תוקן! ===
        $(document).on('click', '.item-checkbox', function() {
            const $row = $(this).closest('.item-row');
            if ($row.hasClass('ghost-row')) return; // לא עושים כלום בשורת רפאים

            const itemId = $row.data('item-id') || 'new'; // מזהה למסד הנתונים
            const $icon = $(this).find('i');

            if ($row.hasClass('pending-delete')) {
                // --- המשתמש התחרט (ביטול מחיקה) ---
                clearTimeout($row.data('deleteTimer')); // עוצרים את הטיימר ששמור על השורה
                
                $row.removeClass('pending-delete');
                $icon.removeClass('fa-rotate-left').addClass('fa-circle'); // מחזיר לעיגול ריק
            } else {
                // --- התחלת תהליך מחיקה ---
                $row.addClass('pending-delete');
                $icon.removeClass('fa-circle').addClass('fa-rotate-left'); // משנה לאייקון "חזור/בטל"
                
                // מתחיל ספירה לאחור של 1.5 שניות ושומר את הטיימר על השורה עצמה
                const timer = setTimeout(function() {
                    // אנימציית העלמה
                    $row.slideUp(300, function() {
                        const catId = $row.data('cat-id');
                        $(this).remove();
                        
                        // אם החנות התרוקנה לגמרי (נשארה רק שורת רפאים), מעלימים גם אותה
                        const $catBlock = $(`#cat-block-${catId}`);
                        if ($catBlock.find('.active-row').length === 0) {
                            $catBlock.fadeOut(300, function() { $(this).remove(); });
                        }
                    });
                    
                    // קריאה לשרת למחיקת הפריט סופית
                    if (itemId !== 'new') {
                        deleteItemFromDB(itemId);
                    }
                }, 1500);

                // שומרים את מזהה הטיימר בתוך האלמנט של השורה
                $row.data('deleteTimer', timer);
            }
        });

        loadShoppingLists();
    });

    // --- טעינת הרשימות מהשרת ---
    function loadShoppingLists() {
        $.get('../app/ajax/fetch_shopping_lists.php', function(response) {
            try {
                const data = JSON.parse(response);
                if(data.status !== 'success') return;

                let listsHtml = '';
                let fabHtml = '';

                if (data.active_categories.length === 0) {
                    listsHtml = `<div style="text-align:center; padding: 40px; color:#888;">
                        <i class="fa-solid fa-basket-shopping fa-2x" style="margin-bottom: 10px; color:#ddd;"></i><br>
                        הרשימה ריקה! לחצו על ה- + למטה כדי להתחיל.
                    </div>`;
                } else {
                    data.active_categories.forEach(cat => {
                        listsHtml += buildCategoryBlock(cat, true);
                    });
                }
                $('#shopping-lists-container').html(listsHtml);

                if (data.empty_categories.length === 0) {
                    fabHtml = `<div class="fab-menu-item" style="color:#aaa; cursor:default; justify-content:center;">כל החנויות פתוחות</div>`;
                } else {
                    data.empty_categories.forEach(cat => {
                        fabHtml += `<div class="fab-menu-item" onclick="openEmptyCategory(${cat.id}, '${cat.name}', '${cat.icon}', this)"><i class="fa-solid ${cat.icon}"></i> <span>${cat.name}</span></div>`;
                    });
                }
                $('#fabMenu').html(fabHtml);
            } catch (e) { console.error("שגיאה:", e); }
        });
    }

    // --- בניית ה-HTML של שורה בודדת (ללא ה-Swipe) ---
    function buildItemRowHtml(item, catId, isGhost = false) {
        const rowClass = isGhost ? 'ghost-row' : 'active-row';
        const iconClass = isGhost ? 'fa-solid fa-plus' : 'fa-regular fa-circle';
        const iconColor = isGhost ? 'color: #aaa;' : '';
        const placeholderName = isGhost ? 'placeholder="הקלד מוצר..."' : '';
        const idAttr = isGhost ? '' : `data-item-id="${item.id}"`;

        return `
            <div class="item-row ${rowClass}" data-cat-id="${catId}" ${idAttr}>
                <div class="item-checkbox"><i class="${iconClass}" style="${iconColor}"></i></div>
                <input type="text" class="item-input ${isGhost ? 'ghost-name' : ''}" value="${item.item_name}" ${placeholderName}>
                <input type="number" inputmode="numeric" pattern="[0-9]*" min="1" class="item-qty-input ${isGhost ? 'ghost-qty' : ''}" value="${item.quantity}">
            </div>
        `;
    }

    // --- בניית בלוק החנות ---
    function buildCategoryBlock(category, hasItems) {
        let itemsHtml = '';
        if (hasItems && category.items) {
            category.items.forEach(item => {
                itemsHtml += buildItemRowHtml(item, category.id, false);
            });
        }
        
        itemsHtml += buildItemRowHtml({ id: 'new', item_name: '', quantity: '1' }, category.id, true);

        return `
            <div class="category-block" id="cat-block-${category.id}">
                <div class="category-header">
                    <div><i class="fa-solid ${category.icon}"></i> ${category.name}</div>
                    <i class="fa-solid fa-chevron-up toggle-arrow"></i>
                </div>
                <div class="category-items-list">${itemsHtml}</div>
            </div>
        `;
    }

    function openEmptyCategory(id, name, icon, btnElement) {
        if ($(`#cat-block-${id}`).length > 0) {
            $(`#cat-block-${id} .ghost-name`).focus();
            $('#fabMenu').fadeOut(200); return;
        }
        const fakeCat = { id: id, name: name, icon: icon };
        const newBlockHtml = buildCategoryBlock(fakeCat, false);
        
        if ($('#shopping-lists-container').text().includes('הרשימה ריקה')) $('#shopping-lists-container').html('');
        $('#shopping-lists-container').append(newBlockHtml);
        $(btnElement).remove();
        
        if($('#fabMenu').children().length === 0) {
            $('#fabMenu').html(`<div class="fab-menu-item" style="color:#aaa; cursor:default; justify-content:center;">כל החנויות פתוחות</div>`);
        }
        $('#fabMenu').fadeOut(200);
        $(`#cat-block-${id} .ghost-name`).focus();
    }

    // --- שמירת פריט ב-DB ---
    // --- שמירת פריט ב-DB (הוספה או עדכון) ---
    function saveItemToDB($row) {
        const catId = $row.data('cat-id');
        const itemId = $row.data('item-id') || 'new'; 
        const name = $row.find('.item-input').val();
        const qty = $row.find('.item-qty-input').val();
        
        if(name.trim() === '') return;

        // שליחת הנתונים לקובץ ה-PHP שלנו
        $.post('../app/ajax/save_shopping_item.php', {
            item_id: itemId,
            category_id: catId,
            item_name: name,
            quantity: qty
        }, function(response) {
            try {
                const res = JSON.parse(response);
                // אם זה מוצר חדש שנוצר עכשיו - אנחנו חייבים לשמור את ה-ID שלו ב-HTML
                // כדי שאם המשתמש ירצה למחוק אותו מיד, נדע איזה ID למחוק!
                if (res.status === 'success' && itemId === 'new') {
                    $row.data('item-id', res.new_id);
                    $row.attr('data-item-id', res.new_id); // מעדכן גם ויזואלית
                }
            } catch (e) {
                console.error("שגיאה בפיענוח התשובה מהשרת:", e);
            }
        });
    }

    // --- מחיקת פריט סופית מה-DB (העברה לארכיון) ---
    function deleteItemFromDB(itemId) {
        if (itemId === 'new') return; // לא נשמר מעולם, אין מה למחוק מהשרת
        
        $.post('../app/ajax/delete_shopping_item.php', {
            item_id: itemId
        }, function(response) {
            // בוצע בהצלחה (הפריט כבר נעלם מהמסך בזכות האנימציה)
            console.log("פריט הועבר לארכיון:", itemId);
        });
    }
</script>
</html>
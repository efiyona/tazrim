<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

require_once ROOT_PATH . '/assets/includes/user_css_href.php';
require_once ROOT_PATH . '/assets/includes/pwa_no_cache_headers.php';

$home_id = $_SESSION['home_id'];
$user_id = $_SESSION['id'];

// 1. שליפת פרטי הבית
$home_data = selectOne('homes', ['id' => $home_id]);
global $today_il;
$today_for = isset($today_il) ? (string) $today_il : date('Y-m-d');
$display_parts = tazrim_home_display_bank_balance($conn, (int) $home_id, $today_for);
$display_balance = $display_parts['display'];
$show_bank_balance = (int) ($home_data['show_bank_balance'] ?? 0);

// בניית הודעת הוואטסאפ וקידוד הקישור
$join_code = $home_data['join_code'];
$whatsapp_text = "היי! הוזמנת לנהל איתי את התקציב של בית '" . $home_data['name'] . "' ב'התזרים' 🏠" . "\n\n" . "קוד ההצטרפות שלנו הוא: " . $join_code . "\n" . "להצטרפות מהירה: " . BASE_URL . "pages/register.php?join_code=" . $join_code;
$whatsapp_url = "https://api.whatsapp.com/send?text=" . urlencode($whatsapp_text);

// 2. שליפת קטגוריות (מחולקות להוצאות והכנסות)
$categories_query = "SELECT * FROM categories WHERE home_id = $home_id AND is_active = 1 ORDER BY type ASC, name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$expenses_cats = [];
$income_cats = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    if ($cat['type'] == 'expense') {
        $expenses_cats[] = $cat;
    } else {
        $income_cats[] = $cat;
    }
}
$categories_array = array_merge($expenses_cats, $income_cats);

// 3. שליפת פעולות קבועות פעילות
$recurring_query = "SELECT r.*, c.name as cat_name, c.icon as cat_icon 
                    FROM recurring_transactions r 
                    LEFT JOIN categories c ON r.category = c.id 
                    WHERE r.home_id = $home_id AND r.is_active = 1 
                    ORDER BY r.day_of_month ASC";
$recurring_result = mysqli_query($conn, $recurring_query);
$recurring_expenses = [];
$recurring_income = [];
if ($recurring_result) {
    while ($rec = mysqli_fetch_assoc($recurring_result)) {
        if ($rec['type'] === 'expense') {
            $recurring_expenses[] = $rec;
        } else {
            $recurring_income[] = $rec;
        }
    }
}

// 4. שליפת חנויות רשימת קניות
$shopping_stores_query = "SELECT id, name, icon, sort_order FROM shopping_categories WHERE home_id = $home_id ORDER BY sort_order ASC, id ASC";
$shopping_stores_result = mysqli_query($conn, $shopping_stores_query);
$shopping_stores = [];
if ($shopping_stores_result) {
    while ($store = mysqli_fetch_assoc($shopping_stores_result)) {
        $shopping_stores[] = $store;
    }
}

// 5. שליפת כל המשתמשים השייכים לבית זה
$members_query = "SELECT first_name, nickname, role, email FROM users WHERE home_id = $home_id ORDER BY (role IN ('admin','home_admin','program_admin')) DESC, first_name ASC";
$members_result = mysqli_query($conn, $members_query);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול הבית | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_user_css_href(), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo BASE_URL; ?>assets/js/tazrim_dialogs.js" defer></script>
    <script src="<?php echo BASE_URL; ?>assets/js/global_modals.js" defer></script>

</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">
                
                <div class="page-header-actions" style="margin-bottom: 25px;">
                    <h1 class="section-title" style="margin-bottom: 0;">ניהול הבית</h1>
                    <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 5px;">הגדרות והעדפות</p>
                </div>

                <div class="management-grid">
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>פרטי הבית</h3>
                        </div>

                        <div class="card-body-padding">
                            <div class="management-block">
                                <span class="block-label">קוד הצטרפות לבית:</span>
                                <div class="join-row">
                                    <span class="join-code-v2"><?php echo $home_data['join_code']; ?></span>
                                    <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn-whatsapp-minimal">
                                        <i class="fa-brands fa-whatsapp"></i> שליחה בוואטסאפ
                                    </a>
                                </div>
                                <p class="block-help">שלח את הקוד הזה לשותפים כדי שיצטרפו לבית שלך.</p>
                            </div>

                            <hr class="management-divider">

                            <div class="management-block">
                                <span class="block-label">תקנון ומדיניות פרטיות:</span>
                                <div class="join-row">
                                    <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/accept_tos.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-whatsapp-minimal" style="text-decoration: none;">
                                        <i class="fa-solid fa-file-lines"></i> צפייה בתקנון
                                    </a>
                                </div>
                                <p class="block-help">כאן אפשר לצפות בנוסח התקנון האחרון שאישרתם.</p>
                            </div>

                            <hr class="management-divider">

                            <form id="update-home-form">
                                <div class="input-group">
                                    <label>שם הבית</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-house"></i>
                                        <input type="text" name="home_name" value="<?php echo htmlspecialchars($home_data['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label>יתרת בנק מוצגת (₪) — ליישור מול הבנק</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-building-columns"></i>
                                        <input type="number" step="0.01" name="bank_balance_display" value="<?php echo htmlspecialchars((string) $display_balance, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <p class="block-help" style="margin-top: 8px;">השאר ריק אם ברצונך לעדכן רק שם בית או הצגה — בלי לשנות את יישור היתרה.</p>
                                </div>
                                <div class="input-group">
                                    <label class="checkbox-container" style="font-size: 0.95rem; font-weight: 600;">
                                        <input type="checkbox" id="show_bank_balance_cb" name="show_bank_balance" value="1" <?php echo $show_bank_balance ? 'checked' : ''; ?>>
                                        הצג כרטיס &quot;יתרה בחשבון&quot; בדף הבית ובדוחות
                                    </label>
                                </div>
                                <div class="input-group">
                                    <button type="button" class="btn-secondary" id="btn-reset-bank-balance" onclick="resetHomeBankBalance()" style="width: 100%;">
                                        <i class="fa-solid fa-rotate-left"></i> איפוס יתרה (מאפס יישור ויתרה מחושבת)
                                    </button>
                                    <p class="block-help" style="margin-top: 8px;">לאחר מחיקת כל התנועות, אפשר לאפס כאן כדי למנוע &quot;יתרת רפאים&quot;.</p>
                                </div>
                                <div id="home-msg" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: 600; text-align: center;"></div>
                                <button type="button" id="btn-update-home" class="btn-primary" onclick="updateHomeDetails()">שמור שינויים</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>משתתפים</h3>
                        </div>
                        <div class="members-list" style="margin-top: 10px;">
                            <?php while ($member = mysqli_fetch_assoc($members_result)): 
                                $is_admin = in_array($member['role'], ['admin', 'home_admin', 'program_admin'], true);
                                $displayName = $member['first_name'];
                                $initial = mb_substr($displayName, 0, 1, 'utf-8');
                            ?>
                                <div class="member-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid #f0f0f0; transition: 0.2s;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 35px; height: 35px; border-radius: 50%; background: <?php echo $is_admin ? 'var(--main-light)' : '#f3f4f6'; ?>; color: <?php echo $is_admin ? 'var(--main)' : '#666'; ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                            <?php echo $initial; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: var(--text); font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($displayName); ?>
                                                <?php if ($member['email'] == $_SESSION['email'] ?? ''): ?>
                                                    <span style="font-weight: normal; font-size: 0.75rem; color: #888; margin-right: 5px;">(אתה)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($member['email']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <span style="font-size: 0.7rem; background: #f3f4f6; color: #666; padding: 3px 8px; border-radius: 12px; font-weight: 600;"><?php echo $member['nickname']; ?></span>

                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="card full-width-card">
                        <div id="manage-home-recurring-panel">
                            <?php include ROOT_PATH . '/app/includes/partials/manage_home_recurring_panel.php'; ?>
                        </div>
                    </div>

                    <div class="card full-width-card">
                        <div id="manage-home-categories-panel">
                            <?php include ROOT_PATH . '/app/includes/partials/manage_home_categories_panel.php'; ?>
                        </div>
                    </div>

                    <div class="card full-width-card">
                        <div id="manage-home-shopping-stores-panel">
                            <?php include ROOT_PATH . '/app/includes/partials/manage_home_shopping_stores_panel.php'; ?>
                        </div>
                    </div>

                </div>
            </div>
            </main>
            </div>

    <div id="recurring-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="rec-modal-title">הוספת פעולה חדשה</h3>
                <button type="button" onclick="closeRecurringModal()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <form id="recurring-form" class="form-fields-pill">
                    <input type="hidden" name="recurring_id" id="recurring-id" value="">

                    <div class="modern-toggle" id="rec-type-toggle">
                        <input type="radio" name="rec_type" id="type-expense" value="expense" checked onchange="filterRecurringCategories()">
                        <label for="type-expense" class="toggle-option expense">הוצאה</label>

                        <input type="radio" name="rec_type" id="type-income" value="income" onchange="filterRecurringCategories()">
                        <label for="type-income" class="toggle-option income">הכנסה</label>
                    </div>

                    <div class="input-group">
                        <label>תיאור הפעולה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" name="rec_description" id="rec-description" required placeholder="למשל: קניות בסופר" pattern=".*\S+.*" title="לא ניתן להזין רק רווחים">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>סכום</label>
                        <div class="input-with-icon input-with-icon--currency">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <input type="number" name="rec_amount" id="rec-amount" step="0.01" min="0.01" required placeholder="0.00" style="font-size: 1.2rem; font-weight: 800;">
                            <input type="hidden" name="currency_code" id="rec-currency-code" value="ILS">
                            <button type="button" id="rec-currency-toggle" class="currency-toggle-btn" onclick="toggleCurrencyField('rec-currency-code', 'rec-currency-toggle')" aria-label="החלף מטבע" title="לחיצה מחליפה בין שקל לדולר">
                                <i class="fa-solid fa-shekel-sign" aria-hidden="true"></i>
                                <span class="currency-toggle-btn__tooltip">לחיצה מחליפה בין שקל לדולר</span>
                            </button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>קטגוריה</label>
                        <div id="rec-category-grid-container"></div>
                        <input type="hidden" name="rec_category" id="rec-selected-category-id" value="">
                    </div>

                    <div class="input-group">
                        <label>תאריך</label>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-calendar-days"></i>
                            <input type="date" name="transaction_date" id="rec-trans-date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="input-group" style="margin-top: 20px; text-align: right;">
                        <label class="checkbox-container" style="font-size: 0.95rem; font-weight: 600;">
                            <input type="checkbox" checked disabled aria-hidden="true" tabindex="-1">
                            פעולה אוטומטית חודשית
                        </label>
                    </div>

                    <div id="rec-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="btn-save-recurring" style="margin-top: 5px;">
                        <i class="fa-solid fa-plus"></i> הוסף פעולה
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="category-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="cat-modal-title">קטגוריה חדשה</h3>
                <button type="button" onclick="closeCategoryModal()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <form id="category-form">
                    <input type="hidden" name="category_id" id="cat-id">

                    <div class="modern-toggle" id="cat-type-toggle" style="margin-bottom: 20px;">
                        <input type="radio" name="cat_type" id="cat-type-exp" value="expense" checked onchange="toggleBudget()">
                        <label for="cat-type-exp" class="toggle-option expense">הוצאה (-)</label>
                        
                        <input type="radio" name="cat_type" id="cat-type-inc" value="income" onchange="toggleBudget()">
                        <label for="cat-type-inc" class="toggle-option income">הכנסה (+)</label>
                    </div>

                    <div class="input-group">
                        <label>שם הקטגוריה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" name="cat_name" id="cat-name" required placeholder="למשל: סופרמרקט">
                        </div>
                    </div>

                    <div class="input-group" id="budget-input-wrapper">
                        <label>תקציב חודשי (₪) - השאר 0 ללא הגבלה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-bullseye"></i>
                            <input type="number" name="cat_budget" id="cat-budget" step="1" min="0" value="0" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>בחר אייקון</label>
                        <div id="icon-selector-grid" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 10px;">
                            </div>
                        <input type="hidden" name="cat_icon" id="cat-icon" value="fa-tag">
                    </div>

                    <div id="cat-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="btn-save-cat" style="margin-top: 15px;">
                        <i class="fa-solid fa-save"></i> שמור קטגוריה
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="shopping-store-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="shopping-store-modal-title">חנות חדשה</h3>
                <button type="button" onclick="closeShoppingStoreModal()" class="close-modal-btn" aria-label="סגור" title="סגור"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body">
                <form id="shopping-store-form">
                    <input type="hidden" name="store_id" id="shopping-store-id">
                    <div class="input-group">
                        <label>שם החנות</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-store"></i>
                            <input type="text" name="store_name" id="shopping-store-name" required placeholder="למשל: שופרסל">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>בחר אייקון</label>
                        <div id="shopping-store-icon-grid" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 10px;"></div>
                        <input type="hidden" name="store_icon" id="shopping-store-icon" value="fa-cart-shopping">
                    </div>

                    <div id="shopping-store-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="btn-save-shopping-store" style="margin-top: 15px;">
                        <i class="fa-solid fa-save"></i> שמור חנות
                    </button>
                </form>
            </div>
        </div>
    </div>

</body>
 <script>
        const allCategories = <?php echo json_encode($categories_array, JSON_UNESCAPED_UNICODE); ?>;

        // === פונקציית עדכון פרטי הבית ב-AJAX ===
        function resetHomeBankBalance() {
            tazrimConfirm({
                title: 'איפוס יתרה',
                message: 'פעולה זו תאפס את יתרת הבנק השמורה (יישור וחישוב מהתנועות) לאפס. להמשיך?',
                confirmText: 'איפוס',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;
                fetch('<?php echo BASE_URL; ?>/app/ajax/reset_home_balance.php', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        tazrimAlert({ title: 'שגיאה', message: data.message || 'לא ניתן לאפס.' });
                    }
                })
                .catch(() => tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת.' }));
            });
        }

        function updateHomeDetails() {
            const form = document.getElementById('update-home-form');
            const formData = new FormData(form);
            const showCb = document.getElementById('show_bank_balance_cb');
            formData.set('show_bank_balance', showCb && showCb.checked ? '1' : '0');
            const btn = document.getElementById('btn-update-home');
            const msgBox = document.getElementById('home-msg');

            // שינוי סטטוס כפתור
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר נתונים...';
            btn.disabled = true;
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>/app/ajax/update_home.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = 'שמור שינויים';
                btn.disabled = false;
                
                if (data.status === 'success') {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = 'var(--sub_main-light)';
                    msgBox.style.color = 'var(--main)';
                    msgBox.innerText = 'הנתונים עודכנו בהצלחה!';
                    
                    // רענון אחרי שנייה כדי שהשם בבר העליון יתעדכן
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = data.message || 'שגיאה בשמירת הנתונים.';
                }
            })
            .catch(error => {
                btn.innerHTML = 'שמור שינויים';
                btn.disabled = false;
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'שגיאת תקשורת עם השרת.';
            });
        }

        // הכנה לפונקציות שייכתבו בשלבים הבאים
        // === פונקציית מחיקת פעולה קבועה ===
        function deleteRecurring(id) {
            tazrimConfirm({
                title: 'מחיקת פעולה קבועה',
                message: 'האם אתה בטוח שברצונך למחוק פעולה קבועה זו לצמיתות?',
                confirmText: 'מחק',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;

                const formData = new FormData();
                formData.append('id', id);

                fetch('<?php echo BASE_URL; ?>/app/ajax/delete_recurring.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        refreshManageHomeRecurringPanel();
                    } else {
                        tazrimAlert({
                            title: 'שגיאה במחיקה',
                            message: data.message || 'אירעה שגיאה לא ידועה.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת. אנא נסה שוב.' });
                });
            });
        }

        const recurringModal = document.getElementById('recurring-modal');
        const recurringForm = document.getElementById('recurring-form');
        const fetchManageHomeRecurringUrl = '<?php echo BASE_URL; ?>/app/ajax/fetch_manage_home_recurring.php';

        function buildRecurringCategorySelect(containerId, hiddenInputId, type, selectedId) {
            const container = document.getElementById(containerId);
            const hiddenInput = document.getElementById(hiddenInputId);
            if (!container || !hiddenInput) {
                return;
            }
            container.innerHTML = '';

            const filteredCats = allCategories.filter(function (cat) { return cat.type === type; });

            if (filteredCats.length === 0) {
                container.innerHTML = '<div style="color:var(--error); font-size:0.9rem; padding: 10px;">לא נמצאו קטגוריות</div>';
                hiddenInput.value = '';
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';

            var selectedCat = filteredCats.find(function (cat) { return String(cat.id) === String(selectedId); });
            var triggerHTML = '';

            if (!selectedCat) {
                hiddenInput.value = '';
                triggerHTML =
                    '<div class="selected-cat-info" style="color: #888;">' +
                    '<i class="fa-solid fa-list-ul" style="color: #ccc;"></i> <span>בחירת קטגוריה...</span>' +
                    '</div>' +
                    '<i class="fa-solid fa-chevron-down" style="color: #ccc; font-size: 0.9rem;"></i>';
            } else {
                hiddenInput.value = selectedCat.id;
                var iconClassInit = selectedCat.icon ? selectedCat.icon : 'fa-tag';
                triggerHTML =
                    '<div class="selected-cat-info">' +
                    '<i class="fa-solid ' + iconClassInit + '" style="color: var(--main);"></i> <span>' + selectedCat.name + '</span>' +
                    '</div>' +
                    '<i class="fa-solid fa-chevron-down" style="color: #ccc; font-size: 0.9rem;"></i>';
            }

            var optionsHTML = '';
            filteredCats.forEach(function (cat) {
                var iconClass = cat.icon ? cat.icon : 'fa-tag';
                optionsHTML +=
                    '<div class="custom-option" data-value="' + cat.id + '" data-name="' +
                    String(cat.name).replace(/&/g, '&amp;').replace(/"/g, '&quot;') +
                    '" data-icon="' + iconClass + '">' +
                    '<i class="fa-solid ' + iconClass + '"></i> <span>' + cat.name + '</span></div>';
            });

            wrapper.innerHTML =
                '<div class="custom-select-trigger">' + triggerHTML + '</div>' +
                '<div class="custom-select-options">' + optionsHTML + '</div>';

            container.appendChild(wrapper);

            var trigger = wrapper.querySelector('.custom-select-trigger');
            var options = wrapper.querySelectorAll('.custom-option');

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                document.querySelectorAll('.custom-select-wrapper').forEach(function (w) {
                    if (w !== wrapper) {
                        w.classList.remove('open');
                    }
                });
                wrapper.classList.toggle('open');
            });

            options.forEach(function (option) {
                option.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var val = option.getAttribute('data-value');
                    var name = option.getAttribute('data-name');
                    var icon = option.getAttribute('data-icon');
                    hiddenInput.value = val;
                    wrapper.querySelector('.selected-cat-info').innerHTML =
                        '<i class="fa-solid ' + icon + '" style="color: var(--main);"></i> <span style="color: var(--text);">' + name + '</span>';
                    wrapper.classList.remove('open');
                });
            });
        }

        function filterRecurringCategories() {
            var selectedType = document.querySelector('#recurring-form input[name="rec_type"]:checked').value;
            buildRecurringCategorySelect('rec-category-grid-container', 'rec-selected-category-id', selectedType);
        }

        function syncCurrencyToggle(inputId, buttonId) {
            var hiddenInput = document.getElementById(inputId);
            var button = document.getElementById(buttonId);
            if (!hiddenInput || !button) {
                return;
            }

            var icon = button.querySelector('i');
            var currencyCode = hiddenInput.value === 'USD' ? 'USD' : 'ILS';
            hiddenInput.value = currencyCode;
            if (icon) {
                icon.className = currencyCode === 'USD' ? 'fa-solid fa-dollar-sign' : 'fa-solid fa-shekel-sign';
            }
            button.setAttribute('aria-label', currencyCode === 'USD' ? 'מטבע נוכחי דולר, לחץ להחלפה לשקל' : 'מטבע נוכחי שקל, לחץ להחלפה לדולר');
        }

        function toggleCurrencyField(inputId, buttonId) {
            var hiddenInput = document.getElementById(inputId);
            var button = document.getElementById(buttonId);
            if (!hiddenInput || !button) {
                return;
            }

            hiddenInput.value = hiddenInput.value === 'USD' ? 'ILS' : 'USD';
            syncCurrencyToggle(inputId, buttonId);
            button.classList.add('tooltip-visible');
            window.setTimeout(function () {
                button.classList.remove('tooltip-visible');
            }, 1200);
        }

        function resetRecurringForm() {
            recurringForm.reset();
            document.getElementById('recurring-id').value = '';
            document.getElementById('type-expense').checked = true;
            document.getElementById('rec-trans-date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('rec-currency-code').value = 'ILS';
            syncCurrencyToggle('rec-currency-code', 'rec-currency-toggle');
            document.getElementById('rec-selected-category-id').value = '';
            document.getElementById('rec-msg').style.display = 'none';
            var submitBtn = document.getElementById('btn-save-recurring');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
            filterRecurringCategories();
        }

        function refreshManageHomeRecurringPanel() {
            const panel = document.getElementById('manage-home-recurring-panel');
            if (!panel) {
                return Promise.resolve();
            }
            panel.style.opacity = '0.55';
            panel.style.pointerEvents = 'none';
            return fetch(fetchManageHomeRecurringUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && typeof data.html === 'string') {
                        panel.innerHTML = data.html;
                    } else {
                        tazrimAlert({
                            title: 'שגיאה',
                            message: 'לא ניתן לרענן את רשימת הפעולות הקבועות.'
                        });
                    }
                })
                .catch(function () {
                    tazrimAlert({
                        title: 'שגיאה',
                        message: 'שגיאת תקשורת בעת רענון הפעולות הקבועות.'
                    });
                })
                .finally(function () {
                    panel.style.opacity = '';
                    panel.style.pointerEvents = '';
                });
        }

        function openAddRecurringModal() {
            resetRecurringForm();
            document.getElementById('rec-modal-title').innerText = 'הוספת פעולה חדשה';
            document.getElementById('rec-type-toggle').style.display = 'flex';
            recurringModal.style.display = 'block';
        }

        function openEditRecurringModal(id, description, amount, type, categoryId, dayOfMonth, currencyCode) {
            recurringForm.reset();
            document.getElementById('recurring-id').value = id;
            document.getElementById('rec-modal-title').innerText = 'עריכת פעולה';
            document.getElementById('rec-type-toggle').style.display = 'none';
            if (type === 'expense') {
                document.getElementById('type-expense').checked = true;
            } else {
                document.getElementById('type-income').checked = true;
            }
            buildRecurringCategorySelect('rec-category-grid-container', 'rec-selected-category-id', type, categoryId);
            document.getElementById('rec-amount').value = amount;
            document.getElementById('rec-currency-code').value = currencyCode || 'ILS';
            syncCurrencyToggle('rec-currency-code', 'rec-currency-toggle');
            document.getElementById('rec-description').value = description;
            var now = new Date();
            var y = now.getFullYear();
            var m = now.getMonth();
            var lastDay = new Date(y, m + 1, 0).getDate();
            var d = Math.min(parseInt(dayOfMonth, 10) || 1, lastDay);
            document.getElementById('rec-trans-date').value =
                y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            document.getElementById('rec-msg').style.display = 'none';
            var submitBtn = document.getElementById('btn-save-recurring');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';
            recurringModal.style.display = 'block';
        }

        function closeRecurringModal() {
            recurringModal.style.display = 'none';
            resetRecurringForm();
        }

        document.addEventListener('click', function () {
            document.querySelectorAll('.custom-select-wrapper').forEach(function (w) {
                w.classList.remove('open');
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target === recurringModal) {
                closeRecurringModal();
            }
        });

        recurringForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btn-save-recurring');
            var msgBox = document.getElementById('rec-msg');

            var selectedCatId = document.getElementById('rec-selected-category-id').value;
            if (!selectedCatId) {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'נא לבחור קטגוריה מהרשימה.';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר נתונים...';
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>/app/ajax/save_recurring.php', {
                method: 'POST',
                body: new FormData(recurringForm)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        closeRecurringModal();
                        refreshManageHomeRecurringPanel();
                    } else {
                        msgBox.style.display = 'block';
                        msgBox.style.backgroundColor = '#fee2e2';
                        msgBox.style.color = 'var(--error)';
                        msgBox.innerText = data.message || 'שגיאה בשמירה.';
                        btn.disabled = false;
                        var isEdit = document.getElementById('recurring-id').value !== '';
                        btn.innerHTML = isEdit
                            ? '<i class="fa-solid fa-save"></i> שמור שינויים'
                            : '<i class="fa-solid fa-plus"></i> הוסף פעולה';
                    }
                })
                .catch(function () {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = 'שגיאת תקשורת. אנא נסה שוב.';
                    btn.disabled = false;
                    var isEdit = document.getElementById('recurring-id').value !== '';
                    btn.innerHTML = isEdit
                        ? '<i class="fa-solid fa-save"></i> שמור שינויים'
                        : '<i class="fa-solid fa-plus"></i> הוסף פעולה';
                });
        });

        syncCurrencyToggle('rec-currency-code', 'rec-currency-toggle');

        // === ניהול פופאפ קטגוריות ===
        const catModal = document.getElementById('category-modal');
        const catForm = document.getElementById('category-form');
        const fetchManageHomeCategoriesUrl = '<?php echo BASE_URL; ?>/app/ajax/fetch_manage_home_categories.php';

        function refreshManageHomeCategoriesPanel() {
            const panel = document.getElementById('manage-home-categories-panel');
            if (!panel) {
                return Promise.resolve();
            }
            panel.style.opacity = '0.55';
            panel.style.pointerEvents = 'none';
            return fetch(fetchManageHomeCategoriesUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && typeof data.html === 'string') {
                        panel.innerHTML = data.html;
                    } else {
                        tazrimAlert({
                            title: 'שגיאה',
                            message: 'לא ניתן לרענן את רשימת הקטגוריות.'
                        });
                    }
                })
                .catch(function () {
                    tazrimAlert({
                        title: 'שגיאה',
                        message: 'שגיאת תקשורת בעת רענון הקטגוריות.'
                    });
                })
                .finally(function () {
                    panel.style.opacity = '';
                    panel.style.pointerEvents = '';
                });
        }
        
        // יצירת גריד האייקונים
        const iconsList = ['fa-tag', 'fa-cart-shopping', 'fa-car', 'fa-house', 'fa-bolt', 'fa-heart-pulse', 'fa-graduation-cap', 'fa-plane', 'fa-shirt', 'fa-utensils', 'fa-paw', 'fa-gift', 'fa-money-bill-wave', 'fa-mobile-screen', 'fa-baby', 'fa-hammer', 'fa-couch', 'fa-truck'];
        const iconGrid = document.getElementById('icon-selector-grid');
        
        iconsList.forEach(icon => {
            const div = document.createElement('div');
            div.className = 'icon-option';
            div.innerHTML = `<i class="fa-solid ${icon}"></i>`;
            div.onclick = () => selectIcon(icon, div);
            iconGrid.appendChild(div);
        });

        function selectIcon(icon, element) {
            document.getElementById('cat-icon').value = icon;
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            if(element) element.classList.add('selected');
        }

        // === פונקציה שמסתירה/מציגה את שדה התקציב לפי סוג הקטגוריה ===
        function toggleBudget() {
            const isExpense = document.getElementById('cat-type-exp').checked;
            const budgetWrapper = document.getElementById('budget-input-wrapper');
            const budgetInput = document.getElementById('cat-budget');
            
            if (isExpense) {
                budgetWrapper.style.display = 'block';
            } else {
                budgetWrapper.style.display = 'none';
                budgetInput.value = 0; // מאפסים ל-0 כדי שלא יישמר זבל במסד
            }
        }

        function openAddCategoryModal() {
            catForm.reset();
            document.getElementById('cat-id').value = '';
            document.getElementById('cat-modal-title').innerText = 'קטגוריה חדשה';
            document.getElementById('cat-type-toggle').style.display = 'flex'; 
            selectIcon('fa-tag', document.querySelector('.icon-option')); 
            
            document.getElementById('cat-type-exp').checked = true; // מחזיר להוצאה כברירת מחדל
            toggleBudget(); // מוודא ששדה התקציב מוצג
            
            catModal.style.display = 'block';
        }

        function openEditCategoryModal(id, name, budget, type, icon) {
            catForm.reset();
            document.getElementById('cat-id').value = id;
            document.getElementById('cat-modal-title').innerText = 'עריכת קטגוריה';
            document.getElementById('cat-name').value = name;
            document.getElementById('cat-budget').value = budget;
            
            document.getElementById('cat-type-toggle').style.display = 'none'; 
            if(type === 'expense') {
                document.getElementById('cat-type-exp').checked = true;
            } else {
                document.getElementById('cat-type-inc').checked = true;
            }
            
            toggleBudget(); // מסתיר את שדה התקציב אם עורכים קטגוריית הכנסה קיימת

            const iconVal = icon || 'fa-tag';
            document.getElementById('cat-icon').value = iconVal;
            document.querySelectorAll('.icon-option').forEach(el => {
                el.classList.remove('selected');
                if(el.innerHTML.includes(iconVal)) el.classList.add('selected');
            });

            catModal.style.display = 'block';
        }

        function closeCategoryModal() {
            catModal.style.display = 'none';
        }

        // שמירת הקטגוריה ב-AJAX
        catForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save-cat');
            const msgBox = document.getElementById('cat-msg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר...';
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>/app/ajax/save_category.php', {
                method: 'POST',
                body: new FormData(catForm)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeCategoryModal();
                    msgBox.style.display = 'none';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-save"></i> שמור קטגוריה';
                    refreshManageHomeCategoriesPanel();
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = data.message;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-save"></i> שמור קטגוריה';
                }
            })
            .catch(err => {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'שגיאת תקשורת.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save"></i> שמור קטגוריה';
            });
        });

        // === ניהול חנויות רשימת קניות ===
        const shoppingStoreModal = document.getElementById('shopping-store-modal');
        const shoppingStoreForm = document.getElementById('shopping-store-form');
        const fetchManageHomeShoppingStoresUrl = '<?php echo BASE_URL; ?>/app/ajax/fetch_manage_home_shopping_stores.php';
        const shoppingStoreIconsList = ['fa-cart-shopping', 'fa-store', 'fa-leaf', 'fa-basket-shopping', 'fa-shop', 'fa-bag-shopping', 'fa-medkit', 'fa-drumstick-bite', 'fa-bread-slice', 'fa-plug', 'fa-box', 'fa-tag'];
        const shoppingStoreIconGrid = document.getElementById('shopping-store-icon-grid');

        shoppingStoreIconsList.forEach(icon => {
            const div = document.createElement('div');
            div.className = 'icon-option shopping-store-icon-option';
            div.innerHTML = `<i class="fa-solid ${icon}"></i>`;
            div.onclick = () => selectShoppingStoreIcon(icon, div);
            shoppingStoreIconGrid.appendChild(div);
        });

        function selectShoppingStoreIcon(icon, element) {
            document.getElementById('shopping-store-icon').value = icon;
            document.querySelectorAll('.shopping-store-icon-option').forEach(el => el.classList.remove('selected'));
            if (element) {
                element.classList.add('selected');
            }
        }

        function refreshManageHomeShoppingStoresPanel() {
            const panel = document.getElementById('manage-home-shopping-stores-panel');
            if (!panel) {
                return Promise.resolve();
            }
            panel.style.opacity = '0.55';
            panel.style.pointerEvents = 'none';
            return fetch(fetchManageHomeShoppingStoresUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && typeof data.html === 'string') {
                        panel.innerHTML = data.html;
                    } else {
                        tazrimAlert({
                            title: 'שגיאה',
                            message: 'לא ניתן לרענן את רשימת החנויות.'
                        });
                    }
                })
                .catch(function () {
                    tazrimAlert({
                        title: 'שגיאה',
                        message: 'שגיאת תקשורת בעת רענון החנויות.'
                    });
                })
                .finally(function () {
                    panel.style.opacity = '';
                    panel.style.pointerEvents = '';
                });
        }

        function openAddShoppingStoreModal() {
            shoppingStoreForm.reset();
            document.getElementById('shopping-store-id').value = '';
            document.getElementById('shopping-store-modal-title').innerText = 'חנות חדשה';
            document.getElementById('shopping-store-msg').style.display = 'none';
            document.getElementById('btn-save-shopping-store').disabled = false;
            document.getElementById('btn-save-shopping-store').innerHTML = '<i class="fa-solid fa-save"></i> שמור חנות';
            selectShoppingStoreIcon('fa-cart-shopping', document.querySelector('.shopping-store-icon-option'));
            shoppingStoreModal.style.display = 'block';
        }

        function openEditShoppingStoreModal(id, name, icon) {
            shoppingStoreForm.reset();
            document.getElementById('shopping-store-id').value = id;
            document.getElementById('shopping-store-modal-title').innerText = 'עריכת חנות';
            document.getElementById('shopping-store-name').value = name;
            document.getElementById('shopping-store-msg').style.display = 'none';
            document.getElementById('btn-save-shopping-store').disabled = false;
            document.getElementById('btn-save-shopping-store').innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';

            const iconVal = icon || 'fa-cart-shopping';
            document.getElementById('shopping-store-icon').value = iconVal;
            document.querySelectorAll('.shopping-store-icon-option').forEach(el => {
                el.classList.remove('selected');
                if (el.innerHTML.includes(iconVal)) {
                    el.classList.add('selected');
                }
            });

            shoppingStoreModal.style.display = 'block';
        }

        function closeShoppingStoreModal() {
            shoppingStoreModal.style.display = 'none';
        }

        shoppingStoreForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save-shopping-store');
            const msgBox = document.getElementById('shopping-store-msg');

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר...';
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>/app/ajax/save_shopping_store.php', {
                method: 'POST',
                body: new FormData(shoppingStoreForm)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeShoppingStoreModal();
                    msgBox.style.display = 'none';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-save"></i> שמור חנות';
                    refreshManageHomeShoppingStoresPanel();
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = data.message || 'שגיאה בשמירת החנות.';
                    btn.disabled = false;
                    const isEdit = document.getElementById('shopping-store-id').value !== '';
                    btn.innerHTML = isEdit ? '<i class="fa-solid fa-save"></i> שמור שינויים' : '<i class="fa-solid fa-save"></i> שמור חנות';
                }
            })
            .catch(() => {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'שגיאת תקשורת.';
                btn.disabled = false;
                const isEdit = document.getElementById('shopping-store-id').value !== '';
                btn.innerHTML = isEdit ? '<i class="fa-solid fa-save"></i> שמור שינויים' : '<i class="fa-solid fa-save"></i> שמור חנות';
            });
        });
    </script>

    <script>
        // === פונקציית מחיקת קטגוריה (מחיקה רכה) ===
        function deleteCategory(id) {
            tazrimConfirm({
                title: 'מחיקת קטגוריה',
                message: 'האם אתה בטוח שברצונך למחוק קטגוריה זו? (פעולות עבר מקטגוריה זו ישמרו בדוחות)',
                confirmText: 'מחק',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;

                const formData = new FormData();
                formData.append('id', id);

                fetch('<?php echo BASE_URL; ?>/app/ajax/delete_category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        refreshManageHomeCategoriesPanel();
                    } else {
                        tazrimAlert({
                            title: 'שגיאה במחיקה',
                            message: data.message || 'אירעה שגיאה.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
                });
            });
        }

        function deleteShoppingStore(id) {
            tazrimConfirm({
                title: 'מחיקת חנות',
                message: 'האם אתה בטוח שברצונך למחוק חנות זו? פריטי עבר ימשיכו להיות משויכים בחנות אחרת כברירת מחדל.',
                confirmText: 'מחק',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;

                const formData = new FormData();
                formData.append('id', id);

                fetch('<?php echo BASE_URL; ?>/app/ajax/delete_shopping_store.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        refreshManageHomeShoppingStoresPanel();
                    } else {
                        tazrimAlert({
                            title: 'שגיאה במחיקה',
                            message: data.message || 'אירעה שגיאה.'
                        });
                    }
                })
                .catch(() => {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
                });
            });
        }
    </script>
</html>
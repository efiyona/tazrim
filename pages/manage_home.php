<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php'); 

$home_id = $_SESSION['home_id'];
$user_id = $_SESSION['id'];

// 1. שליפת פרטי הבית
$home_data = selectOne('homes', ['id' => $home_id]);

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

// 3. שליפת פעולות קבועות פעילות
$recurring_query = "SELECT r.*, c.name as cat_name, c.icon as cat_icon 
                    FROM recurring_transactions r 
                    LEFT JOIN categories c ON r.category = c.id 
                    WHERE r.home_id = $home_id AND r.is_active = 1 
                    ORDER BY r.day_of_month ASC";
$recurring_result = mysqli_query($conn, $recurring_query);

// 4. שליפת כל המשתמשים השייכים לבית זה
$members_query = "SELECT first_name, nickname, role, email FROM users WHERE home_id = $home_id ORDER BY (role = 'admin') DESC, first_name ASC";
$members_result = mysqli_query($conn, $members_query);

// בדיקה האם קיים כבר מפתח API למשתמש הנוכחי
$token_check_query = "SELECT token FROM api_tokens WHERE user_id = $user_id LIMIT 1";
$token_check_result = mysqli_query($conn, $token_check_query);
$existing_token = mysqli_fetch_assoc($token_check_result);
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/user.css">

</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">
                
                <div class="page-header-actions" style="margin-bottom: 25px;">
                    <h1 class="section-title" style="margin-bottom: 0;">ניהול הבית</h1>
                    <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 5px;">הגדרות, תקציבים ופעולות קבועות</p>
                </div>

                <div class="management-grid">
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>פרטי הבית וחיבורים</h3>
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

                            <div class="management-block" style="border-top: 1px solid #f1f5f9; padding-top: 20px; margin-top: 10px;">
                                <span class="block-label">התראות דחיפה (Push):</span>
                                <button type="button" id="btn-enable-notifications" class="btn-generate-v2" style="background-color: var(--main); width: 100%;" onclick="initPushSubscription()">
                                    <i class="fa-solid fa-bell"></i> הפעל התראות במכשיר זה
                                </button>
                                <div id="notif-msg" style="display: none;" class="success-text-small"></div>
                            </div>

                            <div class="management-block">
                                <span class="block-label">חיבור לאייפון (API Key):</span>
                                <?php if ($existing_token): ?>
                                    <div class="api-wrapper-v2">
                                        <input type="text" id="api-token-display" value="<?php echo $existing_token['token']; ?>" readonly>
                                        <button onclick="copyApiToken()" class="copy-btn-v2" title="העתק מפתח">
                                            <i class="fa-regular fa-copy"></i>
                                        </button>
                                    </div>
                                    <div id="copy-msg" style="display: none;" class="success-text-small">
                                        <i class="fa-solid fa-check"></i> המפתח הועתק!
                                    </div>
                                <?php else: ?>
                                    <button type="button" id="btn-generate-api" class="btn-generate-v2" onclick="generateApiToken()">
                                        <i class="fa-solid fa-key"></i> יצירת מפתח חיבור ראשון
                                    </button>
                                <?php endif; ?>
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
                                    <label>יתרת בנק עדכנית (₪)</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-building-columns"></i>
                                        <input type="number" step="0.01" name="initial_balance" value="<?php echo $home_data['initial_balance']; ?>" required>
                                    </div>
                                </div>
                                <div id="home-msg" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: 600; text-align: center;"></div>
                                <button type="button" id="btn-update-home" class="btn-primary" onclick="updateHomeDetails()">שמור שינויים</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>משתתפים בבית</h3>
                        </div>
                        <div class="members-list" style="margin-top: 10px;">
                            <?php while ($member = mysqli_fetch_assoc($members_result)): 
                                $is_admin = ($member['role'] === 'admin');
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

                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3>פעולות קבועות</h3>
                        </div>
                        <div class="recurring-list">
                            <?php if (mysqli_num_rows($recurring_result) > 0): ?>
                                <?php while ($rec = mysqli_fetch_assoc($recurring_result)): ?>
                                    <div class="recurring-list-item">
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <div style="width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; color: var(--text-light);">
                                                <i class="fa-solid <?php echo $rec['cat_icon'] ?: 'fa-tag'; ?>"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: var(--text);"><?php echo $rec['description']; ?></div>
                                                <div style="font-size: 0.8rem; color: #666;">כל <?php echo $rec['day_of_month']; ?> בחודש | <span style="color: <?php echo $rec['type'] == 'expense' ? 'var(--error)' : 'var(--success)'; ?>; font-weight: 600;"><?php echo number_format($rec['amount']); ?> ₪</span></div>
                                            </div>
                                        </div>
                                        <div class="action-btns">
                                            <button class="btn-delete" onclick="deleteRecurring(<?php echo $rec['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #777; padding: 20px;">אין פעולות קבועות כרגע.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card full-width-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--gray); padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                            <h3 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--text);">קטגוריות ותקציב חודשי</h3>
                            <button class="btn-primary" style="width: max-content; margin: 0; padding: 8px 20px; font-size: 0.95rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(35, 114, 39, 0.2);" onclick="openAddCategoryModal()">
                                <i class="fa-solid fa-plus"></i> קטגוריה חדשה
                            </button>
                        </div>
                        
                        <h4 style="margin: 20px 0 12px; color: var(--error); font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-arrow-trend-down"></i> הוצאות
                        </h4>
                        <div class="categories-grid-container">
                            <?php foreach ($expenses_cats as $cat): ?>
                                <div class="category-setting-card expense-cat">
                                    <div class="cat-info-wrapper">
                                        <div class="cat-icon-circle-small">
                                            <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>"></i>
                                        </div>
                                        <span style="font-weight: 700; color: var(--text); font-size: 1.05rem;"><?php echo $cat['name']; ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        
                                        <?php if($cat['budget_limit'] > 0): ?>
                                            <div class="cat-budget-pill">
                                                <i class="fa-solid fa-bullseye"></i> <?php echo number_format($cat['budget_limit']); ?> ₪
                                            </div>
                                        <?php else: ?>
                                            <div class="cat-budget-pill no-budget">
                                                ללא תקציב
                                            </div>
                                        <?php endif; ?>
                                        <div class="action-btns" style="display: flex; gap: 8px;">
                                            <button class="btn-delete" style="background: #fee2e2; color: var(--error); border: none; padding: 8px; border-radius: 8px; cursor: pointer;" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                            <button class="btn-edit" style="background: var(--gray); border: none; padding: 8px; border-radius: 8px; cursor: pointer;" onclick="openEditCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', <?php echo $cat['budget_limit']; ?>, '<?php echo $cat['type']; ?>', '<?php echo $cat['icon']; ?>')">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>                                        
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h4 style="margin: 35px 0 12px; color: var(--success); font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-arrow-trend-up"></i> הכנסות
                        </h4>
                        <div class="categories-grid-container">
                            <?php foreach ($income_cats as $cat): ?>
                                <div class="category-setting-card income-cat">
                                    <div class="cat-info-wrapper">
                                        <div class="cat-icon-circle-small" style="color: var(--success); background-color: #f0fdf4;">
                                            <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>"></i>
                                        </div>
                                        <span style="font-weight: 700; color: var(--text); font-size: 1.05rem;"><?php echo $cat['name']; ?></span>
                                    </div>
                                    <div class="action-btns" style="display: flex; gap: 8px;">
                                        <button class="btn-delete" style="background: #fee2e2; color: var(--error); border: none; padding: 8px; border-radius: 8px; cursor: pointer;" onclick="deleteCategory(<?php echo $cat['id']; ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <button class="btn-edit" style="background: var(--gray); border: none; padding: 8px; border-radius: 8px; cursor: pointer;" onclick="openEditCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', <?php echo $cat['budget_limit']; ?>, '<?php echo $cat['type']; ?>', '<?php echo $cat['icon']; ?>')">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>                                        
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
            </main>
            </div>

    <div id="category-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="cat-modal-title">קטגוריה חדשה</h3>
                <button type="button" onclick="closeCategoryModal()" class="close-modal-btn">&times;</button>
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

</body>
 <script>
        // === פונקציית עדכון פרטי הבית ב-AJAX ===
        function updateHomeDetails() {
            const form = document.getElementById('update-home-form');
            const formData = new FormData(form);
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
            if(confirm('האם אתה בטוח שברצונך למחוק פעולה קבועה זו לצמיתות?')) {
                
                const formData = new FormData();
                formData.append('id', id);

                fetch('<?php echo BASE_URL; ?>/app/ajax/delete_recurring.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // מחיקה הצליחה - נרענן את הדף כדי שהשורה תיעלם מהרשימה
                        window.location.reload();
                    } else {
                        alert('שגיאה במחיקה: ' + (data.message || 'אירעה שגיאה לא ידועה.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאת תקשורת עם השרת. אנא נסה שוב.');
                });
            }
        }
        // === ניהול פופאפ קטגוריות ===
        const catModal = document.getElementById('category-modal');
        const catForm = document.getElementById('category-form');
        
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
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = 'var(--sub_main-light)';
                    msgBox.style.color = 'var(--main)';
                    msgBox.innerText = 'הקטגוריה נשמרה בהצלחה!';
                    setTimeout(() => window.location.reload(), 800);
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
    </script>

    <script>
        // === פונקציית מחיקת קטגוריה (מחיקה רכה) ===
        function deleteCategory(id) {
            if(confirm('האם אתה בטוח שברצונך למחוק קטגוריה זו? (פעולות עבר מקטגוריה זו ישמרו בדוחות)')) {
                const formData = new FormData();
                formData.append('id', id);

                fetch('<?php echo BASE_URL; ?>/app/ajax/delete_category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert('שגיאה במחיקה: ' + (data.message || 'אירעה שגיאה.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאת תקשורת עם השרת.');
                });
            }
        }
    </script>

    <script>
        // העתקת המפתח לקליפבורד
        function copyApiToken() {
            const tokenInput = document.getElementById('api-token-display');
            tokenInput.select();
            navigator.clipboard.writeText(tokenInput.value);
            
            const msg = document.getElementById('copy-msg');
            msg.style.display = 'block';
            setTimeout(() => { msg.style.display = 'none'; }, 2000);
        }

        function generateApiToken() {
            const btn = document.getElementById('btn-generate-api');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מייצר...';

            fetch('<?php echo BASE_URL; ?>app/ajax/generate_api_token.php', {
                method: 'POST'
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch(e) {
                    // אם השרת שלח שגיאת PHP במקום JSON, נראה אותה בקונסול
                    console.error("Server Error Output:", text);
                    throw new Error("השרת שלח תשובה לא תקינה. בדוק את הקונסול.");
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert('שגיאה: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-key"></i> ניסיון חוזר';
                }
            })
            .catch(err => {
                alert(err.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-key"></i> ניסיון חוזר';
            });
        }
    </script>

    <script>
        // שימוש במפתח הציבורי שהגדרנו ב-secrets.php
        const VAPID_PUBLIC_KEY = "<?php echo VAPID_PUBLIC_KEY; ?>";

        // פונקציית עזר להמרת המפתח לפורמט בינארי שהאייפון דורש

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = atob(base64);
            return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
        }

        async function initPushSubscription() {
            const btn = document.getElementById('btn-enable-notifications');
            const msg = document.getElementById('notif-msg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מתחבר לשרתי אפל...';

            // 1. בדיקה אם הדפדפן תומך (חובה PWA באייפון)
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('התראות לא נתמכות. וודא שהוספת את האתר למסך הבית (Add to Home Screen).');
                btn.disabled = false;
                return;
            }

            try {
                // 2. רישום ה-Service Worker
                const register = await navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js');
                
                // 3. בקשת אישור מהמשתמש
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('כדי לקבל התראות, עליך לאשר אותן בהגדרות הדפדפן.');
                    btn.disabled = false;
                    return;
                }

                // 4. יצירת מנוי מול שירות ה-Push
                const convertedVapidKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);

                const subscription = await register.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedVapidKey // שימוש במפתח המומר
                });


                // 5. שליחת המנוי לשרת ב-Hostinger לשמירה במסד
                const response = await fetch('<?php echo BASE_URL; ?>app/ajax/save_subscription.php', {
                    method: 'POST',
                    body: JSON.stringify(subscription),
                    headers: { 'Content-Type': 'application/json' }
                });

                const result = await response.json();
                if (result.status === 'success') {
                    btn.style.display = 'none';
                    msg.style.display = 'block';
                    msg.innerHTML = '<i class="fa-solid fa-check-circle"></i> התראות הופעלו! המכשיר רשום במערכת.';
                }
            } catch (error) {
                console.error('Full Subscription Error:', error);
                // כאן השינוי - אנחנו רוצים לראות את השגיאה האמיתית מהדפדפן
                alert('שגיאה מפורטת: ' + error.name + " - " + error.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bell"></i> נסה שוב';
            }
        }
    </script>
</html>
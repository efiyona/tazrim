<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

$home_id = $_SESSION['home_id'];
$home_data = selectOne('homes', ['id' => $home_id]);
$has_active_categories = (bool) selectOne('categories', ['home_id' => $home_id, 'is_active' => 1]);

if ($has_active_categories) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>הגדרות ראשוניות | התזרים</title>
    <style>
        * { box-sizing: border-box; }
        .welcome-body { background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Heebo', sans-serif; margin: 0; padding: 20px 0; }
        
        .welcome-card { background: white; width: 92%; max-width: 700px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        
        .step { display: none; }
        .step.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes popIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        
        .stepper-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 30px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #ddd; }
        .dot.active { background: var(--main); width: 25px; border-radius: 10px; transition: 0.3s; }
        
        .welcome-icon { font-size: 4rem; color: var(--main); margin-bottom: 20px; }
        
        /* כותרות סקשנים */
        .cat-section-title { font-size: 1.1rem; font-weight: 800; color: var(--text); text-align: right; margin: 30px 0 15px 0; padding-bottom: 5px; border-bottom: 2px solid #eee; display: flex; align-items: center; gap: 8px; }
        
        /* גריד הקטגוריות */
        .cat-suggest-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)); gap: 15px; text-align: right; margin-bottom: 20px; }
        
        /* עיצוב כרטיסיית קטגוריה */
        .cat-option { border: 2px solid var(--main); background: #f0fdf4; border-radius: 15px; padding: 25px 15px 15px; position: relative; display: flex; flex-direction: column; animation: popIn 0.3s ease-out; box-shadow: 0 4px 10px rgba(35, 114, 39, 0.05); }
        .cat-option-header { display: flex; flex-direction: column; align-items: flex-start; }
        .cat-option-header i { font-size: 1.6rem; margin-bottom: 10px; color: var(--main); }
        .cat-option.income .cat-option-header i { color: var(--success); }
        .cat-option-header span.cat-name { font-weight: 800; font-size: 1rem; color: var(--text); }
        
        /* כפתור מחיקת קטגוריה */
        .btn-delete-cat { position: absolute; top: 8px; left: 8px; background: #fee2e2; border: none; color: #dc2626; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 0.8rem; }
        .btn-delete-cat:hover { background: #dc2626; color: white; transform: scale(1.1); }
        
        /* שדה הזנת שם אישי */
        .custom-name-input { width: 100%; padding: 6px; border: 1px dashed var(--main); border-radius: 6px; font-family: inherit; font-size: 0.95rem; font-weight: 700; color: var(--main); background: white; margin-bottom: 5px; }
        .custom-name-input:focus { outline: none; border-style: solid; }

        /* שדה תקציב */
        .budget-wrapper { margin-top: 15px; }
        .budget-wrapper label { font-size: 0.75rem; color: #555; font-weight: 600; margin-bottom: 4px; display: block; }
        .budget-input-container { position: relative; display: flex; align-items: center; }
        .budget-input-container i { position: absolute; left: 10px; color: #888; font-size: 0.8rem; }
        .budget-input-container input.budget-input { width: 100%; padding: 8px 10px 8px 25px; border: 1px solid #c2e0c6; border-radius: 8px; font-family: inherit; font-size: 0.95rem; font-weight: 700; background: white; color: var(--main); transition: 0.2s; }
        .budget-input-container input:focus { outline: none; border-color: var(--main); }

        /* כפתור הוספת קטגוריה (המקווקו) */
        .cat-add-btn { border: 2px dashed #ccc; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: #888; border-radius: 15px; padding: 20px; transition: 0.2s; min-height: 120px; box-shadow: none; }
        .cat-add-btn:hover { border-color: var(--main); color: var(--main); background: #f0fdf4; }
        .cat-add-btn i { font-size: 2rem; margin-bottom: 10px; }
        .cat-add-btn span { font-weight: 700; font-size: 1rem; }
        
        .btn-welcome { background: var(--main); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-welcome:hover { background: var(--main-dark); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(35,114,39,0.3); }
        .btn-welcome:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        
        .hint-text { font-size: 0.85rem; color: #888; margin-top: 5px; }

        @media (max-width: 600px) {
            .welcome-card { padding: 30px 20px; }
            .cat-suggest-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }
    </style>
</head>
<body class="welcome-body">

    <div class="welcome-card">
        <div class="stepper-dots">
            <div class="dot active" id="dot-1"></div>
            <div class="dot" id="dot-2"></div>
            <div class="dot" id="dot-3"></div>
        </div>

        <form id="welcome-form">
            <div class="step active" id="step-1">
                <div class="welcome-icon"><i class="fa-solid fa-house-chimney-window"></i></div>
                <h1 style="font-weight: 800; margin-bottom: 10px;">ברוכים הבאים ל"התזרים"!</h1>
                <p style="color: #666; line-height: 1.6; margin-bottom: 10px;">המערכת שתעזור לכם להשתלט על ההוצאות, לנהל תקציב חכם ולתכנן את העתיד הכלכלי שלכם.</p>
                <p style="color: var(--main); font-weight: 600; background: #f0fdf4; padding: 10px; border-radius: 8px; display: inline-block;">הגדרת הבית תיקח בדיוק 2 דקות.</p>
                <button type="button" class="btn-welcome" onclick="nextStep(2)">בואו נתחיל <i class="fa-solid fa-arrow-left"></i></button>
            </div>

            <div class="step" id="step-2">
                <h2 style="font-weight: 800; margin-bottom: 5px;">הגדרות הבית</h2>
                <p style="color: #666; margin-bottom: 25px;">אל דאגה, תמיד תוכלו לשנות הכל בהמשך.</p>
                
                <div class="input-group" style="text-align: right; margin-bottom: 20px;">
                    <label style="font-weight: 700;">שם הבית (רשות)</label>
                    <input type="text" name="home_name" id="home_name" value="<?php echo htmlspecialchars($home_data['name']); ?>" style="width: 100%; padding: 12px; border: 2px solid #eee; border-radius: 10px; font-size: 1rem;">
                </div>

                <div class="input-group" style="text-align: right; background: #fafafa; padding: 15px; border-radius: 12px; border: 1px solid #eee;">
                    <label style="font-weight: 700; color: var(--text);">יתרה נוכחית בבנק <span style="color: #888; font-weight: normal;">(רשות)</span></label>
                    <p class="hint-text" style="margin-bottom: 10px;">כדי שהמערכת תציג לכם את יתרת העו"ש המדויקת שלכם.</p>
                    <div style="position: relative; display: flex; align-items: center;">
                        <i class="fa-solid fa-shekel-sign" style="position: absolute; left: 15px; color: #888;"></i>
                        <input type="number" id="initial_balance" value="0" step="0.01" style="width: 100%; padding: 12px 12px 12px 35px; border: 2px solid #ddd; border-radius: 10px; font-size: 1.2rem; font-weight: 800; color: var(--main);">
                    </div>
                </div>

                <button type="button" class="btn-welcome" onclick="nextStep(3)">המשך לקטגוריות <i class="fa-solid fa-arrow-left"></i></button>
            </div>

            <div class="step" id="step-3">
                <h2 style="font-weight: 800; margin-bottom: 5px;">הקטגוריות שלכם</h2>
                <p style="color: #666; font-size: 0.95rem;">הכנו עבורכם רשימת ברירת מחדל. <strong style="color: #dc2626;">מחקו</strong> את מה שלא רלוונטי עבורכם, ו<strong style="color: var(--main);">הוסיפו</strong> קטגוריות משלכם בסוף הרשימה.</p>
                
                <div class="cat-section-title"><i class="fa-solid fa-arrow-trend-up" style="color: var(--success);"></i> הכנסות</div>
                <div class="cat-suggest-grid" id="income-grid">
                    
                    <div class="cat-option income">
                        <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                        <div class="cat-option-header">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <span class="cat-name">משכורת</span>
                        </div>
                        <input type="hidden" class="cat-type-input" value="income">
                        <input type="hidden" class="cat-icon-input" value="fa-money-bill-wave">
                    </div>

                    <div class="cat-option income">
                        <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                        <div class="cat-option-header">
                            <i class="fa-solid fa-gift"></i>
                            <span class="cat-name">מתנות והחזרים</span>
                        </div>
                        <input type="hidden" class="cat-type-input" value="income">
                        <input type="hidden" class="cat-icon-input" value="fa-gift">
                    </div>

                    <div class="cat-add-btn" onclick="addCustomCategory('income')">
                        <i class="fa-solid fa-plus"></i>
                        <span>הוסף הכנסה</span>
                    </div>
                </div>

                <div class="cat-section-title"><i class="fa-solid fa-arrow-trend-down" style="color: var(--error);"></i> הוצאות</div>
                <div class="cat-suggest-grid" id="expense-grid">
                    
                    <div class="cat-option expense">
                        <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                        <div class="cat-option-header">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span class="cat-name">סופרמרקט</span>
                        </div>
                        <div class="budget-wrapper">
                            <label>יעד חודשי (₪):</label>
                            <div class="budget-input-container">
                                <i class="fa-solid fa-bullseye"></i>
                                <input type="number" class="budget-input" value="2500" min="0">
                            </div>
                        </div>
                        <input type="hidden" class="cat-type-input" value="expense">
                        <input type="hidden" class="cat-icon-input" value="fa-cart-shopping">
                    </div>

                    <div class="cat-option expense">
                        <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                        <div class="cat-option-header">
                            <i class="fa-solid fa-car"></i>
                            <span class="cat-name">תחבורה ודלק</span>
                        </div>
                        <div class="budget-wrapper">
                            <label>יעד חודשי (₪):</label>
                            <div class="budget-input-container">
                                <i class="fa-solid fa-bullseye"></i>
                                <input type="number" class="budget-input" value="800" min="0">
                            </div>
                        </div>
                        <input type="hidden" class="cat-type-input" value="expense">
                        <input type="hidden" class="cat-icon-input" value="fa-car">
                    </div>

                    <div class="cat-option expense">
                        <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                        <div class="cat-option-header">
                            <i class="fa-solid fa-bolt"></i>
                            <span class="cat-name">חשבונות הבית</span>
                        </div>
                        <div class="budget-wrapper">
                            <label>יעד חודשי (₪):</label>
                            <div class="budget-input-container">
                                <i class="fa-solid fa-bullseye"></i>
                                <input type="number" class="budget-input" value="1500" min="0">
                            </div>
                        </div>
                        <input type="hidden" class="cat-type-input" value="expense">
                        <input type="hidden" class="cat-icon-input" value="fa-bolt">
                    </div>

                    <div class="cat-add-btn" onclick="addCustomCategory('expense')">
                        <i class="fa-solid fa-plus"></i>
                        <span>הוסף הוצאה</span>
                    </div>
                </div>

                <div id="welcome-msg" style="margin-top: 15px; display: none; padding: 10px; border-radius: 8px; font-weight: 700;"></div>

                <button type="submit" id="finish-btn" class="btn-welcome"><i class="fa-solid fa-check"></i> סיימנו, בואו נתחיל!</button>
            </div>
        </form>
    </div>

    <script>
        function nextStep(stepNum) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            
            document.querySelectorAll('.dot').forEach(d => d.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        function removeCat(button) {
            button.closest('.cat-option').remove();
        }

        function addCustomCategory(type) {
            const grid = document.getElementById(type + '-grid');
            const addButton = grid.querySelector('.cat-add-btn');
            
            const isExpense = type === 'expense';
            const iconClass = isExpense ? 'fa-tag' : 'fa-coins';
            
            const newCatHTML = `
                <div class="cat-option ${type} custom-added">
                    <button type="button" class="btn-delete-cat" onclick="removeCat(this)"><i class="fa-solid fa-trash-can"></i></button>
                    <div class="cat-option-header">
                        <i class="fa-solid ${iconClass}"></i>
                        <input type="text" class="custom-name-input" placeholder="שם הקטגוריה..." required>
                    </div>
                    ${isExpense ? `
                    <div class="budget-wrapper">
                        <label>יעד חודשי (₪):</label>
                        <div class="budget-input-container">
                            <i class="fa-solid fa-bullseye"></i>
                            <input type="number" class="budget-input" value="0" min="0">
                        </div>
                    </div>
                    ` : ''}
                    <input type="hidden" class="cat-type-input" value="${type}">
                    <input type="hidden" class="cat-icon-input" value="${iconClass}">
                </div>
            `;
            
            // הזרקת הבלוק לפני כפתור הפלוס
            addButton.insertAdjacentHTML('beforebegin', newCatHTML);
            
            // פוקוס אוטומטי על הקטגוריה החדשה שנוצרה
            const newInputs = grid.querySelectorAll('.custom-name-input');
            if (newInputs.length > 0) {
                newInputs[newInputs.length - 1].focus();
            }
        }

        // בניית הנתונים ושליחה לשרת
        document.getElementById('welcome-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('finish-btn');
            const msgBox = document.getElementById('welcome-msg');
            
            // איסוף כל הקטגוריות הקיימות (שלא נמחקו) והן לא כפתורי הוספה
            const allTiles = document.querySelectorAll('.cat-option:not(.cat-add-btn)');
            
            if(allTiles.length === 0) {
                msgBox.style.display = 'block';
                msgBox.style.background = '#fee2e2';
                msgBox.style.color = '#dc2626';
                msgBox.innerText = 'אתם חייבים להשאיר לפחות קטגוריה אחת כדי להתחיל.';
                return;
            }

            // יצירת אובייקט נתונים לשליחה (כדי לתאום ל-PHP הקיים)
            const formData = new FormData();
            formData.append('home_name', document.getElementById('home_name').value);
            formData.append('initial_balance', document.getElementById('initial_balance').value);

            let hasEmptyCustomNames = false;

            allTiles.forEach(tile => {
                let name = '';
                // בדיקה אם זה קטגוריה דינמית שהמשתמש הוסיף או ברירת מחדל
                if (tile.classList.contains('custom-added')) {
                    name = tile.querySelector('.custom-name-input').value.trim();
                    if (name === '') hasEmptyCustomNames = true;
                } else {
                    name = tile.querySelector('.cat-name').innerText;
                }

                const icon = tile.querySelector('.cat-icon-input').value;
                const type = tile.querySelector('.cat-type-input').value;
                
                // חיפוש שדה תקציב (רק להוצאות)
                const budgetInput = tile.querySelector('.budget-input');
                const budget = budgetInput ? budgetInput.value : 0;

                formData.append('cats[]', `${name}|${icon}|${type}`);
                formData.append('budgets[]', budget);
            });

            if(hasEmptyCustomNames) {
                msgBox.style.display = 'block';
                msgBox.style.background = '#fee2e2';
                msgBox.style.color = '#dc2626';
                msgBox.innerText = 'שכחתם למלא שם באחת הקטגוריות שהוספתם.';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מכין את הבית...';
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>app/ajax/setup_welcome.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.href = '<?php echo BASE_URL; ?>index.php';
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.background = '#fee2e2';
                    msgBox.style.color = '#dc2626';
                    msgBox.innerText = data.message;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> נסו שוב';
                }
            })
            .catch(error => {
                msgBox.style.display = 'block';
                msgBox.style.background = '#fee2e2';
                msgBox.style.color = '#dc2626';
                msgBox.innerText = 'שגיאת תקשורת עם השרת.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> נסו שוב';
            });
        });
    </script>
</body>
</html>
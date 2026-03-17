<?php 
require('path.php'); 

include(ROOT_PATH . '/assets/includes/auth_check.php'); 
include(ROOT_PATH . '/app/controllers/users.php'); 

$home_id = $_SESSION['home_id'];

include(ROOT_PATH . '/assets/includes/process_recurring.php');

// קבלת חודש ושנה מה-URL או ברירת מחדל לחודש הנוכחי
$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// הגדרת חודש קודם והבא (לכפתורי הניווט)
$prev_month = $selected_month - 1;
$prev_year = $selected_year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month == 13) { $next_month = 1; $next_year++; }

// מערך שמות חודשים בעברית
$hebrew_months = [
    1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל', 
    5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט', 
    9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
];
$month_name = $hebrew_months[$selected_month];

// 1. שליפת נתוני הבית וחישוב יתרת מציאות (עד היום בלבד!)
$home_data = selectOne('homes', ['id' => $home_id]);
$initial_balance = $home_data['initial_balance'] ?? 0;

// השאילתה הזו מסכמת את כל הפעולות שהתאריך שלהן קטן או שווה להיום (מתעלמת מעתיד)
$real_balance_query = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as net_balance
    FROM transactions 
    WHERE home_id = $home_id AND transaction_date <= CURRENT_DATE()";

$balance_result = mysqli_query($conn, $real_balance_query);
$balance_data = mysqli_fetch_assoc($balance_result);

// יתרת הבנק = יתרת פתיחה + (הכנסות עד היום - הוצאות עד היום)
$current_bank_balance = $initial_balance + $balance_data['net_balance'];

// 2. חישוב הכנסות החודש (מעודכן לדינמי)
$month_income_query = "SELECT SUM(amount) as total FROM transactions 
                       WHERE home_id = $home_id AND type = 'income' 
                       AND MONTH(transaction_date) = $selected_month 
                       AND YEAR(transaction_date) = $selected_year";
$result_income = mysqli_query($conn, $month_income_query);
$income_data = mysqli_fetch_assoc($result_income);
$total_income = $income_data['total'] ?? 0;

// 3. חישוב הוצאות החודש (מעודכן לדינמי)
$month_expense_query = "SELECT SUM(amount) as total FROM transactions 
                        WHERE home_id = $home_id AND type = 'expense' 
                        AND MONTH(transaction_date) = $selected_month 
                        AND YEAR(transaction_date) = $selected_year";
$result_expense = mysqli_query($conn, $month_expense_query);
$expense_data = mysqli_fetch_assoc($result_expense);
$total_expense = $expense_data['total'] ?? 0;

// 4. שליפת פעולות אחרונות (עם סידור חכם: ממתינות תמיד למעלה)
$transactions_query = "SELECT t.*, c.icon as cat_icon 
                       FROM transactions t 
                       LEFT JOIN categories c ON t.category = c.id 
                       WHERE t.home_id = $home_id 
                       AND MONTH(t.transaction_date) = $selected_month 
                       AND YEAR(t.transaction_date) = $selected_year
                       ORDER BY 
                            CASE WHEN t.transaction_date > CURRENT_DATE() THEN 1 ELSE 0 END DESC,
                            CASE WHEN t.transaction_date > CURRENT_DATE() THEN t.transaction_date END ASC,
                            t.transaction_date DESC, 
                            t.created_at DESC 
                       LIMIT 3";
$result_transactions = mysqli_query($conn, $transactions_query);
// 5. שאילתה לשליפת קטגוריות עם סיכום הוצאות חודשי (רק להוצאות!)
$categories_budget_query = "SELECT 
                            c.id, c.name, c.icon, c.budget_limit,
                            COALESCE(SUM(CASE 
                                WHEN t.type = 'expense' 
                                AND MONTH(t.transaction_date) = $selected_month 
                                AND YEAR(t.transaction_date) = $selected_year 
                                THEN t.amount ELSE 0 END), 0) as current_spending
                        FROM categories c
                        LEFT JOIN transactions t ON c.id = t.category AND t.home_id = $home_id
                        WHERE c.home_id = $home_id 
                        AND c.type = 'expense'
                        GROUP BY c.id, c.name, c.icon, c.budget_limit";
$result_categories = mysqli_query($conn, $categories_budget_query);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>התזרים | דף הבית</title>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fa-solid fa-wallet"></i>
                <span>התזרים</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="active"><i class="fa-solid fa-house"></i> דף הבית</a>
                <a href="#"><i class="fa-solid fa-chart-line"></i> דוחות</a>
                <a href="#"><i class="fa-solid fa-house-chimney-user"></i> ניהול הבית</a>
                <hr>
                </nav>
        </aside>

        <main class="main-content">
            
            <header class="top-bar">
                <div class="header-right">
                    <div class="mobile-menu-btn"><i class="fa-solid fa-bars"></i></div>
                    
                    <div class="user-profile-section">
                        <div class="user-avatar">
                            <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['first_name']; ?>&background=237227&color=fff" alt="פרופיל">
                        </div>
                        <div class="user-details-text">
                            <span class="welcome-text">ברוכים הבאים!</span>
                            <h3 class="user-name"><?php echo $_SESSION['first_name']; ?> (<?php echo $_SESSION['nickname'] ?? 'התותח'; ?>)</h3>
                            <span class="home-name-sub"><?php echo $home_data['name']; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-left">
                    <div class="action-icons">
                        <div class="icon-btn notification-wrapper" title="הודעות מערכת">
                            <i class="fa-solid fa-bell"></i>
                            <span class="notification-badge"></span>
                        </div>
                        <a href="logout.php" class="icon-btn logout-btn-top" title="התנתקות">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
    
                <?php 
                // בדיקה האם אנחנו כרגע מסתכלים על החודש הנוכחי האמיתי
                $is_current_month = ($selected_month == date('m') && $selected_year == date('Y')); 
                ?>

                <div class="page-header-actions flex-between" style="margin-bottom: 25px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <h1 class="section-title" style="margin-bottom: 0;">סיכום חודשי</h1>
                        
                        <?php if (!$is_current_month): ?>
                            <a href="index.php" class="btn-return-today">
                                <i class="fa-solid fa-calendar-day"></i> הנוכחי
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="month-selector">
                        <a href="?m=<?php echo $prev_month; ?>&y=<?php echo $prev_year; ?>" class="month-btn"><i class="fa-solid fa-chevron-right"></i></a>
                        
                        <div class="current-month-display">
                            <i class="fa-regular fa-calendar"></i> <?php echo $month_name . ' ' . $selected_year; ?>
                        </div>
                        
                        <a href="?m=<?php echo $next_month; ?>&y=<?php echo $next_year; ?>" class="month-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card balance">
                        <label>יתרת מציאות (בנק)</label>
                        <div class="amount"><?php echo number_format($current_bank_balance, 2); ?> ₪</div>
                    </div>
                    <div class="stat-card income">
                        <label>הכנסות החודש</label>
                        <div class="amount">+ <?php echo number_format($total_income, 2); ?> ₪</div>
                    </div>
                    <div class="stat-card expenses">
                        <label>הוצאות החודש</label>
                        <div class="amount">- <?php echo number_format($total_expense, 2); ?> ₪</div>
                    </div>
                </div>

                <section class="budget-section">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin: 30px 0 20px;">מעקב תקציב לפי קטגוריה</h2>
                    
                    <div class="category-grid">
                        <?php while($cat = mysqli_fetch_assoc($result_categories)): 
                            // חישוב אחוז ניצול התקציב
                            $budget = $cat['budget_limit'];
                            $spent = $cat['current_spending'];
                            $percent = ($budget > 0) ? min(($spent / $budget) * 100, 100) : 0;
                            $is_over_budget = ($budget > 0 && $spent > $budget);
                        ?>
                            <div class="category-card <?php echo $is_over_budget ? 'over-budget' : ''; ?>">
                                <div class="cat-card-header">
                                    <div class="cat-icon-circle">
                                        <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>"></i>
                                    </div>
                                    <span class="cat-name"><?php echo $cat['name']; ?></span>
                                </div>

                                <div class="cat-card-body">
                                    <div class="spending-info">
                                        <span class="spent-amount"><?php echo number_format($spent, 2); ?> ₪</span>
                                        <span class="budget-total">
                                            <?php echo ($budget > 0) ? "מתוך " . number_format($budget, 0) . " ₪" : "אין תקציב מוגדר"; ?>
                                        </span>
                                    </div>

                                    <?php if($budget > 0): ?>
                                        <div class="percent-label" style="text-align: left; font-size: 0.8rem; font-weight: 700; margin-bottom: 4px; color: <?php echo $is_over_budget ? 'var(--error)' : 'var(--main)'; ?>;">
                                            <?php 
                                                // חישוב האחוז האמיתי (גם מעל 100)
                                                $real_percent = round(($spent / $budget) * 100); 
                                                echo $real_percent . "%"; 
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?php echo ($budget > 0) ? min($percent, 100) : '0'; ?>%;"></div>
                                    </div>
                                </div>

                                <div class="cat-card-footer" style="margin-top: 15px; border-top: 1px solid #f0f0f0; padding-top: 10px;">
                                    <button class="btn-cat-details" onclick="loadCategoryDetails(<?php echo $cat['id']; ?>, '<?php echo $cat['name']; ?>')">
                                        פירוט <i class="fa-solid fa-chevron-left" style="font-size: 0.7rem; margin-right: 5px;"></i>
                                    </button>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    </div>
                </section>

                <section class="ai-advisor-section" style="margin-bottom: 40px;">
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <div class="ai-icon-wrapper">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                            <h2 class="ai-title">תובנות חכמות מ-Gemini</h2>
                        </div>
                        <div class="ai-card-body" id="ai-insight-content">
                            <p class="ai-intro-text">לחצו על הכפתור כדי לקבל ניתוח חכם של קצב ההוצאות שלכם החודש (Burn Rate).</p>
                            <button id="btn-generate-insight" class="btn-ai-generate" onclick="generateAIInsight()">
                                <i class="fa-solid fa-robot"></i> נתח את החודש שלי עכשיו
                            </button>
                        </div>
                    </div>
                </section>

                <div class="transactions-section">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin-bottom: 20px;">פעולות אחרונות</h2>

                    <div id="transactions-list">
                        <?php if (mysqli_num_rows($result_transactions) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result_transactions)): 
                                // בדיקה האם הפעולה היא בעתיד
                                $is_future = strtotime($row['transaction_date']) > strtotime(date('Y-m-d'));
                                $pending_class = $is_future ? 'pending-trans' : '';
                                $display_icon = $is_future ? 'fa-regular fa-clock' : ($row['cat_icon'] ?: 'fa-tag');
                            ?>
                                <div class="transaction-item <?php echo $row['type']; ?> <?php echo $pending_class; ?>">
                                    <div class="transaction-info">
                                        <div class="cat-icon-wrapper">
                                            <i class="fa-solid <?php echo $display_icon; ?>"></i>
                                        </div>
                                        <div class="details">
                                            <span class="desc">
                                                <?php echo $row['description']; ?>
                                                <?php if($is_future) echo '<span style="font-size:0.7rem; background:#eee; padding:2px 6px; border-radius:10px; margin-right:5px; color: #777;">ממתין</span>'; ?>
                                            </span>
                                            <span class="date"><?php echo date('d/m/Y', strtotime($row['transaction_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="transaction-amount">
                                        <?php echo ($row['type'] == 'income') ? '+' : '-'; ?> <?php echo number_format($row['amount'], 2); ?> ₪
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
                                <i class="fa-solid fa-receipt" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
                                <p style="color: var(--text-light);">עדיין אין פעולות. לחצו על ה- + כדי להתחיל.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (mysqli_num_rows($result_transactions) >= 3): ?>
                        <button id="loadMoreBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                           הצג עוד...
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <button class="floating-btn"><i class="fa-solid fa-plus"></i></button>
    </div>

    <div id="category-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="selected-cat-name"></h3>
                <button onclick="closeCatDetails()" class="close-modal-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div id="cat-details-content">
                    </div>
            </div>
        </div>
    </div>

    <?php 
    // שליפת כל הקטגוריות של הבית כדי שה-JS יוכל לסנן אותן
    $all_cats_query = "SELECT id, name, type FROM categories WHERE home_id = $home_id";
    $all_cats_result = mysqli_query($conn, $all_cats_query);
    $categories_array = [];
    while($cat = mysqli_fetch_assoc($all_cats_result)) {
        $categories_array[] = $cat;
    }
    ?>

    <?php 
    // הוספנו את ה-icon לשאילתה כדי שה-JS יוכל להציג אותו בריבועים!
    $all_cats_query = "SELECT id, name, type, icon FROM categories WHERE home_id = $home_id";
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
                <button type="button" onclick="closeAddModal()" class="close-modal-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-transaction-form">
                    
                    <div class="modern-toggle">
                        <input type="radio" name="type" id="type-expense" value="expense" checked onchange="filterCategories()">
                        <label for="type-expense" class="toggle-option expense">הוצאה (-)</label>
                        
                        <input type="radio" name="type" id="type-income" value="income" onchange="filterCategories()">
                        <label for="type-income" class="toggle-option income">הכנסה (+)</label>
                    </div>

                    <div class="input-group">
                        <label>סכום (₪)</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-shekel-sign"></i>
                            <input type="number" name="amount" id="trans-amount" step="0.01" min="0.01" required placeholder="0.00" style="font-size: 1.2rem; font-weight: 800;">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>קטגוריה</label>
                        <div class="category-grid-selector" id="category-grid-container">
                            </div>
                        <input type="hidden" name="category_id" id="selected-category-id" required>
                    </div>

                    <div class="input-group">
                        <label>תיאור הפעולה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" name="description" id="trans-desc" required placeholder="למשל: קניות בסופר" pattern=".*\S+.*" title="לא ניתן להזין רק רווחים">
                        </div>
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

</body>

<script>
    // שמירת החודש והשנה שנבחרו במשתני JS
    const currentMonth = <?php echo $selected_month; ?>;
    const currentYear = <?php echo $selected_year; ?>;

    // --- תפריט מובייל ---
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');

    menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    });

    // --- AJAX טען עוד (פעולות אחרונות) ---
    let offset = 3; 
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const transactionsList = document.getElementById('transactions-list');

    if (loadMoreBtn && transactionsList) {
        loadMoreBtn.addEventListener('click', function() {
            if (loadMoreBtn.getAttribute('data-state') === 'expanded') {
                collapseTransactions();
                return;
            }

            loadMoreBtn.innerText = 'טוען...';
            loadMoreBtn.disabled = true;
            
            // הוספנו את החודש והשנה ל-URL
            fetch(`app/ajax/fetch_transactions.php?offset=${offset}&m=${currentMonth}&y=${currentYear}`)
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === "NO_MORE") {
                        loadMoreBtn.innerText = 'סגור רשימה';
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.setAttribute('data-state', 'expanded');
                        loadMoreBtn.style.backgroundColor = 'var(--gray)';
                    } else {
                        const wrappedData = `<div class="ajax-loaded">${data}</div>`;
                        transactionsList.insertAdjacentHTML('beforeend', wrappedData);
                        offset += 3;
                        loadMoreBtn.innerText = 'הצג עוד...';
                        loadMoreBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadMoreBtn.innerText = 'שגיאה בטעינה';
                    loadMoreBtn.disabled = false;
                });
        });
    }

    function collapseTransactions() {
        const loadedItems = document.querySelectorAll('.ajax-loaded');
        loadedItems.forEach(item => item.remove());
        offset = 3;
        loadMoreBtn.innerText = 'הצג עוד...';
        loadMoreBtn.removeAttribute('data-state');
        loadMoreBtn.style.backgroundColor = 'var(--white)';
        transactionsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // --- AJAX פירוט קטגוריות ---
    function loadCategoryDetails(catId, catName) {
        const modal = document.getElementById('category-details-modal');
        const content = document.getElementById('cat-details-content');
        const title = document.getElementById('selected-cat-name');

        modal.style.display = 'block';
        title.innerText = 'פירוט הוצאות: ' + catName;
        content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> טוען נתונים...</div>';

        // הוספנו את החודש והשנה
        fetch(`app/ajax/fetch_category_details.php?cat_id=${catId}&m=${currentMonth}&y=${currentYear}`)
            .then(response => response.text())
            .then(data => {
                content.innerHTML = data;
            });
    }

    function closeCatDetails() {
        document.getElementById('category-details-modal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('category-details-modal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // --- AJAX היועץ החכם (Gemini) ---
    function generateAIInsight() {
        const btn = document.getElementById('btn-generate-insight');
        const content = document.getElementById('ai-insight-content');

        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מנתח נתונים, אנא המתן...';
        btn.disabled = true;

        // הוספנו את החודש והשנה
        fetch(`app/ajax/generate_ai_insight.php?m=${currentMonth}&y=${currentYear}`)
            .then(response => response.text())
            .then(data => {
                content.innerHTML = `<div class="ai-result-text">${data}</div>`;
            })
            .catch(error => {
                content.innerHTML = '<p style="color: var(--error);">שגיאה בתקשורת עם היועץ החכם.</p>';
            });
    }
</script>

<script>
    // --- ניהול מודאל הוספת פעולה ---
    const addModal = document.getElementById('add-transaction-modal');
    const floatingBtn = document.querySelector('.floating-btn');
    const addForm = document.getElementById('add-transaction-form');
    
    // העברת הקטגוריות מה-PHP ל-JavaScript
    const allCategories = <?php echo json_encode($categories_array); ?>;

    // פתיחת המודאל
    floatingBtn.addEventListener('click', () => {
        addModal.style.display = 'block';
        resetAddForm(); // איפוס תמיד בפתיחה
    });

    // סגירת המודאל (רק מה-X, לא מלחיצה בחוץ)
    function closeAddModal() {
        addModal.style.display = 'none';
        resetAddForm();
    }

    // פונקציית איפוס הטופס
    function resetAddForm() {
        addForm.reset();
        document.getElementById('type-expense').checked = true; // חזרה להוצאה
        document.getElementById('trans-date').value = "<?php echo date('Y-m-d'); ?>"; // תאריך של היום
        document.getElementById('selected-category-id').value = ""; // איפוס הקטגוריה הנסתרת
        document.getElementById('add-trans-msg').style.display = 'none';
        
        const submitBtn = document.getElementById('submit-trans-btn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
        
        filterCategories(); // ציור הריבועים מחדש
    }

    // ציור ריבועי הקטגוריות דינמית
    function filterCategories() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        const gridContainer = document.getElementById('category-grid-container');
        const hiddenInput = document.getElementById('selected-category-id');
        
        gridContainer.innerHTML = ''; // מחיקת הריבועים הקודמים
        hiddenInput.value = ''; // איפוס הבחירה

        allCategories.forEach(cat => {
            if (cat.type === selectedType) {
                // יצירת עטיפת הלייבל (הריבוע עצמו)
                const label = document.createElement('label');
                label.className = 'cat-tile';
                
                // כפתור הרדיו הנסתר
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'cat_tile_selection'; 
                radio.value = cat.id;
                
                // עדכון השדה הנסתר כשתלחץ על הריבוע
                radio.addEventListener('change', function() {
                    hiddenInput.value = this.value;
                });

                // תוכן הריבוע (אייקון + טקסט)
                const tileContent = document.createElement('div');
                tileContent.className = 'tile-content';
                
                const iconClass = cat.icon ? cat.icon : 'fa-tag';
                tileContent.innerHTML = `
                    <div class="cat-icon"><i class="fa-solid ${iconClass}"></i></div>
                    <span class="cat-name-label">${cat.name}</span>
                `;

                label.appendChild(radio);
                label.appendChild(tileContent);
                gridContainer.appendChild(label);
            }
        });
    }

    // אירוע שליחת הטופס (AJAX)
    addForm.addEventListener('submit', function(e) {
        e.preventDefault(); // מניעת רענון דף רגיל
        
        const submitBtn = document.getElementById('submit-trans-btn');
        const msgBox = document.getElementById('add-trans-msg');
        
        // נעילת כפתור למניעת כפילויות
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר נתונים...';
        msgBox.style.display = 'none';

        // איסוף הנתונים
        const formData = new FormData(addForm);

        // שליחה לשרת
        fetch('app/ajax/add_transaction.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // הודעת הצלחה
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = 'var(--sub_main-light)';
                msgBox.style.color = 'var(--main)';
                msgBox.innerText = 'הפעולה נוספה בהצלחה!';
                
                // המתנה קצרה ורענון הדף כדי להציג את הנתונים החדשים
                setTimeout(() => {
                    closeAddModal();
                    window.location.reload(); 
                }, 800);
            } else {
                // הודעת שגיאה
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = data.message || 'אירעה שגיאה בשמירת הנתונים.';
                
                // שחרור הכפתור כדי לנסות שוב
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

    // וודא שחוק הסגירה בלחיצה בחוץ חל רק על המודאל של הקטגוריות, ולא על ההוספה!
    window.onclick = function(event) {
        const catDetailsModal = document.getElementById('category-details-modal');
        // שים לב: אנחנו בודקים פה רק את המודאל של פרטי הקטגוריה
        if (event.target == catDetailsModal) {
            catDetailsModal.style.display = "none";
        }
    }
</script>

</html>
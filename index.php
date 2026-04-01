<?php 
require('path.php'); 

include(ROOT_PATH . '/app/controllers/users.php'); 
include(ROOT_PATH . '/assets/includes/auth_check.php'); 

$home_id = $_SESSION['home_id'];

include(ROOT_PATH . '/assets/includes/process_recurring.php');

// --- ניהול חודש ושנה עם זיכרון בסשן ואבטחה ---
if (isset($_GET['m']) && isset($_GET['y'])) {
    $selected_month = (int)$_GET['m'];
    $selected_year = (int)$_GET['y'];
    
    // אבטחת תאריכים
    if ($selected_month < 1 || $selected_month > 12) { $selected_month = (int)date('m'); }
    if ($selected_year < 2000 || $selected_year > 2100) { $selected_year = (int)date('Y'); }

    // שמירה בזיכרון
    $_SESSION['view_month'] = $selected_month;
    $_SESSION['view_year'] = $selected_year;
} else {
    // משיכה מהזיכרון, ואם אין - ברירת מחדל לחודש הנוכחי
    $selected_month = $_SESSION['view_month'] ?? (int)date('m');
    $selected_year = $_SESSION['view_year'] ?? (int)date('Y');
}

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

// 1. שליפת נתוני הבית וחישוב יתרת מציאות
$home_data = selectOne('homes', ['id' => $home_id]);
$initial_balance = $home_data['initial_balance'] ?? 0;

// לוגיקה שמרנית: הכנסות נספרות רק עד היום (כולל), הוצאות נספרות תמיד (כולל עתידיות)
$real_balance_query = "SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date <= '$today_il' THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as net_balance
    FROM transactions 
    WHERE home_id = $home_id";

$balance_result = mysqli_query($conn, $real_balance_query);
$balance_data = mysqli_fetch_assoc($balance_result);
$current_bank_balance = $initial_balance + $balance_data['net_balance'];

// 2. חישוב הכנסות החודש
$month_income_query = "SELECT SUM(amount) as total FROM transactions 
                       WHERE home_id = $home_id AND type = 'income' 
                       AND MONTH(transaction_date) = $selected_month 
                       AND YEAR(transaction_date) = $selected_year";
$result_income = mysqli_query($conn, $month_income_query);
$income_data = mysqli_fetch_assoc($result_income);
$total_income = $income_data['total'] ?? 0;

// 3. חישוב הוצאות החודש
$month_expense_query = "SELECT SUM(amount) as total FROM transactions 
                        WHERE home_id = $home_id AND type = 'expense' 
                        AND MONTH(transaction_date) = $selected_month 
                        AND YEAR(transaction_date) = $selected_year";
$result_expense = mysqli_query($conn, $month_expense_query);
$expense_data = mysqli_fetch_assoc($result_expense);
$total_expense = $expense_data['total'] ?? 0;

$limit = 4; // כמות ראשונית להצגה

// 1. שאילתת ממתינות (עתידיות) - מוגבל ל-4
$pending_query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
                  FROM transactions t 
                  LEFT JOIN categories c ON t.category = c.id 
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE t.home_id = $home_id 
                  AND t.transaction_date > '$today_il'
                  AND MONTH(t.transaction_date) = $selected_month 
                  AND YEAR(t.transaction_date) = $selected_year
                  ORDER BY t.transaction_date ASC, t.created_at ASC
                  LIMIT $limit";
$pending_result = mysqli_query($conn, $pending_query);

// 2. שאילתת אחרונות (עבר) - מוגבל ל-4
$recent_query = "SELECT t.*, c.icon as cat_icon, u.first_name as user_name 
                 FROM transactions t 
                 LEFT JOIN categories c ON t.category = c.id 
                 LEFT JOIN users u ON t.user_id = u.id
                 WHERE t.home_id = $home_id 
                 AND t.transaction_date <= '$today_il'
                 AND MONTH(t.transaction_date) = $selected_month 
                 AND YEAR(t.transaction_date) = $selected_year
                 ORDER BY t.transaction_date DESC, t.created_at DESC 
                 LIMIT $limit";
$recent_result = mysqli_query($conn, $recent_query);

/// 5. שאילתה לשליפת קטגוריות עם סיכום הוצאות חודשי - ממוין מהגבוה לנמוך
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
                        AND c.is_active = 1
                        GROUP BY c.id, c.name, c.icon, c.budget_limit
                        ORDER BY current_spending DESC"; // הוספת המיון כאן
$result_categories = mysqli_query($conn, $categories_budget_query);
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

                <div class="page-header-actions flex-between" style="margin-bottom: 25px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <h1 class="section-title" style="margin-bottom: 0;">סיכום חודשי</h1>
                        
                        <?php if (!$is_current_month): ?>
                            <a href="index.php?m=<?php echo date('m'); ?>&y=<?php echo date('Y'); ?>" class="btn-return-today">
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
                    <?php if ($initial_balance != 0): ?>
                        <div class="stat-card balance">
                            <label>
                                יתרה בחשבון
                                <?php 
                                    $info_label = "יתרה בחשבון";
                                    $info_key = "real_balance"; // המזהה מהמסד
                                    include(ROOT_PATH . '/assets/includes/info_label.php'); 
                                ?>
                            </label>
                            <div class="amount"><?php echo number_format($current_bank_balance, 0); ?> ₪</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stat-card income">
                        <label>
                            הכנסות החודש
                            <?php 
                                $info_label = "הכנסות החודש";
                                $info_key = "month_income"; 
                                include(ROOT_PATH . '/assets/includes/info_label.php'); 
                            ?>
                        </label>
                        <div class="amount">+ <?php echo number_format($total_income, 0); ?> ₪</div>
                    </div>
                    
                    <div class="stat-card expenses">
                        <label>
                            הוצאות החודש
                            <?php 
                                $info_label = "הוצאות החודש";
                                $info_key = "month_expenses"; // המזהה מהמסד
                                include(ROOT_PATH . '/assets/includes/info_label.php'); 
                            ?>
                        </label>
                        <div class="amount">- <?php echo number_format($total_expense, 0); ?> ₪</div>
                    </div>
                </div>

                <?php if (mysqli_num_rows($pending_result) > 0): ?>
                <div class="transactions-section" style="margin-bottom: 30px;">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        פעולות ממתינות
                    </h2>

                    <div id="pending-transactions-list">
                        <?php while($row = mysqli_fetch_assoc($pending_result)): ?>
                            <div class="transaction-item <?php echo $row['type']; ?> pending-trans">
                                <div class="transaction-info">
                                    <div class="cat-icon-wrapper">
                                        <i class="fa-regular fa-clock"></i> </div>
                                    <div class="details">
                                        <span class="desc">
                                            <?php echo $row['description']; ?>
                                            <?php if($row['user_name']) echo "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>({$row['user_name']})</span>"; ?>
                                        </span>
                                        <span class="date"><?php echo date('d/m/Y', strtotime($row['transaction_date'])); ?></span>
                                    </div>
                                </div>
                                <div class="transaction-actions">
                                    <div class="transaction-amount">
                                        <?php echo ($row['type'] == 'income') ? '+' : '-'; ?> <?php echo number_format($row['amount'], 0); ?> ₪
                                    </div>
                                    <div style="display:flex; gap: 5px;">
                                        <button onclick="openEditTransModal(<?php echo $row['id']; ?>, <?php echo $row['amount']; ?>, <?php echo $row['category']; ?>, '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>', '<?php echo $row['type']; ?>')" style="background: var(--gray); border: none; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="ערוך פעולה">
                                            <i class="fa-solid fa-pen" style="font-size: 1rem;"></i>
                                        </button>
                                        <button onclick="deleteTransaction(<?php echo $row['id']; ?>)" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק פעולה">
                                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if (mysqli_num_rows($pending_result) == 4): ?>
                        <button id="loadMorePendingBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                        הצג עוד ממתינות...
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>


                <div class="transactions-section">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin-bottom: 20px;">פעולות אחרונות</h2>

                    <div id="recent-transactions-list">
                        <?php if (mysqli_num_rows($recent_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($recent_result)): ?>
                                <div class="transaction-item <?php echo $row['type']; ?>">
                                    <div class="transaction-info">
                                        <div class="cat-icon-wrapper">
                                            <i class="fa-solid <?php echo $row['cat_icon'] ?: 'fa-tag'; ?>"></i> </div>
                                        <div class="details">
                                            <span class="desc">
                                                <?php echo $row['description']; ?>
                                                <?php if($row['user_name']) echo "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>({$row['user_name']})</span>"; ?>
                                            </span>
                                            <span class="date"><?php echo date('d/m/Y', strtotime($row['transaction_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="transaction-actions">
                                        <div class="transaction-amount">
                                            <?php echo ($row['type'] == 'income') ? '+' : '-'; ?> <?php echo number_format($row['amount'], 0); ?> ₪
                                        </div>
                                        <div style="display:flex; gap: 5px;">
                                            <button onclick="openEditTransModal(<?php echo $row['id']; ?>, <?php echo $row['amount']; ?>, <?php echo $row['category']; ?>, '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>', '<?php echo $row['type']; ?>')" style="background: var(--gray); border: none; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="ערוך פעולה">
                                                <i class="fa-solid fa-pen" style="font-size: 1rem;"></i>
                                            </button>
                                            <button onclick="deleteTransaction(<?php echo $row['id']; ?>)" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק פעולה">
                                                <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                                            </button>
                                        </div>
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

                    <?php if (mysqli_num_rows($recent_result) == 4): ?>
                        <button id="loadMoreBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                        הצג עוד...
                        </button>
                    <?php endif; ?>
                </div>

                <section class="budget-section">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin: 30px 0 20px;">מעקב תקציב לפי קטגוריה</h2>
                    
                    <div class="category-grid">
                        <?php while($cat = mysqli_fetch_assoc($result_categories)): 
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
                                        <span class="spent-amount"><?php echo number_format($spent, 0); ?> ₪</span>
                                        <span class="budget-total">
                                            <?php echo ($budget > 0) ? "מתוך " . number_format($budget, 0) . " ₪" : "אין תקציב מוגדר"; ?>
                                        </span>
                                    </div>

                                    <?php if($budget > 0): ?>
                                        <div class="percent-label" style="text-align: left; font-size: 0.8rem; font-weight: 700; margin-bottom: 4px; color: <?php echo $is_over_budget ? 'var(--error)' : 'var(--main)'; ?>;">
                                            <?php 
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
                        <label>תיאור הפעולה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" name="description" id="trans-desc" required placeholder="למשל: קניות בסופר" pattern=".*\S+.*" title="לא ניתן להזין רק רווחים">
                        </div>
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
                <button type="button" onclick="closeEditTransModal()" class="close-modal-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-transaction-form">
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
                        <div class="category-grid-selector" id="edit-category-grid-container"></div>
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

</body>

<script>
    const currentMonth = <?php echo $selected_month; ?>;
    const currentYear = <?php echo $selected_year; ?>;

    // ניהול מונים נפרדים לשני האזורים
    let recentOffset = 4;
    let pendingOffset = 4;

    // 1. מאזין לכפתור טעינת פעולות אחרונות
    document.getElementById('loadMoreBtn')?.addEventListener('click', function() {
        if (this.getAttribute('data-state') === 'expanded') {
            collapseTransactions('recent', 'recent-transactions-list', this);
            return;
        }
        loadTransactions('recent', recentOffset, 'recent-transactions-list', this);
    });

    // 2. מאזין לכפתור טעינת פעולות ממתינות
    document.getElementById('loadMorePendingBtn')?.addEventListener('click', function() {
        if (this.getAttribute('data-state') === 'expanded') {
            collapseTransactions('pending', 'pending-transactions-list', this);
            return;
        }
        loadTransactions('pending', pendingOffset, 'pending-transactions-list', this);
    });

    // פונקציה חכמה אחת שטוענת נתונים לפי סוג (status)
    function loadTransactions(status, offset, containerId, btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> טוען...';
        btn.disabled = true;
        
        const url = `app/ajax/fetch_transactions.php?offset=${offset}&status=${status}&m=${currentMonth}&y=${currentYear}`;
        
        fetch(url)
            .then(res => res.text())
            .then(html => {
                if (html.trim() === "" || html.trim() === "NO_MORE") {
                    setButtonToExpanded(btn);
                } else {
                    // עוטפים את הנתונים החדשים בקלאס ייעודי כדי שנוכל למחוק רק אותם בסגירה
                    const wrappedHtml = `<div class="ajax-loaded-${status}">${html}</div>`;
                    document.getElementById(containerId).insertAdjacentHTML('beforeend', wrappedHtml);
                    
                    // מקדמים את המונה רק אחרי טעינה מוצלחת
                    if (status === 'recent') recentOffset += 4;
                    if (status === 'pending') pendingOffset += 4;

                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    // בודקים אם הגענו לסוף הרשימה (פחות מ-4 פעולות חזרו)
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

    // פונקציה שהופכת את הכפתור ל"סגור רשימה"
    function setButtonToExpanded(btn) {
        btn.innerText = 'סגור רשימה';
        btn.disabled = false;
        btn.setAttribute('data-state', 'expanded');
        btn.style.backgroundColor = 'var(--gray)';
    }

    // פונקציה שכווצת חזרה רשימה ספציפית
    function collapseTransactions(status, containerId, btn) {
        // מחיקת כל הבלוקים שנטענו ב-AJAX עבור הסטטוס הספציפי
        const loadedItems = document.querySelectorAll(`.ajax-loaded-${status}`);
        loadedItems.forEach(item => item.remove());
        
        // איפוס המונה הרלוונטי
        if (status === 'recent') recentOffset = 4;
        if (status === 'pending') pendingOffset = 4;
        
        // החזרת הכפתור למצבו המקורי
        btn.innerHTML = status === 'pending' ? 'הצג עוד ממתינות...' : 'הצג עוד...';
        btn.removeAttribute('data-state');
        btn.style.backgroundColor = ''; // מחזיר לעיצוב הרגיל של ה-CSS
        
        // גלילה חלקה חזרה לראש האזור
        document.getElementById(containerId).parentElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function loadCategoryDetails(catId, catName) {
        const modal = document.getElementById('category-details-modal');
        const content = document.getElementById('cat-details-content');
        const title = document.getElementById('selected-cat-name');

        modal.style.display = 'block';
        title.innerText = 'פירוט הוצאות: ' + catName;
        content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> טוען נתונים...</div>';

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

    function generateAIInsight() {
        const btn = document.getElementById('btn-generate-insight');
        const content = document.getElementById('ai-insight-content');

        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מנתח נתונים, אנא המתן...';
        btn.disabled = true;

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
    const addModal = document.getElementById('add-transaction-modal');
    const floatingBtn = document.querySelector('.floating-btn');
    const addForm = document.getElementById('add-transaction-form');
    
    const allCategories = <?php echo json_encode($categories_array); ?>;

    floatingBtn.addEventListener('click', () => {
        addModal.style.display = 'block';
        resetAddForm(); 
    });

    function closeAddModal() {
        addModal.style.display = 'none';
        resetAddForm();
    }

    function resetAddForm() {
        addForm.reset();
        document.getElementById('type-expense').checked = true; 
        document.getElementById('trans-date').value = "<?php echo date('Y-m-d'); ?>"; 
        document.getElementById('selected-category-id').value = ""; 
        document.getElementById('add-trans-msg').style.display = 'none';
        
        const submitBtn = document.getElementById('submit-trans-btn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> הוסף פעולה';
        
        filterCategories(); 
    }

    function filterCategories() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        const gridContainer = document.getElementById('category-grid-container');
        const hiddenInput = document.getElementById('selected-category-id');
        
        gridContainer.innerHTML = ''; 
        hiddenInput.value = ''; 

        allCategories.forEach(cat => {
            if (cat.type === selectedType) {
                const label = document.createElement('label');
                label.className = 'cat-tile';
                
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'cat_tile_selection'; 
                radio.value = cat.id;
                
                radio.addEventListener('change', function() {
                    hiddenInput.value = this.value;
                });

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

    addForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        const submitBtn = document.getElementById('submit-trans-btn');
        const msgBox = document.getElementById('add-trans-msg');
        
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
                    window.location.reload(); 
                }, 800);
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

    window.onclick = function(event) {
        const catDetailsModal = document.getElementById('category-details-modal');
        if (event.target == catDetailsModal) {
            catDetailsModal.style.display = "none";
        }
    }
</script>

<script>
    function deleteTransaction(id) {
        if(confirm('האם אתה בטוח שברצונך למחוק פעולה זו? התקציב ויתרת הבנק יעודכנו בהתאם.')) {
            const formData = new FormData();
            formData.append('id', id);

            fetch('app/ajax/delete_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.reload(); // רענון כדי לעדכן את כל הגרפים והמספרים
                } else {
                    alert('שגיאה במחיקה: ' + (data.message || 'אירעה שגיאה.'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('שגיאת תקשורת.');
            });
        }
    }
</script>

<script>
    // === לוגיקת עריכת פעולה ===
    const editModal = document.getElementById('edit-transaction-modal');
    const editForm = document.getElementById('edit-transaction-form');

    function openEditTransModal(id, amount, categoryId, desc, type) {
        document.getElementById('edit-trans-id').value = id;
        document.getElementById('edit-trans-amount').value = amount;
        document.getElementById('edit-trans-desc').value = desc;
        document.getElementById('edit-trans-type').value = type;
        document.getElementById('edit-selected-category-id').value = categoryId;
        
        // יצירת גריד הקטגוריות בהתאם לסוג (הוצאה/הכנסה)
        const gridContainer = document.getElementById('edit-category-grid-container');
        gridContainer.innerHTML = '';
        
        allCategories.forEach(cat => {
            if (cat.type === type) {
                const label = document.createElement('label');
                label.className = 'cat-tile';
                
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'edit_cat_tile_selection'; 
                radio.value = cat.id;
                if(cat.id == categoryId) radio.checked = true;
                
                radio.addEventListener('change', function() {
                    document.getElementById('edit-selected-category-id').value = this.value;
                });

                const iconClass = cat.icon ? cat.icon : 'fa-tag';
                label.innerHTML = `
                    <div class="tile-content">
                        <div class="cat-icon"><i class="fa-solid ${iconClass}"></i></div>
                        <span class="cat-name-label">${cat.name}</span>
                    </div>
                `;
                label.insertBefore(radio, label.firstChild);
                gridContainer.appendChild(label);
            }
        });

        editModal.style.display = 'block';
    }

    function closeEditTransModal() {
        editModal.style.display = 'none';
        document.getElementById('edit-trans-msg').style.display = 'none';
    }

    editForm.addEventListener('submit', function(e) {
        e.preventDefault(); 
        const submitBtn = document.getElementById('submit-edit-trans-btn');
        const msgBox = document.getElementById('edit-trans-msg');
        
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
                setTimeout(() => { window.location.reload(); }, 800);
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

    // סגירת הפופאפ בלחיצה בחוץ
    window.addEventListener('click', function(event) {
        if (event.target == editModal) {
            closeEditTransModal();
        }
    });
</script>
</html>


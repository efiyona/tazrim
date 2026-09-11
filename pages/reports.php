<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

require_once ROOT_PATH . '/assets/includes/user_css_href.php';

$home_id = $_SESSION['home_id'];
require_once ROOT_PATH . '/app/functions/payment_methods.php';
$payment_methods = tazrim_payment_methods((int)$home_id, true);
$home_data = selectOne('homes', ['id' => $home_id]);

// --- ניהול חודשים ותאריכים עם זיכרון בסשן ואבטחה ---
if (isset($_GET['m']) && isset($_GET['y'])) {
    $current_month = (int)$_GET['m'];
    $current_year = (int)$_GET['y'];
    
    // אבטחת תאריכים
    if ($current_month < 1 || $current_month > 12) { $current_month = (int)date('m'); }
    if ($current_year < 2000 || $current_year > 2100) { $current_year = (int)date('Y'); }

    // שמירה בזיכרון (משתמשים באותם שמות משתנים לסשן כמו בדף הבית)
    $_SESSION['view_month'] = $current_month;
    $_SESSION['view_year'] = $current_year;
} else {
    $current_month = $_SESSION['view_month'] ?? (int)date('m');
    $current_year = $_SESSION['view_year'] ?? (int)date('Y');
}

$start_date = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month == 13) { $next_month = 1; $next_year++; }

$today_m = (int) date('m');
$today_y = (int) date('Y');

$month_names = ["", "ינואר", "פברואר", "מרץ", "אפריל", "מאי", "יוני", "יולי", "אוגוסט", "ספטמבר", "אוקטובר", "נובמבר", "דצמבר"];

// === שליפת נתוני KPI ===
$exp_query = "SELECT SUM(amount) as total FROM transactions WHERE home_id = $home_id AND type = 'expense' AND transaction_date BETWEEN '$start_date' AND '$end_date'";
$exp_result = mysqli_query($conn, $exp_query);
if (!$exp_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 1:</strong> " . mysqli_error($conn) . "</div>");
$total_expenses = mysqli_fetch_assoc($exp_result)['total'] ?? 0;

$inc_query = "SELECT SUM(amount) as total FROM transactions WHERE home_id = $home_id AND type = 'income' AND transaction_date BETWEEN '$start_date' AND '$end_date'";
$inc_result = mysqli_query($conn, $inc_query);
if (!$inc_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 2:</strong> " . mysqli_error($conn) . "</div>");
$total_income = mysqli_fetch_assoc($inc_result)['total'] ?? 0;

$balance = $total_income - $total_expenses;

$days_in_month = date('t', strtotime($start_date));
$current_day = ($current_month == date('m') && $current_year == date('Y')) ? (int)date('d') : $days_in_month;
$daily_avg = $current_day > 0 ? $total_expenses / $current_day : 0;

// === שליפת נתונים לגרף עוגה (התפלגות הוצאות) ===
$pie_query = "SELECT c.name, SUM(t.amount) as total 
              FROM transactions t 
              JOIN categories c ON t.category = c.id 
              WHERE t.home_id = $home_id 
              AND t.type = 'expense' 
              AND t.transaction_date BETWEEN '$start_date' AND '$end_date' 
              GROUP BY t.category 
              ORDER BY total DESC";
$pie_result = mysqli_query($conn, $pie_query);
if (!$pie_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 3:</strong> " . mysqli_error($conn) . "</div>");

$pie_labels = [];
$pie_data = [];
while($row = mysqli_fetch_assoc($pie_result)) {
    $pie_labels[] = $row['name'];
    $pie_data[] = (float)$row['total'];
}

// === שליפת נתונים למדי התקציב (תוקן ORDER BY) ===
$budget_query = "SELECT c.id, c.name, c.budget_limit, c.icon,
                 COALESCE(SUM(t.amount), 0) as spent
                 FROM categories c
                 LEFT JOIN transactions t ON c.id = t.category 
                    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
                 WHERE c.home_id = $home_id 
                 AND c.type = 'expense'
                 AND c.is_active = 1
                 AND c.budget_limit > 0
                 GROUP BY c.id
                 ORDER BY (COALESCE(SUM(t.amount), 0) / c.budget_limit) DESC";
$budget_result = mysqli_query($conn, $budget_query);
if (!$budget_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 4:</strong> " . mysqli_error($conn) . "</div>");

$budgets = [];
while($row = mysqli_fetch_assoc($budget_result)) {
    $budgets[] = $row;
}

// === נתונים לבלוק ייצוא אקסל ===
$categories_query = "SELECT id, name, type, icon FROM categories WHERE home_id = $home_id AND is_active = 1 ORDER BY type ASC, name ASC";
$categories_result = mysqli_query($conn, $categories_query);
if (!$categories_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 5:</strong> " . mysqli_error($conn) . "</div>");

$expense_categories = [];
$income_categories = [];
while ($category_row = mysqli_fetch_assoc($categories_result)) {
    if ($category_row['type'] === 'expense') {
        $expense_categories[] = $category_row;
    } elseif ($category_row['type'] === 'income') {
        $income_categories[] = $category_row;
    }
}

$users_query = "SELECT id, first_name FROM users WHERE home_id = $home_id ORDER BY first_name ASC";
$users_result = mysqli_query($conn, $users_query);
if (!$users_result) die("<div style='direction:rtl; padding:20px; color:red;'><strong>שגיאה 6:</strong> " . mysqli_error($conn) . "</div>");

$house_users = [];
while ($user_row = mysqli_fetch_assoc($users_result)) {
    $house_users[] = $user_row;
}

$categories_array = array_merge($expense_categories, $income_categories);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דוחות ותובנות | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_user_css_href(), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .excel-export-modal { padding: 18px; }
        .excel-export-modal[style*="display: block"] { display: block !important; }
        .excel-export-modal .modal-content { max-width: 980px; max-height: 88vh; }
        .excel-export-card { margin-top: 0; border: 0; box-shadow: none; padding: 0; }
        .excel-export-subtitle { margin: 0 0 14px; color: #667085; font-size: .95rem; }
        .excel-export-form { display: grid; gap: 14px; }
        .excel-export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
        .excel-export-field { display: grid; gap: 6px; }
        .excel-export-field > span { font-size: .85rem; color: #475467; font-weight: 600; }
        .excel-export-field input, .excel-export-field select {
            height: 42px; border: 1.5px solid #ddd; border-radius: 10px; background: #fff;
            padding: 0 12px; font-family: inherit; font-size: .95rem; color: #101828;
            outline: none; transition: all .3s ease;
        }
        .excel-export-field input:focus, .excel-export-field select:focus {
            border-color: var(--main); background: var(--white);
            box-shadow: 0 0 0 4px var(--main-light);
        }
        .excel-export-footer { display: flex; flex-wrap: wrap; gap: 10px; }
        .excel-export-footer .btn-primary {
            margin-top: 0; width: 100%; padding: 14px 18px; font-size: 1.05rem;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            color: #fff !important;
        }
        .excel-export-btn { border: 0; }
        .excel-export-reset-row { display: flex; justify-content: flex-end; margin-bottom: 2px; }
        .excel-export-reset-btn {
            border: 1.5px solid #ddd; background: #fff; font: inherit; font-size: .82rem; font-weight: 700;
            color: var(--text); cursor: pointer; padding: 6px 14px; border-radius: 10px;
            display: inline-flex; align-items: center; gap: 5px; transition: all .2s;
        }
        .excel-export-reset-btn:hover { border-color: var(--main); color: var(--main); }

        .cat-picker { position: relative; }
        .cat-picker__label { font-size: .85rem; color: #475467; font-weight: 600; display: block; margin-bottom: 6px; }
        .cat-picker__trigger {
            display: flex; align-items: center; justify-content: space-between; width: 100%;
            min-height: 42px; padding: 6px 12px; border: 1.5px solid #ddd; border-radius: 10px;
            background: #fff; font-family: inherit; font-size: .9rem; color: #101828;
            cursor: pointer; outline: none; transition: all .3s ease; text-align: right;
        }
        .cat-picker__trigger:hover { border-color: #bbb; }
        .cat-picker__trigger.is-open { border-color: var(--main); box-shadow: 0 0 0 4px var(--main-light); }
        .cat-picker__trigger-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cat-picker__trigger i { font-size: .75rem; color: #98a2b3; transition: transform .2s; flex-shrink: 0; margin-inline-start: 8px; }
        .cat-picker__trigger.is-open i { transform: rotate(180deg); }
        .cat-picker__panel {
            display: none; position: absolute; left: 0; right: 0; top: calc(100% + 4px);
            background: #fff; border: 1px solid #d0d5dd; border-radius: 12px;
            box-shadow: 0 12px 28px rgba(0,0,0,.12); z-index: 60;
            max-height: 340px; overflow-y: auto;
        }
        .cat-picker__panel.is-open { display: block; }
        .cat-picker__actions {
            display: flex; gap: 6px; padding: 8px 14px; border-bottom: 1px solid #f2f4f7;
            position: sticky; top: 0; background: #fff; z-index: 1;
            justify-content: flex-start; direction: ltr;
        }
        .cat-picker__action {
            border: 1.5px solid #ddd; background: #fff; font: inherit; font-size: .78rem; font-weight: 700;
            color: var(--text); cursor: pointer; padding: 5px 12px; border-radius: 8px; transition: all .2s;
        }
        .cat-picker__action:hover { border-color: var(--main); color: var(--main); }
        .cat-picker__group-title {
            padding: 8px 14px 4px; font-size: .78rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .3px; user-select: none;
        }
        .cat-picker__group-title--expense { color: #b42318; }
        .cat-picker__group-title--income { color: #027a48; }
        .cat-picker__row {
            display: flex; align-items: center; gap: 10px; padding: 8px 14px;
            cursor: pointer; transition: background .12s;
        }
        .cat-picker__row:hover { background: #f9fafb; }
        .cat-picker__row:active { background: #f2f4f7; }
        .cat-picker__icon {
            width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; transition: .15s;
        }
        .cat-picker__icon--off { background: #f2f4f7; color: #d0d5dd; border: 2px solid #e4e7ec; }
        .cat-picker__icon--on { background: var(--main); color: #fff; border: 2px solid var(--main); }
        .cat-picker__cat-icon { font-size: .85rem; color: #667085; width: 18px; text-align: center; flex-shrink: 0; }
        .cat-picker__name { font-size: .9rem; color: #344054; font-weight: 500; }
        #budgets-progress-container .btn-cat-details { margin-top: 10px; }
        @media (max-width: 768px) {
            .excel-export-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .excel-export-footer { width: 100%; }
            .excel-export-footer .btn-primary { width: 100%; justify-content: center; }
        }
        @media (max-width: 420px) {
            .excel-export-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">
            
            <?php 
                $is_current_month = ($current_month == date('m') && $current_year == date('Y')); 
            ?>

            <div class="page-header-actions page-header-actions--home flex-between" style="margin-bottom: 25px;">
                <div class="page-header-actions__title-wrap">
                    <h1 class="section-title" style="margin-bottom: 0;">דוחות ותובנות</h1>
                </div>
                
                <div class="home-month-nav shopping-tabs-bar<?php echo !$is_current_month ? ' home-month-nav--has-today' : ''; ?>" id="reports-month-nav" aria-label="ניווט בין חודשים">
                    <div class="shopping-store-tabs">
                        <a id="reports-month-prev" href="?m=<?php echo $prev_month; ?>&y=<?php echo $prev_year; ?>" data-m="<?php echo (int) $prev_month; ?>" data-y="<?php echo (int) $prev_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש קודם">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            <span data-role="month-label"><?php echo $month_names[$prev_month]; ?></span>
                        </a>
                        <div class="home-month-nav__center-cell">
                            <span class="shopping-tab-chip active home-month-nav__current" id="reports-month-current" aria-current="page">
                                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                                <span data-role="current-label"><?php echo $month_names[$current_month] . ' ' . $current_year; ?></span>
                            </span>
                            <a id="reports-month-today" href="<?php echo BASE_URL . '/pages/reports.php?m=' . $today_m . '&y=' . $today_y; ?>" data-m="<?php echo (int) $today_m; ?>" data-y="<?php echo (int) $today_y; ?>" class="shopping-tab-chip shopping-tab-add home-month-nav__today" title="חזרה לחודש הנוכחי"<?php echo $is_current_month ? ' hidden' : ''; ?>>
                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                <span>היום</span>
                            </a>
                        </div>
                        <a id="reports-month-next" href="?m=<?php echo $next_month; ?>&y=<?php echo $next_year; ?>" data-m="<?php echo (int) $next_month; ?>" data-y="<?php echo (int) $next_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש הבא">
                            <span data-role="month-label"><?php echo $month_names[$next_month]; ?></span>
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div id="reports-kpis-wrap">
                <?php include ROOT_PATH . '/app/includes/partials/reports_kpis.php'; ?>
            </div>

            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fa-solid fa-chart-pie" style="color: var(--main);"></i> התפלגות הוצאות</h3>
                    </div>
                    <div class="canvas-wrapper" id="reports-pie-wrap">
                        <canvas id="expensesPieChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fa-solid fa-bullseye" style="color: var(--error);"></i> ניצול תקציבים</h3>
                    </div>
                    <div id="budgets-progress-container" style="padding-top: 10px;">
                        <?php include ROOT_PATH . '/app/includes/partials/reports_budgets.php'; ?>
                    </div>
                </div>
            </div>

            <div id="excel-export-modal" class="modal excel-export-modal" aria-hidden="true">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 style="margin:0;"><i class="fa-solid fa-file-excel" style="color: #1d6f42;"></i> הגדרות ייצוא לאקסל</h3>
                        <button type="button" id="closeExcelExportModalBtn" class="close-modal-btn" aria-label="סגור חלון ייצוא">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="excel-export-subtitle">
                            בחרו בדיוק מה תרצו לייצא. ברירת המחדל היא כל התנועות לחודש הנוכחי.
                        </p>

                        <form id="excelExportForm" class="excel-export-form" action="<?php echo BASE_URL; ?>/app/ajax/export_transactions_excel.php" method="GET">
                    <div class="excel-export-reset-row">
                        <button type="reset" class="excel-export-reset-btn">
                            <i class="fa-solid fa-rotate-left"></i> איפוס פילטרים
                        </button>
                    </div>
                    <div class="excel-export-grid">
                        <label class="excel-export-field">
                            <span>מתאריך</span>
                            <input type="date" id="exportStartDate" name="start_date" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="excel-export-field">
                            <span>עד תאריך</span>
                            <input type="date" id="exportEndDate" name="end_date" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label class="excel-export-field">
                            <span>סוג תנועות</span>
                            <select name="scope" id="exportScope">
                                <option value="all" selected>הכל</option>
                                <option value="expense">הוצאות בלבד</option>
                                <option value="income">הכנסות בלבד</option>
                            </select>
                        </label>
                        <label class="excel-export-field">
                            <span>משתמש</span>
                            <select name="user_id">
                                <option value="">כל המשתמשים</option>
                                <?php foreach ($house_users as $house_user): ?>
                                    <option value="<?php echo (int)$house_user['id']; ?>"><?php echo htmlspecialchars($house_user['first_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="excel-export-field">
                            <span>סכום מינימום</span>
                            <input type="number" name="min_amount" min="0" step="0.01" placeholder="ללא מינימום">
                        </label>
                        <label class="excel-export-field">
                            <span>סכום מקסימום</span>
                            <input type="number" name="max_amount" min="0" step="0.01" placeholder="ללא מקסימום">
                        </label>
                    </div>

                    <div class="cat-picker" id="catPicker">
                        <span class="cat-picker__label">קטגוריות לייצוא</span>
                        <button type="button" id="catPickerTrigger" class="cat-picker__trigger">
                            <span id="catPickerText" class="cat-picker__trigger-text">כל הקטגוריות</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div id="catPickerPanel" class="cat-picker__panel">
                            <div class="cat-picker__actions">
                                <button type="button" class="cat-picker__action" id="catPickerSelectAll">בחר הכל</button>
                                <button type="button" class="cat-picker__action" id="catPickerClearAll">נקה הכל</button>
                            </div>
                            <?php if (!empty($expense_categories)): ?>
                                <div class="cat-picker__group-title cat-picker__group-title--expense"><i class="fa-solid fa-arrow-trend-down"></i> הוצאות</div>
                                <?php foreach ($expense_categories as $cat): $cat_icon = !empty($cat['icon']) ? $cat['icon'] : 'fa-tag'; ?>
                                    <div class="cat-picker__row" data-cat-id="<?php echo (int)$cat['id']; ?>">
                                        <span class="cat-picker__icon cat-picker__icon--on"><i class="fa-solid fa-check"></i></span>
                                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?> cat-picker__cat-icon"></i>
                                        <span class="cat-picker__name"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($income_categories)): ?>
                                <div class="cat-picker__group-title cat-picker__group-title--income"><i class="fa-solid fa-arrow-trend-up"></i> הכנסות</div>
                                <?php foreach ($income_categories as $cat): $cat_icon = !empty($cat['icon']) ? $cat['icon'] : 'fa-tag'; ?>
                                    <div class="cat-picker__row" data-cat-id="<?php echo (int)$cat['id']; ?>">
                                        <span class="cat-picker__icon cat-picker__icon--on"><i class="fa-solid fa-check"></i></span>
                                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?> cat-picker__cat-icon"></i>
                                        <span class="cat-picker__name"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($expense_categories as $cat): ?>
                            <input class="export-cat-cb" type="checkbox" name="category_ids[]" value="<?php echo (int)$cat['id']; ?>" data-type="expense" checked hidden>
                        <?php endforeach; ?>
                        <?php foreach ($income_categories as $cat): ?>
                            <input class="export-cat-cb" type="checkbox" name="category_ids[]" value="<?php echo (int)$cat['id']; ?>" data-type="income" checked hidden>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" name="include_summary" value="1">
                    <input type="hidden" name="include_user" value="1">
                    <input type="hidden" name="include_category" value="1">

                    <div class="excel-export-footer">
                        <button type="submit" class="btn-primary excel-export-btn">ייצוא לאקסל</button>
                    </div>
                        </form>
                    </div>
                </div>
            </div>

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
                    <div class="input-group"><label>אמצעי תשלום</label><select name="payment_method_id" id="edit-payment-method"><option value="" disabled>לא צוין</option><?php foreach ($payment_methods as $pm): ?><option value="<?php echo (int)$pm['id']; ?>"><?php echo htmlspecialchars($pm['name']); ?></option><?php endforeach; ?></select></div>

                    <div id="edit-trans-msg" style="margin-bottom: 15px; font-weight: 700; text-align: center; display: none; padding: 10px; border-radius: 8px;"></div>

                    <button type="submit" class="btn-primary" id="submit-edit-trans-btn" style="margin-top: 5px;">
                        <i class="fa-solid fa-save"></i> שמור שינויים
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>assets/js/tazrim_dialogs.js"></script>

    <script>
        var TAZRIM_REPORTS = {
            api: <?php echo json_encode(BASE_URL . 'app/ajax/fetch_reports_data.php', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            ajaxBase: <?php echo json_encode(rtrim(BASE_URL, '/') . '/app/ajax/', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            pageUrl: <?php echo json_encode(BASE_URL . 'pages/reports.php', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            month: <?php echo (int) $current_month; ?>,
            year: <?php echo (int) $current_year; ?>
        };
        window.TAZRIM_REPORTS = TAZRIM_REPORTS;

        let pieChartInstance = null;
        const PIE_COLORS = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#FDCB6E', '#6C5CE7',
            '#A8E6CF', '#FD999A', '#81ECEC', '#74B9FF', '#A29BFE', '#55efc4', '#ff7675'
        ];

        function renderPieChart(labels, data) {
            const wrap = document.getElementById('reports-pie-wrap');
            if (!wrap) return;
            if (pieChartInstance) {
                try { pieChartInstance.destroy(); } catch (e) { /* ignore */ }
                pieChartInstance = null;
            }
            if (!data || data.length === 0) {
                wrap.innerHTML = '<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#888; font-weight:600;"><i class="fa-solid fa-chart-pie" style="margin-left: 8px; font-size: 1.5rem; color:#ddd;"></i> אין הוצאות בחודש זה.</div>';
                return;
            }
            wrap.innerHTML = '<canvas id="expensesPieChart"></canvas>';
            const ctx = document.getElementById('expensesPieChart').getContext('2d');
            pieChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: PIE_COLORS,
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            rtl: true,
                            labels: { font: { family: 'Heebo', size: 13, weight: '500' }, padding: 15 }
                        },
                        tooltip: {
                            titleFont: { family: 'Heebo', size: 14 },
                            bodyFont: { family: 'Heebo', size: 14, weight: 'bold' },
                            padding: 12,
                            callbacks: {
                                label: function (context) {
                                    return ' ' + context.label + ': ₪' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        renderPieChart(<?php echo json_encode($pie_labels, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($pie_data); ?>);

        (function () {
            const nav = document.getElementById('reports-month-nav');
            if (!nav) return;

            function buildPageUrl(y, m) {
                const base = TAZRIM_REPORTS.pageUrl || (window.location.pathname);
                return base + '?m=' + m + '&y=' + y;
            }

            function setLoading(on) {
                const kpiWrap = document.getElementById('reports-kpis-wrap');
                const budgets = document.getElementById('budgets-progress-container');
                const pieWrap = document.getElementById('reports-pie-wrap');
                [kpiWrap, budgets, pieWrap].forEach(function (n) {
                    if (n) n.style.opacity = on ? '0.55' : '';
                });
            }

            function updateHeader(h) {
                const curLbl = nav.querySelector('[data-role="current-label"]');
                if (curLbl) curLbl.textContent = h.currentLabel;

                const prev = document.getElementById('reports-month-prev');
                if (prev) {
                    prev.setAttribute('data-y', String(h.prev.y));
                    prev.setAttribute('data-m', String(h.prev.m));
                    prev.setAttribute('href', '?m=' + h.prev.m + '&y=' + h.prev.y);
                    const lbl = prev.querySelector('[data-role="month-label"]');
                    if (lbl) lbl.textContent = h.prev.label;
                }
                const next = document.getElementById('reports-month-next');
                if (next) {
                    next.setAttribute('data-y', String(h.next.y));
                    next.setAttribute('data-m', String(h.next.m));
                    next.setAttribute('href', '?m=' + h.next.m + '&y=' + h.next.y);
                    const lbl = next.querySelector('[data-role="month-label"]');
                    if (lbl) lbl.textContent = h.next.label;
                }
                const today = document.getElementById('reports-month-today');
                if (today) {
                    today.setAttribute('data-y', String(h.today.y));
                    today.setAttribute('data-m', String(h.today.m));
                    today.setAttribute('href', buildPageUrl(h.today.y, h.today.m));
                    if (h.isCurrentMonth) today.setAttribute('hidden', '');
                    else today.removeAttribute('hidden');
                }
                nav.classList.toggle('home-month-nav--has-today', !h.isCurrentMonth);
            }

            function updateExportDates(d) {
                const s = document.getElementById('exportStartDate');
                const e = document.getElementById('exportEndDate');
                if (s) s.value = d.start;
                if (e) e.value = d.end;
            }

            let inflight = null;
            function loadMonth(y, m, opts) {
                opts = opts || {};
                if (inflight && typeof inflight.abort === 'function') {
                    try { inflight.abort(); } catch (e) { /* ignore */ }
                }
                const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                inflight = ctrl;
                setLoading(true);
                const body = new URLSearchParams();
                body.set('m', String(m));
                body.set('y', String(y));
                return fetch(TAZRIM_REPORTS.api, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    credentials: 'same-origin',
                    body: body.toString(),
                    signal: ctrl ? ctrl.signal : undefined
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.ok || !res.data) {
                        setLoading(false);
                        return;
                    }
                    const d = res.data;
                    TAZRIM_REPORTS.month = d.header.currentM;
                    TAZRIM_REPORTS.year = d.header.currentY;
                    updateHeader(d.header);
                    const kpiWrap = document.getElementById('reports-kpis-wrap');
                    if (kpiWrap) kpiWrap.innerHTML = d.kpisHtml || '';
                    const bWrap = document.getElementById('budgets-progress-container');
                    if (bWrap) bWrap.innerHTML = d.budgetsHtml || '';
                    renderPieChart(d.pie.labels || [], d.pie.data || []);
                    updateExportDates(d.dates);
                    if (!opts.skipHistory) {
                        try {
                            const url = buildPageUrl(y, m);
                            const state = { reportsMonth: true, year: y, month: m };
                            if (opts.replace) window.history.replaceState(state, '', url);
                            else window.history.pushState(state, '', url);
                        } catch (e) { /* ignore */ }
                    }
                    setLoading(false);
                })
                .catch(function () {
                    setLoading(false);
                });
            }

            nav.addEventListener('click', function (ev) {
                const a = ev.target.closest('a[data-y][data-m]');
                if (!a || !nav.contains(a)) return;
                if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button === 1) return;
                ev.preventDefault();
                const y = parseInt(a.getAttribute('data-y'), 10);
                const m = parseInt(a.getAttribute('data-m'), 10);
                if (!y || !m) return;
                if (Number(TAZRIM_REPORTS.year) === y && Number(TAZRIM_REPORTS.month) === m) return;
                loadMonth(y, m);
            });

            window.addEventListener('popstate', function (ev) {
                const st = ev.state;
                if (st && st.reportsMonth && st.year && st.month) {
                    loadMonth(parseInt(st.year, 10), parseInt(st.month, 10), { skipHistory: true });
                    return;
                }
                const sp = new URLSearchParams(window.location.search);
                const y = parseInt(sp.get('y') || '', 10);
                const m = parseInt(sp.get('m') || '', 10);
                if (y && m) loadMonth(y, m, { skipHistory: true });
            });

            try {
                const st = window.history.state;
                if (!st || !st.reportsMonth) {
                    window.history.replaceState(
                        { reportsMonth: true, year: TAZRIM_REPORTS.year, month: TAZRIM_REPORTS.month },
                        '',
                        window.location.href
                    );
                }
            } catch (e) { /* ignore */ }

            window.__tazrimReportsReload = function () {
                return loadMonth(Number(TAZRIM_REPORTS.year), Number(TAZRIM_REPORTS.month), { skipHistory: true });
            };
        })();

        const exportScope = document.getElementById('exportScope');
        const excelExportModal = document.getElementById('excel-export-modal');
        const closeExcelExportModalBtn = document.getElementById('closeExcelExportModalBtn');

        function closeExcelExportModal() {
            excelExportModal.style.display = 'none';
            excelExportModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('no-scroll');
        }

        /* ──────── Category Picker (collapsible dropdown) ──────── */
        (function () {
            const picker    = document.getElementById('catPicker');
            const trigger   = document.getElementById('catPickerTrigger');
            const panel     = document.getElementById('catPickerPanel');
            const textEl    = document.getElementById('catPickerText');
            const rows      = panel.querySelectorAll('.cat-picker__row');
            const allCbs    = picker.querySelectorAll('.export-cat-cb');
            const cbMap     = {};
            allCbs.forEach(cb => { cbMap[cb.value] = cb; });

            function isOpen() { return panel.classList.contains('is-open'); }

            function open() {
                panel.classList.add('is-open');
                trigger.classList.add('is-open');
            }

            function close() {
                panel.classList.remove('is-open');
                trigger.classList.remove('is-open');
            }

            function syncRow(row) {
                const id = row.dataset.catId;
                const cb = cbMap[id];
                const icon = row.querySelector('.cat-picker__icon');
                if (!cb) return;
                if (cb.checked) {
                    icon.className = 'cat-picker__icon cat-picker__icon--on';
                    icon.innerHTML = '<i class="fa-solid fa-check"></i>';
                } else {
                    icon.className = 'cat-picker__icon cat-picker__icon--off';
                    icon.innerHTML = '';
                }
            }

            function updateText() {
                const total = allCbs.length;
                const names = [];
                allCbs.forEach(cb => {
                    if (cb.checked) {
                        const row = panel.querySelector('[data-cat-id="' + cb.value + '"]');
                        const nameEl = row ? row.querySelector('.cat-picker__name') : null;
                        names.push(nameEl ? nameEl.textContent : '');
                    }
                });
                if (names.length === 0) {
                    textEl.textContent = 'לא נבחרו קטגוריות';
                } else if (names.length === total) {
                    textEl.textContent = 'כל הקטגוריות';
                } else {
                    textEl.textContent = names.join(', ');
                }
            }

            function syncAll() {
                rows.forEach(syncRow);
                updateText();
            }

            trigger.addEventListener('click', () => { isOpen() ? close() : open(); });

            document.addEventListener('click', (e) => {
                if (!picker.contains(e.target)) close();
            });

            rows.forEach(row => {
                row.addEventListener('click', () => {
                    const cb = cbMap[row.dataset.catId];
                    if (!cb) return;
                    cb.checked = !cb.checked;
                    syncRow(row);
                    updateText();
                });
            });

            document.getElementById('catPickerSelectAll').addEventListener('click', () => {
                allCbs.forEach(cb => { cb.checked = true; });
                syncAll();
            });

            document.getElementById('catPickerClearAll').addEventListener('click', () => {
                allCbs.forEach(cb => { cb.checked = false; });
                syncAll();
            });

            document.getElementById('excelExportForm').addEventListener('reset', () => {
                setTimeout(() => {
                    allCbs.forEach(cb => { cb.checked = true; });
                    syncAll();
                    close();
                }, 0);
            });

            syncAll();
        })();

        closeExcelExportModalBtn.addEventListener('click', closeExcelExportModal);
        excelExportModal.addEventListener('click', (event) => {
            if (event.target === excelExportModal) closeExcelExportModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && excelExportModal.style.display === 'block') closeExcelExportModal();
        });
    </script>

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

        var allCategories = <?php echo json_encode($categories_array, JSON_UNESCAPED_UNICODE); ?>;

        function getDetailsRequestUrl(ctx) {
            if (!ctx) return null;
            var base = TAZRIM_REPORTS.ajaxBase;
            if (ctx.mode === 'type' && ctx.type) {
                return base + 'fetch_category_details.php?mode=type&trans_type=' + encodeURIComponent(ctx.type)
                    + '&m=' + TAZRIM_REPORTS.month + '&y=' + TAZRIM_REPORTS.year + '&ui_context=reports';
            }
            if (ctx.id) {
                var tp = ctx.type ? '&trans_type=' + encodeURIComponent(ctx.type) : '';
                return base + 'fetch_category_details.php?cat_id=' + encodeURIComponent(ctx.id) + tp
                    + '&m=' + TAZRIM_REPORTS.month + '&y=' + TAZRIM_REPORTS.year + '&ui_context=reports';
            }
            return null;
        }

        function loadCategoryDetails(catId, catName, type) {
            var modal = document.getElementById('category-details-modal');
            var content = document.getElementById('cat-details-content');
            var title = document.getElementById('selected-cat-name');
            var normalizedType = type === 'income' ? 'income' : 'expense';
            var typeLabel = normalizedType === 'income' ? 'הכנסות' : 'הוצאות';

            window.categoryDetailsContext = { mode: 'category', id: catId, name: catName, type: normalizedType };
            modal.style.display = 'block';
            title.innerText = 'פירוט ' + typeLabel + ': ' + catName;
            content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> רגע…</div>';

            fetch(getDetailsRequestUrl(window.categoryDetailsContext))
                .then(function (response) { return response.text().then(function (t) { return { ok: response.ok, t: t }; }); })
                .then(function (_ref) {
                    var ok = _ref.ok;
                    var t = _ref.t;
                    if (!ok) {
                        var msg = typeof tazrimMessageFromAjaxText === 'function' ? tazrimMessageFromAjaxText(t) : 'הפעולה נכשלה';
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
            var modal = document.getElementById('category-details-modal');
            if (!modal || modal.style.display !== 'block') return;
            var ctx = window.categoryDetailsContext;
            if (!ctx) return;
            var url = getDetailsRequestUrl(ctx);
            if (!url) return;
            var content = document.getElementById('cat-details-content');
            content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fa-solid fa-spinner fa-spin"></i> רגע…</div>';
            return fetch(url)
                .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, t: t }; }); })
                .then(function (_ref2) {
                    var ok = _ref2.ok;
                    var t = _ref2.t;
                    if (!ok) {
                        var msg = typeof tazrimMessageFromAjaxText === 'function' ? tazrimMessageFromAjaxText(t) : 'הפעולה נכשלה';
                        content.innerHTML = '<p style="text-align:center;padding:24px;color:var(--error);font-weight:700;">' + msg + '</p>';
                        return;
                    }
                    content.innerHTML = t;
                });
        }

        window.addEventListener('click', function (event) {
            var modal = document.getElementById('category-details-modal');
            if (modal && event.target === modal) {
                window.categoryDetailsContext = null;
                modal.style.display = 'none';
            }
        });

        function buildCustomSelectReports(containerId, hiddenInputId, type, selectedId) {
            var container = document.getElementById(containerId);
            var hiddenInput = document.getElementById(hiddenInputId);
            container.innerHTML = '';

            var filteredCats = allCategories.filter(function (cat) { return cat.type === type; });

            if (filteredCats.length === 0) {
                container.innerHTML = '<div style="color:var(--error); font-size:0.9rem; padding: 10px;">לא נמצאו קטגוריות</div>';
                hiddenInput.value = '';
                return;
            }

            var wrapper = document.createElement('div');
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
                    if (w !== wrapper) w.classList.remove('open');
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

        var editModal = document.getElementById('edit-transaction-modal');
        var editForm = document.getElementById('edit-transaction-form');

        function openEditTransModal(id, amount, categoryId, desc, type, source, paymentMethodId) {
            window.transactionActionSource = source || 'main';
            document.getElementById('edit-trans-id').value = id;
            document.getElementById('edit-trans-amount').value = amount;
            document.getElementById('edit-trans-desc').value = desc;
            document.getElementById('edit-trans-type').value = type;
            var pmSelect=document.getElementById('edit-payment-method'); pmSelect.value=paymentMethodId||''; pmSelect.disabled=!paymentMethodId; pmSelect.onchange=function(){pmSelect.disabled=false;};
            buildCustomSelectReports('edit-category-grid-container', 'edit-selected-category-id', type, categoryId);
            editModal.style.display = 'block';
        }

        function closeEditTransModal() {
            editModal.style.display = 'none';
            document.getElementById('edit-trans-msg').style.display = 'none';
        }

        function deleteTransaction(id, source) {
            var src = source || window.transactionActionSource || 'main';
            tazrimConfirm({
                title: 'מחיקת פעולה',
                message: 'האם אתה בטוח שברצונך למחוק פעולה זו? התקציב ויתרת הבנק יעודכנו בהתאם.',
                confirmText: 'מחק',
                cancelText: 'ביטול',
                danger: true
            }).then(function (ok) {
                if (!ok) return;

                var formData = new FormData();
                formData.append('id', id);

                fetch(TAZRIM_REPORTS.ajaxBase + 'delete_transaction.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status !== 'success') {
                        tazrimAlert({
                            title: 'שגיאה במחיקה',
                            message: data.message || 'אירעה שגיאה.'
                        });
                        return;
                    }
                    closeEditTransModal();
                    if (src === 'reports-category-details' && typeof window.__tazrimReportsReload === 'function') {
                        Promise.resolve(window.__tazrimReportsReload()).then(function () { refreshOpenCategoryDetailsIfAny(); });
                    } else {
                        closeCatDetails();
                        if (typeof window.__tazrimReportsReload === 'function') window.__tazrimReportsReload();
                    }
                })
                .catch(function () {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת.' });
                });
            });
        }

        document.addEventListener('click', function () {
            document.querySelectorAll('.custom-select-wrapper').forEach(function (w) {
                w.classList.remove('open');
            });
        });

        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = document.getElementById('submit-edit-trans-btn');
            var msgBox = document.getElementById('edit-trans-msg');
            var selectedCatId = document.getElementById('edit-selected-category-id').value;
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

            fetch(TAZRIM_REPORTS.ajaxBase + 'edit_transaction.php', {
                method: 'POST',
                body: new FormData(editForm)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        msgBox.style.display = 'block';
                        msgBox.style.backgroundColor = 'var(--sub_main-light)';
                        msgBox.style.color = 'var(--main)';
                        msgBox.innerText = 'הפעולה עודכנה בהצלחה!';
                        var src = window.transactionActionSource || 'main';
                        setTimeout(function () {
                            closeEditTransModal();
                            if (src === 'reports-category-details' && typeof window.__tazrimReportsReload === 'function') {
                                Promise.resolve(window.__tazrimReportsReload()).then(function () { refreshOpenCategoryDetailsIfAny(); });
                            } else {
                                closeCatDetails();
                                if (typeof window.__tazrimReportsReload === 'function') window.__tazrimReportsReload();
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
                .catch(function () {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = 'שגיאת תקשורת.';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> שמור שינויים';
                });
        });

        window.addEventListener('click', function (event) {
            if (event.target === editModal) {
                closeEditTransModal();
            }
        });

        window.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var excel = document.getElementById('excel-export-modal');
            if (excel && excel.style.display === 'block') return;
            if (document.getElementById('category-details-modal').style.display === 'block') closeCatDetails();
        });
    </script>
</body>
</html>
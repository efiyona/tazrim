<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

require_once ROOT_PATH . '/assets/includes/user_css_href.php';

$home_id = $_SESSION['home_id'];
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
                
                <div class="home-month-nav shopping-tabs-bar<?php echo !$is_current_month ? ' home-month-nav--has-today' : ''; ?>" aria-label="ניווט בין חודשים">
                    <div class="shopping-store-tabs">
                        <a href="?m=<?php echo $prev_month; ?>&y=<?php echo $prev_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש קודם">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            <span><?php echo $month_names[$prev_month]; ?></span>
                        </a>
                        <div class="home-month-nav__center-cell">
                            <span class="shopping-tab-chip active home-month-nav__current" aria-current="page">
                                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                                <span><?php echo $month_names[$current_month] . ' ' . $current_year; ?></span>
                            </span>
                            <?php if (!$is_current_month): ?>
                                <a href="<?php echo BASE_URL . '/pages/reports.php?m=' . date('m') . '&y=' . date('Y'); ?>" class="shopping-tab-chip shopping-tab-add home-month-nav__today" title="חזרה לחודש הנוכחי">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                    <span>היום</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <a href="?m=<?php echo $next_month; ?>&y=<?php echo $next_year; ?>" class="shopping-tab-chip home-month-nav__jump" title="חודש הבא">
                            <span><?php echo $month_names[$next_month]; ?></span>
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="kpi-grid kpi-grid--home">
                <div class="kpi-card kpi-card--home kpi-card--income">
                    <div class="kpi-card__body">
                        <div class="kpi-card__head">
                            <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
                            <span class="kpi-card__label">סה"כ הכנסות</span>
                        </div>
                        <div class="kpi-amount kpi-card__value success-text"><?php echo number_format($total_income) . '₪'; ?>+</div>
                    </div>
                </div>

                <div class="kpi-card kpi-card--home kpi-card--expense">
                    <div class="kpi-card__body">
                        <div class="kpi-card__head">
                            <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
                            <span class="kpi-card__label">סה"כ הוצאות</span>
                        </div>
                        <div class="kpi-amount kpi-card__value error-text"><?php echo number_format($total_expenses) . '₪'; ?>-</div>
                    </div>
                </div>

                <div class="kpi-card kpi-card--home kpi-card--reports-balance">
                    <div class="kpi-card__body">
                        <div class="kpi-card__head">
                            <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                            <span class="kpi-card__label">מאזן החודש</span>
                        </div>
                        <div class="kpi-amount kpi-card__value <?php echo $balance >= 0 ? 'success-text' : 'error-text'; ?>">
                            <span dir="ltr"><?php echo $balance < 0 ? '-' : '+'; ?><?php echo number_format(abs($balance)); ?>₪</span>
                        </div>
                    </div>
                </div>

                <div class="kpi-card kpi-card--home kpi-card--daily-avg">
                    <div class="kpi-card__body">
                        <div class="kpi-card__head">
                            <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-regular fa-calendar-days"></i></span>
                            <span class="kpi-card__label">ממוצע הוצאה יומית</span>
                        </div>
                        <div class="kpi-amount kpi-card__value kpi-card__value--amber"><?php echo number_format($daily_avg) . '₪'; ?></div>
                    </div>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fa-solid fa-chart-pie" style="color: var(--main);"></i> התפלגות הוצאות</h3>
                    </div>
                    <div class="canvas-wrapper">
                        <canvas id="expensesPieChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fa-solid fa-bullseye" style="color: var(--error);"></i> ניצול תקציבים</h3>
                    </div>
                    <div id="budgets-progress-container" style="padding-top: 10px;">
                        
                        <?php if(empty($budgets)): ?>
                            <div style="text-align: center; color: #888; padding: 30px 0;">
                                <i class="fa-solid fa-clipboard-check" style="font-size: 2rem; margin-bottom: 10px; color: #ccc;"></i><br>
                                לא הוגדרו יעדי תקציב לחודש זה.
                            </div>
                        <?php else: ?>
                            <?php foreach($budgets as $b): 
                                // חישוב אחוז הניצול
                                $percent = $b['budget_limit'] > 0 ? ($b['spent'] / $b['budget_limit']) * 100 : 0;
                                $percent_clamped = min($percent, 100); // מונע מהפס לצאת מגבולות הבר
                                
                                // קביעת צבע לפי ניצול: מתחת 75% ירוק, עד 90% כתום, מעל אדום
                                $color = 'var(--success)';
                                if ($percent >= 90) $color = 'var(--error)';
                                elseif ($percent >= 75) $color = '#f59e0b'; // צהוב-כתום
                            ?>
                                <div class="budget-item">
                                    <div class="budget-header">
                                        <span><i class="fa-solid <?php echo $b['icon'] ?: 'fa-tag'; ?>" style="color: #888; margin-left: 5px;"></i> <?php echo $b['name']; ?></span>
                                        <span style="font-size: 0.85rem; color: #666;"><strong style="color: var(--text);"><?php echo number_format($b['spent']); ?></strong> / <?php echo number_format($b['budget_limit']); ?> ₪</span>
                                    </div>
                                    <div class="progress-bar-bg">
                                        <div class="progress-bar-fill" style="width: <?php echo $percent_clamped; ?>%; background-color: <?php echo $color; ?>;"></div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: <?php echo $color; ?>; font-weight: 700; margin-top: 4px;">
                                        <?php echo number_format($percent, 1); ?>% נוצל
                                        <?php if($percent > 100) echo " (חריגה!)"; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            </div>
        </main>
    </div>

    <script>
        const pieLabels = <?php echo json_encode($pie_labels); ?>;
        const pieData = <?php echo json_encode($pie_data); ?>;

        if (pieData.length > 0) {
            const ctx = document.getElementById('expensesPieChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieData,
                        // פלטת צבעים נעימה לעין מותאמת אישית
                        backgroundColor: [
                            '#FF6B6B', '#4ECDC4', '#45B7D1', '#FDCB6E', '#6C5CE7', 
                            '#A8E6CF', '#FD999A', '#81ECEC', '#74B9FF', '#A29BFE', '#55efc4', '#ff7675'
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%', // עובי העוגה
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
                                label: function(context) {
                                    return ' ' + context.label + ': ₪' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // אם אין נתונים לחודש הזה נציג הודעה
            document.querySelector('.canvas-wrapper').innerHTML = '<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#888; font-weight:600;"><i class="fa-solid fa-chart-pie" style="margin-left: 8px; font-size: 1.5rem; color:#ddd;"></i> אין הוצאות בחודש זה.</div>';
        }
    </script>
</body>
</html>
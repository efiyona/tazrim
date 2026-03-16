<?php 
require('path.php'); 

include(ROOT_PATH . '/assets/includes/auth_check.php'); 
include(ROOT_PATH . '/app/controllers/users.php'); 

$home_id = $_SESSION['home_id'];

// 1. שליפת נתוני הבית (בשיטה של db.php)
$home_data = selectOne('homes', ['id' => $home_id]);
$current_bank_balance = $home_data['initial_balance'] ?? 0;

// 2. חישוב הכנסות החודש
$month_income_query = "SELECT SUM(amount) as total FROM transactions 
                       WHERE home_id = $home_id AND type = 'income' 
                       AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) 
                       AND YEAR(transaction_date) = YEAR(CURRENT_DATE())";

$result_income = mysqli_query($conn, $month_income_query);
$income_data = mysqli_fetch_assoc($result_income);
$total_income = $income_data['total'] ?? 0;

// 3. חישוב הוצאות החודש
$month_expense_query = "SELECT SUM(amount) as total FROM transactions 
                        WHERE home_id = $home_id AND type = 'expense' 
                        AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) 
                        AND YEAR(transaction_date) = YEAR(CURRENT_DATE())";

$result_expense = mysqli_query($conn, $month_expense_query);
$expense_data = mysqli_fetch_assoc($result_expense);
$total_expense = $expense_data['total'] ?? 0;

// 4. שליפת 5 פעולות אחרונות
$transactions_query = "SELECT t.*, c.icon as cat_icon 
                       FROM transactions t 
                       LEFT JOIN categories c ON t.category = c.id 
                       WHERE t.home_id = $home_id 
                       ORDER BY t.transaction_date DESC, t.created_at DESC 
                       LIMIT 5";

$result_transactions = mysqli_query($conn, $transactions_query);
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
                <h1 class="section-title">סיכום חודשי</h1>
        
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

                <div class="transactions-section">
                    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin-bottom: 20px;">פעולות אחרונות</h2>

                    <div id="transactions-list">
                        <?php if (mysqli_num_rows($result_transactions) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result_transactions)): ?>
                                <div class="transaction-item <?php echo $row['type']; ?>">
                                    <div class="transaction-info">
                                        <div class="cat-icon-wrapper">
                                            <i class="fa-solid <?php echo $row['cat_icon'] ?: 'fa-tag'; ?>"></i>
                                        </div>
                                        <div class="details">
                                            <span class="desc"><?php echo $row['description']; ?></span>
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

                    <?php if (mysqli_num_rows($result_transactions) >= 5): ?>
                        <button id="loadMoreBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                           הצג עוד...
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <button class="floating-btn"><i class="fa-solid fa-plus"></i></button>
    </div>

    <script>
        // תפריט מובייל
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

        // AJAX טען עוד
        let offset = 5; 
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
                
                fetch(`app/ajax/fetch_transactions.php?offset=${offset}`)
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
                            offset += 5; 
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
            offset = 5;
            loadMoreBtn.innerText = 'הצג עוד...';
            loadMoreBtn.removeAttribute('data-state');
            loadMoreBtn.style.backgroundColor = 'var(--white)';
            transactionsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    </script>
</body>
</html>
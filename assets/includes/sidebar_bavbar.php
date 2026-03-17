<?php
// מזהה את שם הקובץ הנוכחי (למשל: index.php או manage_home.php)
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>

<div class="sidebar-overlay" id="overlay"></div>

<aside class="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-wallet"></i>
        <span>התזרים</span>
    </div>
    <nav class="sidebar-nav">
        <a href="<?php echo BASE_URL . 'index.php'; ?>" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i> דף הבית
        </a>
        <a href="#" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i> דוחות
        </a>
        <a href="<?php echo BASE_URL . 'pages/manage_home.php'; ?>" class="<?php echo ($current_page == 'manage_home.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house-chimney-user"></i> ניהול הבית
        </a>
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
                <a href="<?php echo BASE_URL . 'logout.php'; ?>" class="icon-btn logout-btn-top" title="התנתקות">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

<script>
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
</script>
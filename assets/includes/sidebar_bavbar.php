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
        <a href="<?php echo BASE_URL . 'pages/shopping.php'; ?>" class="<?php echo ($current_page == 'shopping.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i> רשימת קניות
        </a>
        <a href="<?php echo BASE_URL . 'pages/reports.php'; ?>" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i> דוחות
        </a>
        <a href="<?php echo BASE_URL . 'pages/manage_home.php'; ?>" class="<?php echo ($current_page == 'manage_home.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house-chimney-user"></i> ניהול הבית
        </a>
        <hr>
        <a href="<?php echo BASE_URL . 'logout.php'; ?>" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i> התנתקות
        </a>
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
                    <h3 class="user-name">
                        <?php echo $_SESSION['first_name']; ?>
                        <?php echo !empty($_SESSION['nickname']) ? ' (' . $_SESSION['nickname'] . ')' : ''; ?>
                    </h3>
                    <span class="home-name-sub"><?php echo $home_data['name']; ?></span>
                </div>
            </div>
        </div>

        <div class="header-left">
            <div class="action-icons">
                <div class="icon-btn notification-wrapper" id="notifWrapper" title="התראות">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge" id="notifBadge" style="display: none;"></span> 
                    
                    <div class="notifications-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h3>התראות</h3>
                        </div>

                        <div class="notif-body">
                            <div class="notif-empty" id="notifEmpty" style="display: none;">
                                <i class="fa-solid fa-bell-slash"></i>
                                <p>אין התראות חדשות כרגע</p>
                            </div>
                            
                            <div id="notifList"></div>
                        </div>

                    </div>
                </div>

                <a href="<?php echo BASE_URL . 'logout.php'; ?>" class="icon-btn logout-btn-top" title="התנתקות">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

<style>
/* --- עיצוב התראות משלים --- */
.notification-wrapper { position: relative; }

/* ביטול אנימציית קפיצה לכפתור ההתראות כדי לשמור על יציבות הפופאפ */
.notification-wrapper:hover { transform: none !important; background-color: var(--gray-light); }

.notification-badge {
    position: absolute; top: 5px; right: 5px;
    background: var(--error); color: white;
    font-size: 0.65rem; font-weight: 800;
    width: 18px; height: 18px;
    border-radius: 50%; border: 2px solid white;
    display: flex; align-items: center; justify-content: center;
}

.notifications-dropdown {
    display: none; position: absolute; top: 55px; left: 0;
    width: 320px; background: white; border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000;
    border: 1px solid #eee; text-align: right; overflow: hidden;
    animation: fadeInScale 0.2s ease-out;
}

@keyframes fadeInScale {
    from { opacity: 0; transform: translateY(-10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.notifications-dropdown.show { display: block; }

.notif-header { padding: 15px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.notif-header h3 { margin: 0; font-size: 1rem; font-weight: 800; }

.notif-body { max-height: 380px; overflow-y: auto; }
.notif-item {
    display: flex; padding: 15px; gap: 12px;
    border-bottom: 1px solid #f5f5f5; transition: 0.2s; text-decoration: none;
}
.notif-item:hover { background: #f9f9f9; }
.notif-item.unread { background: #f0fdf4; border-right: 4px solid var(--main); }

.notif-icon-circle {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 1rem;
}
.notif-icon-circle.info { background: #e0f2fe; color: #0369a1; }
.notif-icon-circle.warning { background: #ffedd5; color: #9a3412; }
.notif-icon-circle.success { background: #dcfce7; color: #15803d; }

.notif-text p { margin: 0; font-size: 0.9rem; color: var(--text); line-height: 1.4; }
.notif-time { font-size: 0.75rem; color: #888; margin-top: 4px; display: block; }

.notif-empty { padding: 40px 20px; text-align: center; color: #ccc; }
.notif-footer { padding: 12px; border-top: 1px solid #f0f0f0; text-align: center; background: #fafafa; }

/* התאמה למובייל - ייצוב מוחלט */
@media (max-width: 600px) {
    .notifications-dropdown {
        position: fixed !important; top: 75px !important;
        left: 50% !important; transform: translateX(-50%) !important;
        width: 92vw !important; max-width: none !important;
        right: auto !important; margin: 0 !important;
    }
}
</style>

<script>
// --- לוגיקת תפריט מובייל ---
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

// --- לוגיקת התראות ---
document.addEventListener('DOMContentLoaded', function() {
    const notifWrapper = document.getElementById('notifWrapper');
    const notifDropdown = document.getElementById('notifDropdown');

    // 1. פתיחה וסגירה + סימון אוטומטי כנקרא
    notifWrapper.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpening = !notifDropdown.classList.contains('show');
        notifDropdown.classList.toggle('show');

        if (isOpening) {
            // הסרה ויזואלית מיידית של הבאג'
            const badge = document.getElementById('notifBadge');
            if (badge) badge.style.display = 'none';

            // עדכון השרת שכל ההתראות נקראו ע"י המשתמש
            fetch('<?php echo BASE_URL; ?>app/ajax/mark_notifications_read.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.querySelectorAll('.notif-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                    }
                });
        }
    });

    // 2. סגירה בלחיצה מחוץ לפופאפ
    document.addEventListener('click', function(event) {
        if (!notifWrapper.contains(event.target)) {
            notifDropdown.classList.remove('show');
        }
    });

    notifDropdown.addEventListener('click', (e) => e.stopPropagation());

    // 3. טעינת התראות ראשונית
    loadNotifications();

    // 4. בדיקת התראות חדשות בכל דקה (Polling)
    //setInterval(loadNotifications, 60000);
});

function loadNotifications() {
    fetch('<?php echo BASE_URL; ?>app/ajax/fetch_notifications.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
            }
        });
}

function renderNotifications(notifications) {
    const list = document.getElementById('notifList');
    const emptyMsg = document.getElementById('notifEmpty');
    
    list.innerHTML = ''; 

    if (!notifications || notifications.length === 0) {
        emptyMsg.style.display = 'block';
        return;
    }
    
    emptyMsg.style.display = 'none';

    notifications.forEach(n => {
        const unreadClass = n.is_read == 0 ? 'unread' : '';
        const iconType = n.type || 'info';
        
        let iconHtml = '<i class="fa-solid fa-circle-info"></i>';
        if(iconType === 'warning') iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        if(iconType === 'success') iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        
        // שינוי מ-<a> ל-<div> והסרת action_url
        const item = `
            <div class="notif-item ${unreadClass}">
                <div class="notif-icon-circle ${iconType}">
                    ${iconHtml}
                </div>
                <div class="notif-text">
                    <p><strong>${n.title}</strong> ${n.message}</p>
                    <span class="notif-time">${n.time_formatted}</span>
                </div>
            </div>
        `;
        list.insertAdjacentHTML('beforeend', item);
    });
}

function updateBadge(count) {
    const badge = document.getElementById('notifBadge');
    if (count > 0) {
        badge.innerText = count > 9 ? '+9' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}
</script>
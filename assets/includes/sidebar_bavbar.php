<?php
$current_page = basename($_SERVER['SCRIPT_NAME']);

// --- הגדרת הניווט המרכזית ---
$navigation = [
    [
        'name' => 'ראשי',
        'icon' => 'fa-house',
        'url' => BASE_URL . 'index.php',
        'file' => 'index.php'
    ],
    [
        'name' => 'דוחות',
        'icon' => 'fa-chart-line',
        'url' => BASE_URL . 'pages/reports.php', // וודא שהנתיב תואם לתיקיות שלך
        'file' => 'reports.php'
    ],
    [
        'name' => 'קניות',
        'icon' => 'fa-shopping-cart',
        'url' => BASE_URL . 'pages/shopping.php',
        'file' => 'shopping.php'
    ],
    [
        'name' => 'הגדרות',
        'icon' => 'fa-gear',
        'url' => 'javascript:void(0);',
        'file' => ['manage_home.php', 'user_profile.php'],
        'submenu' => [
            ['name' => 'ניהול הבית', 'icon' => 'fa-house-user', 'url' => BASE_URL . 'pages/settings/manage_home.php', 'file' => 'manage_home.php'],
            ['name' => 'החשבון שלי', 'icon' => 'fa-user-gear', 'url' => BASE_URL . 'pages/settings/user_profile.php', 'file' => 'user_profile.php']
        ]
    ]
];


$plus_button_configs = [
    'index.php' => [
        'show' => true,
        'modal_id' => 'add-transaction-modal'
    ],
    'reports.php' => [
        'show' => true,
        'modal_id' => 'filter-reports-modal' 
    ]
];

$current_plus = $plus_button_configs[$current_page] ?? ['show' => false];

function isNavActive($nav_item, $current_page) {
    if (isset($nav_item['file'])) {
        if (is_array($nav_item['file'])) return in_array($current_page, $nav_item['file']);
        return $nav_item['file'] === $current_page;
    }
    return false;
}

// מציאת ההגדרות של הדף הנוכחי לטובת כפתור הפלוס
$show_plus_on_this_page = $current_plus['show'];
$target_modal_id = $current_plus['modal_id'] ?? '';
?>

<div class="floating-nav-wrapper">

    <div class="bottom-nav-bar">
        <ul id="navBarUl">
            <?php foreach ($navigation as $item): 
                $active = isNavActive($item, $current_page);
                $hasSub = isset($item['submenu']);
            ?>
                <li class="list <?php echo $active ? 'active' : ''; ?> <?php echo $hasSub ? 'has-submenu' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="nav-main-link">
                        <span class="icon"><i class="fa-solid <?php echo $item['icon']; ?>"></i></span>
                        <span class="text"><?php echo $item['name']; ?></span>
                    </a>
                    <?php if ($hasSub): ?>
                        <div class="submenu-popup-container">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <a href="<?php echo $sub['url']; ?>" class="submenu-action-btn nav-page-link <?php echo ($current_page == $sub['file']) ? 'active-page' : ''; ?>">
                                    <i class="fa-solid <?php echo $sub['icon']; ?>"></i> <?php echo $sub['name']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <div class="indicator" id="navIndicator"></div>
        </ul>
    </div>
    <?php if ($show_plus_on_this_page): ?>
    <div class="detached-plus-wrapper">
        <div class="plus-btn-detached" onclick="openDynamicModal('<?php echo $target_modal_id; ?>')">
            <i class="fa-solid fa-plus"></i>
        </div>
    </div>
    <?php endif; ?>
</div>

<main class="main-content">
    
    <header class="top-bar">
        <div class="header-right">
            <div class="mobile-menu-btn"><i class="fa-solid fa-bars"></i></div>
            
            <div class="user-profile-section">
                <div class="user-avatar">
                    <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>&background=29b669&color=fff" alt="פרופיל">
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

<script>
/**
 * פונקציה לפתיחת מודאל לפי ID שנשלח מהגדרות העמוד
 */
function openDynamicModal(modalId) {
    if (!modalId) return;
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // נעילת גלילה (אם קיים global_modals.js)
        document.body.classList.add('no-scroll');
        
        // אתחול ספציפי למודאל הוספת פעולה ב-index.php
        if (modalId === 'add-transaction-modal' && typeof resetAddForm === "function") {
            resetAddForm();
        }
    } else {
        console.error("Modal not found: " + modalId);
    }
}

function toggleNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown) dropdown.classList.toggle('show');
}

document.addEventListener('DOMContentLoaded', () => {
    const listItems = document.querySelectorAll('.bottom-nav-bar .list');
    const indicator = document.getElementById('navIndicator');
    const itemWidth = 70; // רוחב האייקון ב-user.css

    // --- תיקון מנוע המיקום האוטומטי (Auto-Alignment) ---
    // שינוי לוגיקה שתתעדף מרכוז אלא אם באמת יש חריגה מהמסך
    function calculateAlignment(btn, popup) {
        popup.classList.remove('align-left', 'align-right', 'align-center');
        
        const rect = btn.getBoundingClientRect();
        const popupWidth = popup.offsetWidth || 150; // הערכת רוחב אם טרם הוצג
        const screenW = window.innerWidth;
        
        // חישוב המרחק של מרכז הכפתור מהקצוות
        const distFromLeft = rect.left + (rect.width / 2);
        const distFromRight = screenW - distFromLeft;
        
        // אם המרחק מכל צד גדול מחצי רוחב הפופאפ + שוליים בטחון (10px), נמרכז
        const halfPopup = (popupWidth / 2) + 10;
        
        if (distFromLeft < halfPopup) {
            popup.classList.add('align-left');
        } else if (distFromRight < halfPopup) {
            popup.classList.add('align-right');
        } else {
            popup.classList.add('align-center');
        }
    }

    // ניהול הזזת האינדיקטור
    function moveIndicator(targetLi) {
        const items = Array.from(targetLi.parentElement.children).filter(c => c.classList.contains('list'));
        const index = items.indexOf(targetLi);
        indicator.style.transform = `translateX(${index * -itemWidth}px)`;
    }

    const initialActive = document.querySelector('.bottom-nav-bar .list.active');
    if (initialActive) moveIndicator(initialActive);

    // לחיצה על תפריטים עם תת-תפריט
    listItems.forEach(item => {
        const link = item.querySelector('.nav-main-link');
        link.addEventListener('click', (e) => {
            if (item.classList.contains('has-submenu')) {
                e.preventDefault();
                const popup = item.querySelector('.submenu-popup-container');
                
                // חישוב מיקום מחדש בכל פתיחה
                if (!item.classList.contains('show-submenu')) {
                    calculateAlignment(item, popup);
                }
                
                listItems.forEach(li => { if(li !== item) li.classList.remove('show-submenu') });
                item.classList.toggle('show-submenu');
            }
        });
    });

    // סגירה בלחיצה בחוץ
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.floating-nav-wrapper')) {
            document.querySelectorAll('.show-submenu').forEach(el => el.classList.remove('show-submenu'));
        }
    });
});
</script>
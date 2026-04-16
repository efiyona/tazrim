<?php
$current_page = basename($_SERVER['SCRIPT_NAME']);

/** סנכרון תפקיד מהמסד לסשן (קישור פאנל אדמין עקבי עם אימות בשרת) */
if (function_exists('selectOne') && isset($_SESSION['id'])) {
    $urow = selectOne('users', ['id' => (int) $_SESSION['id']]);
    if ($urow && isset($urow['role'])) {
        $_SESSION['role'] = $urow['role'];
    }
}

/**
 * הגדרת הניווט המרכזית
 * 'plus_modal' => אם קיים, יוצג כפתור פלוס משמאל לתפריט שיפתח את המודאל המבוקש.
 */
$settings_submenu = [
    ['name' => 'ניהול הבית', 'icon' => 'fa-house-user', 'url' => BASE_URL . 'pages/settings/manage_home.php', 'file' => 'manage_home.php'],
    ['name' => 'החשבון שלי', 'icon' => 'fa-user-gear', 'url' => BASE_URL . 'pages/settings/user_profile.php', 'file' => 'user_profile.php'],
];
$settings_nav_files = ['manage_home.php', 'user_profile.php'];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'program_admin') {
    $settings_submenu[] = [
        'name' => 'פאנל ניהול מערכת',
        'icon' => 'fa-shield-halved',
        'url' => BASE_URL . 'admin/dashboard.php',
        'file' => ['dashboard.php', 'table.php'],
    ];
    $settings_nav_files[] = 'dashboard.php';
    $settings_nav_files[] = 'table.php';
}

$navigation = [
    [
        'name' => 'ראשי',
        'icon' => 'fa-house',
        'url' => BASE_URL . 'index.php',
        'file' => 'index.php',
        'plus_modal' => 'add-transaction-modal'
    ],
    [
        'name' => 'דוחות',
        'icon' => 'fa-chart-line',
        'url' => BASE_URL . 'pages/reports.php',
        'file' => 'reports.php',
        'plus_modal' => null,
    ],
    [
        'name' => 'קניות',
        'icon' => 'fa-cart-shopping',
        'url' => BASE_URL . 'pages/shopping.php',
        'file' => 'shopping.php',
        'plus_modal' => 'add-shopping-item-modal' 
    ],
    [
        'name' => 'הגדרות',
        'icon' => 'fa-gear',
        'url' => 'javascript:void(0);',
        'file' => $settings_nav_files,
        'plus_modal' => null,
        'submenu' => $settings_submenu
    ]
];

function isNavActive($nav_item, $current_page) {
    if (isset($nav_item['file'])) {
        if (is_array($nav_item['file'])) return in_array($current_page, $nav_item['file']);
        return $nav_item['file'] === $current_page;
    }
    return false;
}

function tazrim_submenu_page_active(array $sub, $current_page) {
    $f = $sub['file'] ?? '';
    if (is_array($f)) {
        return in_array($current_page, $f, true);
    }
    return $f === $current_page;
}

// מציאת הגדרות הדף הנוכחי
$current_config = null;
foreach ($navigation as $item) {
    if (isNavActive($item, $current_page)) {
        $current_config = $item;
        break;
    }
}
$target_modal_id = $current_config['plus_modal'] ?? null;
require_once ROOT_PATH . '/app/features/ai_chat/bootstrap.php';
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
                                <a href="<?php echo $sub['url']; ?>" class="submenu-action-btn nav-page-link <?php echo tazrim_submenu_page_active($sub, $current_page) ? 'active-page' : ''; ?>">
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
   <?php if ($target_modal_id): ?>
    <?php $is_shopping_fab = $current_page === 'shopping.php'; ?>
    <div class="detached-plus-wrapper <?php echo $is_shopping_fab ? 'detached-plus-wrapper--danger' : ''; ?>">
        <div
            class="plus-btn-detached <?php echo $is_shopping_fab ? 'plus-btn-detached--danger' : ''; ?>"
            <?php if (!$is_shopping_fab): ?>onclick="openDynamicModal('<?php echo $target_modal_id; ?>')"<?php endif; ?>
            aria-label="<?php echo $is_shopping_fab ? 'אפשרויות מחיקה' : 'הוספה'; ?>"
            title="<?php echo $is_shopping_fab ? 'אפשרויות מחיקה' : 'הוספה'; ?>"
        >
            <i class="fa-solid <?php echo $is_shopping_fab ? 'fa-trash-can' : 'fa-plus'; ?>"></i>
        </div>
    </div>
    <?php endif; ?>
</div>

<main class="main-content">
    
    <header class="top-bar">
        <div class="header-right">
            <div class="mobile-menu-btn"><i class="fa-solid fa-bars"></i></div>
            
            <div class="user-profile-section">
                <div class="user-avatar" role="img" aria-label="פרופיל"><?php
                    $fn = isset($_SESSION['first_name']) ? trim((string) $_SESSION['first_name']) : '';
                    $ln = isset($_SESSION['last_name']) ? trim((string) $_SESSION['last_name']) : '';
                    $a1 = $fn !== '' ? mb_substr($fn, 0, 1, 'UTF-8') : 'מ';
                    $a2 = $ln !== '' ? mb_substr($ln, 0, 1, 'UTF-8') : 'ש';
                    echo htmlspecialchars($a1 . $a2, ENT_QUOTES, 'UTF-8');
                ?></div>
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
                <?php ai_chat_render_launcher_button(); ?>
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

    <button type="button" id="quickFeedbackBtn" class="quick-feedback-btn" aria-label="דיווח מהיר">
        דיווח מהיר
    </button>

    <div id="quickFeedbackModal" class="quick-feedback-modal" aria-hidden="true">
        <div class="quick-feedback-backdrop" data-close="1"></div>
        <div class="quick-feedback-card" role="dialog" aria-modal="true" aria-labelledby="quickFeedbackTitle">
            <div class="quick-feedback-header">
                <button type="button" class="quick-feedback-close" id="quickFeedbackClose" aria-label="סגור">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <h3 id="quickFeedbackTitle">דיווח באג / רעיון חדש</h3>
            </div>

            <div class="quick-feedback-body">
                <div class="quick-feedback-kinds">
                    <button type="button" class="qf-kind active" data-kind="bug">
                        <i class="fa-solid fa-bug"></i> באג
                    </button>
                    <button type="button" class="qf-kind" data-kind="idea">
                        <i class="fa-regular fa-lightbulb"></i> רעיון לפיצ'ר
                    </button>
                </div>

                <label class="qf-label" for="qfTitle">כותרת קצרה (אופציונלי)</label>
                <input id="qfTitle" type="text" class="qf-input" placeholder="לדוגמה: תקלה בשמירת פעולה">

                <label class="qf-label" for="qfScreen">באיזה מסך זה קרה? (אופציונלי)</label>
                <input id="qfScreen" type="text" class="qf-input" placeholder="לדוגמה: ראשי / דוחות / קניות">

                <label class="qf-label" for="qfMessage">פירוט</label>
                <textarea id="qfMessage" class="qf-textarea" placeholder="כתבו כאן מה קרה או מה תרצו שנוסיף..."></textarea>
                <div class="qf-helper">מינימום 8 תווים כדי שנוכל לטפל מהר.</div>
                <div id="qfMsg" class="qf-msg" style="display:none;"></div>
            </div>

            <div class="quick-feedback-footer">
                <button type="button" id="qfSubmit" class="qf-submit">שליחה</button>
            </div>
        </div>
    </div>
    <?php ai_chat_render_lazy_loader(); ?>

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

/* --- דיווח מהיר גלובלי --- */
.quick-feedback-btn {
    position: fixed;
    left: -52px;
    top: 66%;
    width: 132px;
    height: 28px;
    border: 0;
    border-radius: 12px 12px 0 0;
    background: var(--main);
    color: #fff;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.2px;
    transform: rotate(90deg) translateZ(0);
    cursor: pointer;
    z-index: 1400;
    box-shadow: none;
    -webkit-appearance: none;
    appearance: none;
}
.quick-feedback-btn:hover { opacity: 0.92; }
.quick-feedback-btn:focus { outline: none; }
.quick-feedback-btn:focus-visible {
    outline: 2px solid rgba(255, 255, 255, 0.95);
    outline-offset: 2px;
}

.quick-feedback-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
}
.quick-feedback-modal.open { display: block; }
.quick-feedback-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
}
.quick-feedback-card {
    position: relative;
    width: min(430px, 94vw);
    max-height: 86vh;
    margin: 7vh auto;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 22px 60px rgba(0,0,0,0.24);
}
.quick-feedback-header {
    display: flex;
    flex-direction: row-reverse;
    align-items: center;
    gap: 12px;
    justify-content: space-between;
    padding: 14px 16px 8px;
}
.quick-feedback-header h3 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 800;
}
.quick-feedback-close {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--text-light);
    cursor: pointer;
}
.quick-feedback-body {
    padding: 0 16px 12px;
    max-height: 56vh;
    overflow: auto;
}
.quick-feedback-kinds {
    display: flex;
    flex-direction: row-reverse;
    gap: 10px;
    margin-bottom: 10px;
}
.qf-kind {
    flex: 1;
    min-height: 42px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    font-weight: 700;
    color: var(--text);
    cursor: pointer;
}
.qf-kind.active {
    background: var(--main);
    border-color: var(--main);
    color: #fff;
}
.qf-label {
    display: block;
    margin: 9px 0 5px;
    font-size: 0.82rem;
    font-weight: 700;
}
.qf-input,
.qf-textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.93rem;
    text-align: right;
}
.qf-textarea {
    min-height: 112px;
    resize: vertical;
}
.qf-helper {
    margin-top: 6px;
    font-size: 0.75rem;
    color: var(--text-light);
}
.qf-msg {
    margin-top: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: right;
}
.quick-feedback-footer {
    padding: 12px 16px 16px;
}
.qf-submit {
    width: 100%;
    min-height: 46px;
    border: 0;
    border-radius: 999px;
    background: var(--main);
    color: #fff;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
}
.qf-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .quick-feedback-btn {
        left: -56px;
        top: 72%;
        width: 138px;
    }
    .quick-feedback-card {
        width: 94vw;
        margin-top: 8vh;
        max-height: 84vh;
    }
}
</style>

<script>
// --- לוגיקת התראות ---
document.addEventListener('DOMContentLoaded', function() {
    const notifWrapper = document.getElementById('notifWrapper');
    const notifDropdown = document.getElementById('notifDropdown');

    if (!notifWrapper || !notifDropdown) {
        return;
    }

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

document.addEventListener('DOMContentLoaded', function() {
    const feedbackBtn = document.getElementById('quickFeedbackBtn');
    const feedbackModal = document.getElementById('quickFeedbackModal');
    const feedbackClose = document.getElementById('quickFeedbackClose');
    const qfSubmit = document.getElementById('qfSubmit');
    const qfTitle = document.getElementById('qfTitle');
    const qfScreen = document.getElementById('qfScreen');
    const qfMessage = document.getElementById('qfMessage');
    const qfMsg = document.getElementById('qfMsg');
    const kindBtns = Array.from(document.querySelectorAll('.qf-kind'));

    if (!feedbackBtn || !feedbackModal || !qfSubmit) return;

    let selectedKind = 'bug';
    const MIN_LEN = 8;
    let busy = false;

    function openFeedbackModal() {
        feedbackModal.classList.add('open');
        feedbackModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('no-scroll');
    }

    function closeFeedbackModal() {
        if (busy) return;
        feedbackModal.classList.remove('open');
        feedbackModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('no-scroll');
    }

    function setMsg(text, ok) {
        qfMsg.style.display = 'block';
        qfMsg.style.color = ok ? 'var(--main)' : 'var(--error)';
        qfMsg.textContent = text;
    }

    function clearMsg() {
        qfMsg.style.display = 'none';
        qfMsg.textContent = '';
    }

    function resetForm() {
        selectedKind = 'bug';
        kindBtns.forEach((btn) => btn.classList.toggle('active', btn.dataset.kind === 'bug'));
        qfTitle.value = '';
        qfScreen.value = '';
        qfMessage.value = '';
        clearMsg();
    }

    function refreshSubmitState() {
        const valid = (qfMessage.value || '').trim().length >= MIN_LEN;
        qfSubmit.disabled = busy || !valid;
    }

    feedbackBtn.addEventListener('click', openFeedbackModal);
    feedbackClose.addEventListener('click', closeFeedbackModal);
    feedbackModal.addEventListener('click', function(e) {
        if (e.target && e.target.getAttribute('data-close') === '1') {
            closeFeedbackModal();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && feedbackModal.classList.contains('open')) {
            closeFeedbackModal();
        }
    });

    kindBtns.forEach((btn) => {
        btn.addEventListener('click', function() {
            selectedKind = btn.dataset.kind === 'idea' ? 'idea' : 'bug';
            kindBtns.forEach((k) => k.classList.toggle('active', k === btn));
        });
    });

    qfMessage.addEventListener('input', refreshSubmitState);
    qfTitle.addEventListener('input', clearMsg);
    qfScreen.addEventListener('input', clearMsg);

    qfSubmit.addEventListener('click', function() {
        if (busy) return;
        const msg = (qfMessage.value || '').trim();
        if (msg.length < MIN_LEN) {
            setMsg('נא לפרט לפחות 8 תווים.', false);
            refreshSubmitState();
            return;
        }

        busy = true;
        refreshSubmitState();
        qfSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שולח...';
        clearMsg();

        fetch('<?php echo BASE_URL; ?>app/ajax/submit_feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kind: selectedKind,
                title: (qfTitle.value || '').trim(),
                screen: (qfScreen.value || '').trim(),
                message: msg
            })
        })
        .then((res) => res.json())
        .then((data) => {
            if (data.status === 'success') {
                setMsg('הדיווח נשלח בהצלחה. תודה!', true);
                setTimeout(() => {
                    resetForm();
                    closeFeedbackModal();
                    loadNotifications();
                }, 650);
            } else {
                setMsg(data.message || 'שליחת הדיווח נכשלה.', false);
            }
        })
        .catch(() => {
            setMsg('שגיאת תקשורת עם השרת.', false);
        })
        .finally(() => {
            busy = false;
            qfSubmit.textContent = 'שליחה';
            refreshSubmitState();
        });
    });

    resetForm();
    refreshSubmitState();
});
</script>

<script>
// --- הפונקציה בחוץ! עכשיו היא גלובלית ומונעת חיתוך בכל האתר ---
function calculateAlignment(btn, popup) {
    if(!popup) return;
    popup.classList.remove('align-left', 'align-right', 'align-center');
    
    const rect = btn.getBoundingClientRect();
    const screenW = window.innerWidth;
    const popupWidth = popup.offsetWidth || 200; 
    
    const btnCenter = rect.left + (rect.width / 2);
    const halfPopup = (popupWidth / 2) + 20; 

    // אם אין מספיק מקום משמאל - נצמיד לשמאל (מובייל)
    if (btnCenter < halfPopup) {
        popup.classList.add('align-left'); 
    } 
    // אם אין מספיק מקום מימין - נצמיד לימין
    else if (screenW - btnCenter < halfPopup) {
        popup.classList.add('align-right'); 
    } 
    // אם יש מקום - תמיד נמרכז בצורה סימטרית ומושלמת!
    else {
        popup.classList.add('align-center'); 
    }
}

function openDynamicModal(modalId) {
    if (!modalId) return;
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.classList.add('no-scroll');
        if (modalId === 'add-transaction-modal' && typeof resetAddForm === "function") {
            resetAddForm();
        }
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

    function moveIndicator(targetLi) {
        if (!indicator || !targetLi) return;
        const items = Array.from(targetLi.parentElement.children).filter(c => c.classList.contains('list'));
        const index = items.indexOf(targetLi);
        indicator.style.transform = `translateX(${index * -itemWidth}px)`;
    }

    const initialActive = document.querySelector('.bottom-nav-bar .list.active');
    if (initialActive) moveIndicator(initialActive);

    listItems.forEach(item => {
        const link = item.querySelector('.nav-main-link');
        link.addEventListener('click', (e) => {
            // לחיצה על הטאב של העמוד הנוכחי — גלילה לראש + רענון (כמו באפליקציה)
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

            if (item.classList.contains('has-submenu')) {
                e.preventDefault();

                const plusWrapper = document.querySelector('.detached-plus-wrapper');
                if (plusWrapper) plusWrapper.classList.remove('open');

                const popup = item.querySelector('.submenu-popup-container');
                
                if (!item.classList.contains('show-submenu')) {
                    calculateAlignment(item, popup);
                }
                
                listItems.forEach(li => { if(li !== item) li.classList.remove('show-submenu') });
                item.classList.toggle('show-submenu');
            } else {
                if (item.classList.contains('active')) {
                    e.preventDefault();
                    window.scrollTo(0, 0);
                    window.location.reload();
                    return;
                }
                const iconEl = link.querySelector('.icon i');
                if (iconEl) {
                    iconEl.className = 'fa-solid fa-spinner fa-spin';
                }
            }
        });
    });

    // רענון רק כשלוחצים שוב על אותו עמוד מתוך תת-התפריט (active-page) — לא על כפתור ההילוך
    document.querySelectorAll('.bottom-nav-bar .submenu-action-btn.nav-page-link').forEach((subLink) => {
        subLink.addEventListener('click', (e) => {
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            if (!subLink.classList.contains('active-page')) return;
            e.preventDefault();
            e.stopPropagation();
            window.scrollTo(0, 0);
            window.location.reload();
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.floating-nav-wrapper')) {
            document.querySelectorAll('.show-submenu').forEach(el => el.classList.remove('show-submenu'));
            document.querySelectorAll('.detached-plus-wrapper.open').forEach(el => el.classList.remove('open'));
        }
    });
});
</script>


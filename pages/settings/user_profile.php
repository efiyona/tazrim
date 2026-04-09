<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

require_once ROOT_PATH . '/assets/includes/user_css_href.php';
require_once ROOT_PATH . '/assets/includes/pwa_no_cache_headers.php';

$user_id = $_SESSION['id'];
$home_id = $_SESSION['home_id'];

// שליפת נתוני הבית בשביל הבר העליון
$home_data = selectOne('homes', ['id' => $home_id]);

// שליפת נתוני המשתמש הנוכחי
$user_data = selectOne('users', ['id' => $user_id]);

// זיהוי אם המשתמש גולש ממכשיר אפל (אייפון/אייפד)
$is_ios = preg_match('/iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>החשבון שלי | התזרים</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tazrim_user_css_href(), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo BASE_URL; ?>assets/js/tazrim_dialogs.js" defer></script>

    <style>
        /* עיצובים ספציפיים לעמוד הפרופיל שמשתלבים עם העיצוב הקיים */
        .readonly-input {
            background-color: #f3f4f6 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
            border-color: #e2e8f0 !important;
        }
        
        .danger-card {
            border: 1px solid #fca5a5;
        }
        
        .danger-card .card-header {
            background-color: #fef2f2;
            border-bottom: 1px solid #fca5a5;
            border-radius: 14px 14px 0 0;
        }
        
        .danger-card .card-header h3 {
            color: var(--error);
        }

        .btn-danger-outline {
            background-color: transparent;
            color: var(--error);
            border: 2px solid var(--error);
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-danger-outline:hover {
            background-color: #fef2f2;
            transform: translateY(-1px);
        }

        /* עיצוב מתג (Toggle) להעדפות */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .3s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .3s; border-radius: 50%;
        }
        .toggle-switch input:checked + .slider { background-color: var(--main); }
        .toggle-switch input:checked + .slider:before { transform: translateX(22px); }
        
        .preference-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 0; border-bottom: 1px solid #f1f5f9;
        }
        .preference-item:last-child { border-bottom: none; }
        .preference-info h4 { margin: 0 0 4px 0; font-size: 1rem; color: var(--text-dark); }
        .preference-info p { margin: 0; font-size: 0.8rem; color: var(--text-light); }

        .ios-tazrim-card-header h3 {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body class="bg-gray">

    <div class="sidebar-overlay" id="overlay"></div>

    <div class="dashboard-container">
        
        <?php include(ROOT_PATH . '/assets/includes/sidebar_bavbar.php'); ?>

            <div class="content-wrapper">
                
                <div class="page-header-actions" style="margin-bottom: 25px;">
                    <h1 class="section-title" style="margin-bottom: 0;">החשבון שלי</h1>
                    <p style="color: var(--text-light); font-size: 0.9rem; margin-top: 5px;">ניהול פרופיל אישי, סיסמה והגדרות פרטיות</p>
                </div>

                <div class="management-grid">
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>פרטים אישיים</h3>
                        </div>
                        <div class="card-body-padding">
                            <form id="profile-form">
                                <div class="input-group">
                                    <label>אימייל</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-envelope"></i>
                                        <input type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" class="readonly-input" readonly>
                                    </div>
                                    <p class="block-help" style="margin-top: 5px;">כתובת האימייל משמשת להתחברות, ולא ניתנת לשינוי.</p>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="input-group">
                                        <label>שם פרטי</label>
                                        <div class="input-with-icon">
                                            <i class="fa-solid fa-user"></i>
                                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label>שם משפחה</label>
                                        <div class="input-with-icon">
                                            <i class="fa-solid fa-signature"></i>
                                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>כינוי במערכת (Nickname)</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-at"></i>
                                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($user_data['nickname']); ?>">
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>טלפון</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-phone"></i>
                                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>" required>
                                    </div>
                                </div>

                                <div id="profile-msg" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: 600; text-align: center;"></div>
                                <button type="button" id="btn-update-profile" class="btn-primary" onclick="updateProfile()">שמירה</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>העדפות והתראות</h3>
                        </div>
                        <div class="card-body-padding">
                            
                            <div id="not-subscribed-view" style="display: none; text-align: center; padding: 10px 0;">
                                <div style="width: 70px; height: 70px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: #94a3b8;">
                                    <i class="fa-solid fa-bell-slash" style="font-size: 2rem;"></i>
                                </div>
                                <h4 style="margin: 0 0 8px 0; color: var(--text-dark); font-weight: 800;">התראות כבויות</h4>
                                <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 20px; line-height: 1.5;">
                                    הפעלת התראות כדי לקבל עדכונים בזמן אמת על חריגות מתקציב, הוצאות חדשות של שותפים ועדכוני מערכת, ישירות למכשיר הזה.
                                </p>

                                <?php if ($is_ios): ?>
                                <div style="background-color: #fffbeb; border: 1px solid #fde68a; color: #d97706; padding: 12px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; text-align: right; line-height: 1.5;">
                                    <strong style="display: block; margin-bottom: 5px;"><i class="fa-brands fa-apple"></i> משתמשי אייפון ואייפד:</strong> 
                                    כדי להפעיל התראות, עליך קודם להוסיף את המערכת למסך הבית: לחץ על כפתור השיתוף (קובייה עם חץ) למטה &gt; "הוסף למסך הבית". לאחר מכן פתח את האפליקציה ממסך הבית והפעל.
                                </div>
                                <?php endif; ?>

                                <button type="button" id="btn-enable-notifications" class="btn-primary" onclick="initPushSubscription()" style="width: 100%;">
                                    הפעלת התראות במכשיר
                                </button>
                            </div>

                            <div id="subscribed-view" style="display: none;">
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>התראות חריגה מתקציב</h4>
                                        <p>קבלת התראה כשהבית חורג מהתקציב החודשי שהוגדר לקטגוריה</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_budget_alerts" checked onchange="updatePreference('budget_alerts', this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                
                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>התראות פעולה חדשה</h4>
                                        <p>קבלת התראה על כל הוצאה או הכנסה שאחד מבני הבית הכניס</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_large_expenses" onchange="updatePreference('large_expenses', this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>עדכוני מערכת</h4>
                                        <p>קבלת עדכונים על שיפורים ועדכונים שביצענו</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_weekly_summary" checked onchange="updatePreference('weekly_summary', this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <hr class="management-divider" style="margin: 20px 0;">
                                
                                <button type="button" id="btn-disable-notifications" onclick="disablePushSubscription()" style="width: 100%; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s;">
                                    <i class="fa-solid fa-bell-slash"></i> בטל התראות במכשיר זה
                                </button>
                            </div>

                        </div>
                    </div>

                    <div class="card full-width-card" id="ios-tazrim-card">
                        <div class="card-header ios-tazrim-card-header">
                            <h3><i class="fa-brands fa-apple" aria-hidden="true"></i> התזרים באייפון</h3>
                        </div>
                        <div class="card-body-padding" id="ios-tazrim-panel-mount">
                            <div class="ios-tazrim-loading">
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>טוען את האזור…</span>
                            </div>
                        </div>
                    </div>

                    <div class="card danger-card full-width-card">
                        <div class="card-body-padding">

                            <div class="management-block" style="margin-bottom: 0;">
                                <h4 style="margin: 0 0 5px 0; color: var(--text-dark); font-weight: 700;">עזיבת הבית</h4>
                                <p class="block-help" style="margin-bottom: 15px;">פעולה זו תמחק את החשבון שלך ואת כל המידע האישי שלך מהמערכת. פעולה זו בלתי הפיכה.</p>
                                <button type="button" id="btn-delete-account" class="btn-primary btn-danger-outline" style="background-color: #fee2e2; border-color: #fca5a5;" onclick="deleteAccount()">מחיקת חשבון לצמיתות</button>

                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        // עדכון פרטים אישיים
        function updateProfile() {
            const form = document.getElementById('profile-form');
            const formData = new FormData(form);
            const btn = document.getElementById('btn-update-profile');
            const msgBox = document.getElementById('profile-msg');

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שומר...';
            btn.disabled = true;
            msgBox.style.display = 'none';

            // TODO: Create app/ajax/update_profile.php
            fetch('<?php echo BASE_URL; ?>app/ajax/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = 'שמור פרטים';
                btn.disabled = false;
                
                if (data.status === 'success') {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = 'var(--sub_main-light)';
                    msgBox.style.color = 'var(--main)';
                    msgBox.innerText = 'הפרטים עודכנו בהצלחה!';
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = data.message || 'שגיאה בשמירת הנתונים.';
                }
            })
            .catch(err => {
                btn.innerHTML = 'שמור פרטים';
                btn.disabled = false;
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'שגיאת תקשורת עם השרת.';
            });
        }

        // עדכון סיסמה
        function updatePassword() {
            const form = document.getElementById('password-form');
            const newPass = form.elements['new_password'].value;
            const confirmPass = form.elements['confirm_password'].value;
            const msgBox = document.getElementById('password-msg');

            if (newPass !== confirmPass) {
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'הסיסמאות החדשות אינן תואמות.';
                return;
            }

            const formData = new FormData(form);
            const btn = document.getElementById('btn-update-password');

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מעדכן...';
            btn.disabled = true;
            msgBox.style.display = 'none';

            // TODO: Create app/ajax/update_password.php
            fetch('<?php echo BASE_URL; ?>app/ajax/update_password.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = 'עדכן סיסמה';
                btn.disabled = false;
                
                if (data.status === 'success') {
                    form.reset();
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = 'var(--sub_main-light)';
                    msgBox.style.color = 'var(--main)';
                    msgBox.innerText = 'הסיסמה שונתה בהצלחה!';
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.backgroundColor = '#fee2e2';
                    msgBox.style.color = 'var(--error)';
                    msgBox.innerText = data.message || 'שגיאה בעדכון הסיסמה.';
                }
            })
            .catch(err => {
                btn.innerHTML = 'עדכן סיסמה';
                btn.disabled = false;
                msgBox.style.display = 'block';
                msgBox.style.backgroundColor = '#fee2e2';
                msgBox.style.color = 'var(--error)';
                msgBox.innerText = 'שגיאת תקשורת עם השרת.';
            });
        }

        // עדכון העדפות
        function updatePreference(prefName, isChecked) {
            console.log(`Preference ${prefName} changed to ${isChecked}`);
            // TODO: Create AJAX call to save preferences to DB if needed
        }

        // פעולות אזור סכנה
        function leaveHome() {
            tazrimConfirm({
                title: 'עזיבת הבית',
                message: 'האם אתה בטוח שברצונך לעזוב את הבית? פעולה זו תנתק אותך מנתוני התזרים.',
                confirmText: 'עזוב',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;
                // TODO: AJAX call to app/ajax/leave_home.php
                tazrimAlert({ title: 'בקרוב', message: 'פונקציית עזיבת הבית תופעל כאן' });
            });
        }

        function deleteAccount() {
            tazrimConfirm({
                title: 'מחיקת חשבון לצמיתות',
                message: 'אזהרה: האם אתה בטוח לחלוטין שברצונך למחוק את חשבונך לצמיתות?\n\nאם אתה המשתמש היחיד בבית - כל נתוני הבית (הוצאות, קטגוריות ודוחות) ימחקו גם הם ללא אפשרות שחזור!',
                confirmText: 'מחק חשבון',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;

                const btns = document.querySelectorAll('.btn-danger-outline');
                const deleteBtn = btns[btns.length - 1];
                const originalHtml = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מוחק חשבון ונתונים...';
                deleteBtn.disabled = true;

                fetch('<?php echo BASE_URL; ?>app/ajax/delete_account.php', {
                    method: 'POST'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = '<?php echo BASE_URL; ?>pages/login.php?deleted=1';
                    } else {
                        tazrimAlert({ title: 'לא ניתן למחוק', message: data.message || 'אירעה שגיאה.' });
                        deleteBtn.innerHTML = originalHtml;
                        deleteBtn.disabled = false;
                    }
                })
                .catch(function() {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת בעת ניסיון המחיקה.' });
                    deleteBtn.innerHTML = originalHtml;
                    deleteBtn.disabled = false;
                });
            });
        }

        function escapeHtmlIos(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        function refreshIosTazrimPanel() {
            const mount = document.getElementById('ios-tazrim-panel-mount');
            if (!mount) return;

            mount.innerHTML = '<div class="ios-tazrim-loading"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>טוען…</span></div>';

            fetch('<?php echo BASE_URL; ?>app/ajax/get_ios_tazrim_panel.php', { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'success' && data.html) {
                        mount.innerHTML = data.html;
                        return;
                    }
                    const msg = data.message || 'לא ניתן לטעון את האזור.';
                    mount.innerHTML = '<div class="ios-tazrim-error">' + escapeHtmlIos(msg) + '</div>';
                })
                .catch(function() {
                    mount.innerHTML = '<div class="ios-tazrim-error">שגיאת תקשורת עם השרת.</div>';
                });
        }

        function iosPanelCopyToken() {
            const tokenInput = document.getElementById('ios-api-token-display');
            if (!tokenInput) return;
            tokenInput.select();
            navigator.clipboard.writeText(tokenInput.value);

            const msg = document.getElementById('ios-copy-msg');
            if (msg) {
                msg.style.display = 'block';
                setTimeout(function() { msg.style.display = 'none'; }, 2000);
            }
        }

        function iosPanelDeleteToken() {
            tazrimConfirm({
                title: 'מחיקת מפתח חיבור',
                message: 'למחוק את מפתח החיבור? קיצורי הדרך והחיבורים שמשתמשים בו לא יעבדו עד שתיצור מפתח חדש.',
                confirmText: 'מחק מפתח',
                cancelText: 'ביטול',
                danger: true
            }).then(function(ok) {
                if (!ok) return;

                const btn = document.getElementById('btn-ios-delete-api-token');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מוחק...';
                }

                fetch('<?php echo BASE_URL; ?>app/ajax/delete_api_token.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        refreshIosTazrimPanel();
                    } else {
                        tazrimAlert({
                            title: 'שגיאה',
                            message: data.message || 'שגיאה במחיקת המפתח.'
                        });
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-trash"></i> מחיקת המפתח מהמערכת';
                        }
                    }
                })
                .catch(() => {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-trash"></i> מחיקת המפתח מהמערכת';
                    }
                });
            });
        }

        function iosPanelGenerateToken() {
            const btn = document.getElementById('btn-ios-generate-api');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> יוצר מפתח…';

            fetch('<?php echo BASE_URL; ?>app/ajax/generate_api_token.php', {
                method: 'POST'
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server Error Output:', text);
                    throw new Error('השרת שלח תשובה לא תקינה.');
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    refreshIosTazrimPanel();
                } else {
                    tazrimAlert({ title: 'שגיאה', message: data.message || 'אירעה שגיאה.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-key"></i> יצירת מפתח חיבור';
                }
            })
            .catch(err => {
                tazrimAlert({ title: 'שגיאה', message: err.message || 'אירעה שגיאה.' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-key"></i> יצירת מפתח חיבור';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('ios-tazrim-panel-mount')) {
                refreshIosTazrimPanel();
            }
        });
    </script>

    <script>
        // ==========================================
        // לוגיקת התראות דחיפה (Push) למכשיר הנוכחי
        // ==========================================
        const VAPID_PUBLIC_KEY = "<?php echo VAPID_PUBLIC_KEY; ?>";

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = atob(base64);
            return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
        }

        // בדיקה בעת טעינת הדף: האם המכשיר הזה כבר מנוי להתראות?
        document.addEventListener("DOMContentLoaded", async () => {
            const notSubView = document.getElementById('not-subscribed-view');
            const subView = document.getElementById('subscribed-view');
            
            let isSubscribed = false;
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                try {
                    const register = await navigator.serviceWorker.getRegistration();
                    if (register) {
                        const subscription = await register.pushManager.getSubscription();
                        if (subscription) isSubscribed = true;
                    }
                } catch (e) {
                    console.error("Error checking subscription:", e);
                }
            }

            if (isSubscribed) {
                notSubView.style.display = 'none';
                subView.style.display = 'block';
            } else {
                notSubView.style.display = 'block';
                subView.style.display = 'none';
            }
        });

        // החזרנו את הפונקציה המדויקת שעבדה לך ב-manage_home!
        async function initPushSubscription() {
            const btn = document.getElementById('btn-enable-notifications');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מתחבר להגדרות המכשיר...';

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('התראות לא נתמכות. וודא שהוספת את האתר למסך הבית (Add to Home Screen).');
                btn.disabled = false;
                btn.innerHTML = 'הפעלת התראות במכשיר';
                return;
            }

            try {
                // 1. רישום ה-Service Worker
                const register = await navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js');
                
                // 2. בקשת אישור מהמשתמש
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('כדי לקבל התראות, עליך לאשר אותן בהגדרות הדפדפן/מכשיר.');
                    btn.disabled = false;
                    btn.innerHTML = 'הפעלת התראות במכשיר';
                    return;
                }

                // 3. יצירת מנוי מול שירות ה-Push
                const convertedVapidKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);

                const subscription = await register.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedVapidKey
                });

                // 4. שליחת המנוי לשרת
                const response = await fetch('<?php echo BASE_URL; ?>app/ajax/save_subscription.php', {
                    method: 'POST',
                    body: JSON.stringify(subscription),
                    headers: { 'Content-Type': 'application/json' }
                });

                const result = await response.json();
                if (result.status === 'success') {
                    // הצלחה! מרעננים את הדף כדי להציג את תפריט ההעדפות
                    window.location.reload();
                } else {
                    alert('שגיאה בשמירת המנוי: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = 'נסה שוב';
                }
            } catch (error) {
                console.error('Full Subscription Error:', error);
                alert('שגיאה מפורטת: ' + error.name + " - " + error.message);
                btn.disabled = false;
                btn.innerHTML = 'נסה שוב';
            }
        }

        // פונקציית כיבוי ומחיקה
        async function disablePushSubscription() {
            if(confirm("האם אתה בטוח שברצונך לכבות את ההתראות למכשיר זה?")) {
                const btn = document.getElementById('btn-disable-notifications');
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מסיר מכשיר...';
                
                try {
                    const register = await navigator.serviceWorker.getRegistration();
                    if (register) {
                        const subscription = await register.pushManager.getSubscription();
                        if (subscription) {
                            // מחיקה ממסד הנתונים
                            await fetch('<?php echo BASE_URL; ?>app/ajax/delete_subscription.php', {
                                method: 'POST',
                                body: JSON.stringify({ endpoint: subscription.endpoint }),
                                headers: { 'Content-Type': 'application/json' }
                            });
                            
                            // ביטול המנוי ברמת הדפדפן
                            await subscription.unsubscribe();
                        }
                    }
                    window.location.reload();
                } catch (e) {
                    alert("שגיאה בביטול ההתראות: " + e.message);
                    btn.innerHTML = '<i class="fa-solid fa-bell-slash"></i> בטל התראות במכשיר זה';
                }
            }
        }
    </script>
</body>
</html>
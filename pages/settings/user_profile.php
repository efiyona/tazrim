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
$user_email = (string)($user_data['email'] ?? '');
$email_parts = explode('@', $user_email, 2);
$email_masked = $user_email;
if (count($email_parts) === 2 && $email_parts[0] !== '') {
    $name_part = $email_parts[0];
    $visible = mb_substr($name_part, 0, 2, 'UTF-8');
    if ($visible === '') {
        $visible = mb_substr($name_part, 0, 1, 'UTF-8');
    }
    $email_masked = $visible . str_repeat('*', max(2, mb_strlen($name_part, 'UTF-8') - mb_strlen($visible, 'UTF-8'))) . '@' . $email_parts[1];
}

// העדפות התראות Push (ברמת משתמש) - מטבלה ייעודית
$notify_prefs = selectOne('user_notification_preferences', ['user_id' => $user_id]);
$notify_home = isset($notify_prefs['notify_home_transactions']) ? (int) $notify_prefs['notify_home_transactions'] : 1;
$notify_budget = isset($notify_prefs['notify_budget']) ? (int) $notify_prefs['notify_budget'] : 1;
$notify_system = isset($notify_prefs['notify_system']) ? (int) $notify_prefs['notify_system'] : 1;

// זיהוי אם המשתמש גולש ממכשיר אפל (אייפון/אייפד, כולל iPadOS עם UA של מק) — להתראות / PWA
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_ios = (bool) preg_match('/iPhone|iPad|iPod/i', $user_agent)
    || (stripos($user_agent, 'Macintosh') !== false && stripos($user_agent, 'Mobile') !== false);

require_once ROOT_PATH . '/app/includes/ios_tazrim_panel_visibility.php';
require_once ROOT_PATH . '/app/helpers/phone_uniqueness.php';
// אזור קיצורי דרך + API: אייפון, אייפד או מק — לא באנדרואיד
$show_ios_tazrim_panel = tazrim_show_ios_tazrim_panel($user_agent);

require_once ROOT_PATH . '/app/features/ai_chat/services/user_preferences_repository.php';
$ai_prefs_flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_pref_delete'], $_POST['ai_pref_key']) && is_string($_POST['ai_pref_key'])) {
    $delKey = trim($_POST['ai_pref_key']);
    if ($delKey !== '' && ai_user_pref_delete($conn, (int) $user_id, $delKey)) {
        $ai_prefs_flash = 'ok';
    }
}
$ai_prefs_rows = [];
if ($conn instanceof mysqli) {
    $apStmt = $conn->prepare('SELECT pref_key, pref_value, updated_at FROM ai_user_preferences WHERE user_id = ? ORDER BY updated_at DESC');
    if ($apStmt) {
        $apStmt->bind_param('i', $user_id);
        $apStmt->execute();
        $apRes = $apStmt->get_result();
        $ai_prefs_rows = $apRes ? $apRes->fetch_all(MYSQLI_ASSOC) : [];
        $apStmt->close();
    }
}
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
            border-radius: 999px;
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

        .notif-section-block { margin-bottom: 4px; }
        .notif-section-title {
            margin: 0 0 12px 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notif-device-banner {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 0.88rem;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        .notif-device-banner.ok {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .notif-device-banner.muted {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }
        .notif-device-banner.warn {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
        }

        .password-reset-steps .step-hidden {
            display: none;
        }
        .password-reset-steps .form-group {
            margin-bottom: 16px;
        }
        .password-reset-steps .helper-text {
            margin-top: 6px;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        .password-reset-alert {
            display: none;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.35;
        }
        .password-reset-alert.success {
            background: var(--sub_main-light);
            color: var(--main);
            border: 1px solid rgba(56, 193, 114, 0.26);
        }
        .password-reset-alert.error {
            background: #fee2e2;
            color: var(--error);
            border: 1px solid rgba(248, 113, 113, 0.34);
        }
        .password-reset-code {
            letter-spacing: 0.35em;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .btn-secondary-light {
            width: 100%;
            border: 1px solid #cbd5e1;
            background: #e2e8f0;
            color: #334155;
            border-radius: 999px;
            min-height: 42px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-device-notifications-muted {
            width: 100%;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 12px;
            border-radius: 999px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
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
                                        <input type="tel" name="phone" value="<?php echo htmlspecialchars(tazrim_phone_for_display($user_data['phone'] ?? '')); ?>" required>
                                    </div>
                                </div>

                                <div id="profile-msg" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px; font-weight: 600; text-align: center;"></div>
                                <button type="button" id="btn-update-profile" class="btn-primary" onclick="updateProfile()">שמירה</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>שינוי סיסמה</h3>
                        </div>
                        <div class="card-body-padding password-reset-steps">
                            <p class="block-help" style="margin-bottom: 12px;">
                                לאבטחת החשבון, נשלח קוד אימות למייל שלך:
                                <strong><?php echo htmlspecialchars($email_masked, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </p>

                            <div id="profile-pass-alert" class="password-reset-alert" role="alert"></div>

                            <div id="profile-pass-step-1">
                                <button type="button" id="profile-pass-send-btn" class="btn-primary" style="margin-top: 0;">
                                    <i class="fa-solid fa-paper-plane"></i> שלח קוד אימות למייל
                                </button>
                            </div>

                            <div id="profile-pass-step-2" class="step-hidden">
                                <div class="form-group">
                                    <label for="profile-pass-code">קוד אימות (6 ספרות)</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        <input type="text" id="profile-pass-code" class="password-reset-code" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code">
                                    </div>
                                    <p class="helper-text">הקוד בתוקף ל-10 דקות.</p>
                                </div>
                                <button type="button" id="profile-pass-verify-btn" class="btn-primary" style="margin-top: 0;">
                                    אמת קוד והמשך
                                </button>
                                <button type="button" id="profile-pass-resend-btn" class="btn-secondary-light">
                                    שלח קוד מחדש
                                </button>
                            </div>

                            <div id="profile-pass-step-3" class="step-hidden">
                                <div class="form-group">
                                    <label for="profile-pass-new">סיסמה חדשה</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-lock"></i>
                                        <input type="password" id="profile-pass-new" minlength="4" placeholder="לפחות 4 תווים" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="profile-pass-confirm">אימות סיסמה חדשה</label>
                                    <div class="input-with-icon">
                                        <i class="fa-solid fa-check-double"></i>
                                        <input type="password" id="profile-pass-confirm" minlength="4" placeholder="הקלדה חוזרת" autocomplete="new-password">
                                    </div>
                                </div>
                                <button type="button" id="profile-pass-reset-btn" class="btn-primary" style="margin-top: 0;">
                                    עדכן סיסמה
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>העדפות והתראות</h3>
                        </div>
                        <div class="card-body-padding">

                            <!-- 1) מצב מכשיר (Push) -->
                            <div class="notif-section-block">
                                <h4 class="notif-section-title"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i> במכשיר הזה</h4>
                                <p id="device-push-no-support" class="block-help" style="display: none; margin-bottom: 12px;">
                                    הדפדפן או המכשיר לא תומכים בהתראות Push, או שהאתר לא מותקן כאפליקציה (PWA). נסה דפדפן מעודכן או הוסף למסך הבית.
                                </p>

                                <div id="device-push-subscribed" style="display: none;">
                                    <div class="notif-device-banner ok">
                                        <strong>התראות Push מופעלות במכשיר הזה.</strong><br>
                                        תקבל התראות כאן רק אם גם סוגי ההתראות למטה מופעלים בחשבון שלך.
                                    </div>
                                    <button type="button" id="btn-disable-notifications" class="btn-device-notifications-muted" onclick="disablePushSubscription()">
                                        <i class="fa-solid fa-bell-slash"></i> בטל התראות במכשיר זה
                                    </button>
                                </div>

                                <div id="device-push-unsubscribed" style="display: none;">
                                    <div class="notif-device-banner muted">
                                        <strong>במכשיר זה לא מופעלת קבלת Push.</strong><br>
                                        עדיין אפשר להגדיר למטה מה תרצה לקבל — ההפעלה בפועל תתבצע אחרי שתאשר התראות במכשיר.
                                    </div>
                                    <?php if ($is_ios): ?>
                                    <div class="notif-device-banner warn" style="margin-bottom: 14px;">
                                        <strong><i class="fa-brands fa-apple"></i> אייפון / אייפד:</strong>
                                        הוסף את התזרים למסך הבית (שיתוף → הוסף למסך הבית), פתח מהאייקון, ואז הפעל התראות.
                                    </div>
                                    <?php endif; ?>
                                    <button type="button" id="btn-enable-notifications" class="btn-primary" onclick="initPushSubscription()" style="width: 100%;">
                                        <i class="fa-solid fa-bell"></i> הפעלת התראות במכשיר
                                    </button>
                                </div>
                            </div>

                            <hr class="management-divider" style="margin: 22px 0;">

                            <!-- 2) העדפות משתמש (חל על החשבון) -->
                            <div class="notif-section-block" id="push-preferences-block" style="display: none;">
                                <h4 class="notif-section-title"><i class="fa-solid fa-sliders" aria-hidden="true"></i> מה לקבל ב-Push</h4>
                                <p class="block-help" style="margin-bottom: 14px;">
                                    ההגדרות נשמרות לחשבון שלך וחלות על כל המכשירים שבהם מופעלות התראות Push. התראות בתוך האפליקציה (פעמון) ימשיכו להופיע גם כשהמתגים כבויים.
                                </p>

                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>פעולות של בני הבית</h4>
                                        <p>הוצאה או הכנסה חדשה שמישהו אחר בבית הזין — לא תקבל על פעולות שאתה מזין (לשאר השותפים כן).</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_notify_home" <?php echo $notify_home ? 'checked' : ''; ?> onchange="updateNotificationPreference('home_transactions', this)">
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>התראות תקציב</h4>
                                        <p>כשסך ההוצאות בקטגוריה מגיע לתקרה שהוגדרה לחודש הנוכחי.</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_notify_budget" <?php echo $notify_budget ? 'checked' : ''; ?> onchange="updateNotificationPreference('budget', this)">
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                <div class="preference-item">
                                    <div class="preference-info">
                                        <h4>עדכוני מערכת</h4>
                                        <p>הודעות מוצר ושידורים מהצוות (למשל עדכונים ושיפורים).</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="pref_notify_system" <?php echo $notify_system ? 'checked' : ''; ?> onchange="updateNotificationPreference('system', this)">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>תזרי — מה נשמר בשבילך</h3>
                        </div>
                        <div class="card-body-padding">
                            <p class="block-help" style="margin-bottom: 14px;">
                                העדפות ויעדים שהעוזר תזרי שמר בשיחה (למשל יעדי חיסכון). אפשר למחוק כאן — המחיקה משפיעה רק עליך.
                            </p>
                            <?php if ($ai_prefs_flash === 'ok'): ?>
                                <p class="block-help" style="margin-bottom: 12px; color: var(--success, #15803d);">הפריט נמחק.</p>
                            <?php endif; ?>
                            <?php if ($ai_prefs_rows === []): ?>
                                <p class="block-help" style="margin-bottom: 0;">אין כרגע העדפות שמורות מהעוזר.</p>
                            <?php else: ?>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($ai_prefs_rows as $apr): ?>
                                        <?php
                                        $pk = htmlspecialchars((string) ($apr['pref_key'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $pv = htmlspecialchars((string) ($apr['pref_value'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $pu = htmlspecialchars((string) ($apr['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <li style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px;">
                                            <div style="font-weight: 700; margin-bottom: 6px;"><?php echo $pk; ?></div>
                                            <pre style="white-space: pre-wrap; margin: 0 0 10px 0; font-size: 0.85rem; color: #475569;"><?php echo $pv; ?></pre>
                                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                                                <span style="font-size: 0.8rem; color: #94a3b8;">עודכן: <?php echo $pu; ?></span>
                                                <form method="post" action="" style="margin: 0;" onsubmit="return confirm('למחוק את ההעדפה הזו?');">
                                                    <input type="hidden" name="ai_pref_key" value="<?php echo $pk; ?>">
                                                    <button type="submit" name="ai_pref_delete" value="1" class="btn-secondary-light" style="padding: 6px 12px; font-size: 0.85rem;">
                                                        מחיקה
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($show_ios_tazrim_panel): ?>
                    <div class="card full-width-card" id="ios-tazrim-card">
                        <div class="card-header ios-tazrim-card-header">
                            <h3><i class="fa-brands fa-apple" aria-hidden="true"></i> קיצורי דרך ל־iOS</h3>
                        </div>
                        <div class="card-body-padding" id="ios-tazrim-panel-mount">
                            <div class="ios-tazrim-loading">
                                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>טוען את האזור…</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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

        function initProfilePasswordReset() {
            const endpoint = '<?php echo BASE_URL; ?>app/ajax/profile_password_reset.php';
            const alertBox = document.getElementById('profile-pass-alert');
            const step1 = document.getElementById('profile-pass-step-1');
            const step2 = document.getElementById('profile-pass-step-2');
            const step3 = document.getElementById('profile-pass-step-3');
            const codeInput = document.getElementById('profile-pass-code');
            const newPassInput = document.getElementById('profile-pass-new');
            const confirmPassInput = document.getElementById('profile-pass-confirm');
            const sendBtn = document.getElementById('profile-pass-send-btn');
            const verifyBtn = document.getElementById('profile-pass-verify-btn');
            const resetBtn = document.getElementById('profile-pass-reset-btn');
            const resendBtn = document.getElementById('profile-pass-resend-btn');
            let resendCooldownTimer = null;
            let resendCooldownRemaining = 0;

            if (!alertBox || !step1 || !step2 || !step3 || !sendBtn || !verifyBtn || !resetBtn || !resendBtn) {
                return;
            }

            function showAlert(message, isSuccess) {
                alertBox.style.display = 'block';
                alertBox.classList.remove('success', 'error');
                alertBox.classList.add(isSuccess ? 'success' : 'error');
                alertBox.textContent = message;
            }

            function clearAlert() {
                alertBox.style.display = 'none';
                alertBox.textContent = '';
                alertBox.classList.remove('success', 'error');
            }

            function setBtnLoading(btn, loadingText, isLoading) {
                if (!btn) return;
                if (isLoading) {
                    btn.disabled = true;
                    if (!btn.dataset.originalText) {
                        btn.dataset.originalText = btn.innerHTML;
                    }
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + loadingText;
                    return;
                }
                btn.disabled = false;
                if (btn.dataset.originalText) {
                    btn.innerHTML = btn.dataset.originalText;
                }
            }

            function updateResendButtonState() {
                if (resendCooldownRemaining > 0) {
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'שלח קוד מחדש בעוד ' + resendCooldownRemaining + ' שנ׳';
                    return;
                }
                resendBtn.disabled = false;
                resendBtn.textContent = 'שלח קוד מחדש';
            }

            function startResendCooldown(seconds) {
                resendCooldownRemaining = seconds;
                updateResendButtonState();
                if (resendCooldownTimer) {
                    clearInterval(resendCooldownTimer);
                }
                resendCooldownTimer = setInterval(function() {
                    resendCooldownRemaining -= 1;
                    if (resendCooldownRemaining <= 0) {
                        resendCooldownRemaining = 0;
                        clearInterval(resendCooldownTimer);
                        resendCooldownTimer = null;
                    }
                    updateResendButtonState();
                }, 1000);
            }

            function postAction(formData) {
                return fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function(res) {
                    return res.json();
                });
            }

            sendBtn.addEventListener('click', function() {
                clearAlert();
                setBtnLoading(sendBtn, 'שולח קוד...', true);

                const fd = new FormData();
                fd.append('action', 'send_code');

                postAction(fd)
                    .then(function(data) {
                        if (data.status !== 'success') {
                            showAlert(data.message || 'שליחת הקוד נכשלה.', false);
                            return;
                        }
                        showAlert(data.message || 'קוד נשלח בהצלחה.', true);
                        step1.classList.add('step-hidden');
                        step2.classList.remove('step-hidden');
                        startResendCooldown(30);
                        if (codeInput) codeInput.focus();
                    })
                    .catch(function() {
                        showAlert('שגיאת תקשורת עם השרת.', false);
                    })
                    .finally(function() {
                        setBtnLoading(sendBtn, '', false);
                    });
            });

            resendBtn.addEventListener('click', function() {
                if (resendCooldownRemaining > 0) return;
                clearAlert();
                setBtnLoading(resendBtn, 'שולח קוד...', true);
                const fd = new FormData();
                fd.append('action', 'send_code');
                postAction(fd)
                    .then(function(data) {
                        if (data.status !== 'success') {
                            showAlert(data.message || 'שליחת הקוד נכשלה.', false);
                            return;
                        }
                        showAlert(data.message || 'קוד חדש נשלח למייל.', true);
                        startResendCooldown(30);
                    })
                    .catch(function() {
                        showAlert('שגיאת תקשורת עם השרת.', false);
                    })
                    .finally(function() {
                        setBtnLoading(resendBtn, '', false);
                        updateResendButtonState();
                    });
            });

            verifyBtn.addEventListener('click', function() {
                clearAlert();
                const code = (codeInput.value || '').trim();
                if (!/^\d{6}$/.test(code)) {
                    showAlert('נא להזין קוד בן 6 ספרות.', false);
                    return;
                }

                setBtnLoading(verifyBtn, 'מאמת...', true);
                const fd = new FormData();
                fd.append('action', 'verify_code');
                fd.append('code', code);

                postAction(fd)
                    .then(function(data) {
                        if (data.status !== 'success') {
                            showAlert(data.message || 'אימות הקוד נכשל.', false);
                            return;
                        }
                        showAlert(data.message || 'הקוד אומת בהצלחה.', true);
                        step2.classList.add('step-hidden');
                        step3.classList.remove('step-hidden');
                        if (newPassInput) newPassInput.focus();
                    })
                    .catch(function() {
                        showAlert('שגיאת תקשורת עם השרת.', false);
                    })
                    .finally(function() {
                        setBtnLoading(verifyBtn, '', false);
                    });
            });

            resetBtn.addEventListener('click', function() {
                clearAlert();
                const pass = (newPassInput.value || '').trim();
                const confirm = (confirmPassInput.value || '').trim();
                if (pass.length < 4) {
                    showAlert('הסיסמה חייבת להיות לפחות 4 תווים.', false);
                    return;
                }
                if (pass !== confirm) {
                    showAlert('הסיסמאות אינן תואמות.', false);
                    return;
                }

                setBtnLoading(resetBtn, 'מעדכן...', true);
                const fd = new FormData();
                fd.append('action', 'reset_password');
                fd.append('password', pass);
                fd.append('confirm_password', confirm);

                postAction(fd)
                    .then(function(data) {
                        if (data.status !== 'success') {
                            showAlert(data.message || 'עדכון הסיסמה נכשל.', false);
                            return;
                        }
                        showAlert(data.message || 'הסיסמה עודכנה בהצלחה.', true);
                        if (codeInput) codeInput.value = '';
                        if (newPassInput) newPassInput.value = '';
                        if (confirmPassInput) confirmPassInput.value = '';
                        step3.classList.add('step-hidden');
                        step1.classList.remove('step-hidden');
                        resendCooldownRemaining = 0;
                        updateResendButtonState();
                    })
                    .catch(function() {
                        showAlert('שגיאת תקשורת עם השרת.', false);
                    })
                    .finally(function() {
                        setBtnLoading(resetBtn, '', false);
                    });
            });

            updateResendButtonState();
        }

        // העדפות התראות (ברמת משתמש)
        function updateNotificationPreference(prefKey, inputEl) {
            const isChecked = inputEl.checked;
            inputEl.disabled = true;
            const fd = new FormData();
            fd.append('pref', prefKey);
            fd.append('value', isChecked ? '1' : '0');
            fetch('<?php echo BASE_URL; ?>app/ajax/save_notification_preferences.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status !== 'success') {
                    inputEl.checked = !isChecked;
                    tazrimAlert({ title: 'לא נשמר', message: data.message || 'אירעה שגיאה בשמירה.' });
                }
            })
            .catch(function() {
                inputEl.checked = !isChecked;
                tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
            })
            .finally(function() {
                inputEl.disabled = false;
            });
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
                            btn.innerHTML = 'מחיקת מפתח';
                        }
                    }
                })
                .catch(() => {
                    tazrimAlert({ title: 'שגיאה', message: 'שגיאת תקשורת עם השרת.' });
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'מחיקת מפתח';
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
            initProfilePasswordReset();
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

        window.__taNotifState = { support: true, subscribed: false, permission: 'default' };

        function applyPushUiState() {
            const subOn = document.getElementById('device-push-subscribed');
            const subOff = document.getElementById('device-push-unsubscribed');
            const noSupport = document.getElementById('device-push-no-support');
            const prefs = document.getElementById('push-preferences-block');
            if (!subOn || !subOff || !noSupport || !prefs) return;

            const st = window.__taNotifState || { support: false, subscribed: false };
            if (!st.support) {
                noSupport.style.display = 'block';
                subOn.style.display = 'none';
                subOff.style.display = 'none';
                prefs.style.display = 'none';
                return;
            }
            noSupport.style.display = 'none';
            subOn.style.display = st.subscribed ? 'block' : 'none';
            subOff.style.display = st.subscribed ? 'none' : 'block';
            prefs.style.display = st.subscribed ? 'block' : 'none';
        }

        document.addEventListener("DOMContentLoaded", async () => {
            const subOn = document.getElementById('device-push-subscribed');
            const subOff = document.getElementById('device-push-unsubscribed');
            if (!subOn || !subOff) return;

            const support = ('serviceWorker' in navigator && 'PushManager' in window);
            window.__taNotifState.support = support;
            if (typeof Notification !== 'undefined') {
                window.__taNotifState.permission = Notification.permission;
            }

            if (!support) {
                applyPushUiState();
                return;
            }

            let isSubscribed = false;
            try {
                const register = await navigator.serviceWorker.getRegistration();
                if (register) {
                    const subscription = await register.pushManager.getSubscription();
                    if (subscription) isSubscribed = true;
                }
            } catch (e) {
                console.error("Error checking subscription:", e);
            }

            window.__taNotifState.subscribed = isSubscribed;
            applyPushUiState();
        });

        // החזרנו את הפונקציה המדויקת שעבדה לך ב-manage_home!
        async function initPushSubscription() {
            const btn = document.getElementById('btn-enable-notifications');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מתחבר להגדרות המכשיר...';

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                tazrimAlert({
                    title: 'תמיכה מוגבלת',
                    message: 'התראות Push לא נתמכות כאן. נסה דפדפן מעודכן או הוסף את האתר למסך הבית (Add to Home Screen).'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bell"></i> הפעלת התראות במכשיר';
                return;
            }

            try {
                // 1. רישום ה-Service Worker
                const register = await navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js');
                
                // 2. בקשת אישור מהמשתמש
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    tazrimAlert({
                        title: 'נדרש אישור',
                        message: 'כדי לקבל התראות Push, יש לאשר אותן בהגדרות הדפדפן או המכשיר.'
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-bell"></i> הפעלת התראות במכשיר';
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
                    window.__taNotifState.subscribed = true;
                    window.__taNotifState.permission = 'granted';
                    applyPushUiState();
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> הופעל בהצלחה';
                    setTimeout(function() {
                        btn.innerHTML = '<i class="fa-solid fa-bell"></i> הפעלת התראות במכשיר';
                    }, 1200);
                } else {
                    tazrimAlert({ title: 'שגיאה', message: result.message || 'שגיאה בשמירת המנוי.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-bell"></i> נסה שוב';
                }
            } catch (error) {
                console.error('Full Subscription Error:', error);
                tazrimAlert({
                    title: 'שגיאה',
                    message: (error && error.message) ? (error.name + ': ' + error.message) : 'אירעה שגיאה בהפעלה.'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bell"></i> נסה שוב';
            }
        }

        // פונקציית כיבוי ומחיקה
        async function disablePushSubscription() {
            const ok = await tazrimConfirm({
                title: 'ביטול התראות במכשיר זה',
                message: 'האם אתה בטוח שברצונך לכבות התראות במכשיר הנוכחי?',
                confirmText: 'בטל התראות',
                cancelText: 'חזור',
                danger: true
            });
            if (!ok) return;

            const btn = document.getElementById('btn-disable-notifications');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
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
                window.__taNotifState.subscribed = false;
                applyPushUiState();
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bell-slash"></i> בטל התראות במכשיר זה';
            } catch (e) {
                tazrimAlert({
                    title: 'שגיאה בביטול התראות',
                    message: e && e.message ? e.message : 'אירעה שגיאה בלתי צפויה.'
                });
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    </script>
</body>
</html>
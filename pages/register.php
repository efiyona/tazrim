<?php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php");

$prefilled_code = isset($_GET['join_code']) ? htmlspecialchars($_GET['join_code']) : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
</head>
<body class="page-auth auth-cp auth-register">

    <div class="gradient-bg" aria-hidden="true"></div>

    <div class="form-container" role="main">
        <div class="logo">
            <div class="cp-login-logo-mark" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" role="img">
                    <circle cx="24" cy="24" r="22" fill="url(#cp-reg-logo-grad)"/>
                    <path fill="#fff" d="M14 17h20a2 2 0 012 2v2H12v-2a2 2 0 012-2zm0 6h22v10a2 2 0 01-2 2H14a2 2 0 01-2-2V23zm14 2a1.5 1.5 0 100 3h4a1.5 1.5 0 100-3h-4z"/>
                    <defs>
                        <linearGradient id="cp-reg-logo-grad" x1="10" y1="8" x2="38" y2="42" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#29b669"/>
                            <stop offset="1" stop-color="#4FD1C5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h1 class="cp-login-brand">תזרים</h1>
            <p class="cp-login-tagline">יצירת חשבון חדש לניהול תקציב הבית</p>
        </div>

        <?php if (count($errors) > 0): ?>
            <div class="alert-error cp-login-alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" id="registerForm" class="auth-form cp-login-form">
            <div class="cp-reg-row">
                <div class="form-group">
                    <label for="reg_first_name">שם פרטי</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" name="first_name" id="reg_first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required placeholder="השם שלך" autocomplete="given-name">
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg_last_name">שם משפחה</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h10M4 17h14"/></svg>
                        </span>
                        <input type="text" name="last_name" id="reg_last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required placeholder="שם המשפחה" autocomplete="family-name">
                    </div>
                </div>
            </div>

            <div class="cp-reg-row">
                <div class="form-group">
                    <label for="reg_email">אימייל</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" name="email" id="reg_email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required placeholder="mail@example.com" autocomplete="email">
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg_phone">טלפון</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </span>
                        <input type="tel" name="phone" id="reg_phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required placeholder="05XXXXXXXX" autocomplete="tel">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="reg_nickname">כינוי (Nickname)</label>
                <div class="input-with-icon">
                    <span class="cp-login-input-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>
                    </span>
                    <input type="text" name="nickname" id="reg_nickname" class="form-control" value="<?php echo htmlspecialchars($nickname); ?>" placeholder="איך לקרוא לך במערכת?" autocomplete="nickname">
                </div>
            </div>

            <div class="form-group">
                <label for="reg_pass">סיסמה</label>
                <div class="input-with-icon">
                    <span class="cp-login-input-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" name="password" id="reg_pass" class="form-control" required placeholder="לפחות 4 תווים" autocomplete="new-password">
                </div>
            </div>

            <p class="cp-reg-section-label">בחירת מסלול בית</p>
            <div class="cp-reg-radio-group">
                <label class="cp-reg-radio-card active" id="btnCreate">
                    <input type="radio" name="home_action" value="create" checked>
                    <span class="cp-reg-radio-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>
                    </span>
                    <span>יצירת בית</span>
                </label>
                <label class="cp-reg-radio-card" id="btnJoin">
                    <input type="radio" name="home_action" value="join">
                    <span class="cp-reg-radio-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    </span>
                    <span>הצטרפות לבית</span>
                </label>
            </div>

            <div id="fieldsCreate">
                <div class="form-group">
                    <label for="reg_home_name">שם הבית (כינוי לבית)</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </span>
                        <input type="text" name="home_name" id="reg_home_name" class="form-control" placeholder="למשל: הבית של משפחת ישראלי">
                    </div>
                </div>
            </div>

            <div id="fieldsJoin" class="hidden">
                <div class="form-group">
                    <label for="reg_home_code">קוד בית</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                        </span>
                        <input type="text" name="home_code" id="reg_home_code" class="form-control" value="<?php echo $prefilled_code; ?>" placeholder="הזינו את קוד ההצטרפות">
                    </div>
                </div>
            </div>

            <div class="cp-reg-tos">
                <label>
                    <input type="checkbox" name="accept_tos" id="acceptTosCb" value="1" onchange="toggleRegBtn()">
                    <span>קראתי ואני מסכים ל<a href="#" onclick="openTosModal(event)">תנאי השימוש ומדיניות הפרטיות</a></span>
                </label>
            </div>

            <button type="submit" name="register_btn" id="regBtn" class="submit-btn" disabled>השלמת הרשמה</button>
        </form>

        <p class="auth-switch">כבר יש לך חשבון? <a href="<?php echo BASE_URL; ?>pages/login.php">התחבר כאן</a></p>
    </div>

    <div id="tosModal" class="cp-tos-modal-overlay">
        <div class="cp-tos-modal-box">
            <button type="button" onclick="closeTosModal()" class="close-modal-btn cp-tos-modal-close" aria-label="סגור" title="סגור">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
            <h2>תקנון המערכת</h2>
            <div class="cp-tos-modal-body">
                <?php include(ROOT_PATH . '/assets/includes/tos_content.php'); ?>
            </div>
            <button type="button" class="submit-btn cp-tos-modal-done" onclick="closeTosModal()">קראתי את התקנון</button>
        </div>
    </div>

    <script>
        const rCreate = document.querySelector('input[name="home_action"][value="create"]');
        const rJoin = document.querySelector('input[name="home_action"][value="join"]');
        const fCreate = document.getElementById('fieldsCreate');
        const fJoin = document.getElementById('fieldsJoin');
        const bCreate = document.getElementById('btnCreate');
        const bJoin = document.getElementById('btnJoin');

        rCreate.onchange = () => {
            fCreate.classList.remove('hidden'); fJoin.classList.add('hidden');
            bCreate.classList.add('active'); bJoin.classList.remove('active');
        };
        rJoin.onchange = () => {
            fJoin.classList.remove('hidden'); fCreate.classList.add('hidden');
            bJoin.classList.add('active'); bCreate.classList.remove('active');
        };

        const prefilledCode = "<?php echo $prefilled_code; ?>";
        if (prefilledCode !== "") {
            rJoin.checked = true;
            fJoin.classList.remove('hidden');
            fCreate.classList.add('hidden');
            bJoin.classList.add('active');
            bCreate.classList.remove('active');
        }

        document.getElementById('registerForm').onsubmit = (e) => {
            if (document.getElementById('reg_pass').value.length < 4) {
                e.preventDefault();
                tazrimAlert({ title: 'בדיקת סיסמה', message: 'הסיסמה חייבת להיות לפחות 4 תווים' });
            }
        };

        function toggleRegBtn() {
            document.getElementById('regBtn').disabled = !document.getElementById('acceptTosCb').checked;
        }

        function openTosModal(e) {
            e.preventDefault();
            document.getElementById('tosModal').style.display = 'flex';
        }

        function closeTosModal() {
            document.getElementById('tosModal').style.display = 'none';
        }
    </script>
</body>
</html>

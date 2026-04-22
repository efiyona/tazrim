<?php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php");

$prefilled_code = isset($_GET['join_code']) ? htmlspecialchars($_GET['join_code']) : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <style>
        :root {
            --bg-1: #f4fff8;
            --bg-2: #e8f9ee;
            --card-bg: rgba(255, 255, 255, 0.92);
            --text-main: #113126;
            --text-muted: #587469;
            --accent: #21a95d;
            --accent-hover: #1a8d4d;
            --border: #d1d5db;
            --danger-bg: #fff2f2;
            --danger-border: #ffcccc;
            --danger-text: #a92525;
            --shadow: 0 24px 60px rgba(25, 67, 48, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Heebo", "Assistant", Arial, sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at 20% 15%, #d8f4e3 0%, transparent 45%),
                radial-gradient(circle at 80% 85%, #d5efe4 0%, transparent 40%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2));
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-layout {
            width: min(1080px, 100%);
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(8px);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
        }

        .auth-side {
            background: var(--white);
            color: #fff;
            padding: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .side-logo {
            display: block;
            width: 100%;
            height: 100%;
            max-height: 300px;
            object-fit: contain;
        }

        .side-logo-mobile {
            display: none;
        }

        .auth-panel {
            padding: 44px clamp(22px, 4vw, 40px);
        }

        .auth-header {
            margin-bottom: 22px;
        }

        .auth-header h2 {
            margin: auto;
            text-align: center;
            font-size: clamp(1.4rem, 2.2vw, 1.75rem);
            font-weight: 800;
        }

        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            border-radius: 14px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }

        .alert-error ul {
            margin: 0;
            padding-right: 18px;
        }

        .auth-form {
            display: grid;
            gap: 14px;
        }

        .stepper-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d1d5db;
            transition: 0.3s ease;
        }

        .dot.active {
            background: var(--accent);
            width: 26px;
            border-radius: 10px;
        }

        .step {
            display: none;
            animation: fadeIn 0.28s ease;
        }

        .step.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cp-reg-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group label {
            margin: 0;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
            text-align: right;
        }

        .input-with-icon {
            position: relative;
        }

        .cp-login-input-icon {
            position: absolute;
            inset-inline-start: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 16px;
            padding-inline-start: 48px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text-main);
            font-size: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(33, 169, 93, 0.18);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .cp-reg-section-label {
            display: block;
            margin: 4px 0 2px;
            font-weight: 700;
            font-size: 14px;
            color: var(--text-main);
            text-align: right;
        }

        .cp-reg-radio-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .cp-reg-radio-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 12px;
            cursor: pointer;
            text-align: center;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
            background: #f8fafc;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
            margin: 0;
        }

        .cp-reg-radio-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }

        .cp-reg-radio-card .cp-reg-radio-icon {
            display: flex;
            color: var(--accent);
        }

        .cp-reg-radio-card.active {
            border-color: var(--accent);
            background: #ebfaf1;
            box-shadow: 0 0 0 1px rgba(41, 182, 105, 0.2);
        }

        .cp-reg-tos {
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .cp-reg-tos label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
            margin: 0;
            line-height: 1.45;
            text-align: right;
        }

        .cp-reg-tos input[type="checkbox"] {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            accent-color: var(--accent);
            margin-top: 2px;
        }

        .cp-reg-tos a {
            color: var(--accent);
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            letter-spacing: 0.02em;
            position: relative;
        }

        .submit-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(41, 182, 105, 0.28);
        }

        .submit-btn::after {
            content: "\f060";
            font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome";
            font-weight: 900;
            font-size: 0.9em;
            line-height: 1;
            display: inline-block;
            max-width: 0;
            opacity: 0;
            margin-inline-start: 0;
            transform: translateX(6px);
            overflow: hidden;
            white-space: nowrap;
            vertical-align: -0.05em;
            transition:
                max-width 0.3s ease,
                opacity 0.25s ease,
                margin-inline-start 0.3s ease,
                transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submit-btn:hover::after,
        .submit-btn:focus-visible::after {
            max-width: 1.2em;
            opacity: 1;
            margin-inline-start: 8px;
            transform: translateX(0);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .submit-btn:disabled:hover {
            background: var(--accent);
        }

        .submit-btn:disabled::after {
            max-width: 0 !important;
            opacity: 0 !important;
            margin-inline-start: 0 !important;
            transform: translateX(6px) !important;
        }

        .auth-switch {
            margin: 18px 0 0;
            text-align: center;
            font-size: 15px;
            color: var(--text-muted);
        }

        .auth-switch a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .auth-switch a:hover {
            text-decoration: underline;
        }

        .step-nav {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .step-nav .submit-btn {
            margin: 0;
        }

        .submit-btn.btn-secondary {
            background: #f1f5f9;
            color: #1f2937;
            box-shadow: none;
        }

        .submit-btn.btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: none;
        }

        @media (prefers-reduced-motion: reduce) {
            .submit-btn::after,
            .submit-btn:hover::after,
            .submit-btn:focus-visible::after {
                transition: none;
                transform: none;
            }
        }

        .hidden {
            display: none;
        }

        .cp-tos-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cp-tos-modal-box {
            position: relative;
            background: #fff;
            width: min(760px, 100%);
            max-height: 86vh;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .cp-tos-modal-box h2 {
            margin: 0;
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1.15rem;
        }

        .cp-tos-modal-body {
            padding: 16px 22px;
            overflow: auto;
            line-height: 1.6;
        }

        .cp-tos-modal-done {
            margin: 0 22px 22px;
            width: calc(100% - 44px);
        }

        .cp-tos-modal-close {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 10px;
            background: #f1f5f9;
            color: #334155;
            display: grid;
            place-items: center;
            cursor: pointer;
        }

        @media (max-width: 860px) {
            body {
                padding-top: 36px;
            }

            .auth-layout {
                grid-template-columns: 1fr;
                width: min(520px, calc(100% - 36px));
                max-width: 520px;

                padding: 20px;
            }

            .auth-side {
                order: -1;
                min-height: 150px;
            }

            .auth-panel {
                padding: 0 20px;
                padding-bottom: 20px;
            }

            .side-logo {
                max-width: 300px;
            }

            .side-logo-desktop {
                display: none;
            }

            .side-logo-mobile {
                display: block;
            }

            .auth-header h2 {
                font-size: clamp(1.55rem, 5.4vw, 1.9rem);
            }
            
        }

        @media (max-width: 620px) {
            .cp-reg-radio-group {
                grid-template-columns: 1fr;
            }

            .auth-panel {
                padding: 26px 16px;
            }

            .step-nav {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="auth-cp auth-register">
    <main class="auth-layout" role="main">
        <section class="auth-side" aria-label="מיתוג והסבר">
            <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver.png" alt="תזרים" class="side-logo side-logo-desktop">
            <img src="<?php echo BASE_URL; ?>assets/images/logo-header.png" alt="תזרים" class="side-logo side-logo-mobile">
        </section>

        <section class="auth-panel">
            <header class="auth-header">
                <h2>הרשמה למערכת</h2>
            </header>

            <div class="stepper-dots" aria-hidden="true">
                <div class="dot active" id="dot-1"></div>
                <div class="dot" id="dot-2"></div>
            </div>

        <?php if (count($errors) > 0): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" id="registerForm" class="auth-form">
            <div class="step active" id="step-1">
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
                            <input type="tel" name="phone" id="reg_phone" class="form-control" value="<?php echo htmlspecialchars(tazrim_phone_for_display($phone)); ?>" required placeholder="05XXXXXXXX" autocomplete="tel">
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

                <div class="step-nav">
                    <button type="button" id="nextToStep2" class="submit-btn">המשך להגדרת הבית</button>
                </div>
            </div>

            <div class="step" id="step-2">
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

                <div class="step-nav">
                    <button type="button" id="backToStep1" class="submit-btn btn-secondary">חזרה לפרטי משתמש</button>
                    <button type="submit" name="register_btn" id="regBtn" class="submit-btn" disabled>השלמת הרשמה</button>
                </div>
            </div>
        </form>

        <p class="auth-switch">כבר יש לך חשבון? <a href="<?php echo BASE_URL; ?>pages/login.php">התחבר כאן</a></p>
        </section>
    </main>

    <div id="tosModal" class="cp-tos-modal-overlay">
        <div class="cp-tos-modal-box">
            <button type="button" onclick="closeTosModal()" class="close-modal-btn cp-tos-modal-close" aria-label="סגור" title="סגור">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
            <h2>תקנון המערכת</h2>
            <div class="cp-tos-modal-body">
                <?php echo tazrim_tos_content_html(); ?>
            </div>
            <button type="button" class="submit-btn cp-tos-modal-done" onclick="closeTosModal()">קראתי את התקנון</button>
        </div>
    </div>

    <script>
        function goToStep(stepNum) {
            document.querySelectorAll('.step').forEach((step) => step.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            document.querySelectorAll('.dot').forEach((dot) => dot.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        function validateStepOne() {
            const requiredStepOneIds = ['reg_first_name', 'reg_last_name', 'reg_email', 'reg_phone', 'reg_pass'];
            for (const fieldId of requiredStepOneIds) {
                const field = document.getElementById(fieldId);
                if (!field) {
                    continue;
                }
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            if (document.getElementById('reg_pass').value.length < 4) {
                tazrimAlert({ title: 'בדיקת סיסמה', message: 'הסיסמה חייבת להיות לפחות 4 תווים' });
                return false;
            }
            return true;
        }

        const rCreate = document.querySelector('input[name="home_action"][value="create"]');
        const rJoin = document.querySelector('input[name="home_action"][value="join"]');
        const fCreate = document.getElementById('fieldsCreate');
        const fJoin = document.getElementById('fieldsJoin');
        const bCreate = document.getElementById('btnCreate');
        const bJoin = document.getElementById('btnJoin');
        const nextToStep2Btn = document.getElementById('nextToStep2');
        const backToStep1Btn = document.getElementById('backToStep1');

        nextToStep2Btn.addEventListener('click', () => {
            if (validateStepOne()) {
                goToStep(2);
            }
        });

        backToStep1Btn.addEventListener('click', () => {
            goToStep(1);
        });

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

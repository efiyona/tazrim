<?php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php");

$prefilled_code = isset($_GET['join_code']) ? htmlspecialchars($_GET['join_code']) : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הרשמה | תזרים</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;900&display=swap');

        :root {
            --primary: #21a95d; /* הירוק של תזרים */
            --primary-dark: #1a8d4d;
            --bg-light: #f8fafc; /* רקע כללי בהיר */
            --bg-card: #ffffff; /* רקע טופס לבן */
            --text-main: #0f172a; /* טקסט כהה */
            --text-muted: #64748b; /* טקסט אפור */
            --border-color: #e2e8f0; /* גבולות בהירים */
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Heebo', sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-main);
            overflow-x: hidden;
            padding: 0 !important;
        }

        .auth-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ----- Form Section (Right Side in RTL) ----- */
        .auth-form-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            z-index: 10;
            position: relative;
            background-color: #f8fafc;
        }

        .auth-container {
            width: 100%;
            max-width: 480px; /* מעט רחב יותר מעמוד ההתחברות כדי להכיל 2 עמודות */
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 2.5rem 2.5rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.05);
            animation: slideIn 0.6s ease-out;
            margin-bottom: 3rem;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-header h1 {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .auth-header p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* Stepper */
        .stepper-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 2rem;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #cbd5e1;
            transition: 0.3s ease;
        }

        .dot.active {
            background: var(--primary);
            width: 26px;
            border-radius: 10px;
        }

        .step {
            display: none;
            animation: fadeInStep 0.3s ease;
        }

        .step.active {
            display: block;
        }

        @keyframes fadeInStep {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Alerts */
        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error ul {
            padding-inline-start: 1.2rem;
            margin: 0;
        }

        /* Form Inputs */
        .cp-reg-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            color: var(--text-main);
            font-weight: 600;
        }

        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrapper i.icon-main {
            position: absolute;
            right: 1.2rem;
            color: #94a3b8;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: var(--text-main);
            padding: 0.9rem 2.8rem 0.9rem 1rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(33, 169, 93, 0.15);
        }

        .form-control:focus ~ i.icon-main {
            color: var(--primary);
        }

        .toggle-password {
            position: absolute;
            left: 1.2rem;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        /* Radio Group (Step 2) */
        .cp-reg-section-label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .cp-reg-radio-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .cp-reg-radio-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 1.2rem 1rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 16px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            background: #f8fafc;
            font-weight: 600;
            font-size: 1rem;
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

        .cp-reg-radio-card i {
            font-size: 1.5rem;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .cp-reg-radio-card.active {
            border-color: var(--primary);
            background: #f0fdf4;
            box-shadow: 0 4px 12px rgba(33, 169, 93, 0.1);
        }

        .cp-reg-radio-card.active i {
            color: var(--primary);
        }

        /* TOS Checkbox */
        .cp-reg-tos {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .cp-reg-tos label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-main);
            margin: 0;
        }

        .cp-reg-tos input[type="checkbox"] {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            accent-color: var(--primary);
        }

        .cp-reg-tos a {
            color: var(--primary);
            text-decoration: underline;
            font-weight: 700;
        }

        /* Submit Buttons */
        .step-nav {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }

        .btn-submit {
            flex: 1;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 999px; /* כפתור מעוגל לחלוטין */
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(33, 169, 93, 0.4);
        }

        .btn-submit:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-main);
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05);
        }

        /* Footer */
        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-weight: 500;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .back-home {
            position: absolute;
            bottom: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.2s;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 0.6rem 1.2rem;
            border-radius: 999px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .back-home:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        /* ----- Visual Section (Left Side) ----- */
        .auth-visual-side {
            flex: 1.2;
            display: none;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 992px) {
            .auth-visual-side {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
        }

        /* Animated Background Elements */
        .glass-orb {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), transparent);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: float 8s infinite ease-in-out alternate;
        }

        .orb-1 { width: 400px; height: 400px; top: -100px; left: -100px; }
        .orb-2 { width: 300px; height: 300px; bottom: -50px; right: 10%; animation-delay: -4s; background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent); }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-30px) scale(1.05); }
        }

        .visual-content {
            z-index: 2;
            text-align: center;
            padding: 2rem;
        }

        .visual-logo {
            max-width: 280px;
            margin-bottom: 2rem;
        }

        /* Modal Styles */
        .hidden { display: none !important; }
        
        .cp-tos-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
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
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .cp-tos-modal-box h2 {
            margin: 0;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 1.25rem;
            color: var(--text-main);
        }

        .cp-tos-modal-body {
            padding: 1.5rem;
            overflow: auto;
            line-height: 1.6;
            color: var(--text-muted);
        }

        .cp-tos-modal-done {
            margin: 1.5rem;
        }

        .cp-tos-modal-close {
            position: absolute;
            top: 1.2rem;
            left: 1.2rem;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 50%;
            background: #f1f5f9;
            color: var(--text-muted);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cp-tos-modal-close:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        @media (max-width: 620px) {
            .cp-reg-row { grid-template-columns: 1fr; gap: 0; }
            .step-nav { flex-direction: column; }
            .auth-container { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

    <main class="auth-wrapper">
        
        <section class="auth-form-side">
            <div class="auth-container">
                <header class="auth-header">
                    <h1>הרשמה למערכת</h1>
                    <p>יצירת חשבון וחיבור לבית</p>
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

                <form action="register.php" method="POST" id="registerForm">
                    
                    <div class="step active" id="step-1">
                        <div class="cp-reg-row">
                            <div class="form-group">
                                <label for="reg_first_name">שם פרטי</label>
                                <div class="input-icon-wrapper">
                                    <input type="text" name="first_name" id="reg_first_name" class="form-control" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required placeholder="השם שלך" autocomplete="given-name">
                                    <i class="fa-regular fa-user icon-main"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="reg_last_name">שם משפחה</label>
                                <div class="input-icon-wrapper">
                                    <input type="text" name="last_name" id="reg_last_name" class="form-control" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required placeholder="שם המשפחה" autocomplete="family-name">
                                    <i class="fa-regular fa-user icon-main"></i>
                                </div>
                            </div>
                        </div>

                        <div class="cp-reg-row">
                            <div class="form-group">
                                <label for="reg_email">אימייל</label>
                                <div class="input-icon-wrapper">
                                    <input type="email" name="email" id="reg_email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required placeholder="mail@example.com" autocomplete="email">
                                    <i class="fa-regular fa-envelope icon-main"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="reg_phone">טלפון</label>
                                <div class="input-icon-wrapper">
                                    <input type="tel" name="phone" id="reg_phone" class="form-control" value="<?php echo htmlspecialchars(tazrim_phone_for_display($phone ?? '')); ?>" required placeholder="05XXXXXXXX" autocomplete="tel">
                                    <i class="fa-solid fa-phone icon-main"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg_nickname">כינוי (Nickname)</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="nickname" id="reg_nickname" class="form-control" value="<?php echo htmlspecialchars($nickname ?? ''); ?>" placeholder="איך לקרוא לך במערכת?" autocomplete="nickname">
                                <i class="fa-solid fa-user-tag icon-main"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg_pass">סיסמה</label>
                            <div class="input-icon-wrapper">
                                <input type="password" name="password" id="reg_pass" class="form-control" required placeholder="לפחות 4 תווים" autocomplete="new-password">
                                <i class="fa-solid fa-lock icon-main"></i>
                                <i class="fa-regular fa-eye toggle-password" id="togglePassword" title="הצג/הסתר סיסמה"></i>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" id="nextToStep2" class="btn-submit">
                                המשך להגדרת הבית <i class="fa-solid fa-arrow-left"></i>
                            </button>
                        </div>
                    </div>

                    <div class="step" id="step-2">
                        <p class="cp-reg-section-label">בחירת מסלול בית</p>
                        
                        <div class="cp-reg-radio-group">
                            <label class="cp-reg-radio-card active" id="btnCreate">
                                <input type="radio" name="home_action" value="create" checked>
                                <i class="fa-solid fa-house-chimney-window"></i>
                                <span>יצירת בית</span>
                            </label>
                            <label class="cp-reg-radio-card" id="btnJoin">
                                <input type="radio" name="home_action" value="join">
                                <i class="fa-solid fa-people-roof"></i>
                                <span>הצטרפות לבית</span>
                            </label>
                        </div>

                        <div id="fieldsCreate">
                            <div class="form-group">
                                <label for="reg_home_name">שם הבית (כינוי לבית)</label>
                                <div class="input-icon-wrapper">
                                    <input type="text" name="home_name" id="reg_home_name" class="form-control" placeholder="למשל: הבית של משפחת ישראלי">
                                    <i class="fa-solid fa-house icon-main"></i>
                                </div>
                            </div>
                        </div>

                        <div id="fieldsJoin" class="hidden">
                            <div class="form-group">
                                <label for="reg_home_code">קוד בית</label>
                                <div class="input-icon-wrapper">
                                    <input type="text" name="home_code" id="reg_home_code" class="form-control" value="<?php echo $prefilled_code; ?>" placeholder="הזינו את קוד ההצטרפות">
                                    <i class="fa-solid fa-key icon-main"></i>
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
                            <button type="button" id="backToStep1" class="btn-submit btn-secondary">
                                <i class="fa-solid fa-arrow-right"></i> חזור שלב
                            </button>
                            <button type="submit" name="register_btn" id="regBtn" class="btn-submit" disabled>
                                השלמת הרשמה <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="auth-footer">
                    כבר יש לך חשבון? <a href="<?php echo BASE_URL; ?>pages/login.php">התחבר כאן</a>
                </div>
            </div>
        </section>

        <section class="auth-visual-side">
            <div class="glass-orb orb-1"></div>
            <div class="glass-orb orb-2"></div>
            
            <div class="visual-content">
                <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver-white.png" alt="תזרים" class="visual-logo" onerror="this.style.display='none'">
                <p style="color: rgba(255,255,255,0.85); font-size: 1.2rem; margin-top: 1rem; max-width: 400px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    שליטה מלאה על ההוצאות, ההכנסות והתקציב הביתי שלך במקום אחד. התחילו עכשיו.
                </p>
            </div>
        </section>

    </main>

    <div id="tosModal" class="cp-tos-modal-overlay">
        <div class="cp-tos-modal-box">
            <button type="button" onclick="closeTosModal()" class="cp-tos-modal-close" aria-label="סגור" title="סגור">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h2>תקנון המערכת</h2>
            <div class="cp-tos-modal-body">
                <?php echo tazrim_tos_content_html(); ?>
            </div>
            <button type="button" class="btn-submit cp-tos-modal-done" onclick="closeTosModal()">קראתי את התקנון</button>
        </div>
    </div>

    <script>
        // הצג / הסתר סיסמה
        document.addEventListener('DOMContentLoaded', () => {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('reg_pass');
            if(togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                    this.style.color = type === 'text' ? 'var(--primary)' : '#94a3b8';
                });
            }
        });

        // לוגיקת צעדים
        function goToStep(stepNum) {
            document.querySelectorAll('.step').forEach((step) => step.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            document.querySelectorAll('.dot').forEach((dot) => dot.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        // ולידציית שלב 1
        function validateStepOne() {
            const requiredStepOneIds = ['reg_first_name', 'reg_last_name', 'reg_email', 'reg_phone', 'reg_pass'];
            for (const fieldId of requiredStepOneIds) {
                const field = document.getElementById(fieldId);
                if (!field) continue;
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            if (document.getElementById('reg_pass').value.length < 4) {
                alert('הסיסמה חייבת להיות לפחות 4 תווים'); // החלף ב-tazrimAlert אם קיים
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
            if (validateStepOne()) goToStep(2);
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
                alert('הסיסמה חייבת להיות לפחות 4 תווים');
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
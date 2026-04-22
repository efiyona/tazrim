<?php require_once('../path.php'); ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>שחזור סיסמה | תזרים</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;700;900&display=swap');

        :root {
            --primary: #21a95d;
            --primary-dark: #1a8d4d;
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
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

        /* ----- Form Section ----- */
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
            max-width: 480px;
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
            margin-bottom: 2rem;
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

        /* Alerts */
        .cp-forgot-alert {
            display: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            font-size: 0.95rem;
        }

        .step-hidden {
            display: none;
        }

        /* Form Inputs */
        .cp-reg-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
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

        .cp-forgot-code-input {
            text-align: center;
            letter-spacing: 0.3em;
            padding-inline-start: 1rem;
            padding-inline-end: 1rem;
            font-weight: 700;
            font-size: 1.2rem;
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

        /* Submit Buttons */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 999px;
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
            margin-top: 0.8rem;
            background: #f1f5f9;
            color: var(--text-main);
            border: 1px solid #cbd5e1;
            padding: 0.8rem;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.05);
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

        @media (max-width: 620px) {
            .cp-reg-row { grid-template-columns: 1fr; gap: 0; }
            .auth-container { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

    <main class="auth-wrapper">
        
        <section class="auth-form-side">
            <div class="auth-container">
                <header class="auth-header">
                    <h1>שחזור סיסמה</h1>
                    <p id="step-desc">הזינו אימייל לקבלת קוד אימות</p>
                </header>

                <div id="alert-msg" class="cp-forgot-alert" role="alert"></div>

                <form id="forgot-form">
                    
                    <div id="step-1">
                        <div class="form-group">
                            <label for="email">כתובת אימייל</label>
                            <div class="input-icon-wrapper">
                                <input type="email" id="email" class="form-control" placeholder="name@example.com" required autocomplete="email">
                                <i class="fa-regular fa-envelope icon-main"></i>
                            </div>
                        </div>
                        <button type="button" id="btn-step-1" class="btn-submit">
                            לקבלת קוד אימות <i class="fa-solid fa-arrow-left"></i>
                        </button>
                    </div>

                    <div id="step-2" class="step-hidden">
                        <div class="form-group">
                            <label for="code">קוד אימות (6 ספרות)</label>
                            <div class="input-icon-wrapper" style="justify-content: center;">
                                <input type="text" id="code" class="form-control cp-forgot-code-input" placeholder="123456" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code">
                            </div>
                        </div>
                        <button type="button" id="btn-step-2" class="btn-submit">
                            אימות קוד <i class="fa-solid fa-check-double"></i>
                        </button>
                        <button type="button" id="btn-resend-code" class="btn-submit btn-secondary">
                            <i class="fa-solid fa-rotate-right"></i> שלח קוד מחדש
                        </button>
                    </div>

                    <div id="step-3" class="step-hidden">
                        <div class="cp-reg-row">
                            <div class="form-group">
                                <label for="password">סיסמה חדשה</label>
                                <div class="input-icon-wrapper">
                                    <input type="password" id="password" class="form-control" placeholder="סיסמה חדשה" autocomplete="new-password">
                                    <i class="fa-solid fa-lock icon-main"></i>
                                    <i class="fa-regular fa-eye toggle-password password-eye"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">אימות סיסמה</label>
                                <div class="input-icon-wrapper">
                                    <input type="password" id="confirm_password" class="form-control" placeholder="הזינו שוב" autocomplete="new-password">
                                    <i class="fa-solid fa-lock icon-main"></i>
                                    <i class="fa-regular fa-eye toggle-password confirm-eye"></i>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="btn-step-3" class="btn-submit">
                            עדכון סיסמה <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                    </div>
                </form>

                <div class="auth-footer">
                    נזכרת בסיסמה? <a href="<?php echo BASE_URL; ?>pages/login.php">להתחברות</a>
                </div>
            </div>

        </section>

        <section class="auth-visual-side">
            <div class="glass-orb orb-1"></div>
            <div class="glass-orb orb-2"></div>
            
            <div class="visual-content">
                <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver-white.png" alt="תזרים" class="visual-logo" onerror="this.style.display='none'">
                <p style="color: rgba(255,255,255,0.85); font-size: 1.2rem; margin-top: 1rem; max-width: 400px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    שכחתם סיסמה? לא נורא. נשחזר אותה ומיד תוכלו לחזור לנהל את התזרים שלכם.
                </p>
            </div>
        </section>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // הצג/הסתר סיסמה
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
                this.style.color = type === 'text' ? 'var(--primary)' : '#94a3b8';
            });
        });

        $(document).ready(function() {
            const handler = '../app/ajax/forgot_password_handler.php';
            const loginSuccessUrl = '<?php echo BASE_URL; ?>pages/login.php?reset=success';
            let email = '';
            let resendCooldownTimer = null;
            let resendCooldownRemaining = 0;

            function setResendState(enabled) {
                const $btn = $('#btn-resend-code');
                if (!$btn.length) return;
                if (enabled) {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> שלח קוד מחדש');
                    return;
                }
                $btn.prop('disabled', true).html('<i class="fa-solid fa-hourglass-half"></i> שלח קוד מחדש בעוד ' + resendCooldownRemaining + ' שנ׳');
            }

            function startResendCooldown(seconds) {
                resendCooldownRemaining = seconds;
                setResendState(false);
                if (resendCooldownTimer) clearInterval(resendCooldownTimer);
                resendCooldownTimer = setInterval(function() {
                    resendCooldownRemaining -= 1;
                    if (resendCooldownRemaining <= 0) {
                        clearInterval(resendCooldownTimer);
                        resendCooldownTimer = null;
                        setResendState(true);
                        return;
                    }
                    setResendState(false);
                }, 1000);
            }

            function sendCode($sourceBtn) {
                email = $('#email').val();
                if (!email) {
                    showAlert('נא להזין אימייל תקין', 'error');
                    return;
                }

                const originalHTML = $sourceBtn.html();
                $sourceBtn.prop('disabled', true).html('שולח... <i class="fa-solid fa-spinner fa-spin"></i>');
                
                $.post(handler, { action: 'send_code', email: email }, function(res) {
                    if (res.status === 'success') {
                        $('#step-1').fadeOut(300, function() {
                            $('#step-2').removeClass('step-hidden').hide().fadeIn(300);
                            $('#step-desc').text('הזינו את הקוד שנשלח אליכם לתיבת המייל');
                            showAlert(res.message, 'success');
                        });
                        startResendCooldown(30);
                    } else {
                        showAlert(res.message, 'error');
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                }).always(function() {
                    if ($sourceBtn.attr('id') === 'btn-step-1') {
                        $sourceBtn.html(originalHTML);
                    }
                    if ($sourceBtn.attr('id') === 'btn-resend-code') {
                        if (resendCooldownRemaining > 0) {
                            setResendState(false);
                        } else {
                            $sourceBtn.prop('disabled', false).html('<i class="fa-solid fa-rotate-right"></i> שלח קוד מחדש');
                        }
                    } else {
                        $sourceBtn.prop('disabled', false);
                    }
                });
            }

            // עדכון הפונקציה כך שתתאים לעיצוב המודרני
            function showAlert(msg, type) {
                const color = type === 'success' ? '#15803d' : '#b91c1c';
                const bg = type === 'success' ? '#f0fdf4' : '#fef2f2';
                const borderColor = type === 'success' ? '#bbf7d0' : '#fecaca';
                
                $('#alert-msg').html(msg).css({
                    'display': 'block', 
                    'color': color, 
                    'background': bg, 
                    'border': '1px solid ' + borderColor
                });
            }

            $('#btn-step-1').click(function() {
                sendCode($(this));
            });

            $('#btn-resend-code').click(function() {
                if (resendCooldownRemaining > 0) return;
                sendCode($(this));
            });

            $('#btn-step-2').click(function() {
                const code = $('#code').val();
                
                const $btn = $(this);
                const originalHTML = $btn.html();
                $btn.prop('disabled', true).html('מאמת... <i class="fa-solid fa-spinner fa-spin"></i>');

                $.post(handler, { action: 'verify_code', email: email, code: code }, function(res) {
                    if (res.status === 'success') {
                        $('#step-2').fadeOut(300, function() {
                            $('#step-3').removeClass('step-hidden').hide().fadeIn(300);
                            $('#step-desc').text('בחרו סיסמה חדשה וחזקה');
                            $('#alert-msg').hide();
                        });
                    } else {
                        showAlert(res.message, 'error');
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                }).always(function() {
                    $btn.prop('disabled', false).html(originalHTML);
                });
            });

            $('#btn-step-3').click(function() {
                const pass = $('#password').val();
                const confirm = $('#confirm_password').val();
                
                const $btn = $(this);
                const originalHTML = $btn.html();
                $btn.prop('disabled', true).html('מעדכן... <i class="fa-solid fa-spinner fa-spin"></i>');

                $.post(handler, { action: 'reset_password', email: email, password: pass, confirm_password: confirm }, function(res) {
                    if (res.status === 'success') {
                        showAlert(res.message, 'success');
                        setTimeout(function() { window.location.href = loginSuccessUrl; }, 2000);
                    } else {
                        showAlert(res.message, 'error');
                        $btn.prop('disabled', false).html(originalHTML);
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                    $btn.prop('disabled', false).html(originalHTML);
                });
            });
        });
    </script>
</body>
</html>
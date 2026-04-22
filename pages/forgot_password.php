<?php require_once('../path.php'); ?>
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
            width: min(980px, 100%);
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

        .auth-header p {
            margin: 10px 0 0;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .cp-forgot-alert {
            display: none;
            border: 1px solid rgba(245, 101, 101, 0.35);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .auth-form {
            display: grid;
            gap: 14px;
        }

        .step-hidden {
            display: none;
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

        .cp-forgot-code-input {
            text-align: center;
            letter-spacing: 0.32em;
            padding-inline-start: 16px;
            font-weight: 700;
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
            transition: max-width 0.3s ease, opacity 0.25s ease, margin-inline-start 0.3s ease, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submit-btn:hover::after,
        .submit-btn:focus-visible::after {
            max-width: 1.2em;
            opacity: 1;
            margin-inline-start: 8px;
            transform: translateX(0);
        }

        .submit-btn.btn-secondary {
            margin-top: 8px;
            background: #e2e8f0;
            color: #334155;
            box-shadow: none;
            width: auto;
            min-width: 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            justify-self: start;
        }

        .submit-btn.btn-secondary:hover {
            background: #cfd8e3;
            box-shadow: none;
        }

        .submit-btn.btn-secondary::after {
            display: none;
        }

        .submit-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .submit-btn:disabled::after {
            opacity: 0 !important;
            max-width: 0 !important;
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

        @media (prefers-reduced-motion: reduce) {
            .submit-btn::after,
            .submit-btn:hover::after,
            .submit-btn:focus-visible::after {
                transition: none;
                transform: none;
            }
        }

        @media (max-width: 860px) {
            body {
                padding-top: 36px;
            }

            .auth-layout {
                grid-template-columns: 1fr;
                width: 95%;
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
            .cp-reg-row {
                grid-template-columns: 1fr;
            }

            .auth-panel {
                padding: 26px 16px;
            }
        }
    </style>
</head>
<body class="auth-cp auth-forgot">
    <main class="auth-layout" role="main">
        <section class="auth-side" aria-label="מיתוג והסבר">
            <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver.png" alt="תזרים" class="side-logo side-logo-desktop">
            <img src="<?php echo BASE_URL; ?>assets/images/logo-header.png" alt="תזרים" class="side-logo side-logo-mobile">
        </section>

        <section class="auth-panel">
            <header class="auth-header">
                <h2>שחזור סיסמה</h2>
                <p id="step-desc">הזינו מייל לקבלת קוד אימות</p>
            </header>

            <div id="alert-msg" class="cp-forgot-alert" role="alert"></div>

            <form id="forgot-form" class="auth-form">
            <div id="step-1">
                <div class="form-group">
                    <label for="email">כתובת אימייל</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" id="email" class="form-control" placeholder="name@example.com" required autocomplete="email">
                    </div>
                </div>
                <button type="button" id="btn-step-1" class="submit-btn">לקבלת קוד אימות</button>
            </div>

            <div id="step-2" class="step-hidden">
                <div class="form-group">
                    <label for="code">קוד אימות (6 ספרות)</label>
                    <div class="input-with-icon">
                        <span class="cp-login-input-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <input type="text" id="code" class="form-control cp-forgot-code-input" placeholder="123456" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code">
                    </div>
                </div>
                <button type="button" id="btn-step-2" class="submit-btn">אימות קוד</button>
                <button type="button" id="btn-resend-code" class="submit-btn btn-secondary">
                    שלח קוד מחדש
                </button>
            </div>

            <div id="step-3" class="step-hidden">
                <div class="cp-reg-row">
                    <div class="form-group">
                        <label for="password">סיסמה חדשה</label>
                        <div class="input-with-icon">
                            <span class="cp-login-input-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" id="password" class="form-control" placeholder="סיסמה חדשה" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">אימות סיסמה</label>
                        <div class="input-with-icon">
                            <span class="cp-login-input-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </span>
                            <input type="password" id="confirm_password" class="form-control" placeholder="הזינו שוב" autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-step-3" class="submit-btn">עדכון סיסמה</button>
            </div>
            </form>

            <p class="auth-switch">נזכרת בסיסמה? <a href="<?php echo BASE_URL; ?>pages/login.php">להתחברות</a></p>
        </section>
    </main>

    <script>
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
                    $btn.prop('disabled', false).text('שלח קוד מחדש');
                    return;
                }
                $btn.prop('disabled', true).text('שלח קוד מחדש בעוד ' + resendCooldownRemaining + ' שנ׳');
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
                    showAlert('נא להזין מייל', 'error');
                    return;
                }

                const originalText = $sourceBtn.text();
                $sourceBtn.prop('disabled', true).text('שולח...');
                $.post(handler, { action: 'send_code', email: email }, function(res) {
                    if (res.status === 'success') {
                        $('#step-1').fadeOut(300, function() {
                            $('#step-2').removeClass('step-hidden').hide().fadeIn(300);
                            $('#step-desc').text('הזינו את הקוד שנשלח אליכם');
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
                        $sourceBtn.text(originalText);
                    }
                    if ($sourceBtn.attr('id') === 'btn-resend-code') {
                        if (resendCooldownRemaining > 0) {
                            setResendState(false);
                        } else {
                            $sourceBtn.prop('disabled', false).text('שלח קוד מחדש');
                        }
                    } else {
                        $sourceBtn.prop('disabled', false);
                    }
                });
            }

            function showAlert(msg, type) {
                const color = type === 'success' ? 'var(--main)' : 'var(--error)';
                const bg = type === 'success' ? 'var(--main-light)' : '#fff5f5';
                $('#alert-msg').html(msg).css({'display': 'block', 'color': color, 'background': bg, 'border-color': type === 'success' ? 'rgba(41,182,105,0.25)' : 'rgba(245,101,101,0.35)'});
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
                $.post(handler, { action: 'verify_code', email: email, code: code }, function(res) {
                    if (res.status === 'success') {
                        $('#step-2').fadeOut(300, function() {
                            $('#step-3').removeClass('step-hidden').hide().fadeIn(300);
                            $('#step-desc').text('בחרו סיסמה חדשה');
                            $('#alert-msg').hide();
                        });
                    } else {
                        showAlert(res.message, 'error');
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                });
            });

            $('#btn-step-3').click(function() {
                const pass = $('#password').val();
                const confirm = $('#confirm_password').val();
                $.post(handler, { action: 'reset_password', email: email, password: pass, confirm_password: confirm }, function(res) {
                    if (res.status === 'success') {
                        showAlert(res.message, 'success');
                        setTimeout(function() { window.location.href = loginSuccessUrl; }, 2000);
                    } else {
                        showAlert(res.message, 'error');
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                });
            });
        });
    </script>
</body>
</html>

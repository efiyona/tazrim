<?php require_once('../path.php'); ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
</head>
<body class="page-auth auth-cp auth-forgot">

    <div class="gradient-bg" aria-hidden="true"></div>

    <div class="form-container" role="main">
        <div class="logo">
            <div class="cp-login-logo-mark" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" role="img">
                    <circle cx="24" cy="24" r="22" fill="url(#cp-forgot-logo-grad)"/>
                    <path fill="#fff" d="M14 17h20a2 2 0 012 2v2H12v-2a2 2 0 012-2zm0 6h22v10a2 2 0 01-2 2H14a2 2 0 01-2-2V23zm14 2a1.5 1.5 0 100 3h4a1.5 1.5 0 100-3h-4z"/>
                    <defs>
                        <linearGradient id="cp-forgot-logo-grad" x1="10" y1="8" x2="38" y2="42" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#29b669"/>
                            <stop offset="1" stop-color="#4FD1C5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h1 class="cp-login-brand">תזרים</h1>
            <p class="cp-login-tagline" id="step-desc">הזינו מייל לקבלת קוד אימות</p>
        </div>

        <div id="alert-msg" class="cp-forgot-alert" style="display:none;" role="alert"></div>

        <form id="forgot-form" class="auth-form cp-login-form">
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
                <button type="button" id="btn-step-1" class="submit-btn">שלח קוד אימות</button>
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
                <button type="button" id="btn-step-2" class="submit-btn">אמת קוד והמשך</button>
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
                <button type="button" id="btn-step-3" class="submit-btn">עדכן סיסמה והתחבר</button>
            </div>
        </form>

        <p class="auth-switch">נזכרת בסיסמה? <a href="<?php echo BASE_URL; ?>pages/login.php">להתחברות</a></p>
    </div>

    <script>
        $(document).ready(function() {
            const handler = '../app/ajax/forgot_password_handler.php';
            const loginSuccessUrl = '<?php echo BASE_URL; ?>pages/login.php?reset=success';
            let email = '';

            function showAlert(msg, type) {
                const color = type === 'success' ? 'var(--main)' : 'var(--error)';
                const bg = type === 'success' ? 'var(--main-light)' : '#fff5f5';
                $('#alert-msg').html(msg).css({'display': 'block', 'color': color, 'background': bg, 'border-color': type === 'success' ? 'rgba(41,182,105,0.25)' : 'rgba(245,101,101,0.35)'});
            }

            $('#btn-step-1').click(function() {
                email = $('#email').val();
                if (!email) return showAlert('נא להזין מייל', 'error');

                $(this).prop('disabled', true).text('שולח...');
                $.post(handler, { action: 'send_code', email: email }, function(res) {
                    if (res.status === 'success') {
                        $('#step-1').fadeOut(300, function() {
                            $('#step-2').removeClass('step-hidden').hide().fadeIn(300);
                            $('#step-desc').text('הזינו את הקוד שנשלח אליכם');
                            showAlert(res.message, 'success');
                        });
                    } else {
                        showAlert(res.message, 'error');
                        $('#btn-step-1').prop('disabled', false).text('שלח קוד אימות');
                    }
                }, 'json').fail(function() {
                    showAlert('שגיאת תקשורת. נסו שוב.', 'error');
                    $('#btn-step-1').prop('disabled', false).text('שלח קוד אימות');
                });
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

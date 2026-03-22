<?php require_once('../path.php'); ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <style>
        .step-hidden { display: none; }
        .loading-spinner { display: none; margin-right: 10px; }
    </style>
</head>
<body class="bg-gray">
    <div class="split-screen-container">
        <div class="form-side flex-center">
            <div class="form-wrapper">
                <div class="brand-mobile">התזרים</div>
                <h1 class="page-title">איפוס סיסמה</h1>
                <p class="page-subtitle" id="step-desc">הזינו מייל לקבלת קוד אימות</p>

                <div id="alert-msg" style="display:none; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: 700;"></div>

                <form id="forgot-form" class="auth-form">
                    <div id="step-1">
                        <div class="input-group">
                            <label>כתובת אימייל</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" id="email" placeholder="name@example.com" required>
                            </div>
                        </div>
                        <button type="button" id="btn-step-1" class="btn-primary">
                            <i class="fa-solid fa-paper-plane"></i> שלח קוד אימות
                        </button>
                    </div>

                    <div id="step-2" class="step-hidden">
                        <div class="input-group">
                            <label>קוד אימות (6 ספרות)</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-shield-halved"></i>
                                <input type="number" id="code" placeholder="123456" style="letter-spacing: 5px; font-weight: 800;">
                            </div>
                        </div>
                        <button type="button" id="btn-step-2" class="btn-primary">אמת קוד והמשך</button>
                    </div>

                    <div id="step-3" class="step-hidden">
                        <div class="input-group">
                            <label>סיסמה חדשה</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="password" placeholder="הזינו סיסמה חדשה">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>אימות סיסמה</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-circle-check"></i>
                                <input type="password" id="confirm_password" placeholder="הזינו שוב">
                            </div>
                        </div>
                        <button type="button" id="btn-step-3" class="btn-primary">עדכן סיסמה והתחבר</button>
                    </div>
                </form>

                <p class="auth-switch">נזכרת בסיסמה? <a href="login.php">חזרה להתחברות</a></p>
            </div>
        </div>

        <div class="brand-side flex-center">
            <div class="brand-content text-center">
                <i class="fa-solid fa-key brand-icon"></i>
                <h2 class="brand-title">אבטחת חשבון</h2>
                <p class="brand-text">תהליך האיפוס שלנו מאובטח ומהיר כדי שתוכלו לחזור לנהל את התקציב בראש שקט.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const handler = '../app/ajax/forgot_password_handler.php';
            let email = '';

            function showAlert(msg, type) {
                const color = type === 'success' ? 'var(--main)' : 'var(--error)';
                const bg = type === 'success' ? 'var(--main-light)' : '#fff5f5';
                $('#alert-msg').html(msg).css({'display': 'block', 'color': color, 'background': bg});
            }

            // שלב 1: שליחת מייל
            $('#btn-step-1').click(function() {
                email = $('#email').val();
                if(!email) return showAlert('נא להזין מייל', 'error');

                $(this).prop('disabled', true).html('שולח...');
                $.post(handler, { action: 'send_code', email: email }, function(res) {
                    if(res.status === 'success') {
                        $('#step-1').fadeOut(300, function() {
                            $('#step-2').fadeIn();
                            $('#step-desc').text('הזינו את הקוד שנשלח אליכם');
                            showAlert(res.message, 'success');
                        });
                    } else {
                        showAlert(res.message, 'error');
                        $('#btn-step-1').prop('disabled', false).html('שלח קוד אימות');
                    }
                });
            });

            // שלב 2: אימות קוד
            $('#btn-step-2').click(function() {
                const code = $('#code').val();
                $.post(handler, { action: 'verify_code', email: email, code: code }, function(res) {
                    if(res.status === 'success') {
                        $('#step-2').fadeOut(300, function() {
                            $('#step-3').fadeIn();
                            $('#step-desc').text('בחרו סיסמה חדשה');
                            $('#alert-msg').hide();
                        });
                    } else {
                        showAlert(res.message, 'error');
                    }
                });
            });

            // שלב 3: עדכון סיסמה
            $('#btn-step-3').click(function() {
                const pass = $('#password').val();
                const confirm = $('#confirm_password').val();
                $.post(handler, { action: 'reset_password', email: email, password: pass, confirm_password: confirm }, function(res) {
                    if(res.status === 'success') {
                        showAlert(res.message, 'success');
                        setTimeout(() => window.location.href = 'login.php?reset=success', 2000);
                    } else {
                        showAlert(res.message, 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>
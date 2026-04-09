<?php
// pages/login.php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php"); // חיבור הלוגיקה

if (isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . 'index.php');
    exit();
} elseif (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $user = selectOne('users', ['remember_token' => $token]);
    
    if ($user) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['home_id'] = $user['home_id'];
        $_SESSION['role'] = $user['role'];
        
        header('location: ' . BASE_URL . 'index.php');
        exit();
    } else {
        setcookie('remember_token', '', time() - 3600, "/");
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
</head>
<body class="page-auth auth-cp auth-login">

    <div class="gradient-bg" aria-hidden="true"></div>

    <div class="form-container" role="main">
        <div class="logo">
            <div class="cp-login-logo-mark" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" role="img">
                    <circle cx="24" cy="24" r="22" fill="url(#cp-login-logo-grad)"/>
                    <path fill="#fff" d="M14 17h20a2 2 0 012 2v2H12v-2a2 2 0 012-2zm0 6h22v10a2 2 0 01-2 2H14a2 2 0 01-2-2V23zm14 2a1.5 1.5 0 100 3h4a1.5 1.5 0 100-3h-4z"/>
                    <defs>
                        <linearGradient id="cp-login-logo-grad" x1="10" y1="8" x2="38" y2="42" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#29b669"/>
                            <stop offset="1" stop-color="#4FD1C5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h1 class="cp-login-brand">תזרים</h1>
            <p class="cp-login-tagline">התחברות למערכת ניהול תקציב הבית</p>
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

        <form action="login.php" method="POST" class="auth-form cp-login-form">
            <div class="form-group">
                <label for="email">כתובת אימייל</label>
                <div class="input-with-icon">
                    <span class="cp-login-input-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($email) ? $email : ''; ?>" placeholder="name@example.com" required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label for="password">סיסמה</label>
                <div class="input-with-icon">
                    <span class="cp-login-input-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" name="password" id="password" class="form-control" placeholder="הזינו סיסמה" required autocomplete="current-password">
                </div>
            </div>

            <div class="checkbox-group">
                <div class="checkbox-group-start">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <label for="remember_me">זכור אותי</label>
                </div>
                <a href="<?php echo BASE_URL; ?>pages/forgot_password.php" class="forgot-link">שכחת סיסמה?</a>
            </div>

            <button type="submit" name="login_btn" class="submit-btn">התחברות</button>
        </form>

        <div class="divider"><span>או המשך עם</span></div>

        <div class="social-login">
            <button type="button" class="social-btn google-btn">
                <svg class="google-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google
            </button>
        </div>

        <p class="signup-link">
            אין לך חשבון? <a href="<?php echo BASE_URL; ?>pages/register.php">הרשמה כאן</a>
        </p>
    </div>

</body>
</html>

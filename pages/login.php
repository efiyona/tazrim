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
        $_SESSION['last_name'] = $user['last_name'];
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
    <style>
        :root {
            --bg-1: #f4fff8;
            --bg-2: #e8f9ee;
            --card-bg: rgba(255, 255, 255, 0.92);
            --text-main: #113126;
            --text-muted: #587469;
            --accent: #21a95d;
            --accent-hover: #1a8d4d;
            --border: #d7e8de;
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

        .auth-header span {
            color: var(--text-muted);
            font-size: 0.95rem;
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

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group label {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .input-shell {
            position: relative;
        }

        .input-shell svg {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            color: #679080;
            pointer-events: none;
        }

        .input-shell input {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            padding-inline-start: 48px;
            font-size: 16px;
            color: var(--text-main);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .input-shell input:focus {
            outline: none;
            border-color: var(--accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(33, 169, 93, 0.18);
        }

        .meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .remember-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.94rem;
        }

        .remember-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-wrap label {
            cursor: pointer;
        }

        .link-inline,
        .signup-link a {
            color: var(--accent);
            font-weight: 700;
            font-size: 0.94rem;
            text-decoration: none;
        }

        .link-inline:hover {
            text-decoration: underline;
        }

        .submit-btn {
            margin-top: 10px;
            width: 100%;
            padding: 16px;
            border: 0;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            font-size: 16px;
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

        @media (prefers-reduced-motion: reduce) {
            .submit-btn::after,
            .submit-btn:hover::after,
            .submit-btn:focus-visible::after {
                transition: none;
                transform: none;
            }
        }

        .signup-link {
            margin: 18px 0 0;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.94rem;
        }

        .signup-link a:hover {
            text-decoration: underline;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border: 2px solid var(--border);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.6);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
        }

        .back-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(33, 169, 93, 0.12);
        }
        
        .back-btn i {
            font-size: 0.9em;
            transition: transform 0.2s ease;
        }

        .back-btn:hover i {
            transform: translateX(3px); /* אפקט חמוד של תזוזה לכיוון הטקסט ב-RTL */
        }

    </style>
</head>
<body>
    <main class="auth-layout" role="main">
        <section class="auth-side" aria-label="מיתוג והסבר">
            <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver.png" alt="תזרים" class="side-logo side-logo-desktop">
            <img src="<?php echo BASE_URL; ?>assets/images/logo-header.png" alt="תזרים" class="side-logo side-logo-mobile">
        </section>

        <section class="auth-panel">
            <header class="auth-header">
                <h2>ברוכים הבאים</h2>
            </header>

            <?php if (count($errors) > 0): ?>
                <div class="alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">כתובת אימייל</label>
                    <div class="input-shell">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($email) ? $email : ''; ?>" placeholder="name@example.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">סיסמה</label>
                    <div class="input-shell">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" id="password" class="form-control" placeholder="הזינו סיסמה" required autocomplete="current-password">
                    </div>
                </div>

                <div class="meta-row">
                    <div class="remember-wrap">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        <label for="remember_me">זכור אותי</label>
                    </div>
                    <a href="<?php echo BASE_URL; ?>pages/forgot_password.php" class="link-inline">שכחת סיסמה?</a>
                </div>

                <button type="submit" name="login_btn" class="submit-btn">התחברות</button>
            </form>

            <p class="signup-link">
                אין לך חשבון? <a href="<?php echo BASE_URL; ?>pages/register.php">הרשמה כאן</a>
            </p>
        </section>
    </main>

    <div style="text-align: center; margin-top: 24px;">
        <a href="<?php echo BASE_URL; ?>landing/index.php" class="back-btn">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            חזרה לדף הבית
        </a>
    </div>

</body>
</html>

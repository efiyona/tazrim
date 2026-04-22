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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות | תזרים</title>
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
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.05);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .auth-header h1 {
            font-size: 2.2rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .auth-header p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }

        /* Alerts */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error ul {
            padding-inline-start: 1.2rem;
            margin: 0;
        }

        /* Form Inputs */
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
            padding: 0.9rem 2.8rem 0.9rem 2.8rem;
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

        /* Toggle Password Eye */
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

        /* Options Row */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 500;
        }

        .remember-me input {
            accent-color: var(--primary);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .forgot-pass {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .forgot-pass:hover {
            color: var(--primary-dark);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 999px; /* כפתור מעוגל לחלוטין לפי הבקשה */
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(33, 169, 93, 0.4);
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
            border-radius: 999px; /* מעוגל גם פה שיתאים */
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
            /* החלפנו לרקע ירוק חי ויפה שמתאים לתזרים במקום השחור-משעמם */
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

        .orb-1 {
            width: 400px;
            height: 400px;
            top: -100px;
            left: -100px;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            bottom: -50px;
            right: 10%;
            animation-delay: -4s;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
        }

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

    </style>
</head>
<body>

    <main class="auth-wrapper">
        
        <section class="auth-form-side">
            <div class="auth-container">
                <header class="auth-header">
                    <h1>ברוכים הבאים</h1>
                    <p>התחברו כדי להמשיך לנהל את התזרים</p>
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

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="email">כתובת אימייל</label>
                        <div class="input-icon-wrapper">
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo isset($email) ? $email : ''; ?>" placeholder="name@example.com" required autocomplete="email">
                            <i class="fa-regular fa-envelope icon-main"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">סיסמה</label>
                        <div class="input-icon-wrapper">
                            <input type="password" name="password" id="password" class="form-control" placeholder="הזינו סיסמה" required autocomplete="current-password">
                            <i class="fa-solid fa-lock icon-main"></i>
                            <i class="fa-regular fa-eye toggle-password" id="togglePassword" title="הצג/הסתר סיסמה"></i>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            זכור אותי
                        </label>
                        <a href="<?php echo BASE_URL; ?>pages/forgot_password.php" class="forgot-pass">שכחת סיסמה?</a>
                    </div>

                    <button type="submit" name="login_btn" class="btn-submit">
                        התחברות <i class="fa-solid fa-arrow-left"></i>
                    </button>
                </form>

                <div class="auth-footer">
                    אין לך חשבון? <a href="<?php echo BASE_URL; ?>pages/register.php">הרשמה כאן</a>
                </div>
            </div>

            <a href="<?php echo BASE_URL; ?>landing/index.php" class="back-home">
                <i class="fa-solid fa-house"></i> חזרה לדף הבית
            </a>
        </section>

        <section class="auth-visual-side">
            <div class="glass-orb orb-1"></div>
            <div class="glass-orb orb-2"></div>
            
            <div class="visual-content">
                <img src="<?php echo BASE_URL; ?>assets/images/tazrim-logo-ver-white.png" alt="תזרים" class="visual-logo" onerror="this.style.display='none'">
                <p style="color: rgba(255,255,255,0.85); font-size: 1.2rem; margin-top: 1rem; max-width: 400px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    שליטה מלאה על ההוצאות, ההכנסות והתקציב הביתי שלך במקום אחד.
                </p>
            </div>
        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
                
                this.style.color = type === 'text' ? 'var(--primary)' : '#94a3b8';
            });
        });
    </script>
</body>
</html>
<?php
// pages/login.php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php"); // חיבור הלוגיקה

// === בדיקה: האם המשתמש כבר מחובר או שיש לו עוגייה תקינה? ===
if (isset($_SESSION['id'])) {
    // יש סשן פעיל - טיסה ישירה לדף הבית
    header('location: ' . BASE_URL . 'index.php');
    exit();
} elseif (isset($_COOKIE['remember_token'])) {
    // אין סשן, אבל יש עוגייה - נבדוק אותה
    $token = $_COOKIE['remember_token'];
    $user = selectOne('users', ['remember_token' => $token]);
    
    if ($user) {
        // העוגייה תקינה! מבצעים התחברות אוטומטית וטסים לדף הבית
        $_SESSION['id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['home_id'] = $user['home_id'];
        $_SESSION['role'] = $user['role'];
        
        header('location: ' . BASE_URL . 'index.php');
        exit();
    } else {
        // העוגייה קיימת אבל מזויפת/פגת תוקף במסד - מוחקים אותה
        setcookie('remember_token', '', time() - 3600, "/");
    }
}
// ==========================================================
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
</head>
<body class="bg-gray page-auth">

    <div class="split-screen-container">
        
        <div class="form-side flex-center">
            <div class="form-wrapper">
                <div class="brand-mobile">התזרים</div>
                
                <h1 class="page-title">שלום!</h1>
                <p class="page-subtitle">כניסה לניהול תקציב הבית</p>

               <?php if(count($errors) > 0): ?>
                    <div class="alert-error">
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="auth-form">
                    
                    <div class="input-group">
                        <label for="email">כתובת אימייל</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" id="email" value="<?php echo isset($email) ? $email : ''; ?>" placeholder="name@example.com" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">סיסמה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="הזינו סיסמה" required>
                        </div>
                    </div>

                    <div class="form-actions flex-between">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember_me" checked>
                            <span class="checkmark"></span>
                            זכור אותי
                        </label>
                        <a href="<?php echo BASE_URL; ?>pages/forgot_password.php" class="forgot-password">שכחת סיסמה?</a>
                    </div>

                    <button type="submit" name="login_btn" class="btn-primary">התחברות</button>
                </form>

                <p class="auth-switch">
                    עדיין אין לך חשבון? <a href="<?php echo BASE_URL; ?>pages/register.php">הרשמה</a>
                </p>
            </div>
        </div>

        <div class="brand-side flex-center">
            <div class="brand-content text-center">
                <i class="fa-solid fa-wallet brand-icon"></i>
                <h2 class="brand-title">התזרים</h2>
                <p class="brand-text">
                    שליטה מלאה בהוצאות, בהכנסות ובתקציב המשפחתי, מכל מקום ובכל זמן.
                </p>
            </div>
        </div>

    </div>

</body>
</html>
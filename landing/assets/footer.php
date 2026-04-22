<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="footer-logo" aria-label="התזרים - רענון הדף">
                    <img src="<?php echo BASE_URL . 'assets/images/logo-header.png'; ?>" alt="התזרים">
                </a>
                <p>שליטה מלאה בתזרים המשפחתי, בלי לחבר את חשבון הבנק.</p>
            </div>

            <nav class="footer-col" aria-label="ניווט במערכת">
                <h4>המערכת</h4>
                <ul>
                    <li><a href="<?php echo BASE_URL . 'pages/register.php'; ?>"><i class="fa-solid fa-angle-left"></i> פתיחת חשבון</a></li>
                    <li><a href="<?php echo BASE_URL . 'pages/login.php'; ?>"><i class="fa-solid fa-angle-left"></i> כניסה למערכת</a></li>
                    <li><a href="#steps"><i class="fa-solid fa-angle-left"></i> איך זה עובד</a></li>
                </ul>
            </nav>

            <nav class="footer-col" aria-label="מידע משפטי">
                <h4>מידע</h4>
                <ul>
                    <li><a href="<?php echo BASE_URL . 'landing/terms.php'; ?>"><i class="fa-solid fa-angle-left"></i> מדיניות פרטיות</a></li>
                    <li><a href="mailto:support@hatazrim.com"><i class="fa-solid fa-angle-left"></i> יצירת קשר</a></li>
                </ul>
            </nav>

            <div class="footer-trust">
                <div class="footer-trust-item"><i class="fa-solid fa-lock"></i> מאובטח ומוצפן</div>
                <div class="footer-trust-item"><i class="fa-solid fa-shield-halved"></i> פרטיות מלאה</div>
                <div class="footer-trust-item"><i class="fa-solid fa-hand-holding-heart"></i>מותאם למשפחות</div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> התזרים. כל הזכויות שמורות.</p>
            <span class="footer-tagline">נבנה באהבה <i class="fa-solid fa-heart"></i> למשפחות צעירות</span>
        </div>
    </div>
</footer>

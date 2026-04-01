<?php
require('../path.php');
include(ROOT_PATH . "/app/controllers/users.php");

// תפיסת קוד ההצטרפות מהקישור (אם הגיעו דרך הוואטסאפ)
$prefilled_code = isset($_GET['join_code']) ? htmlspecialchars($_GET['join_code']) : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
</head>
<body class="bg-gray">

    <div class="split-screen-container">
        <div class="form-side flex-center">
            <div class="form-wrapper">
                <div class="brand-mobile">התזרים</div>
                <h1 class="page-title">הצטרפות למערכת</h1>
                <p class="page-subtitle">יצירת חשבון חדש בניהול תקציב הבית</p>

                <?php if(count($errors) > 0): ?>
                    <div class="alert-error">
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" id="registerForm">
                    <div class="input-group">
                        <label>שם פרטי</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="first_name" value="<?php echo $first_name; ?>" required placeholder="השם שלך">                        
                        </div>
                    </div>
                    <div class="input-group">
                        <label>שם משפחה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-signature"></i>
                            <input type="text" name="last_name" value="<?php echo $last_name; ?>" required placeholder="שם המשפחה">                        
                        </div>
                    </div>
                    <div class="input-group">
                        <label>כינוי (Nickname)</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-at"></i>
                            <input type="text" name="nickname" value="<?php echo $nickname; ?>" placeholder="איך לקרוא לך במערכת?">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>אימייל</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" value="<?php echo $email; ?>" required placeholder="mail@example.com">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>טלפון</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="phone" value="<?php echo $phone; ?>" required placeholder="05XXXXXXXX">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>סיסמה</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="reg_pass" required placeholder="לפחות 4 תווים">
                        </div>
                    </div>

                    <label style="display: block; margin-bottom: 10px; font-weight: 700; font-size: 0.9rem;">בחירת מסלול בית</label>
                    <div class="radio-group">
                        <label class="radio-card active" id="btnCreate">
                            <input type="radio" name="home_action" value="create" checked style="display:none;">
                            <i class="fa-solid fa-plus-circle"></i>
                            <div>יצירת בית</div>
                        </label>
                        <label class="radio-card" id="btnJoin">
                            <input type="radio" name="home_action" value="join" style="display:none;">
                            <i class="fa-solid fa-link"></i>
                            <div>הצטרפות לבית</div>
                        </label>
                    </div>

                    <div id="fieldsCreate">
                        <div class="input-group">
                            <label>שם הבית (כינוי לבית)</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-house"></i>
                                <input type="text" name="home_name" placeholder="למשל: הבית של משפחת ישראלי">
                            </div>
                        </div>
                    </div>

                    <div id="fieldsJoin" class="hidden">
                        <div class="input-group">
                            <label>קוד בית</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-key"></i>
                                <input type="text" name="home_code" value="<?php echo $prefilled_code; ?>" placeholder="הזינו את קוד ההצטרפות">
                            </div>
                        </div>
                    </div>

                    <div class="input-group" style="margin-top: 25px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; margin: 0; color: #334155;">
                            <input type="checkbox" name="accept_tos" id="acceptTosCb" value="1" style="width: 20px; height: 20px; accent-color: var(--main); cursor: pointer;" onchange="toggleRegBtn()">
                            <span>קראתי ואני מסכים ל<a href="#" onclick="openTosModal(event)" style="color: var(--main); text-decoration: underline;">תנאי השימוש ומדיניות הפרטיות</a></span>
                        </label>
                    </div>

                    <button type="submit" name="register_btn" id="regBtn" class="btn-primary" disabled>השלמת הרשמה</button>

                </form>

                <p class="auth-switch">כבר יש לך חשבון? <a href="login.php">התחבר כאן</a></p>
            </div>
        </div>

        <div class="brand-side flex-center">
            <div class="brand-content text-center">
                <i class="fa-solid fa-wallet brand-icon"></i>
                <h2 class="brand-title">התזרים</h2>
                <p class="brand-text">מצטרפים לניהול תקציב חכם ופשוט.</p>
            </div>
        </div>
    </div>

    <div id="tosModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;">
        <div style="background: white; width: 100%; max-width: 600px; border-radius: 16px; padding: 25px; position: relative; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <button type="button" onclick="closeTosModal()" style="position: absolute; top: 15px; left: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            <h2 style="margin-top: 0; margin-bottom: 15px; font-weight: 800; color: var(--main-dark); text-align: center;">תקנון המערכת</h2>
            
            <div>
                <?php include(ROOT_PATH . '/assets/includes/tos_content.php'); ?>
            </div>
            
            <button type="button" class="btn-primary" onclick="closeTosModal()" style="margin-top: auto;">קראתי את התקנון</button>
        </div>
    </div>

    <script>
        const rCreate = document.querySelector('input[value="create"]');
        const rJoin = document.querySelector('input[value="join"]');
        const fCreate = document.getElementById('fieldsCreate');
        const fJoin = document.getElementById('fieldsJoin');
        const bCreate = document.getElementById('btnCreate');
        const bJoin = document.getElementById('btnJoin');

        rCreate.onchange = () => {
            fCreate.classList.remove('hidden'); fJoin.classList.add('hidden');
            bCreate.classList.add('active'); bJoin.classList.remove('active');
        };
        rJoin.onchange = () => {
            fJoin.classList.remove('hidden'); fCreate.classList.add('hidden');
            bJoin.classList.add('active'); bCreate.classList.remove('active');
        };

        // --- קוד חדש: בדיקה אם הגענו מקישור של וואטסאפ ---
        const prefilledCode = "<?php echo $prefilled_code; ?>";
        if (prefilledCode !== "") {
            // אם יש קוד בקישור, נבחר אוטומטית באופציית "הצטרפות לבית" ונציג אותה
            rJoin.checked = true;
            fJoin.classList.remove('hidden'); 
            fCreate.classList.add('hidden');
            bJoin.classList.add('active'); 
            bCreate.classList.remove('active');
        }

        document.getElementById('registerForm').onsubmit = (e) => {
            if(document.getElementById('reg_pass').value.length < 4) {
                e.preventDefault(); alert('הסיסמה חייבת להיות לפחות 4 תווים');
            }
        };

        // --- לוגיקת תקנון תנאי שימוש (TOS) ---
        function toggleRegBtn() {
            document.getElementById('regBtn').disabled = !document.getElementById('acceptTosCb').checked;
        }

        function openTosModal(e) {
            e.preventDefault(); // מונע קפיצה של הדף למעלה בגלל הלינק
            document.getElementById('tosModal').style.display = 'flex';
        }

        function closeTosModal() {
            document.getElementById('tosModal').style.display = 'none';
        }
        
    </script>
</body>
</html>
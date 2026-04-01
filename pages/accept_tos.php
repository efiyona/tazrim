<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');

// אנחנו לא מכניסים פה את auth_check.php כדי למנוע לולאה אינסופית,
// אבל אנחנו כן חייבים לוודא שהמשתמש מחובר!
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . 'pages/login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>אישור תקנון | התזרים</title>
    <style>
        * { box-sizing: border-box; }
        .welcome-body { background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Assistant', sans-serif; margin: 0; padding: 20px 0; }
        
        .welcome-card { background: white; width: 92%; max-width: 600px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        
        .step { display: none; }
        .step.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .stepper-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 30px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #ddd; }
        .dot.active { background: var(--main); width: 25px; border-radius: 10px; transition: 0.3s; }
        
        .welcome-icon { font-size: 4rem; color: var(--main); margin-bottom: 20px; }
        
        .btn-welcome { background: var(--main); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-welcome:hover { background: var(--main-dark); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(35,114,39,0.3); }
        .btn-welcome:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        
        /* אזור התקנון הנגלל */
        .tos-scroll-box {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            max-height: 350px;
            overflow-y: auto;
            text-align: right;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text);
            margin-bottom: 20px;
        }
        
        .tos-scroll-box h4 { color: var(--main); margin-top: 20px; margin-bottom: 5px; font-weight: 800; }
        .tos-scroll-box h4:first-child { margin-top: 0; }
        
        /* צ'קבוקס אישור */
        .accept-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
            background: #f0fdf4;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #c2e0c6;
            cursor: pointer;
        }

        .accept-checkbox-wrapper input {
            width: 20px;
            height: 20px;
            accent-color: var(--main);
            cursor: pointer;
        }

        .accept-checkbox-wrapper label {
            font-weight: 700;
            color: var(--main-dark);
            cursor: pointer;
            user-select: none;
        }

        @media (max-width: 600px) {
            .welcome-card { padding: 30px 20px; }
        }
    </style>
</head>
<body class="welcome-body">

    <div class="welcome-card">
        <div class="stepper-dots">
            <div class="dot active" id="dot-1"></div>
            <div class="dot" id="dot-2"></div>
        </div>

        <form id="tos-form">
            <div class="step active" id="step-1">
                <div class="welcome-icon"><i class="fa-solid fa-file-signature"></i></div>
                <h1 style="font-weight: 800; margin-bottom: 10px;">עדכנו את תנאי השימוש!</h1>
                <p style="color: #666; line-height: 1.6; margin-bottom: 10px;">כדי להמשיך להשתמש במערכת בבטחה, אנא קראו ואשרו את התקנון המעודכן שלנו.</p>
                <p style="color: var(--main); font-weight: 600; background: #f0fdf4; padding: 10px; border-radius: 8px; display: inline-block;">
                    עודכן לאחרונה: <?php echo TOS_LAST_UPDATED; ?>
                </p>
                <button type="button" class="btn-welcome" onclick="nextStep(2)">מעבר לתקנון <i class="fa-solid fa-arrow-left"></i></button>
            </div>

            <div class="step" id="step-2">
                <h2 style="font-weight: 800; margin-bottom: 15px;">תקנון ומדיניות פרטיות</h2>
                
                <?php include(ROOT_PATH . '/assets/includes/tos_content.php'); ?>

                <label class="accept-checkbox-wrapper">
                    <input type="checkbox" id="tos-checkbox" onchange="toggleSubmitBtn()">
                    <span>קראתי ואני מסכים לתנאי השימוש ומדיניות הפרטיות</span>
                </label>

                <div id="tos-msg" style="margin-top: 15px; display: none; padding: 10px; border-radius: 8px; font-weight: 700;"></div>

                <button type="submit" id="finish-btn" class="btn-welcome" disabled>
                    <i class="fa-solid fa-check"></i> מאושר - המשך למערכת
                </button>
            </div>
        </form>
    </div>

    <script>
        function nextStep(stepNum) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            
            document.querySelectorAll('.dot').forEach(d => d.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        // מדליק ומכבה את כפתור השליחה לפי ה-Checkbox
        function toggleSubmitBtn() {
            const isChecked = document.getElementById('tos-checkbox').checked;
            document.getElementById('finish-btn').disabled = !isChecked;
        }

        // שליחת האישור לשרת
        document.getElementById('tos-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('finish-btn');
            const msgBox = document.getElementById('tos-msg');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> מתעד אישור...';
            msgBox.style.display = 'none';

            fetch('<?php echo BASE_URL; ?>app/ajax/process_tos.php', {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    // האישור נשמר במסד והסשן עודכן, אפשר לחזור לעמוד הראשי
                    window.location.href = '<?php echo BASE_URL; ?>index.php';
                } else {
                    msgBox.style.display = 'block';
                    msgBox.style.background = '#fee2e2';
                    msgBox.style.color = '#dc2626';
                    msgBox.innerText = data.message;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> נסו שוב';
                }
            })
            .catch(error => {
                msgBox.style.display = 'block';
                msgBox.style.background = '#fee2e2';
                msgBox.style.color = '#dc2626';
                msgBox.innerText = 'שגיאת תקשורת עם השרת.';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> נסו שוב';
            });
        });
    </script>
</body>
</html>
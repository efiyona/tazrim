<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');

// אבל אנחנו כן חייבים לוודא שהמשתמש מחובר!
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . 'pages/login.php');
    exit();
}

$user_id_for_tos = $_SESSION['id'];
$current_tos_version = tazrim_tos_version();
$tos_query = "SELECT tos_version, accepted_at FROM tos_agreements WHERE user_id = $user_id_for_tos ORDER BY accepted_at DESC LIMIT 1";
$tos_result = mysqli_query($conn, $tos_query);
$tos_data = mysqli_fetch_assoc($tos_result);
$accepted_version = (string) ($tos_data['tos_version'] ?? '');
$accepted_at = (string) ($tos_data['accepted_at'] ?? '');
$is_view_only = $accepted_version !== '' && $accepted_version === $current_tos_version;
$display_version = $is_view_only ? $accepted_version : $current_tos_version;
$display_last_updated = tazrim_tos_last_updated_by_version($display_version);
$display_content_html = tazrim_tos_content_html_by_version($display_version);
$accepted_at_label = '';

if ($is_view_only) {
    $_SESSION['tos_version'] = $current_tos_version;
    if ($accepted_at !== '') {
        $accepted_ts = strtotime($accepted_at);
        if ($accepted_ts !== false) {
            $accepted_at_label = date('d/m/Y H:i', $accepted_ts);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title><?php echo $is_view_only ? 'צפייה בתקנון' : 'אישור תקנון'; ?> | התזרים</title>
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

        .tos-meta-row {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .tos-chip {
            background: #f0fdf4;
            color: var(--main-dark);
            border: 1px solid #c2e0c6;
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 700;
            font-size: 0.92rem;
        }

        .btn-secondary-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 12px;
            padding: 12px 20px;
            border-radius: 12px;
            background: #f8fafc;
            color: var(--text);
            border: 1px solid #dbe3ea;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-secondary-link:hover {
            background: #eef4f8;
        }
        
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
                <h1 style="font-weight: 800; margin-bottom: 10px;">
                    <?php echo $is_view_only ? 'התקנון שאישרתם' : 'עדכנו את תנאי השימוש!'; ?>
                </h1>
                <p style="color: #666; line-height: 1.6; margin-bottom: 10px;">
                    <?php echo $is_view_only
                        ? 'כאן אפשר לצפות בכל עת בנוסח התקנון האחרון שאישרתם.'
                        : 'כדי להמשיך להשתמש במערכת בבטחה, אנא קראו ואשרו את התקנון המעודכן שלנו.'; ?>
                </p>
                <p style="color: var(--main); font-weight: 600; background: #f0fdf4; padding: 10px; border-radius: 8px; display: inline-block;">
                    עודכן לאחרונה: <?php echo htmlspecialchars($display_last_updated, ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <button type="button" class="btn-welcome" onclick="nextStep(2)">
                    <?php echo $is_view_only ? 'צפייה בתקנון' : 'מעבר לתקנון'; ?> <i class="fa-solid fa-arrow-left"></i>
                </button>
            </div>

            <div class="step" id="step-2">
                <h2 style="font-weight: 800; margin-bottom: 15px;">תקנון ומדיניות פרטיות</h2>

                <div class="tos-meta-row">
                    <span class="tos-chip">גרסה: <?php echo htmlspecialchars($display_version, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($accepted_at_label !== ''): ?>
                        <span class="tos-chip">אושר בתאריך: <?php echo htmlspecialchars($accepted_at_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php echo $display_content_html; ?>

                <?php if (!$is_view_only): ?>
                    <label class="accept-checkbox-wrapper">
                        <input type="checkbox" id="tos-checkbox" onchange="toggleSubmitBtn()">
                        <span>קראתי ואני מסכים לתנאי השימוש ומדיניות הפרטיות</span>
                    </label>

                    <div id="tos-msg" style="margin-top: 15px; display: none; padding: 10px; border-radius: 8px; font-weight: 700;"></div>

                    <button type="submit" id="finish-btn" class="btn-welcome" disabled>
                        <i class="fa-solid fa-check"></i> מאושר - המשך למערכת
                    </button>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>pages/settings/manage_home.php" class="btn-secondary-link">
                        <i class="fa-solid fa-arrow-right"></i> חזרה להגדרות הבית
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        const isViewOnly = <?php echo $is_view_only ? 'true' : 'false'; ?>;

        function nextStep(stepNum) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-' + stepNum).classList.add('active');
            
            document.querySelectorAll('.dot').forEach(d => d.classList.remove('active'));
            document.getElementById('dot-' + stepNum).classList.add('active');
        }

        // מדליק ומכבה את כפתור השליחה לפי ה-Checkbox
        function toggleSubmitBtn() {
            if (isViewOnly) return;
            const isChecked = document.getElementById('tos-checkbox').checked;
            document.getElementById('finish-btn').disabled = !isChecked;
        }

        // שליחת האישור לשרת
        document.getElementById('tos-form').addEventListener('submit', function(e) {
            if (isViewOnly) {
                return;
            }
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
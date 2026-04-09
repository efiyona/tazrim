<?php
require('path.php');
include(ROOT_PATH . '/app/database/db.php');
include_once(ROOT_PATH . '/app/functions/push_functions.php');

// --- טיפול בבקשת ה-AJAX לשליחת ההודעות ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'broadcast') {
    header('Content-Type: application/json');
    
    $admin_pass = $_POST['admin_pass'] ?? '';
    $title = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $body = mysqli_real_escape_string($conn, trim($_POST['body'] ?? ''));
    $link = mysqli_real_escape_string($conn, trim($_POST['link'] ?? '/'));
    
    if (empty($admin_pass) || empty($title) || empty($body)) {
        echo json_encode(['status' => 'error', 'message' => 'אנא מלאו את כל שדות החובה.']);
        exit;
    }
    
    // אימות מול המשתמש efiyona10@gmail.com
    $auth_query = "SELECT password FROM users WHERE email = 'efiyona10@gmail.com' LIMIT 1";
    $auth_result = mysqli_query($conn, $auth_query);
    
    if (mysqli_num_rows($auth_result) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש מנהל לא נמצא במערכת.']);
        exit;
    }
    
    $admin_data = mysqli_fetch_assoc($auth_result);
    
    // בדיקת סיסמה (תומך גם בסיסמה מוצפנת - מה שנהוג - וגם בטקסט פשוט כגיבוי)
    $is_valid = false;
    if (password_verify($admin_pass, $admin_data['password'])) {
        $is_valid = true;
    } elseif ($admin_pass === $admin_data['password']) {
        $is_valid = true;
    }
    
    if (!$is_valid) {
        echo json_encode(['status' => 'error', 'message' => 'קוד אבטחה שגוי.']);
        exit;
    }
    
    // משיכת כל בתי האב במערכת
    $homes_query = "SELECT id FROM homes";
    $homes_result = mysqli_query($conn, $homes_query);
    
    $sent_count = 0;
    while ($home = mysqli_fetch_assoc($homes_result)) {
        // שימוש בפונקציה הקיימת שלא שונתה - שליחה לכלל בני הבית
        sendPushToEntireHome($home['id'], $title, $body, $link, 'system');
        $sent_count++;
    }
    
    echo json_encode(['status' => 'success', 'message' => "ההודעה נשלחה בהצלחה ל-$sent_count בתים!"]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <?php include(ROOT_PATH . '/assets/includes/setup_meta_data.php'); ?>
    <title>ניהול מערכת - שידור הודעות | התזרים</title>
    <style>
        * { box-sizing: border-box; }
        .welcome-body { background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Assistant', sans-serif; margin: 0; padding: 20px; }
        
        .welcome-card { background: white; width: 100%; max-width: 600px; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        
        .welcome-icon { font-size: 3.5rem; color: var(--main); margin-bottom: 15px; }
        
        .input-group { text-align: right; margin-bottom: 20px; }
        .input-group label { font-weight: 700; display: block; margin-bottom: 8px; color: var(--text); }
        .input-with-icon { position: relative; display: flex; align-items: center; }
        .input-with-icon i { position: absolute; right: 15px; color: #888; }
        .input-with-icon input, .input-with-icon textarea { 
            width: 100%; padding: 12px 40px 12px 15px; border: 2px solid #eee; border-radius: 10px; font-family: inherit; font-size: 1rem; transition: 0.2s; outline: none; background: #fafafa;
        }
        .input-with-icon textarea { min-height: 100px; resize: vertical; }
        .input-with-icon input:focus, .input-with-icon textarea:focus { border-color: var(--main); background: white; }
        
        .btn-welcome { background: var(--main); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-size: 1.1rem; font-weight: 700; cursor: pointer; margin-top: 15px; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-welcome:hover { background: var(--main-dark); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(35,114,39,0.3); }
        .btn-welcome:disabled { background: #ccc; cursor: not-allowed; transform: none; box-shadow: none; }
        
        #msg-box { display: none; margin-top: 20px; padding: 15px; border-radius: 10px; font-weight: 700; }
        .success-msg { background: #dcfce7; color: #15803d; }
        .error-msg { background: #fee2e2; color: #dc2626; }
        
        @media (max-width: 600px) {
            .welcome-card { padding: 30px 20px; }
        }
    </style>
</head>
<body class="welcome-body">

    <div class="welcome-card">
        <div class="welcome-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <h1 style="font-weight: 800; margin-bottom: 10px; font-size: 2rem;">הודעת מערכת גלובלית</h1>
        <p style="color: #666; line-height: 1.6; margin-bottom: 30px;">שליחת התראת Push (פוש) לכלל המשתמשים במערכת (לפי בתים).</p>
        
        <form id="broadcast-form">
            <input type="hidden" name="action" value="broadcast">
            
            <div class="input-group">
                <label>כותרת ההודעה</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-heading"></i>
                    <input type="text" name="title" required placeholder="למשל: פיצ'ר חדש במערכת! 🚀">
                </div>
            </div>
            
            <div class="input-group">
                <label>תוכן ההודעה</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-align-right"></i>
                    <textarea name="body" required placeholder="למשל: שמחים לעדכן על השקת מודול רשימת הקניות..."></textarea>
                </div>
            </div>
            
            <div class="input-group">
                <label>קישור בלחיצה (אופציונלי)</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-link"></i>
                    <input type="text" name="link" value="/" placeholder="למשל: /shopping.php">
                </div>
            </div>
            
            <div class="input-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #eee;">
                <label style="color: var(--error);">קוד אבטחה (סיסמת מנהל)</label>
                <div class="input-with-icon">
                    <i class="fa-solid fa-lock" style="color: var(--error);"></i>
                    <input type="password" name="admin_pass" required placeholder="הזן את הסיסמה שלך לאישור" style="border-color: #fca5a5;">
                </div>
            </div>
            
            <button type="submit" id="submit-btn" class="btn-welcome">
                <i class="fa-solid fa-paper-plane"></i> שידור הודעה לכל המשתמשים
            </button>
            
            <div id="msg-box"></div>
        </form>
    </div>

    <script>
        document.getElementById('broadcast-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const btn = document.getElementById('submit-btn');
            const msgBox = document.getElementById('msg-box');

            tazrimConfirm({
                title: 'שידור פוש לכל המשתמשים',
                message: 'האם אתה בטוח שברצונך לשלוח הודעת פוש לכל המשתמשים? לא ניתן לבטל פעולה זו.',
                confirmText: 'שלח',
                cancelText: 'ביטול',
                danger: true
            }).then(function(confirmed) {
                if (!confirmed) return;

                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> שולח הודעות...';
                msgBox.style.display = 'none';

                const formData = new FormData(form);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    msgBox.style.display = 'block';
                    msgBox.className = '';

                    if(data.status === 'success') {
                        msgBox.classList.add('success-msg');
                        msgBox.innerText = data.message;
                        form.reset();
                    } else {
                        msgBox.classList.add('error-msg');
                        msgBox.innerText = data.message;
                    }
                })
                .catch(err => {
                    msgBox.style.display = 'block';
                    msgBox.className = 'error-msg';
                    msgBox.innerText = 'שגיאת תקשורת עם השרת.';
                    console.error(err);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> שידור הודעה לכל המשתמשים';
                });
            });
        });
    </script>
</body>
</html>
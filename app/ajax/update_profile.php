<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

// בדיקה שהמשתמש אכן מחובר
if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר או שהסשן פג.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id'];
    
    // קליטת הנתונים מהטופס וניקוי רווחים מיותרים
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $nickname   = trim($_POST['nickname'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');

    // ולידציה בסיסית בצד שרת
    if (empty($first_name) || empty($last_name) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'אנא מלא את כל שדות החובה (שם פרטי, שם משפחה וטלפון).']);
        exit();
    }

    // מערך הנתונים לעדכון (תואם לפונקציית update שיש לך ב-db.php)
    $data = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'nickname'   => $nickname,
        'phone'      => $phone
    ];

    // עדכון טבלת המשתמשים
    $updateResult = update('users', $user_id, $data);

    if ($updateResult !== false) {
        // חשוב: עדכון הסשן הנוכחי כדי שהשם/כינוי ישתנו מיד בבר העליון בלי צורך בהתחברות מחדש
        $_SESSION['first_name'] = $first_name;
        $_SESSION['nickname'] = $nickname;
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה בעדכון הנתונים במסד.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}
?>
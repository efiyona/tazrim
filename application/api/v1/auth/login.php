<?php
// התרת גישה מרחוק מהאפליקציה בטלפון (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// יציאה מהירה אם מדובר בבקשת Preflight של הדפדפן/אפליקציה
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// חיבור למסד הנתונים והפונקציות של המערכת (4 רמות למעלה לשורש האתר)
require_once('../../../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// 1. קבלת הנתונים שנשלחו מהאפליקציה
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    echo json_encode(['status' => 'error', 'message' => 'אנא הזן אימייל וסיסמה']);
    exit();
}

$email = trim($data->email);
$password = $data->password;

// 2. שליפת המשתמש ממסד הנתונים
$user = selectOne('users', ['email' => $email]);

// 3. בדיקת סיסמה 
if ($user && password_verify($password, $user['password'])) {
    
    // 4. ייצור טוקן אבטחה ייחודי לאפליקציה (64 תווים אקראיים)
    $api_token = bin2hex(random_bytes(32));
    
    // 5. עדכון הטוקן החדש בטבלת המשתמשים
    $update_id = update('users', $user['id'], ['api_token' => $api_token]);
    
    if ($update_id) {
        // 6. הצלחה! החזרת הנתונים לאפליקציה בפורמט JSON
        echo json_encode([
            'status' => 'success',
            'message' => 'התחברת בהצלחה',
            'data' => [
                'user_id' => $user['id'],
                'home_id' => $user['home_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'nickname' => $user['nickname'],
                'api_token' => $api_token
            ]
        ]);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת פנימית (עדכון טוקן)']);
        exit();
    }
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'פרטי התחברות שגויים']);
    exit();
}
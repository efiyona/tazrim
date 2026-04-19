<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

// השרת עונה בפורמט JSON כדי שהאייפון ידע לקרוא את התשובה בקלות
header('Content-Type: application/json; charset=utf-8');

// 1. קבלת הטוקן ובדיקת שיטת הבקשה
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST method is allowed.']);
    exit();
}

$token = $_POST['api_token'] ?? '';

if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'API Token is missing.']);
    exit();
}

// 2. אימות הטוקן מול מסד הנתונים
$token_query = "SELECT user_id, home_id FROM api_tokens WHERE token = '$token' LIMIT 1";
$token_result = mysqli_query($conn, $token_query);

if (mysqli_num_rows($token_result) === 0) {
    // טוקן לא חוקי או שנמחק
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Token.']);
    exit();
}

$auth_data = mysqli_fetch_assoc($token_result);
$home_id = $auth_data['home_id'];

// עדכון תאריך שימוש אחרון (בשביל המעקב שלך בטבלה)
mysqli_query($conn, "UPDATE api_tokens SET last_used = CURRENT_TIMESTAMP() WHERE token = '$token'");

// מקבלים את סוג הפעולה מהאייפון. אם האייפון לא שלח כלום, ברירת המחדל תהיה הוצאה.
$type = $_POST['type'] ?? 'expense';

// אבטחה קטנה: מוודאים שהערך הוא רק 'expense' או 'income' כדי למנוע הזרקות קוד
if (!in_array($type, ['expense', 'income'])) {
    $type = 'expense';
}

// 3. שליפת הקטגוריות הפעילות לפי הסוג שהתקבל
$cat_query = "SELECT id, name, icon FROM categories WHERE home_id = $home_id AND type = '$type' AND is_active = 1 ORDER BY name ASC";
$cat_result = mysqli_query($conn, $cat_query);

// פונקציית עזר לתרגום האייקונים שלך לאימוג'ים עבור האייפון
function getEmoji($fa_icon) {
    $conversion = [
        'fa-cart-shopping' => '🛒',
        'fa-car'           => '🚗',
        'fa-utensils'      => '🍽️',
        'fa-bolt'          => '⚡',
        'fa-house'         => '🏠',
        'fa-shirt'         => '👕',
        'fa-heart-pulse'   => '❤️',
        'fa-tag'           => '🏷️',
        'fa-money-bill-wave' => '💵',
        'fa-plane'         => '✈️',
        'fa-graduation-cap' => '🎓',
        'fa-paw'           => '🐾',
        'fa-gift'          => '🎁',
        'fa-mobile-screen' => '📱',
        'fa-baby'          => '👶',
        'fa-hammer'        => '🔨',
        'fa-couch'         => '🛋️',
        'fa-truck'         => '🚚'
    ];
    return $conversion[$fa_icon] ?? '💰'; // ברירת מחדל אם לא נמצא
}

$categories = [];
while ($row = mysqli_fetch_assoc($cat_result)) {
    // 1. קודם מרכיבים את השם היפה שיוצג למשתמש
    $emoji = getEmoji($row['icon']);
    $display_name = $emoji . " " . $row['name'];
    
    // 2. המפתח הוא השם היפה, הערך הוא *רק* ה-ID כמספר
    $categories[$display_name] = (int)$row['id']; 
}

// שלח את התשובה פעם אחת בלבד וסיים את הריצה
echo json_encode([
    'status' => 'success',
    'categories' => $categories
], JSON_UNESCAPED_UNICODE);
exit();
?>
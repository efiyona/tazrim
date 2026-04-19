<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מחובר.']);
    exit;
}

$user_id = (int) $_SESSION['id'];
$pref = $_POST['pref'] ?? '';
$value = isset($_POST['value']) ? (int) $_POST['value'] : null;

if ($value !== 0 && $value !== 1) {
    echo json_encode(['status' => 'error', 'message' => 'נתונים לא תקינים.']);
    exit;
}

$col = null;
switch ($pref) {
    case 'home_transactions':
        $col = 'notify_home_transactions';
        break;
    case 'budget':
        $col = 'notify_budget';
        break;
    case 'system':
        $col = 'notify_system';
        break;
}
if ($col === null) {
    echo json_encode(['status' => 'error', 'message' => 'נתונים לא תקינים.']);
    exit;
}

// יצירת שורה ראשונית אם המשתמש עדיין ללא העדפות שמורות
$seed = $conn->prepare(
    "INSERT INTO user_notification_preferences (user_id, notify_home_transactions, notify_budget, notify_system)
     VALUES (?, 1, 1, 1)
     ON DUPLICATE KEY UPDATE user_id = user_id"
);
if (!$seed) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
    exit;
}
$seed->bind_param('i', $user_id);
$seed->execute();
$seed->close();

$sql = "UPDATE user_notification_preferences SET `$col` = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
    exit;
}
$stmt->bind_param('ii', $value, $user_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'לא ניתן לשמור.']);
}
$stmt->close();

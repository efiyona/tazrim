<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'המשתמש לא מחובר.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit;
}

$allowed = ['light', 'dark', 'system'];
$theme_preference = $_POST['theme_preference'] ?? '';
if (!in_array($theme_preference, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'ערך תצוגה לא תקין.']);
    exit;
}

$user_id = (int) $_SESSION['id'];
$stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת שרת.']);
    exit;
}

$stmt->bind_param('si', $theme_preference, $user_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'לא ניתן היה לשמור את ההעדפה.']);
    exit;
}

$_SESSION['theme_preference'] = $theme_preference;
echo json_encode([
    'status' => 'success',
    'theme_preference' => $theme_preference
]);
exit;
?>

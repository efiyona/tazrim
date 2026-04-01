<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// קבלת נתוני ה-JSON מהבקשה
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['endpoint'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing endpoint']);
    exit();
}

$endpoint = $data['endpoint'];

// מחיקת המכשיר (ה-endpoint) הספציפי ממסד הנתונים
$stmt = $conn->prepare("DELETE FROM user_subscriptions WHERE endpoint = ?");
$stmt->bind_param("s", $endpoint);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$stmt->close();
?>
<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit();
}

$user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'נדרשת התחברות.']);
    exit();
}

$query = "DELETE FROM api_tokens WHERE user_id = $user_id";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים.']);
}

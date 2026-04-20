<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
    exit();
}

$home_id = $_SESSION['home_id'] ?? null;
if (!$home_id) {
    echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
    exit();
}

tazrim_reset_home_bank_balance_fields($conn, (int) $home_id);
echo json_encode(['status' => 'success']);

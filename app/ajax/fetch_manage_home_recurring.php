<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json; charset=utf-8');

$home_id = isset($_SESSION['home_id']) ? (int) $_SESSION['home_id'] : 0;
if ($home_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

$recurring_query = "SELECT r.*, c.name as cat_name, c.icon as cat_icon 
                    FROM recurring_transactions r 
                    LEFT JOIN categories c ON r.category = c.id 
                    WHERE r.home_id = $home_id AND r.is_active = 1 
                    ORDER BY r.day_of_month ASC";
$recurring_result = mysqli_query($conn, $recurring_query);
$recurring_expenses = [];
$recurring_income = [];
if ($recurring_result) {
    while ($rec = mysqli_fetch_assoc($recurring_result)) {
        if ($rec['type'] === 'expense') {
            $recurring_expenses[] = $rec;
        } else {
            $recurring_income[] = $rec;
        }
    }
}

ob_start();
include ROOT_PATH . '/app/includes/partials/manage_home_recurring_panel.php';
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE);

<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $home_id = $_SESSION['home_id'] ?? null;

    if (!$home_id) {
        echo json_encode(['status' => 'error', 'message' => 'משתמש לא מחובר.']);
        exit();
    }

    $home_name = trim($_POST['home_name'] ?? '');
    $show_bank_balance = !empty($_POST['show_bank_balance']) && (string) $_POST['show_bank_balance'] === '1' ? 1 : 0;
    $bank_target = isset($_POST['bank_balance_display']) ? trim((string) $_POST['bank_balance_display']) : '';

    if (empty($home_name)) {
        echo json_encode(['status' => 'error', 'message' => 'שם הבית לא יכול להיות ריק.']);
        exit();
    }

    $home_name_clean = mysqli_real_escape_string($conn, $home_name);
    $hid = (int) $home_id;

    $update_query = "UPDATE homes SET name = '$home_name_clean', show_bank_balance = $show_bank_balance WHERE id = $hid";

    if (!mysqli_query($conn, $update_query)) {
        echo json_encode(['status' => 'error', 'message' => 'שגיאה במסד הנתונים: ' . mysqli_error($conn)]);
        exit();
    }

    if ($bank_target !== '') {
        // 12,299.77 — הסרת פסיקי אלפים; אחרת (float)"12,299.77" ב-PHP נהיה 12
        $bank_target_norm = preg_replace('/\s+/', '', $bank_target);
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $bank_target_norm)) {
            $bank_target_norm = str_replace(',', '', $bank_target_norm);
        }
        if (!is_numeric($bank_target_norm)) {
            echo json_encode(['status' => 'error', 'message' => 'יתרת בנק חייבת להיות מספר.']);
            exit();
        }
        $today = date('Y-m-d');
        tazrim_apply_user_bank_balance_target($conn, $hid, (float) $bank_target_norm, $today);
    }

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'בקשה לא חוקית.']);
}

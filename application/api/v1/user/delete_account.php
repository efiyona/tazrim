<?php
/**
 * מחיקת חשבון — אותה לוגיקה כמו app/ajax/delete_account.php (אימות בטוקן API)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }

    $user_id = (int) ($user['id'] ?? 0);
    $home_id = (int) ($user['home_id'] ?? 0);
    $user_role = $user['role'] ?? '';

    if ($user_id <= 0 || $home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתוני משתמש לא תקינים.']);
        exit();
    }

    $users_in_home_query = "SELECT id, role, email FROM users WHERE home_id = $home_id";
    $users_in_home_result = mysqli_query($conn, $users_in_home_query);
    $users_count = mysqli_num_rows($users_in_home_result);
    $is_only_user = ($users_count === 1);

    $user_email = '';
    while ($row = mysqli_fetch_assoc($users_in_home_result)) {
        if ((int) $row['id'] === $user_id) {
            $user_email = $row['email'];
        }
    }
    mysqli_data_seek($users_in_home_result, 0);

    mysqli_begin_transaction($conn);

    try {
        mysqli_query($conn, "DELETE FROM api_tokens WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM tos_agreements WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM user_subscriptions WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM notification_reads WHERE user_id = $user_id");
        mysqli_query($conn, "DELETE FROM notifications WHERE user_id = $user_id");

        if (!empty($user_email)) {
            $safe_email = mysqli_real_escape_string($conn, $user_email);
            mysqli_query($conn, "DELETE FROM password_resets WHERE email = '$safe_email'");
        }

        if ($is_only_user) {
            mysqli_query($conn, "DELETE FROM transactions WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM recurring_transactions WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM shopping_items WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM shopping_categories WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM categories WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM notifications WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM ai_api_logs WHERE home_id = $home_id");
            mysqli_query($conn, "DELETE FROM homes WHERE id = $home_id");
        } else {
            $heir_query = "SELECT id, role FROM users WHERE home_id = $home_id AND id != $user_id ORDER BY (role IN ('home_admin','program_admin','admin')) DESC LIMIT 1";
            $heir_res = mysqli_query($conn, $heir_query);

            if ($heir_row = mysqli_fetch_assoc($heir_res)) {
                $heir_id = (int) $heir_row['id'];

                mysqli_query($conn, "UPDATE transactions SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
                mysqli_query($conn, "UPDATE recurring_transactions SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
                mysqli_query($conn, "UPDATE ai_api_logs SET user_id = $heir_id WHERE user_id = $user_id AND home_id = $home_id");
                mysqli_query($conn, "UPDATE notifications SET creator_id = $heir_id WHERE creator_id = $user_id AND home_id = $home_id");
                mysqli_query($conn, "UPDATE homes SET primary_user_id = $heir_id WHERE primary_user_id = $user_id AND id = $home_id");

                if (in_array($user_role, ['home_admin', 'program_admin', 'admin'], true)) {
                    $other_admins_query = "SELECT id FROM users WHERE home_id = $home_id AND id != $user_id AND role IN ('home_admin','program_admin','admin')";
                    $other_admins_res = mysqli_query($conn, $other_admins_query);

                    if (mysqli_num_rows($other_admins_res) == 0) {
                        mysqli_query($conn, "UPDATE users SET role = 'home_admin' WHERE id = $heir_id");
                    }
                }
            }
        }

        mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
        mysqli_commit($conn);

        echo json_encode(['status' => 'success']);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'אירעה שגיאה במסד הנתונים בתהליך המחיקה.']);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

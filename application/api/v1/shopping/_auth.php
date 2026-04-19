<?php
/**
 * שימוש פנימי: אימות טוקן API והחזרת home_id.
 * @return array{user: array, home_id: int, conn: mysqli}
 */
function shopping_api_require_user(mysqli $conn) {
    $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }
    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן לא חוקי.']);
        exit();
    }
    $home_id = (int) ($user['home_id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית.']);
        exit();
    }
    return ['user' => $user, 'home_id' => $home_id];
}

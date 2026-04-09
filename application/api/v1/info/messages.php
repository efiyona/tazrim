<?php
/**
 * הודעות הסבר (טבלת info_messages) — כמו info_label.php באתר.
 * GET ?token=...&keys=month_expenses,month_income,real_balance
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    $defaultKeys = ['month_expenses', 'month_income', 'real_balance'];
    $keysParam = isset($_GET['keys']) ? trim($_GET['keys']) : '';
    $keys = $keysParam !== '' ? preg_split('/\s*,\s*/', $keysParam) : $defaultKeys;
    $keys = array_values(array_filter($keys, function ($k) {
        return is_string($k) && preg_match('/^[a-z0-9_]{1,50}$/i', $k);
    }));
    if (empty($keys)) {
        $keys = $defaultKeys;
    }

    $messages = [];
    foreach ($keys as $k) {
        $row = selectOne('info_messages', ['msg_key' => $k]);
        if ($row) {
            $messages[$k] = [
                'title' => (string) ($row['title'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
            ];
        } else {
            $messages[$k] = [
                'title' => 'מידע חסר',
                'content' => 'לא נמצא הסבר מקושר במערכת.',
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => ['messages' => $messages],
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

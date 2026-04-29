<?php
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

    require_once __DIR__ . '/_auth.php';
    require_once ROOT_PATH . '/app/functions/user_gemini_key.php';
    $auth = shopping_api_require_user($conn);
    $home_id = $auth['home_id'];
    $api_uid = (int) ($auth['user']['id'] ?? 0);

    $gemini_ordered_keys = tazrim_user_gemini_plain_keys_ordered($conn, $api_uid);
    if ($gemini_ordered_keys === []) {
        echo json_encode(['status' => 'error', 'code' => 'gemini_key_missing', 'message' => 'נדרש מפתח Gemini אישי במסך החשבון.']);
        exit();
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['status' => 'error', 'message' => 'גוף בקשה לא תקין.']);
        exit();
    }

    $cat_id = (int) ($body['category_id'] ?? 0);
    $items_data = $body['items'] ?? null;
    if ($cat_id <= 0 || !is_array($items_data) || count($items_data) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים']);
        exit();
    }

    $chk = mysqli_query($conn, "SELECT id FROM shopping_categories WHERE id = $cat_id AND home_id = $home_id LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'חנות לא תקינה']);
        exit();
    }

    $gemini_models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];

    $items_list_for_ai = '';
    foreach ($items_data as $item) {
        if (!is_array($item)) {
            continue;
        }
        $iid = (int) ($item['id'] ?? 0);
        $name = (string) ($item['name'] ?? '');
        if ($iid > 0 && $name !== '') {
            $items_list_for_ai .= "ID: {$iid}, Name: {$name}\n";
        }
    }

    if ($items_list_for_ai === '') {
        echo json_encode(['status' => 'error', 'message' => 'אין פריטים למיון']);
        exit();
    }

    $prompt = "Return ONLY a JSON array of integers representing the sorted IDs of these items by supermarket aisles path:\n$items_list_for_ai";

    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.1],
    ];

    $http_code = 0;
    $response = '';
    $curl_err = '';
    $max_attempts_per_model = 2;
    $retryable = [429, 500, 503];

    foreach ($gemini_models as $model_name) {
        $gr = tazrim_user_gemini_v1beta_generate_content_with_key_rotation(
            $gemini_ordered_keys,
            $model_name,
            $data,
            45,
            false,
            $max_attempts_per_model,
            $retryable
        );
        $http_code = $gr['http'];
        $response = $gr['raw'];
        $curl_err = $gr['curl_err'];

        if (!empty($gr['ok'])) {
            break;
        }
    }

    if ($http_code !== 200) {
        $friendly = 'שגיאת תקשורת עם גוגל';
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
            $status = $decoded['error']['status'] ?? '';
            if (stripos($msg, 'high demand') !== false || $status === 'UNAVAILABLE') {
                $friendly = 'שירות הבינה המלאכותית של גוגל עמוס כרגע. נסו שוב בעוד דקה–שתיים.';
            } elseif ($http_code === 404 || $status === 'NOT_FOUND') {
                $friendly = 'המודל המבוקש לא זמין ב-API. עודכנו מודלים — נסו שוב; אם זה נמשך, בדקו ב-Google AI Studio אילו מודלים פתוחים למפתח שלכם.';
            }
        }
        echo json_encode([
            'status' => 'error',
            'message' => $friendly,
            'debug_raw' => "HTTP Code: $http_code | cURL Error: $curl_err | Response: $response",
        ]);
        exit();
    }

    $responseData = json_decode($response, true);
    $raw_ai_reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'EMPTY_CONTENT';

    $clean_reply = $raw_ai_reply;
    if (preg_match('/\[.*\]/s', $raw_ai_reply, $matches)) {
        $clean_reply = $matches[0];
    }

    $sorted_ids = json_decode($clean_reply, true);

    if (is_array($sorted_ids) && count($sorted_ids) > 0) {
        $current_order = 1;
        foreach ($sorted_ids as $id) {
            $id = (int) $id;
            $update_query = "UPDATE shopping_items SET sort_order = $current_order WHERE id = $id AND home_id = $home_id AND category_id = $cat_id";
            mysqli_query($conn, $update_query);
            $current_order++;
        }
        echo json_encode(['status' => 'success', 'debug_raw' => $raw_ai_reply]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'כשל בפענוח מערך הנתונים',
            'debug_raw' => $raw_ai_reply,
        ]);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'שגיאת מערכת בשרת.']);
}

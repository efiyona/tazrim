<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/user_gemini_key.php';

header('Content-Type: application/json');

if (!isset($_SESSION['home_id']) || !isset($_SESSION['id']) || !isset($_POST['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים']);
    exit();
}

$home_id = $_SESSION['home_id'];
$user_id = (int) $_SESSION['id'];
$gemini_ordered_keys = tazrim_user_gemini_plain_keys_ordered($conn, $user_id);
if ($gemini_ordered_keys === []) {
    echo json_encode(['status' => 'error', 'code' => 'gemini_key_missing', 'message' => 'נדרש מפתח Gemini אישי בהגדרות החשבון.']);
    exit();
}
// מודלים תקפים ל-v1beta generateContent (1.5 ללא סיומת גרסה / pro ללא מזהה מלא — עלולים 404). גיבוי: פלאש → פלאש-לייט → 2.0
$gemini_models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];

$items_data = json_decode($_POST['items'], true);
$items_list_for_ai = "";
foreach ($items_data as $item) {
    $items_list_for_ai .= "ID: {$item['id']}, Name: {$item['name']}\n";
}

$prompt = "Return ONLY a JSON array of integers representing the sorted IDs of these items by supermarket aisles path.
Important: ignore quantities/units when understanding each item name (example: \"2 כוסות חלב\" should be treated as \"חלב\"):
$items_list_for_ai";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.1]
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

// בדיקה אם בכלל קיבלנו תשובה מה-API
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
        'debug_raw' => "HTTP Code: $http_code | cURL Error: $curl_err | Response: $response"
    ]);
    exit();
}

$responseData = json_decode($response, true);
$raw_ai_reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'EMPTY_CONTENT';

// ניסיון חילוץ JSON
$clean_reply = $raw_ai_reply;
if (preg_match('/\[.*\]/s', $raw_ai_reply, $matches)) {
    $clean_reply = $matches[0];
}

$sorted_ids = json_decode($clean_reply, true);

if (is_array($sorted_ids) && count($sorted_ids) > 0) {
    $cat_id = (int)$_POST['category_id'];
    $current_order = 1;
    foreach ($sorted_ids as $id) {
        $id = (int)$id;
        $update_query = "UPDATE shopping_items SET sort_order = $current_order WHERE id = $id AND home_id = $home_id AND category_id = $cat_id";
        mysqli_query($conn, $update_query);
        $current_order++;
    }
    
    echo json_encode(['status' => 'success', 'debug_raw' => $raw_ai_reply]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'כשל בפענוח מערך הנתונים', 
        'debug_raw' => $raw_ai_reply
    ]);
}
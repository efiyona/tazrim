<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once('../../secrets.php'); 

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['home_id']) || !isset($_POST['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'נתונים חסרים']);
    exit();
}

$home_id = $_SESSION['home_id'];
$api_key = GEMINI_API_KEY;
// השתמשתי בשם המודל שעובד לך בקובץ ה-Insights
$model_name = 'gemini-2.5-flash'; 

$items_data = json_decode($_POST['items'], true);
$items_list_for_ai = "";
foreach ($items_data as $item) {
    $items_list_for_ai .= "ID: {$item['id']}, Name: {$item['name']}\n";
}

$prompt = "Return ONLY a JSON array of integers representing the sorted IDs of these items by supermarket aisles path:
$items_list_for_ai";

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.1]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

// בדיקה אם בכלל קיבלנו תשובה מה-API
if ($http_code !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת תקשורת עם גוגל',
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
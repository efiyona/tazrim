<?php
require_once('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/user_gemini_key.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['home_id']) || !isset($_SESSION['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא מורשה']);
    exit();
}

if (!isset($_FILES['recipe_images'])) {
    echo json_encode(['status' => 'error', 'message' => 'לא התקבלו תמונות']);
    exit();
}

function recipe_build_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'is_recipe' => ['type' => 'boolean'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string']
            ],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'is_staple' => ['type' => 'boolean']
                    ],
                    'required' => ['name', 'is_staple']
                ]
            ]
        ],
        'required' => ['is_recipe', 'items', 'warnings']
    ];
}

function recipe_collect_uploaded_images(): array
{
    $files = $_FILES['recipe_images'] ?? null;
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFiles = 8;
    $maxBytesPerFile = 6 * 1024 * 1024;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $out = [];

    $count = min(count($files['name']), $maxFiles);
    for ($i = 0; $i < $count; $i++) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) continue;

        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        $size = (int) ($files['size'][$i] ?? 0);
        if ($tmp === '' || !is_file($tmp) || $size <= 0 || $size > $maxBytesPerFile) continue;

        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if (!in_array($mime, $allowed, true)) continue;

        $bin = @file_get_contents($tmp);
        if (!is_string($bin) || $bin === '') continue;

        $out[] = [
            'mime_type' => $mime,
            'base64' => base64_encode($bin)
        ];
    }

    if ($finfo) finfo_close($finfo);
    return $out;
}

function recipe_call_gemini_with_images(array $images, array $orderedApiKeys): array
{
    $models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    $schema = recipe_build_schema();

    $prompt = "You are given one or more images of recipe ingredients list.\n"
        . "Return JSON only according to schema.\n"
        . "Rules:\n"
        . "1) Extract all ingredients from all images (merge across pages).\n"
        . "2) Keep quantities/units inside item name exactly as shown.\n"
        . "3) Do not include tools or cookware.\n"
        . "4) Mark pantry staples with is_staple=true (e.g salt, water, pepper, basic oil, sugar).\n"
        . "5) If images are not recipe ingredients list, set is_recipe=false.";

    $parts = [['text' => $prompt]];
    foreach ($images as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $img['mime_type'],
                'data' => $img['base64']
            ]
        ];
    }

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => $parts
        ]],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema
        ]
    ];

    $recipeRetryable = [429, 500, 503];

    foreach ($models as $model) {
        $gr = tazrim_user_gemini_v1beta_generate_content_with_key_rotation(
            $orderedApiKeys,
            $model,
            $payload,
            55,
            false,
            3,
            $recipeRetryable
        );
        $raw = $gr['raw'];
        if (!$gr['ok'] || $raw === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            continue;
        }
        $json = json_decode($text, true);
        if (is_array($json)) {
            return ['ok' => true, 'data' => $json];
        }
    }

    return ['ok' => false];
}

function recipe_normalize_items(array $items): array
{
    $seen = [];
    $out = [];
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') continue;
        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = [
            'name' => $name,
            'is_staple' => !empty($row['is_staple'])
        ];
    }
    return $out;
}

$images = recipe_collect_uploaded_images();
if (count($images) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'לא נקלטו תמונות תקינות. העלו JPG/PNG/WEBP.']);
    exit();
}

$recipeUserId = (int) $_SESSION['id'];
$recipeOrderedKeys = tazrim_user_gemini_plain_keys_ordered($conn, $recipeUserId);
if ($recipeOrderedKeys === []) {
    echo json_encode([
        'status' => 'error',
        'code' => 'gemini_key_missing',
        'message' => 'נדרש מפתח Gemini אישי בהגדרות החשבון.',
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$ai = recipe_call_gemini_with_images($images, $recipeOrderedKeys);
if (!$ai['ok']) {
    echo json_encode(['status' => 'error', 'message' => 'שירות הבינה עמוס כרגע, נסו שוב בעוד רגע.']);
    exit();
}

$data = $ai['data'];
$isRecipe = !empty($data['is_recipe']);
$items = recipe_normalize_items(is_array($data['items'] ?? null) ? $data['items'] : []);
$warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

if (!$isRecipe) {
    echo json_encode([
        'status' => 'error',
        'message' => 'התמונות לא זוהו כרשימת מצרכים של מתכון. נסו צילום ברור יותר.',
        'source_mode' => 'images'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (count($items) === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'לא זוהו מצרכים בתמונות. נסו תמונות חדות יותר.',
        'source_mode' => 'images'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode([
    'status' => 'success',
    'items' => $items,
    'warnings' => $warnings,
    'source_mode' => 'images',
    'images_count' => count($images)
], JSON_UNESCAPED_UNICODE);


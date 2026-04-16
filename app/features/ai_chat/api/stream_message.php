<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

header('Content-Type: text/event-stream; charset=utf-8');
header('X-Accel-Buffering: no');

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function ai_chat_sse_event(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

/** הסרת ```json ... ``` אם המודל עטף את ה-JSON */
function ai_chat_strip_json_fences(string $raw): string
{
    $s = trim($raw);
    if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
        return trim($m[1]);
    }

    return $s;
}

/**
 * קריאה סינכרונית ל-Gemini (ללא סטרים) — לשלב ניתוב.
 *
 * @return string|null טקסט מועמד ראשון או null
 */
function ai_chat_gemini_generate_text(string $apiKey, string $model, array $body): ?string
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 28);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $http !== 200) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');

    return $text !== '' ? $text : null;
}

/**
 * מחליט אם לעבור לשלב תשובה מעמיקה (קריאת JSON קטנה).
 *
 * @return array{needs_deep:bool,reason_code:string,user_hint:string}
 */
function ai_chat_route_decision(string $apiKey, string $message, array $scope): array
{
    $default = ['needs_deep' => false, 'reason_code' => 'simple', 'user_hint' => ''];
    $routerInstruction = ai_chat_build_router_system_instruction($scope);
    $baseBody = [
        'system_instruction' => [
            'parts' => [['text' => $routerInstruction]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => "הודעת המשתמש (לסיווג בלבד):\n{$message}"]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.12,
            'maxOutputTokens' => 220,
        ],
    ];

    $routerModels = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    foreach ($routerModels as $routerModel) {
        foreach ([true, false] as $jsonMime) {
            $body = $baseBody;
            if ($jsonMime) {
                $body['generationConfig']['responseMimeType'] = 'application/json';
            }
            $text = ai_chat_gemini_generate_text($apiKey, $routerModel, $body);
            if ($text === null) {
                continue;
            }
            $parsed = json_decode(ai_chat_strip_json_fences($text), true);
            if (!is_array($parsed)) {
                continue;
            }
            $needs = false;
            if (array_key_exists('needs_deep', $parsed)) {
                $v = $parsed['needs_deep'];
                if (is_bool($v)) {
                    $needs = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $needs = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $needs = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }
            $reason = (string) ($parsed['reason_code'] ?? 'simple');
            $hint = trim((string) ($parsed['user_hint'] ?? ''));
            if (function_exists('mb_substr')) {
                $hint = mb_substr($hint, 0, 48);
            } else {
                $hint = substr($hint, 0, 48);
            }
            if ($needs && $hint === '') {
                $hint = 'מנתח את השאלה לעומק';
            }

            return [
                'needs_deep' => $needs,
                'reason_code' => $reason !== '' ? $reason : 'other',
                'user_hint' => $hint,
            ];
        }
    }

    return $default;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    ai_chat_sse_event('error', ['message' => 'invalid_json']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$chatId = (int) ($payload['chat_id'] ?? 0);

$clientScope = $payload['scope'] ?? ['topic' => 'system'];
if (!is_array($clientScope)) {
    $clientScope = ['topic' => 'system'];
}
$t = (string) ($clientScope['topic'] ?? 'system');
$clientScope = ['topic' => $t === 'financial' ? 'financial' : 'system'];

$scope = $clientScope;
$scopeSnapshot = '{"topic":"system"}';

if ($chatId > 0) {
    $prefetchedChat = ai_chat_repo_get($conn, $chatId, $userId);
    if (!$prefetchedChat) {
        ai_chat_sse_event('error', ['message' => 'chat_not_found']);
        exit;
    }
    $stored = json_decode((string) ($prefetchedChat['scope_snapshot'] ?? '{}'), true);
    if (is_array($stored)) {
        $st = (string) ($stored['topic'] ?? 'system');
        $scope = ['topic' => $st === 'financial' ? 'financial' : 'system'];
    } else {
        $scope = ['topic' => 'system'];
    }
    $enc = json_encode($scope, JSON_UNESCAPED_UNICODE);
    $scopeSnapshot = $enc !== false ? $enc : '{"topic":"system"}';
} else {
    $enc = json_encode($scope, JSON_UNESCAPED_UNICODE);
    $scopeSnapshot = $enc !== false ? $enc : '{"topic":"system"}';
    $prefetchedChat = null;
}

$guard = ai_chat_guard_validate_input($message);
if (!$guard['ok']) {
    if ($chatId <= 0) {
        $chatId = ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
    }
    ai_chat_repo_add_message($conn, $chatId, 'user', $message);
    $refusal = ai_chat_guard_refusal_text();
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $refusal, 'policy-local');
    ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => true]);
    ai_chat_sse_event('token', ['text' => $refusal]);
    ai_chat_sse_event('done', ['chat_id' => $chatId]);
    exit;
}

if ($chatId <= 0) {
    $chatId = ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
}

$chat = $prefetchedChat ?? ai_chat_repo_get($conn, $chatId, $userId);
if (!$chat) {
    ai_chat_sse_event('error', ['message' => 'chat_not_found']);
    exit;
}

ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
ai_chat_repo_add_message($conn, $chatId, 'user', $message);
ai_chat_repo_update_title_if_default($conn, $chatId, $message);

$historyRows = ai_chat_repo_get_messages($conn, $chatId, $userId, 20);
$history = [];
foreach ($historyRows as $row) {
    $role = ($row['role'] === 'assistant') ? 'model' : 'user';
    $history[] = [
        'role' => $role,
        'parts' => [['text' => (string) $row['content']]],
    ];
}

$userFirstName = ai_chat_format_user_first_name((string) ($_SESSION['first_name'] ?? ''));
$modelContext = ai_chat_build_model_context_block($conn, $homeId, is_array($scope) ? $scope : [], $userFirstName);

$pageCtx = $payload['page_context'] ?? null;
$pagePath = '';
$pageTitle = '';
if (is_array($pageCtx)) {
    $pagePath = trim((string) ($pageCtx['path'] ?? ''));
    $pageTitle = trim((string) ($pageCtx['title'] ?? ''));
}
if (ai_chat_scope_topic(is_array($scope) ? $scope : []) === 'system') {
    $pageBlock = ai_chat_format_client_page_context($pagePath, $pageTitle);
    if ($pageBlock !== '') {
        $modelContext .= "\n\n---\n" . $pageBlock;
    }
}

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if ($apiKey === '') {
    ai_chat_sse_event('error', ['message' => 'gemini_key_missing']);
    exit;
}

$route = ai_chat_route_decision($apiKey, $message, is_array($scope) ? $scope : []);
$needsDeep = !empty($route['needs_deep']);

if ($needsDeep) {
    $modelContext .= "\n\n---\n" . ai_chat_build_deep_system_layer_suffix();
} else {
    $modelContext .= "\n\n---\n### מצב שאלה רגילה\n"
        . "תשובה קצרה: עד כ־6–8 משפטים, אלא אם המשתמש ביקש מפורט במפורש.\n";
}

$genTemp = $needsDeep ? 0.34 : 0.28;
$genMax = $needsDeep ? 1100 : 720;

$requestBody = [
    'system_instruction' => [
        'parts' => [['text' => $modelContext]],
    ],
    'contents' => $history,
    'generationConfig' => [
        'temperature' => $genTemp,
        'maxOutputTokens' => $genMax,
    ],
];

$models = $needsDeep
    ? ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite']
    : ['gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-2.5-flash'];
$assistantText = '';
$usedModel = '';
$streamOk = false;
$streamErr = '';
$lastModelChunk = '';

ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => false, 'deep_pass' => $needsDeep]);
if ($needsDeep) {
    ai_chat_sse_event('thinking', [
        'hint' => (string) ($route['user_hint'] ?? ''),
        'reason_code' => (string) ($route['reason_code'] ?? 'other'),
    ]);
}

foreach ($models as $modelName) {
    $usedModel = $modelName;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:streamGenerateContent?alt=sse&key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$assistantText, &$lastModelChunk) {
        $lines = preg_split("/\r\n|\n|\r/", (string) $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strncmp($line, 'data:', 5) !== 0) {
                continue;
            }
            $json = trim(substr($line, 5));
            if ($json === '' || $json === '[DONE]') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }
            $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($text !== '') {
                $delta = $text;
                if ($lastModelChunk !== '' && strpos($text, $lastModelChunk) === 0) {
                    $delta = substr($text, strlen($lastModelChunk));
                }
                if ($delta !== '') {
                    $assistantText .= $delta;
                    ai_chat_sse_event('token', ['text' => $delta]);
                }
                $lastModelChunk = $text;
            }
        }
        return strlen($chunk);
    });

    $execOk = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($execOk !== false && $httpCode === 200 && $assistantText !== '') {
        $streamOk = true;
        break;
    }

    $streamErr = $curlErr !== '' ? $curlErr : "http_{$httpCode}";
    $assistantText = '';
    $lastModelChunk = '';
}

if (!$streamOk) {
    $fallbackText = 'לא הצלחתי להשיב כרגע. נסו שוב בעוד רגע.';
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $fallbackText, $usedModel !== '' ? $usedModel : 'fallback');
    ai_chat_sse_event('token', ['text' => $fallbackText]);
    ai_chat_sse_event('done', ['chat_id' => $chatId, 'fallback' => true]);

    $statusText = mysqli_real_escape_string($conn, 'AI Chat Failed: ' . $streamErr);
    mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");
    exit;
}

$assistantText = trim($assistantText);
ai_chat_repo_add_message($conn, $chatId, 'assistant', $assistantText, $usedModel);
ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
ai_chat_sse_event('done', ['chat_id' => $chatId, 'deep_pass' => $needsDeep]);

$deepTag = $needsDeep ? ' deep=1' : '';
$statusText = mysqli_real_escape_string($conn, 'AI Chat Success (Model: ' . $usedModel . $deepTag . ')');
mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");
<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';

header('Content-Type: text/event-stream; charset=utf-8');
header('X-Accel-Buffering: no');

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function ai_chat_sse_event(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

/** הסרת ```json ... ``` אם המודל עטף את ה-JSON */
function ai_chat_strip_json_fences(string $raw): string
{
    $s = trim($raw);
    if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
        return trim($m[1]);
    }

    return $s;
}

/**
 * קריאה סינכרונית ל-Gemini (ללא סטרים) — לשלב ניתוב.
 *
 * @return string|null טקסט מועמד ראשון או null
 */
function ai_chat_gemini_generate_text(string $apiKey, string $model, array $body): ?string
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 28);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $http !== 200) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');

    return $text !== '' ? $text : null;
}

/**
 * מחליט אם לעבור לשלב תשובה מעמיקה (קריאת JSON קטנה).
 *
 * @return array{needs_deep:bool,reason_code:string,user_hint:string}
 */
function ai_chat_route_decision(string $apiKey, string $message, array $scope): array
{
    $default = ['needs_deep' => false, 'reason_code' => 'simple', 'user_hint' => ''];
    $routerInstruction = ai_chat_build_router_system_instruction($scope);
    $baseBody = [
        'system_instruction' => [
            'parts' => [['text' => $routerInstruction]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => "הודעת המשתמש (לסיווג בלבד):\n{$message}"]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.12,
            'maxOutputTokens' => 220,
        ],
    ];

    $routerModels = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    foreach ($routerModels as $routerModel) {
        foreach ([true, false] as $jsonMime) {
            $body = $baseBody;
            if ($jsonMime) {
                $body['generationConfig']['responseMimeType'] = 'application/json';
            }
            $text = ai_chat_gemini_generate_text($apiKey, $routerModel, $body);
            if ($text === null) {
                continue;
            }
            $parsed = json_decode(ai_chat_strip_json_fences($text), true);
            if (!is_array($parsed)) {
                continue;
            }
            $needs = false;
            if (array_key_exists('needs_deep', $parsed)) {
                $v = $parsed['needs_deep'];
                if (is_bool($v)) {
                    $needs = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $needs = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $needs = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }
            $reason = (string) ($parsed['reason_code'] ?? 'simple');
            $hint = trim((string) ($parsed['user_hint'] ?? ''));
            if (function_exists('mb_substr')) {
                $hint = mb_substr($hint, 0, 48);
            } else {
                $hint = substr($hint, 0, 48);
            }
            if ($needs && $hint === '') {
                $hint = 'מנתח את השאלה לעומק';
            }

            return [
                'needs_deep' => $needs,
                'reason_code' => $reason !== '' ? $reason : 'other',
                'user_hint' => $hint,
            ];
        }
    }

    return $default;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    ai_chat_sse_event('error', ['message' => 'invalid_json']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$chatId = (int) ($payload['chat_id'] ?? 0);

$clientScope = $payload['scope'] ?? ['topic' => 'system'];
if (!is_array($clientScope)) {
    $clientScope = ['topic' => 'system'];
}
$t = (string) ($clientScope['topic'] ?? 'system');
$clientScope = ['topic' => $t === 'financial' ? 'financial' : 'system'];

$scope = $clientScope;
$scopeSnapshot = '{"topic":"system"}';

if ($chatId > 0) {
    $prefetchedChat = ai_chat_repo_get($conn, $chatId, $userId);
    if (!$prefetchedChat) {
        ai_chat_sse_event('error', ['message' => 'chat_not_found']);
        exit;
    }
    $stored = json_decode((string) ($prefetchedChat['scope_snapshot'] ?? '{}'), true);
    if (is_array($stored)) {
        $st = (string) ($stored['topic'] ?? 'system');
        $scope = ['topic' => $st === 'financial' ? 'financial' : 'system'];
    } else {
        $scope = ['topic' => 'system'];
    }
    $enc = json_encode($scope, JSON_UNESCAPED_UNICODE);
    $scopeSnapshot = $enc !== false ? $enc : '{"topic":"system"}';
} else {
    $enc = json_encode($scope, JSON_UNESCAPED_UNICODE);
    $scopeSnapshot = $enc !== false ? $enc : '{"topic":"system"}';
    $prefetchedChat = null;
}

$guard = ai_chat_guard_validate_input($message);
if (!$guard['ok']) {
    if ($chatId <= 0) {
        $chatId = ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
    }
    ai_chat_repo_add_message($conn, $chatId, 'user', $message);
    $refusal = ai_chat_guard_refusal_text();
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $refusal, 'policy-local');
    ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => true]);
    ai_chat_sse_event('token', ['text' => $refusal]);
    ai_chat_sse_event('done', ['chat_id' => $chatId]);
    exit;
}

if ($chatId <= 0) {
    $chatId = ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
}

$chat = $prefetchedChat ?? ai_chat_repo_get($conn, $chatId, $userId);
if (!$chat) {
    ai_chat_sse_event('error', ['message' => 'chat_not_found']);
    exit;
}

ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
ai_chat_repo_add_message($conn, $chatId, 'user', $message);
ai_chat_repo_update_title_if_default($conn, $chatId, $message);

$historyRows = ai_chat_repo_get_messages($conn, $chatId, $userId, 20);
$history = [];
foreach ($historyRows as $row) {
    $role = ($row['role'] === 'assistant') ? 'model' : 'user';
    $history[] = [
        'role' => $role,
        'parts' => [['text' => (string) $row['content']]],
    ];
}

$userFirstName = ai_chat_format_user_first_name((string) ($_SESSION['first_name'] ?? ''));
$modelContext = ai_chat_build_model_context_block($conn, $homeId, is_array($scope) ? $scope : [], $userFirstName);

$pageCtx = $payload['page_context'] ?? null;
$pagePath = '';
$pageTitle = '';
if (is_array($pageCtx)) {
    $pagePath = trim((string) ($pageCtx['path'] ?? ''));
    $pageTitle = trim((string) ($pageCtx['title'] ?? ''));
}
if (ai_chat_scope_topic(is_array($scope) ? $scope : []) === 'system') {
    $pageBlock = ai_chat_format_client_page_context($pagePath, $pageTitle);
    if ($pageBlock !== '') {
        $modelContext .= "\n\n---\n" . $pageBlock;
    }
}

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if ($apiKey === '') {
    ai_chat_sse_event('error', ['message' => 'gemini_key_missing']);
    exit;
}

$route = ai_chat_route_decision($apiKey, $message, is_array($scope) ? $scope : []);
$needsDeep = !empty($route['needs_deep']);

if ($needsDeep) {
    $modelContext .= "\n\n---\n" . ai_chat_build_deep_system_layer_suffix();
} else {
    $modelContext .= "\n\n---\n### מצב שאלה רגילה\n"
        . "תשובה קצרה: עד כ־6–8 משפטים, אלא אם המשתמש ביקש מפורט במפורש.\n";
}

$genTemp = $needsDeep ? 0.34 : 0.28;
$genMax = $needsDeep ? 1100 : 720;

$requestBody = [
    'system_instruction' => [
        'parts' => [['text' => $modelContext]],
    ],
    'contents' => $history,
    'generationConfig' => [
        'temperature' => $genTemp,
        'maxOutputTokens' => $genMax,
    ],
];

$models = $needsDeep
    ? ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite']
    : ['gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-2.5-flash'];
$assistantText = '';
$usedModel = '';
$streamOk = false;
$streamErr = '';
$lastModelChunk = '';

ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => false, 'deep_pass' => $needsDeep]);
if ($needsDeep) {
    ai_chat_sse_event('thinking', [
        'hint' => (string) ($route['user_hint'] ?? ''),
        'reason_code' => (string) ($route['reason_code'] ?? 'other'),
    ]);
}

foreach ($models as $modelName) {
    $usedModel = $modelName;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:streamGenerateContent?alt=sse&key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$assistantText, &$lastModelChunk) {
        $lines = preg_split("/\r\n|\n|\r/", (string) $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strncmp($line, 'data:', 5) !== 0) {
                continue;
            }
            $json = trim(substr($line, 5));
            if ($json === '' || $json === '[DONE]') {
                continue;
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }
            $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($text !== '') {
                $delta = $text;
                if ($lastModelChunk !== '' && strpos($text, $lastModelChunk) === 0) {
                    $delta = substr($text, strlen($lastModelChunk));
                }
                if ($delta !== '') {
                    $assistantText .= $delta;
                    ai_chat_sse_event('token', ['text' => $delta]);
                }
                $lastModelChunk = $text;
            }
        }
        return strlen($chunk);
    });

    $execOk = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($execOk !== false && $httpCode === 200 && $assistantText !== '') {
        $streamOk = true;
        break;
    }

    $streamErr = $curlErr !== '' ? $curlErr : "http_{$httpCode}";
    $assistantText = '';
    $lastModelChunk = '';
}

if (!$streamOk) {
    $fallbackText = 'לא הצלחתי להשיב כרגע. נסו שוב בעוד רגע.';
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $fallbackText, $usedModel !== '' ? $usedModel : 'fallback');
    ai_chat_sse_event('token', ['text' => $fallbackText]);
    ai_chat_sse_event('done', ['chat_id' => $chatId, 'fallback' => true]);

    $statusText = mysqli_real_escape_string($conn, 'AI Chat Failed: ' . $streamErr);
    mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");
    exit;
}

$assistantText = trim($assistantText);
ai_chat_repo_add_message($conn, $chatId, 'assistant', $assistantText, $usedModel);
ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
ai_chat_sse_event('done', ['chat_id' => $chatId, 'deep_pass' => $needsDeep]);

$deepTag = $needsDeep ? ' deep=1' : '';
$statusText = mysqli_real_escape_string($conn, 'AI Chat Success (Model: ' . $usedModel . $deepTag . ')');
mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");

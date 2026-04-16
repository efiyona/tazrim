<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/agent_data.php';
require_once __DIR__ . '/../services/agent_schema.php';

header('Content-Type: text/event-stream; charset=utf-8');
header('X-Accel-Buffering: no');

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// חותמת עבור register_shutdown_function — אם ה-done לא נשלח עד הסוף, נשלח error+done עם פרטים.
$GLOBALS['admin_ai_chat_stream_state'] = [
    'done_emitted' => false,
    'chat_id' => 0,
    'last_gemini_error' => '',
];

register_shutdown_function(function () {
    $state = $GLOBALS['admin_ai_chat_stream_state'] ?? [];
    if (!empty($state['done_emitted'])) return;

    $err = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    $isFatal = is_array($err) && in_array($err['type'] ?? 0, $fatalTypes, true);

    $detail = $isFatal
        ? 'PHP fatal: ' . (string) ($err['message'] ?? 'unknown') . ' @ ' . (string) ($err['file'] ?? '?') . ':' . (int) ($err['line'] ?? 0)
        : 'stream ended without done event';
    if (!empty($state['last_gemini_error'])) {
        $detail .= ' | last_gemini_error: ' . $state['last_gemini_error'];
    }

    // הימנע משבירת SSE שכבר באמצע
    echo "event: error\ndata: " . json_encode([
        'message' => $isFatal ? 'שגיאת שרת בלתי-צפויה במהלך העיבוד' : 'השידור הסתיים ללא סיום תקין',
        'detail' => $detail,
        'code' => $isFatal ? 'php_fatal' : 'stream_incomplete',
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    echo "event: done\ndata: " . json_encode(['chat_id' => (int) ($state['chat_id'] ?? 0), 'fallback' => true, 'error' => true], JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
});

function admin_ai_chat_sse_event(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
    if ($event === 'done') {
        if (isset($GLOBALS['admin_ai_chat_stream_state']) && is_array($GLOBALS['admin_ai_chat_stream_state'])) {
            $GLOBALS['admin_ai_chat_stream_state']['done_emitted'] = true;
            if (isset($payload['chat_id'])) {
                $GLOBALS['admin_ai_chat_stream_state']['chat_id'] = (int) $payload['chat_id'];
            }
        }
    }
}

function admin_ai_chat_strip_json_fences(string $raw): string
{
    $s = trim($raw);
    if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
        return trim($m[1]);
    }

    return $s;
}

function admin_ai_chat_should_retry_http_code(int $httpCode): bool
{
    return in_array($httpCode, [429, 500, 502, 503, 504], true);
}

function admin_ai_chat_backoff_usleep(int $attempt): void
{
    $scheduleMs = [320, 900, 1800];
    $idx = max(0, min($attempt - 1, count($scheduleMs) - 1));
    $baseMs = $scheduleMs[$idx];
    $jitterMs = random_int(0, 220);
    usleep(($baseMs + $jitterMs) * 1000);
}

function admin_ai_chat_gemini_record_error(string $info): void
{
    if (isset($GLOBALS['admin_ai_chat_stream_state']) && is_array($GLOBALS['admin_ai_chat_stream_state'])) {
        $trim = function_exists('mb_substr') ? mb_substr($info, 0, 260, 'UTF-8') : substr($info, 0, 260);
        $GLOBALS['admin_ai_chat_stream_state']['last_gemini_error'] = $trim;
    }
}

function admin_ai_chat_gemini_generate_text(string $apiKey, string $model, array $body): ?string
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $maxAttempts = 3;
    $lastHttp = 0;
    $lastSnippet = '';
    $lastCurlErr = '';
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $lastHttp = $http;
        $lastCurlErr = (string) $curlErr;
        if (is_string($raw) && $raw !== '') {
            $lastSnippet = function_exists('mb_substr') ? mb_substr($raw, 0, 220, 'UTF-8') : substr($raw, 0, 220);
        }

        if ($raw !== false && $http === 200) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                admin_ai_chat_gemini_record_error("model={$model} non_json_response http=200");
                return null;
            }
            $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if ($text === '') {
                // יתכן blockReason / finishReason חריג
                $block = (string) ($decoded['promptFeedback']['blockReason'] ?? '');
                $finish = (string) ($decoded['candidates'][0]['finishReason'] ?? '');
                admin_ai_chat_gemini_record_error("model={$model} empty_text block={$block} finish={$finish}");
                return null;
            }
            return $text;
        }

        if (!admin_ai_chat_should_retry_http_code($http) || $attempt >= $maxAttempts) {
            $snip = preg_replace('/\s+/', ' ', (string) $lastSnippet);
            admin_ai_chat_gemini_record_error("model={$model} http={$lastHttp} curl_err={$lastCurlErr} body=" . (string) $snip);
            return null;
        }
        admin_ai_chat_backoff_usleep($attempt);
    }

    $snip = preg_replace('/\s+/', ' ', (string) $lastSnippet);
    admin_ai_chat_gemini_record_error("model={$model} exhausted http={$lastHttp} curl_err={$lastCurlErr} body=" . (string) $snip);
    return null;
}

function admin_ai_chat_route_decision(string $apiKey, string $message): array
{
    $default = ['needs_deep' => false, 'reason_code' => 'simple', 'user_hint' => ''];
    $routerInstruction = admin_ai_chat_build_router_system_instruction();
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
            $text = admin_ai_chat_gemini_generate_text($apiKey, $routerModel, $body);
            if ($text === null) {
                continue;
            }
            $parsed = json_decode(admin_ai_chat_strip_json_fences($text), true);
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

function admin_ai_chat_extract_questions(string $text): ?array
{
    if (preg_match('/\[\[QUESTIONS\]\]\s*(.*?)\s*\[\[\/QUESTIONS\]\]/s', $text, $m)) {
        $json = trim($m[1]);
        $parsed = json_decode($json, true);
        if (is_array($parsed) && !empty($parsed)) {
            $questions = [];
            foreach ($parsed as $q) {
                if (!isset($q['text'])) {
                    continue;
                }
                $questions[] = [
                    'id' => $q['id'] ?? ('q' . count($questions)),
                    'text' => (string) $q['text'],
                    'options' => isset($q['options']) && is_array($q['options']) ? array_values($q['options']) : [],
                ];
            }
            return !empty($questions) ? $questions : null;
        }
    }
    return null;
}

function admin_ai_chat_strip_questions_block(string $text): string
{
    return trim(preg_replace('/\[\[QUESTIONS\]\]\s*.*?\s*\[\[\/QUESTIONS\]\]/s', '', $text));
}

function admin_ai_chat_extract_data_query(string $text): ?array
{
    if (preg_match('/\[\[DATA_QUERY\]\]\s*(.*?)\s*\[\[\/DATA_QUERY\]\]/s', $text, $m)) {
        $json = admin_ai_chat_strip_json_fences($m[1]);
        $parsed = json_decode($json, true);
        return is_array($parsed) ? $parsed : null;
    }
    return null;
}

function admin_ai_chat_strip_data_query_block(string $text): string
{
    return trim(preg_replace('/\[\[DATA_QUERY\]\]\s*.*?\s*\[\[\/DATA_QUERY\]\]/s', '', $text));
}

function admin_ai_chat_extract_action(string $text): ?array
{
    if (preg_match('/\[\[ACTION\]\]\s*(.*?)\s*\[\[\/ACTION\]\]/s', $text, $m)) {
        $json = admin_ai_chat_strip_json_fences($m[1]);
        $parsed = json_decode($json, true);
        return is_array($parsed) ? $parsed : null;
    }
    return null;
}

/**
 * מזהה בלוק ACTION שנפתח אך לא נסגר (נקטע באמצע בגלל maxOutputTokens).
 */
function admin_ai_chat_has_truncated_action(string $text): bool
{
    $hasOpen = strpos($text, '[[ACTION]]') !== false;
    $hasClose = strpos($text, '[[/ACTION]]') !== false;
    return $hasOpen && !$hasClose;
}

function admin_ai_chat_strip_action_block(string $text): string
{
    $cleaned = preg_replace('/\[\[ACTION\]\]\s*.*?\s*\[\[\/ACTION\]\]/s', '', $text);
    // גם במקרה של בלוק פתוח שלא נסגר — חתוך הכל מה-[[ACTION]] והלאה
    $cleaned = preg_replace('/\[\[ACTION\]\].*$/s', '', (string) $cleaned);
    // הסר סמנים פנימיים שאסור שידלפו למשתמש
    $cleaned = preg_replace('/\[\[QUESTIONS_ASKED\]\]/u', '', (string) $cleaned);
    $cleaned = preg_replace('/\[\[QUESTIONS\]\]\s*.*?\s*\[\[\/QUESTIONS\]\]/s', '', (string) $cleaned);
    return trim((string) $cleaned);
}

function admin_ai_chat_validate_action_shape(array $action): array
{
    $act = strtolower((string) ($action['action'] ?? ''));
    $table = (string) ($action['table'] ?? '');
    if (!in_array($act, ['create', 'update', 'delete', 'sql'], true)) {
        return ['ok' => false, 'error' => 'invalid_action_type'];
    }
    // פעולת sql — מבנה שונה לגמרי (משפט SQL גולמי, בלי whitelist)
    if ($act === 'sql') {
        $sql = trim((string) ($action['sql'] ?? ''));
        if ($sql === '') {
            return ['ok' => false, 'error' => 'missing_sql'];
        }
        if (function_exists('mb_strlen') ? mb_strlen($sql, 'UTF-8') < 4 : strlen($sql) < 4) {
            return ['ok' => false, 'error' => 'sql_too_short'];
        }
        // description חייב להיות מפורט כי אין לנו מידע אחר על הפעולה
        $desc = trim((string) ($action['description'] ?? ''));
        if ($desc === '') {
            return ['ok' => false, 'error' => 'missing_description_for_sql'];
        }
        return ['ok' => true];
    }
    if (!admin_ai_agent_can_write($table)) {
        return ['ok' => false, 'error' => 'table_not_writable:' . $table];
    }
    if (in_array($act, ['update', 'delete'], true) && (int) ($action['id'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'missing_id_for_' . $act];
    }
    if ($act === 'delete' && !empty($action['data'])) {
        return ['ok' => false, 'error' => 'delete_must_not_have_data'];
    }
    if (in_array($act, ['create', 'update'], true)) {
        if (empty($action['data']) || !is_array($action['data'])) {
            return ['ok' => false, 'error' => 'missing_data_for_' . $act];
        }
        foreach ($action['data'] as $col => $_v) {
            if (!admin_ai_agent_is_field_writable($table, (string) $col)) {
                return ['ok' => false, 'error' => 'field_not_writable:' . $col];
            }
        }
    }
    return ['ok' => true];
}

function admin_ai_chat_chunk_and_emit(string $text): void
{
    $text = trim($text);
    if ($text === '') {
        return;
    }
    // שלח במקטעים קצרים כדי ליצור תחושת סטרים
    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    $chunkSize = 80;
    for ($i = 0; $i < $len; $i += $chunkSize) {
        $chunk = function_exists('mb_substr')
            ? mb_substr($text, $i, $chunkSize, 'UTF-8')
            : substr($text, $i, $chunkSize);
        admin_ai_chat_sse_event('token', ['text' => $chunk]);
        usleep(15000);
    }
}

/**
 * וולידטור — קריאת AI נפרדת שבודקת אם הפעולה תואמת את הבקשה המקורית.
 */
function admin_ai_chat_run_validator(string $apiKey, string $originalRequest, array $chatHistoryText, array $action): array
{
    $validatorInstruction = admin_ai_chat_build_validator_instruction();

    $historyExcerpt = '';
    foreach (array_slice($chatHistoryText, -6) as $entry) {
        $role = (string) ($entry['role'] ?? 'user');
        $text = (string) ($entry['text'] ?? '');
        $historyExcerpt .= "[{$role}] {$text}\n";
    }

    $userPayload = "הבקשה המקורית של המנהל:\n«{$originalRequest}»\n\n"
        . "היסטוריית שיחה אחרונה:\n{$historyExcerpt}\n\n"
        . "הפעולה שהסוכן מציע לבצע:\n" . json_encode($action, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
        . "החזר JSON עם approved, confidence, analysis ו-suggestion (במקרה של דחייה).";

    $body = [
        'system_instruction' => ['parts' => [['text' => $validatorInstruction]]],
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $userPayload]]],
        ],
        'generationConfig' => [
            'temperature' => 0.15,
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json',
        ],
    ];

    $models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];
    foreach ($models as $m) {
        $text = admin_ai_chat_gemini_generate_text($apiKey, $m, $body);
        if ($text === null) {
            continue;
        }
        $parsed = json_decode(admin_ai_chat_strip_json_fences($text), true);
        if (!is_array($parsed)) {
            continue;
        }
        $approved = !empty($parsed['approved']);
        return [
            'ok' => true,
            'approved' => $approved,
            'confidence' => (string) ($parsed['confidence'] ?? 'medium'),
            'analysis' => trim((string) ($parsed['analysis'] ?? '')),
            'warnings' => isset($parsed['warnings']) && is_array($parsed['warnings']) ? $parsed['warnings'] : [],
            'suggestion' => trim((string) ($parsed['suggestion'] ?? '')),
            'model' => $m,
        ];
    }

    return [
        'ok' => false,
        'approved' => false,
        'confidence' => 'low',
        'analysis' => 'לא הצלחתי להריץ את הוולידטור. מבטל לזהירות.',
        'warnings' => ['validator_unavailable'],
        'suggestion' => '',
        'model' => '',
    ];
}

// ─────────────────────────────────────────────────────────
// Main flow
// ─────────────────────────────────────────────────────────

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    admin_ai_chat_sse_event('error', ['message' => 'invalid_json']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$chatId = (int) ($payload['chat_id'] ?? 0);
$scopeSnapshot = '{}';
// עדכון ה-shutdown handler כך שיוכל לשלוח done עם ה-chat_id הנכון
if (isset($GLOBALS['admin_ai_chat_stream_state']) && is_array($GLOBALS['admin_ai_chat_stream_state'])) {
    $GLOBALS['admin_ai_chat_stream_state']['chat_id'] = $chatId;
}

if ($chatId > 0) {
    $prefetchedChat = admin_ai_chat_repo_get($conn, $chatId, $userId);
    if (!$prefetchedChat) {
        admin_ai_chat_sse_event('error', ['message' => 'chat_not_found']);
        exit;
    }
} else {
    $prefetchedChat = null;
}

$guard = admin_ai_chat_guard_validate_input($message);
if (!$guard['ok']) {
    if ($chatId <= 0) {
        $chatId = admin_ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
    }
    admin_ai_chat_repo_add_message($conn, $chatId, 'user', $message);
    $refusal = admin_ai_chat_guard_refusal_text();
    admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $refusal, 'policy-local');
    admin_ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    admin_ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => true]);
    admin_ai_chat_sse_event('token', ['text' => $refusal]);
    admin_ai_chat_sse_event('done', ['chat_id' => $chatId]);
    exit;
}

if ($chatId <= 0) {
    $chatId = admin_ai_chat_repo_create($conn, $userId, $scopeSnapshot, 'שיחה חדשה');
}

$chat = $prefetchedChat ?? admin_ai_chat_repo_get($conn, $chatId, $userId);
if (!$chat) {
    admin_ai_chat_sse_event('error', ['message' => 'chat_not_found']);
    exit;
}

admin_ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
admin_ai_chat_repo_add_message($conn, $chatId, 'user', $message);
admin_ai_chat_repo_update_title_if_default($conn, $chatId, $message);

$historyRows = admin_ai_chat_repo_get_messages($conn, $chatId, $userId, 20);
$history = [];
$historyText = [];
foreach ($historyRows as $row) {
    $role = ($row['role'] === 'assistant') ? 'model' : 'user';
    $content = (string) $row['content'];
    // Strip internal markers that should not be echoed back by the model.
    // [[QUESTIONS_ASKED]] is a persistence-only marker (no useful content) — remove it entirely.
    // Keep [[ACTION_PROPOSED]] and [[EXECUTION_RESULT]] blocks so the model knows what it proposed and what actually ran.
    $contentForModel = trim(preg_replace('/\[\[QUESTIONS_ASKED\]\]/u', '', $content));
    if ($contentForModel === '') {
        continue;
    }
    $history[] = [
        'role' => $role,
        'parts' => [['text' => $contentForModel]],
    ];
    $historyText[] = ['role' => $row['role'], 'text' => $contentForModel];
}

$pageCtx = $payload['page_context'] ?? null;
$pagePath = '';
$pageTitle = '';
if (is_array($pageCtx)) {
    $pagePath = trim((string) ($pageCtx['path'] ?? ''));
    $pageTitle = trim((string) ($pageCtx['title'] ?? ''));
}
$pageBlock = admin_ai_chat_format_client_page_context($pagePath, $pageTitle);

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if ($apiKey === '') {
    admin_ai_chat_sse_event('error', ['message' => 'gemini_key_missing']);
    exit;
}

$route = admin_ai_chat_route_decision($apiKey, $message);
$needsDeep = !empty($route['needs_deep']);

$userFirstName = admin_ai_chat_format_user_first_name((string) ($_SESSION['first_name'] ?? ''));
$modelContext = admin_ai_chat_build_model_context_block($userFirstName);

if ($pageBlock !== '') {
    $modelContext .= "\n\n---\n" . $pageBlock;
}

if ($needsDeep) {
    $modelContext .= "\n\n---\n" . admin_ai_chat_build_deep_system_layer_suffix();
}

$genTemp = $needsDeep ? 0.32 : 0.28;
$genMax = 4000;

admin_ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => false, 'deep_pass' => $needsDeep]);
if ($needsDeep) {
    admin_ai_chat_sse_event('thinking', [
        'hint' => (string) ($route['user_hint'] ?? ''),
        'reason_code' => (string) ($route['reason_code'] ?? 'other'),
    ]);
}

$agentModels = $needsDeep
    ? ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite']
    : ['gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-2.5-flash'];

$maxDataQueries = 3;
$dataQueryCount = 0;
$maxValidatorRetries = 2;
$validatorRetryCount = 0;
$usedModel = '';
$finalText = '';
$finalAction = null;
$finalValidation = null;
$emittedQuestions = null;
$agentError = '';

while (true) {
    $requestBody = [
        'system_instruction' => ['parts' => [['text' => $modelContext]]],
        'contents' => $history,
        'generationConfig' => [
            'temperature' => $genTemp,
            'maxOutputTokens' => $genMax,
        ],
    ];

    $rawText = null;
    foreach ($agentModels as $modelName) {
        $rawText = admin_ai_chat_gemini_generate_text($apiKey, $modelName, $requestBody);
        if ($rawText !== null) {
            $usedModel = $modelName;
            break;
        }
    }

    if ($rawText === null) {
        $agentError = 'gemini_unreachable';
        break;
    }

    $rawText = trim($rawText);

    $dataQuery = admin_ai_chat_extract_data_query($rawText);
    if ($dataQuery !== null && $dataQueryCount < $maxDataQueries) {
        $dataQueryCount++;
        $tableName = (string) ($dataQuery['table'] ?? '');
        admin_ai_chat_sse_event('data_fetching', [
            'hint' => 'שולף נתונים' . ($tableName !== '' ? " מ־{$tableName}" : '') . '…',
            'query' => $dataQuery,
        ]);
        $queryResult = admin_ai_chat_agent_query($conn, $dataQuery);

        $history[] = [
            'role' => 'model',
            'parts' => [['text' => $rawText]],
        ];
        $history[] = [
            'role' => 'user',
            'parts' => [['text' => "תוצאת DATA_QUERY (אל תציג אותה ישירות — השתמש בה כדי להמשיך):\n" . json_encode($queryResult, JSON_UNESCAPED_UNICODE)]],
        ];
        continue;
    }

    $questions = admin_ai_chat_extract_questions($rawText);
    if ($questions !== null) {
        $emittedQuestions = $questions;
        $finalText = admin_ai_chat_strip_questions_block($rawText);
        break;
    }

    // טיפול בבלוק ACTION שנקטע באמצע (למשל כשהתוכן ארוך מדי)
    if (admin_ai_chat_has_truncated_action($rawText) && $validatorRetryCount < $maxValidatorRetries) {
        $validatorRetryCount++;
        $history[] = [
            'role' => 'model',
            'parts' => [['text' => admin_ai_chat_strip_action_block($rawText)]],
        ];
        $history[] = [
            'role' => 'user',
            'parts' => [['text' => 'בלוק [[ACTION]] שלך נקטע באמצע (לא הופיע [[/ACTION]]). קצר את ה-description, וודא שכל התוכן הארוך נמצא רק בתוך data (לא בפסקת ההסבר), והחזר בלוק ACTION שלם.']],
        ];
        continue;
    }

    $action = admin_ai_chat_extract_action($rawText);
    if ($action !== null) {
        $shape = admin_ai_chat_validate_action_shape($action);
        if (!$shape['ok']) {
            // הסוכן ניסה פעולה לא חוקית — תיקון
            if ($validatorRetryCount < $maxValidatorRetries) {
                $validatorRetryCount++;
                $history[] = [
                    'role' => 'model',
                    'parts' => [['text' => $rawText]],
                ];
                $history[] = [
                    'role' => 'user',
                    'parts' => [['text' => "הפעולה שהצעת אינה תקפה: {$shape['error']}. תקן ונסה שוב. אם זה לא אפשרי — הסבר למנהל מה הבעיה."]],
                ];
                continue;
            }
            $finalText = admin_ai_chat_strip_action_block($rawText)
                . "\n\n⚠️ לא הצלחתי לייצר פעולה חוקית (שגיאה: " . $shape['error'] . "). אנא נסה לנסח מחדש.";
            break;
        }

        admin_ai_chat_sse_event('validating', ['hint' => 'מאמת את הפעולה…']);
        $validation = admin_ai_chat_run_validator($apiKey, $message, $historyText, $action);

        if ($validation['approved']) {
            $finalText = admin_ai_chat_strip_action_block($rawText);
            $finalAction = $action;
            $finalValidation = $validation;
            break;
        }

        // נדחה — ניסיון תיקון
        if ($validatorRetryCount < $maxValidatorRetries) {
            $validatorRetryCount++;
            admin_ai_chat_sse_event('validation_rejected', [
                'analysis' => $validation['analysis'],
                'suggestion' => $validation['suggestion'],
                'attempt' => $validatorRetryCount,
            ]);
            $suggestion = $validation['suggestion'] !== '' ? $validation['suggestion'] : $validation['analysis'];
            $history[] = [
                'role' => 'model',
                'parts' => [['text' => $rawText]],
            ];
            $history[] = [
                'role' => 'user',
                'parts' => [['text' => "הפעולה שהצעת נדחתה על ידי הוולידטור. המלצה: {$suggestion}\nנסה שוב או שאל שאלת הבהרה (QUESTIONS) אם חסר מידע."]],
            ];
            continue;
        }

        // נגמרו ניסיונות
        $finalText = admin_ai_chat_strip_action_block($rawText)
            . "\n\n⚠️ לא הצלחתי לדייק בפעולה לאחר מספר ניסיונות. סיבה: " . $validation['analysis']
            . "\nנסח בבקשה מחדש עם פרטים מדויקים יותר.";
        break;
    }

    // תשובה רגילה
    $finalText = admin_ai_chat_strip_action_block($rawText);
    if ($finalText === '' && $rawText !== '') {
        $finalText = 'לא הצלחתי לנסח פעולה תקינה. נסה לנסח את הבקשה מחדש, אם אפשר עם פחות פירוט (ה-HTML הארוך יכול להיכנס ישירות ל-data).';
    }
    break;
}

if ($agentError !== '' && $finalText === '') {
    $lastGeminiErr = (string) ($GLOBALS['admin_ai_chat_stream_state']['last_gemini_error'] ?? '');
    $reasonHuman = $agentError === 'gemini_unreachable'
        ? 'המודל לא הגיב (ייתכן timeout, בעיית רשת, או חריגת מכסה).'
        : $agentError;
    $fallbackText = "לא הצלחתי להשיב כרגע.\n\nסיבה: {$reasonHuman}";
    if ($lastGeminiErr !== '') {
        $fallbackText .= "\nפרטים טכניים: {$lastGeminiErr}";
    }
    $fallbackText .= "\n\nנסו שוב בעוד רגע. אם החוזר שוב — בדקו את ai_api_logs לפרטים.";
    admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $fallbackText, $usedModel !== '' ? $usedModel : 'fallback');
    admin_ai_chat_sse_event('error', [
        'message' => 'תשובה לא התקבלה מהמודל',
        'detail' => $lastGeminiErr !== '' ? $lastGeminiErr : $agentError,
        'code' => $agentError,
    ]);
    admin_ai_chat_sse_event('token', ['text' => $fallbackText]);
    admin_ai_chat_sse_event('done', ['chat_id' => $chatId, 'fallback' => true]);
    $logLine = 'Admin AI Chat Failed: ' . $agentError . ' | ' . $lastGeminiErr;
    $statusText = mysqli_real_escape_string($conn, $logLine);
    @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");
    exit;
}

$finalText = trim($finalText);

if ($emittedQuestions !== null) {
    $savedText = $finalText !== '' ? $finalText . "\n\n[[QUESTIONS_ASKED]]" : '[[QUESTIONS_ASKED]]';
    admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $savedText, $usedModel);
    admin_ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    if ($finalText !== '') {
        admin_ai_chat_chunk_and_emit($finalText);
    }
    admin_ai_chat_sse_event('questions', ['questions' => $emittedQuestions, 'preamble' => $finalText]);
    admin_ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_questions' => true]);
} elseif ($finalAction !== null) {
    if ($finalText !== '') {
        admin_ai_chat_chunk_and_emit($finalText);
    }
    $actionPayload = [
        'action' => (string) $finalAction['action'],
        'table' => (string) ($finalAction['table'] ?? ''),
        'description' => (string) ($finalAction['description'] ?? ''),
        'validation' => [
            'approved' => true,
            'confidence' => (string) ($finalValidation['confidence'] ?? 'medium'),
            'analysis' => (string) ($finalValidation['analysis'] ?? ''),
            'warnings' => $finalValidation['warnings'] ?? [],
        ],
    ];
    if (isset($finalAction['id'])) {
        $actionPayload['id'] = (int) $finalAction['id'];
    }
    if (isset($finalAction['data']) && is_array($finalAction['data'])) {
        $actionPayload['data'] = $finalAction['data'];
    }
    if (isset($finalAction['sql'])) {
        $actionPayload['sql'] = (string) $finalAction['sql'];
    }
    if (isset($finalAction['kind'])) {
        $actionPayload['kind'] = (string) $finalAction['kind'];
    }
    admin_ai_chat_sse_event('action', $actionPayload);

    $savedText = $finalText . "\n\n[[ACTION_PROPOSED]]" . json_encode($actionPayload, JSON_UNESCAPED_UNICODE) . "[[/ACTION_PROPOSED]]";
    admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $savedText, $usedModel);
    admin_ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    admin_ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_action' => true]);
} else {
    admin_ai_chat_chunk_and_emit($finalText);
    admin_ai_chat_repo_add_message($conn, $chatId, 'assistant', $finalText, $usedModel);
    admin_ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    admin_ai_chat_sse_event('done', ['chat_id' => $chatId, 'deep_pass' => $needsDeep]);
}

$deepTag = $needsDeep ? ' deep=1' : '';
$agentTag = ($finalAction !== null) ? ' action=' . (string) $finalAction['action'] . ':' . (string) $finalAction['table'] : '';
$qTag = ($dataQueryCount > 0) ? ' dq=' . $dataQueryCount : '';
$vTag = ($validatorRetryCount > 0) ? ' vret=' . $validatorRetryCount : '';
$statusText = mysqli_real_escape_string($conn, 'Admin AI Chat Success (Model: ' . $usedModel . $deepTag . $agentTag . $qTag . $vTag . ')');
mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");

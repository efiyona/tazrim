<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/services/user_preferences_repository.php';
require_once dirname(__DIR__) . '/services/data_boundary_guard.php';
require_once dirname(__DIR__) . '/services/user_agent_transport.php';
require_once dirname(__DIR__) . '/services/reply_quality_gate.php';
require_once dirname(__DIR__) . '/services/user_action_llm_validator.php';
require_once dirname(__DIR__) . '/services/allowed_chat_pages.php';

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

function ai_chat_strip_json_fences(string $raw): string
{
    $s = trim($raw);
    if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
        return trim($m[1]);
    }

    return $s;
}

function ai_chat_should_retry_http_code(int $httpCode): bool
{
    return in_array($httpCode, [429, 500, 502, 503, 504], true);
}

function ai_chat_backoff_usleep(int $attempt): void
{
    $scheduleMs = [320, 900, 1800];
    $idx = max(0, min($attempt - 1, count($scheduleMs) - 1));
    $baseMs = $scheduleMs[$idx];
    $jitterMs = random_int(0, 220);
    usleep(($baseMs + $jitterMs) * 1000);
}

/**
 * @return string|null טקסט מועמד ראשון או null
 */
function ai_chat_gemini_generate_text_timed(string $apiKey, string $model, array $body, int $timeoutSec = 22): ?string
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $maxAttempts = 2;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw !== false && $http === 200) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');

            return $text !== '' ? $text : null;
        }

        if (!ai_chat_should_retry_http_code($http) || $attempt >= $maxAttempts) {
            return null;
        }
        ai_chat_backoff_usleep($attempt);
    }

    return null;
}

/**
 * @return array<string,mixed>
 */
function ai_chat_route_decision_v2(string $apiKey, string $message, string $routerUserBlock): array
{
    $defaults = ai_chat_router_normalize(null, null);
    $anchor = ai_chat_build_server_time_anchor_block();
    $instr = ai_chat_build_router_instruction_v2($anchor, '');
    $baseBody = [
        'system_instruction' => [
            'parts' => [['text' => $instr]],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $routerUserBlock . "\n\nהודעת המשתמש (לסיווג בלבד):\n{$message}"]],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.12,
            'maxOutputTokens' => 320,
        ],
    ];

    $routerModels = ['gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    foreach ($routerModels as $routerModel) {
        foreach ([true, false] as $jsonMime) {
            $body = $baseBody;
            if ($jsonMime) {
                $body['generationConfig']['responseMimeType'] = 'application/json';
            }
            $text = ai_chat_gemini_generate_text_timed($apiKey, $routerModel, $body, 22);
            if ($text === null) {
                continue;
            }
            $parsed = json_decode(ai_chat_strip_json_fences($text), true);
            if (!is_array($parsed)) {
                continue;
            }
            $norm = ai_chat_router_normalize($parsed, null);
            $norm['needs_deep'] = $defaults['needs_deep'];
            if (array_key_exists('needs_deep', $parsed)) {
                $v = $parsed['needs_deep'];
                if (is_bool($v)) {
                    $norm['needs_deep'] = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $norm['needs_deep'] = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $norm['needs_deep'] = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }
            if (array_key_exists('needs_full_transactions', $parsed)) {
                $v2 = $parsed['needs_full_transactions'];
                if (is_bool($v2)) {
                    $norm['needs_full_transactions'] = $v2;
                } elseif (is_int($v2) || is_float($v2)) {
                    $norm['needs_full_transactions'] = ((int) $v2) === 1;
                } elseif (is_string($v2)) {
                    $norm['needs_full_transactions'] = in_array(strtolower(trim($v2)), ['1', 'true', 'yes'], true);
                }
            }

            return $norm;
        }
    }

    return $defaults;
}

function ai_chat_json_canonicalize(array $action): string
{
    $sorted = $action;
    ksort($sorted);

    return json_encode($sorted, JSON_UNESCAPED_UNICODE);
}

/**
 * @param array<string,mixed> $action
 * @return array{ok:bool,error?:string}
 */
function ai_chat_validate_user_action_shape(array $action): array
{
    $kind = strtolower(trim((string) ($action['kind'] ?? $action['action'] ?? '')));
    if ($kind === 'save_user_preference') {
        $k = trim((string) ($action['pref_key'] ?? $action['key'] ?? ''));
        $v = trim((string) ($action['pref_value'] ?? $action['value'] ?? ''));
        if (!ai_user_pref_allowed_key($k)) {
            return ['ok' => false, 'error' => 'bad_pref_key'];
        }
        if ($v === '' || strlen($v) > 8000) {
            return ['ok' => false, 'error' => 'bad_pref_value'];
        }

        return ['ok' => true];
    }
    if ($kind === 'create_category') {
        $name = trim((string) ($action['name'] ?? ''));
        $type = (string) ($action['type'] ?? 'expense');
        if ($type !== 'expense' && $type !== 'income') {
            return ['ok' => false, 'error' => 'bad_cat_type'];
        }
        $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($len < 1 || $len > 100) {
            return ['ok' => false, 'error' => 'bad_cat_name'];
        }
        $icon = trim((string) ($action['icon'] ?? ''));
        if ($icon !== '' && !preg_match('/^fa-[a-z0-9-]{1,40}$/', $icon)) {
            return ['ok' => false, 'error' => 'bad_cat_icon'];
        }
        if (isset($action['budget_limit'])) {
            $b = (float) $action['budget_limit'];
            if ($b < 0 || $b > 99999999) {
                return ['ok' => false, 'error' => 'bad_cat_budget'];
            }
        }
        $init = $action['initial_transaction'] ?? null;
        if ($init !== null && !is_array($init)) {
            return ['ok' => false, 'error' => 'bad_init_tx_shape'];
        }
        if (is_array($init)) {
            $amt = (float) ($init['amount'] ?? 0);
            $desc = trim((string) ($init['description'] ?? ''));
            $d = trim((string) ($init['transaction_date'] ?? ''));
            if ($amt <= 0 || $desc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                return ['ok' => false, 'error' => 'bad_init_tx_fields'];
            }
        }

        return ['ok' => true];
    }
    if ($kind === 'create_transaction') {
        $type = (string) ($action['type'] ?? '');
        if ($type !== 'expense' && $type !== 'income') {
            return ['ok' => false, 'error' => 'bad_tx_type'];
        }
        $amount = (float) ($action['amount'] ?? 0);
        $cid = (int) ($action['category_id'] ?? 0);
        $desc = trim((string) ($action['description'] ?? ''));
        $d = trim((string) ($action['transaction_date'] ?? ''));
        if ($amount <= 0 || $cid <= 0 || $desc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return ['ok' => false, 'error' => 'bad_tx_fields'];
        }

        return ['ok' => true];
    }
    if ($kind === 'update_user_nickname') {
        $nick = trim((string) ($action['nickname'] ?? ''));
        if ($nick === '') {
            return ['ok' => false, 'error' => 'bad_nickname_empty'];
        }
        $len = function_exists('mb_strlen') ? mb_strlen($nick, 'UTF-8') : strlen($nick);
        if ($len > 80) {
            return ['ok' => false, 'error' => 'bad_nickname_long'];
        }

        return ['ok' => true];
    }

    return ['ok' => false, 'error' => 'unknown_kind'];
}

function ai_chat_emit_agent_error_and_done(
    int $chatId,
    string $userMessage,
    string $code = 'agent_error',
    ?mysqli $persistConn = null,
    string $scopeSnapshotForTouch = '{}'
): void {
    ai_chat_sse_event('agent_error', [
        'message' => $userMessage,
        'code' => $code,
    ]);
    if ($persistConn instanceof mysqli && $chatId > 0 && trim($userMessage) !== '') {
        ai_chat_repo_add_message($persistConn, $chatId, 'assistant', $userMessage, $code);
        ai_chat_repo_touch($persistConn, $chatId, $scopeSnapshotForTouch);
    }
    ai_chat_sse_event('done', ['chat_id' => $chatId, 'error' => true]);
}

function ai_chat_emit_text_as_tokens(string $text): void
{
    $text = (string) $text;
    if ($text === '') {
        return;
    }
    // חיתוך לפי בתים (substr) שובר תווים רב-בתיים בעברית — חייבים mb_* או אירוע יחיד
    if (!function_exists('mb_strlen') || !function_exists('mb_substr')) {
        ai_chat_sse_event('token', ['text' => $text]);

        return;
    }
    $enc = 'UTF-8';
    $step = 72;
    $n = mb_strlen($text, $enc);
    if ($n <= $step) {
        ai_chat_sse_event('token', ['text' => $text]);

        return;
    }
    for ($i = 0; $i < $n; $i += $step) {
        $chunk = mb_substr($text, $i, $step, $enc);
        if ($chunk !== '') {
            ai_chat_sse_event('token', ['text' => $chunk]);
        }
    }
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    ai_chat_sse_event('error', ['message' => 'invalid_json']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$chatId = (int) ($payload['chat_id'] ?? 0);

$currentView = $payload['current_view'] ?? null;
if (!is_array($currentView)) {
    $currentView = [];
}
$pageCtx = $payload['page_context'] ?? null;
if (is_array($pageCtx)) {
    if (empty($currentView['path']) && isset($pageCtx['path'])) {
        $currentView['path'] = $pageCtx['path'];
    }
    if (empty($currentView['title']) && isset($pageCtx['title'])) {
        $currentView['title'] = $pageCtx['title'];
    }
}

$scopeSnapshot = '{}';

$prefetchedChat = null;
if ($chatId > 0) {
    $prefetchedChat = ai_chat_repo_get($conn, $chatId, $userId);
    if (!$prefetchedChat) {
        ai_chat_sse_event('error', ['message' => 'chat_not_found']);
        exit;
    }
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

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if ($apiKey === '') {
    ai_chat_sse_event('error', ['message' => 'gemini_key_missing']);
    exit;
}

$prefs = ai_user_pref_list_for_prompt($conn, $userId);
$routerViewBlock = ai_chat_format_current_view_for_router($currentView !== [] ? $currentView : null);
$route = ai_chat_route_decision_v2($apiKey, $message, $routerViewBlock);
$route = ai_chat_router_normalize($route, $currentView !== [] ? $currentView : null);

$needsDeep = !empty($route['needs_deep']);
$needsFullTransactions = !empty($route['needs_full_transactions']);

$userFirstName = ai_chat_format_user_first_name((string) ($_SESSION['first_name'] ?? ''));
$sessionNickname = trim((string) ($_SESSION['nickname'] ?? ''));
$identityBlock = ai_chat_build_session_identity_block($userFirstName, $sessionNickname);

$modelContext = ai_chat_compose_system_context(
    $conn,
    $homeId,
    $route,
    $currentView !== [] ? $currentView : null,
    $prefs,
    $userFirstName,
    [
        'need_full_transactions' => $needsFullTransactions,
        'user_message_for_rag' => $message,
        'identity_context_block' => $identityBlock,
    ]
);

$guardCtx = ai_chat_guard_context_block($modelContext, $homeId);
if (!$guardCtx['ok'] && ($guardCtx['error'] ?? '') === 'context_too_large') {
    $modelContext = (string) ($guardCtx['content'] ?? $modelContext);
} elseif (!$guardCtx['ok']) {
    ai_chat_emit_agent_error_and_done($chatId, 'לא הצלחנו להכין את ההקשר. נסו שוב בעוד רגע.', 'guard', $conn, $scopeSnapshot);
    exit;
} else {
    $modelContext = (string) $guardCtx['content'];
}

if ($needsDeep) {
    $modelContext .= "\n\n---\n" . ai_chat_build_deep_system_layer_suffix();
} else {
    $modelContext .= "\n\n---\n### מצב שאלה רגילה\n"
        . "העדף תשובה קומפקטית; אם המשתמש ביקש סיכום/פריסה/מצב חודש — אפשר עד כ־14 משפטים עם פסקאות ברורות. אל תחתוך באמצע משפט.\n";
}

$innerInstr = $modelContext . "\n\n---\n"
    . "כעת נסח את התשובה הסופית למשתמש.\n"
    . "- **קישורי מערכת (PAGE):** ב־[[PAGE:נתיב|כפתור]] — **רק** הנתיבים מהרשימה הבאה. נתיב אחר ייחשב שגוי ולא יוצג כקישור:\n"
    . ai_chat_format_allowed_pages_for_prompt() . "\n"
    . "- **פרטיות טכנית:** אסור להציג למשתמש מזהי קטגוריה, מזהי פעולה, מספרי id, את המילה category_id, או JSON/שמות שדות מהמערכת. מזהים מספריים מותרים **רק** בתוך בלוק [[ACTION]] כשמוסיפים פעולה — לא בהסבר חופשי, לא ברשימות \"למידע\".\n"
    . "- **עיצוב:** חלק לקטעים קצרים; כותרות משנה עם **הדגשה** (למשל **הכנסות**); רשימות עם * בשורה נפרדת; שורה ריקה בין נושאים — לא גוש טקסט אחד.\n"
    . "- תשובות שלמות: אל תחתוך באמצע מילה או משפט. אל תשאיר רשימות עם שורות ריקות או תבניות ריקות.\n"
    . "- גרפים: בחלון הצ'אט אין גרף חי. אם מבקשים גרף — הפנה ל־[[PAGE:/pages/reports.php|דוחות]] או לדף הבית, ואפשר לסכם בקצרה בטקסט לפי הנתונים שקיבלת.\n"
    . "- אם חסר מידע קריטי — בלוק [[QUESTIONS]] כמתואר במדריך הכלים.\n"
    . "- יצירת קטגוריה חדשה (כולל פעולה ראשונה באותה קטגוריה): בלוק [[ACTION]] עם `create_category` — שדות: `name`, `type` (expense|income), אופציונלי `icon` (למשל fa-bolt), אופציונלי `budget_limit`, ואופציונלי `initial_transaction` עם `amount`, `description`, `transaction_date` (אותו סוג כמו הקטגוריה). אחרי אישור המשתמש זה נרשם בבית שלו בלבד.\n"
    . "- הוספת הוצאה/הכנסה לקטגוריה **קיימת**: בלוק [[ACTION]] עם `create_transaction`; **חובה** `category_id` פנימי מהמיפוי בנתוני המערכת (לא להציג למשתמש). סכום חיובי, תיאור, תאריך YYYY-MM-DD.\n"
    . "- עדכון כינוי במערכת: בלוק [[ACTION]] עם `{\"kind\":\"update_user_nickname\",\"nickname\":\"...\"}` — רק אחרי שהמשתמש ביקש במפורש; לא לשלוח טלפון/אימייל.\n"
    . "- שמירת העדפה לטווח ארוך: `save_user_preference` עם מפתח goal_* או fact_*.\n"
    . "- אפשר משפט מבוא קצר אחד לפני בלוק ACTION/QUESTIONS. אם אין צורך בכלים — עברית רגילה בלבד בלי בלוקים.\n";

$innerBody = [
    'system_instruction' => [
        'parts' => [['text' => $innerInstr]],
    ],
    'contents' => $history,
    'generationConfig' => [
        'temperature' => $needsDeep ? 0.32 : 0.26,
        'maxOutputTokens' => $needsDeep ? 4500 : 2200,
    ],
];

ai_chat_sse_event('meta', ['chat_id' => $chatId, 'blocked' => false, 'deep_pass' => $needsDeep, 'route' => $route]);
ai_chat_sse_event('agent_step', [
    'phase' => 'route',
    'label' => 'ניתוב',
    'hint' => 'מזהים את סוג השאלה והטווח הרלוונטי…',
]);
ai_chat_sse_event('agent_step', [
    'phase' => 'context',
    'label' => 'איסוף נתונים',
    'hint' => 'מרכיבים תזרים, קטגוריות והעדפות מהמערכת (לפי ההרשאות שלך)…',
]);
if ($needsDeep) {
    ai_chat_sse_event('thinking', [
        'hint' => (string) ($route['user_hint'] ?? ''),
        'reason_code' => (string) ($route['reason_code'] ?? 'other'),
    ]);
} else {
    ai_chat_sse_event('agent_step', [
        'phase' => 'compose',
        'label' => 'ניסוח',
        'hint' => 'מנסחים תשובה…',
    ]);
}

$draftModels = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];
$draftText = null;
$draftModel = $draftModels[0];
foreach ($draftModels as $tryModel) {
    $draftModel = $tryModel;
    $draftText = ai_chat_gemini_generate_text_timed($apiKey, $tryModel, $innerBody, 38);
    if ($draftText !== null && trim($draftText) !== '') {
        break;
    }
}
if ($draftText === null) {
    ai_chat_emit_agent_error_and_done(
        $chatId,
        'היי, המערכת קצת עמוסה כרגע ומתקשה לשלוף את כל הנתונים. אפשר לנסות לשאול שוב?',
        'timeout',
        $conn,
        $scopeSnapshot
    );
    $log = mysqli_real_escape_string($conn, 'AI Chat agent_error: inner_timeout');
    @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$log}')");
    exit;
}

$draftText = trim($draftText);
$questions = ai_chat_extract_questions_block($draftText);
if ($questions !== null) {
    $preamble = trim(ai_chat_strip_questions_block($draftText));
    $save = $preamble !== '' ? $preamble : 'יש לי כמה שאלות כדי להמשיך:';
    $questionsContext = ai_chat_build_questions_context_text($questions);
    $savedText = $save;
    if ($questionsContext !== '') {
        $savedText = $savedText !== '' ? ($savedText . "\n\n" . $questionsContext) : $questionsContext;
    }
    $savedText = $savedText !== '' ? ($savedText . "\n\n[[QUESTIONS_ASKED]]") : '[[QUESTIONS_ASKED]]';
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $savedText, $draftModel);
    if ($preamble !== '') {
        ai_chat_emit_text_as_tokens($preamble);
    }
    ai_chat_sse_event('questions', ['questions' => $questions, 'preamble' => $preamble]);
    ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_questions' => true]);
    @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, 'AI Chat questions')");
    exit;
}

$action = ai_chat_extract_action_block($draftText);
if ($action !== null) {
    ai_chat_sse_event('validating', [
        'hint' => 'בודקים שההצעה מאושרת במדיניות המערכת…',
    ]);
    $shape = ai_chat_validate_user_action_shape($action);
    if (!$shape['ok']) {
        $clean = ai_chat_strip_action_block($draftText);
        if ($clean === '') {
            $clean = 'לא הצלחתי להבין את הפעולה המוצעת. נסו לנסח שוב.';
        }
        ai_chat_emit_text_as_tokens($clean);
        ai_chat_repo_add_message($conn, $chatId, 'assistant', $clean, $draftModel);
        ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_action' => false]);
        exit;
    }

    $validatorHistoryText = [];
    foreach ($historyRows as $row) {
        $validatorHistoryText[] = [
            'role' => (string) ($row['role'] ?? 'user'),
            'text' => (string) ($row['content'] ?? ''),
        ];
    }
    ai_chat_sse_event('agent_step', [
        'phase' => 'llm_validate',
        'label' => 'וולידטור AI',
        'hint' => 'בודקים שהפעולה תואמת את בקשתך ובטוחה לביצוע…',
    ]);
    $val = ai_chat_run_user_action_validator($apiKey, $message, $validatorHistoryText, $action);
    if (!$val['approved']) {
        $clean = trim(ai_chat_strip_action_block($draftText));
        $analysis = $val['analysis'] !== '' ? $val['analysis'] : 'הפעולה לא אושרה בבדיקת האיכות.';
        $sug = $val['suggestion'] !== '' ? "\n\n**מה אפשר לעשות:** " . $val['suggestion'] : '';
        $msg = ($clean !== '' ? $clean . "\n\n" : '')
            . 'לא אישרנו את ביצוע הפעולה כרגע: ' . $analysis . $sug;
        ai_chat_emit_text_as_tokens($msg);
        $modelTag = $draftModel . ($val['model'] !== '' ? '+validator:' . $val['model'] : '+validator_reject');
        ai_chat_repo_add_message($conn, $chatId, 'assistant', $msg, $modelTag);
        ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
        ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_action' => false, 'validator' => 'rejected']);
        $log = mysqli_real_escape_string($conn, 'AI Chat action_validator_reject');
        @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$log}')");
        exit;
    }

    $canonical = ai_chat_json_canonicalize($action);
    $proposedAt = (int) round(microtime(true) * 1000);
    $proposalId = bin2hex(random_bytes(16));
    $sig = ai_chat_sign_proposal($canonical, $chatId, $proposedAt);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['ai_chat_proposals'][$proposalId] = [
        'canonical' => $canonical,
        'chat_id' => $chatId,
        'proposed_at' => $proposedAt,
        't' => time(),
        'proposal_type' => (string) ($action['kind'] ?? 'action'),
    ];
    $preamble = trim(ai_chat_strip_action_block($draftText));
    if ($preamble !== '') {
        ai_chat_emit_text_as_tokens($preamble);
    }
    $actionPayload = json_decode($canonical, true) ?: $action;
    if (($actionPayload['kind'] ?? '') === 'create_transaction') {
        $cidEnrich = (int) ($actionPayload['category_id'] ?? 0);
        if ($cidEnrich > 0 && $homeId > 0) {
            $nStmt = $conn->prepare('SELECT name FROM categories WHERE id = ? AND home_id = ? AND is_active = 1 LIMIT 1');
            if ($nStmt) {
                $nStmt->bind_param('ii', $cidEnrich, $homeId);
                $nStmt->execute();
                $nRow = $nStmt->get_result()->fetch_assoc();
                $nStmt->close();
                if ($nRow && trim((string) ($nRow['name'] ?? '')) !== '') {
                    $actionPayload['category_display_name'] = trim((string) $nRow['name']);
                }
            }
        }
    }
    $actionPayload['proposal_id'] = $proposalId;
    $actionPayload['signature'] = $sig;
    $actionPayload['proposed_at'] = $proposedAt;
    $actionPayload['chat_id'] = $chatId;
    ai_chat_sse_event('action', $actionPayload);
    $saved = ($preamble !== '' ? $preamble . "\n\n" : '') . '[[ACTION_PROPOSED]]' . $canonical . '[[/ACTION_PROPOSED]]';
    ai_chat_repo_add_message($conn, $chatId, 'assistant', $saved, $draftModel);
    ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
    ai_chat_sse_event('done', ['chat_id' => $chatId, 'has_action' => true, 'proposed_at' => $proposedAt]);
    @mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, 'AI Chat action_proposed')");
    exit;
}

$currentModel = $draftModel;
$currentDraft = trim($draftText);
$workHistory = $history;
$maxReplyPolishRetries = 1;
$replyPolishRetries = 0;
$finalText = '';

while (true) {
    $candidate = trim(ai_chat_strip_action_block($currentDraft));
    $candidate = trim(ai_chat_strip_questions_block($candidate));
    $scan = ai_chat_reply_quality_scan($candidate);
    if (!$scan['suspicious']) {
        $finalText = $candidate;
        break;
    }
    $gate = ai_chat_run_reply_polish_gate($apiKey, $message, $candidate, $scan['reasons']);
    if ($gate['acceptable']) {
        $finalText = $candidate;
        break;
    }
    if ($replyPolishRetries >= $maxReplyPolishRetries) {
        $finalText = $candidate;
        break;
    }
    $replyPolishRetries++;
    ai_chat_sse_event('agent_step', [
        'phase' => 'refine',
        'label' => 'שיפור ניסוח',
        'hint' => 'מתאימים את התשובה להצגה למשתמש…',
    ]);
    $workHistory[] = ['role' => 'model', 'parts' => [['text' => $currentDraft]]];
    $hint = $gate['retry_instruction_he'] !== ''
        ? $gate['retry_instruction_he']
        : ai_chat_reply_polish_default_hint($scan['reasons']);
    $workHistory[] = [
        'role' => 'user',
        'parts' => [['text' => "שער איכות: התשובה לא מוכנה להצגה למשתמש.\n{$hint}"]],
    ];
    $refineGen = $innerBody['generationConfig'];
    $refineGen['temperature'] = min(0.22, (float) ($refineGen['temperature'] ?? 0.26));
    $refineBody = [
        'system_instruction' => $innerBody['system_instruction'],
        'contents' => $workHistory,
        'generationConfig' => $refineGen,
    ];
    $nextDraft = null;
    foreach ($draftModels as $tryModel) {
        $nextDraft = ai_chat_gemini_generate_text_timed($apiKey, $tryModel, $refineBody, 38);
        if ($nextDraft !== null && trim($nextDraft) !== '') {
            $currentModel = $tryModel;
            break;
        }
    }
    if ($nextDraft === null || trim($nextDraft) === '') {
        $finalText = $candidate !== '' ? $candidate : 'לא הצלחתי לשפר את הניסוח. נסו לשאול שוב.';
        break;
    }
    $currentDraft = trim($nextDraft);
}

$scanFinal = ai_chat_reply_quality_scan($finalText);
if ($scanFinal['suspicious']) {
    $polishMaxOut = $needsDeep ? 2200 : 900;
    $polishBody = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => ai_chat_reply_quality_polish_user_message($finalText, $scanFinal['reasons'])]],
            ],
        ],
        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => $polishMaxOut],
    ];
    $polished = ai_chat_gemini_generate_text_timed($apiKey, 'gemini-2.0-flash', $polishBody, 22);
    if ($polished !== null && trim($polished) !== '') {
        $finalText = trim($polished);
    }
}

if ($finalText === '') {
    $finalText = 'לא הצלחתי לנסח תשובה. נסו שוב.';
}

ai_chat_emit_text_as_tokens($finalText);
ai_chat_repo_add_message($conn, $chatId, 'assistant', $finalText, $currentModel);
ai_chat_repo_touch($conn, $chatId, $scopeSnapshot);
ai_chat_sse_event('done', ['chat_id' => $chatId, 'deep_pass' => $needsDeep]);

$deepTag = $needsDeep ? ' deep=1' : '';
$polishTag = $replyPolishRetries > 0 ? ' refine=' . $replyPolishRetries : '';
$statusText = mysqli_real_escape_string($conn, 'AI Chat Success (Model: ' . $currentModel . $deepTag . $polishTag . ')');
@mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ({$homeId}, {$userId}, '{$statusText}')");

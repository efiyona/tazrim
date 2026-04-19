<?php
/**
 * יצירת נושא + HTML (מייל) עם Gemini — SSE, שאלות חוזרות, fallback מודלים.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init_ajax.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'גוף בקשה לא תקין.'], 400);
}

$csrf = $body['csrf_token'] ?? '';
if (!tazrim_admin_csrf_validate($csrf)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'פג תוקף אבטחה. רעננו את הדף.'], 419);
}

$phase = trim((string) ($body['phase'] ?? 'generate'));
$instructions = trim((string) ($body['instructions'] ?? ''));
if ($instructions === '' && $phase === 'generate') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא למלא הוראות לבינה.'], 400);
}
if (function_exists('mb_strlen') && mb_strlen($instructions, 'UTF-8') > 6000) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'הוראות ארוכות מדי.'], 400);
}

if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מפתח AI לא מוגדר בשרת (GEMINI_API_KEY).'], 503);
}

require_once dirname(__DIR__) . '/features/ai_chat/services/agent_send_mail.php';
$pubBase = admin_ai_chat_resolve_public_base_url();
$pubBlock = $pubBase !== ''
    ? 'בסיס כתובות מלא לקישורים במייל (השתמש בדיוק בערך הזה כשורש ל-href — אל תשתמש בנתיב שמתחיל בסלאש לבד בלי בסיס מלא):' . "\n" . $pubBase . "\n\n"
    : "אין בסיס ציבורי ידוע מהשרת — השתמש בקישורים יחסיים זהירים או הימנע מקישורים.\n\n";

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function me_sse(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

function me_sse_done_error(string $message): void
{
    me_sse('done', ['status' => 'error', 'message' => $message]);
    exit;
}

$systemPrompt = $pubBlock . <<<'PROMPT'
אתה קופירייטר לאפליקציה בעברית בשם «התזרים» — ניהול תקציב משפחתי.
עליך לכתוב **מייל HTML** למשתמשי המערכת (ניסוח מקצועי, RTL).

### פורמט תשובה (JSON בלבד — ללא טקסט לפני/אחרי, ללא ```)

אם **יש לך שאלות** לפני יצירת התוכן:
החזר JSON עם המפתח "questions" בלבד:
{
  "questions": [
    {
      "id": "q1",
      "text": "השאלה בעברית",
      "options": ["אפשרות א", "אפשרות ב", "אפשרות ג"]
    }
  ]
}
כללי שאלות: עד 3 שאלות; 2–4 אפשרויות; שאל רק כשחסר מידע קריטי.

אם **אין שאלות** (או שכבר קיבלת תשובות):
החזר JSON עם המפתחות subject, html_body, ואופציונלית text_body.
דוגמה למבנה (התאם את התוכן; אל תעתיק טקסט דמה):
{ "subject": "...", "html_body": "<div dir=rtl>...</div>", "text_body": "..." }

### כללי html_body
- עטוף ב-div עם dir=rtl; עיצוב נקי (טבלאות/כפתורים inline בסיסי מותר).
- כל href ו-src חייבים להיות **URL מלא** כשהובהר בסיס למעלה.
- ללא `<script>`, ללא `<iframe>`.
- עברית ברורה; טון מכבד ומקצועי.

ההנחיות מהמנהל:
PROMPT;

$conversationHistory = [];
$answersFromUser = isset($body['answers']) && is_array($body['answers']) ? $body['answers'] : [];

if ($phase === 'answer' && !empty($answersFromUser)) {
    $prevInstructions = trim((string) ($body['original_instructions'] ?? $instructions));
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt
        . "\n\n---\n" . $prevInstructions
        . "\n\n---\nזכור: החזר רק JSON (questions או subject+html_body)."
    ]]];
    $prevQuestions = isset($body['prev_questions']) ? json_encode($body['prev_questions'], JSON_UNESCAPED_UNICODE) : '[]';
    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $prevQuestions]]];
    $answersText = "תשובות לשאלות:\n";
    foreach ($answersFromUser as $ans) {
        $qid = $ans['id'] ?? '';
        $val = $ans['value'] ?? '';
        $answersText .= "- {$qid}: {$val}\n";
    }
    $answersText .= "\n---\nעכשיו צור JSON סופי עם subject ו-html_body (ו-text_body אם רלוונטי).";
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' => $answersText]]];
} else {
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt
        . "\n\n---\n" . $instructions
        . "\n\n---\nזכור: החזר רק JSON (questions או subject+html_body)."
    ]]];
}

$api_key = GEMINI_API_KEY;
$gemini_models = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];
$maxAttemptsPerModel = 2;
$retryableCodes = [429, 500, 502, 503, 504];
$maxJsonRetries = 2;

me_sse('thinking', ['hint' => 'מנתח את ההנחיות ומנסח מייל…']);

function me_call_gemini(array $conversationHistory, string $apiKey, array $models, int $maxAttempts, array $retryable): array
{
    $data = [
        'contents' => $conversationHistory,
        'generationConfig' => [
            'temperature' => 0.42,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
        ],
    ];

    $httpCode = 0;
    $response = '';
    foreach ($models as $modelName) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . $apiKey;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 120,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['http' => 200, 'body' => $response, 'model' => $modelName];
            }
            if (in_array($httpCode, $retryable, true)) {
                usleep(600000 + random_int(0, 300000));
                continue;
            }
            break;
        }
    }

    return ['http' => $httpCode, 'body' => $response, 'model' => $models[count($models) - 1] ?? ''];
}

function me_extract_json(string $rawText): ?array
{
    $s = trim($rawText);
    if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', $s, $m)) {
        $s = trim($m[1]);
    } elseif ($s !== '' && $s[0] !== '{' && $s[0] !== '[') {
        if (preg_match('/\{[\s\S]*\}/s', $s, $m)) {
            $s = $m[0];
        }
    }
    $parsed = json_decode($s, true);

    return is_array($parsed) ? $parsed : null;
}

$jsonResult = null;
$lastError = '';

for ($jsonTry = 0; $jsonTry <= $maxJsonRetries; $jsonTry++) {
    if ($jsonTry > 0) {
        me_sse('thinking', ['hint' => 'מנסה שוב (ניסיון ' . ($jsonTry + 1) . ')…']);
        $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
            'התשובה לא הייתה JSON תקין. החזר אובייקט JSON תקין בלבד.'
        ]]];
    }

    $result = me_call_gemini($conversationHistory, $api_key, $gemini_models, $maxAttemptsPerModel, $retryableCodes);

    if ($result['http'] !== 200) {
        $friendly = 'שגיאת תקשורת עם שירות הבינה';
        $decoded = json_decode($result['body'], true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = (string) $decoded['error']['message'];
            $status = $decoded['error']['status'] ?? '';
            if (stripos($msg, 'high demand') !== false || $status === 'UNAVAILABLE') {
                $friendly = 'שירות הבינה עמוס כרגע. נסו שוב בעוד דקה–שתיים.';
            } elseif ($result['http'] === 404 || $status === 'NOT_FOUND') {
                $friendly = 'המודל לא זמין ב-API. נסו שוב מאוחר יותר.';
            }
        }
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        me_sse_done_error($friendly);
    }

    $responseData = json_decode($result['body'], true);
    $rawAiReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($rawAiReply) || $rawAiReply === '') {
        $lastError = 'תשובה ריקה מהמודל.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        me_sse_done_error($lastError);
    }

    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $rawAiReply]]];

    $parsed = me_extract_json($rawAiReply);
    if (!is_array($parsed)) {
        $lastError = 'לא ניתן לפענח את תשובת ה-AI כ-JSON.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        me_sse_done_error($lastError);
    }

    $jsonResult = $parsed;
    break;
}

if (!$jsonResult) {
    me_sse_done_error($lastError ?: 'שגיאה לא צפויה.');
}

if (isset($jsonResult['questions']) && is_array($jsonResult['questions']) && count($jsonResult['questions']) > 0) {
    $questions = [];
    foreach ($jsonResult['questions'] as $q) {
        if (!isset($q['text'])) {
            continue;
        }
        $questions[] = [
            'id' => $q['id'] ?? ('q' . count($questions)),
            'text' => (string) $q['text'],
            'options' => isset($q['options']) && is_array($q['options']) ? array_values($q['options']) : [],
        ];
    }
    if (!empty($questions)) {
        me_sse('questions', ['questions' => $questions]);
        me_sse('done', ['status' => 'questions', 'questions' => $questions]);
        exit;
    }
}

$subject = isset($jsonResult['subject']) ? trim((string) $jsonResult['subject']) : '';
$html = isset($jsonResult['html_body']) ? trim((string) $jsonResult['html_body']) : '';
$text = isset($jsonResult['text_body']) ? trim((string) $jsonResult['text_body']) : '';

if ($subject === '' || $html === '') {
    me_sse('thinking', ['hint' => 'חסרים נתונים, מבקש מחדש…']);
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        'התשובה חסרה subject או html_body. החזר JSON מלא עם subject ו-html_body (מחרוזת HTML תקינה). text_body אופציונלי.'
    ]]];

    $result2 = me_call_gemini($conversationHistory, $api_key, $gemini_models, $maxAttemptsPerModel, $retryableCodes);
    if ($result2['http'] === 200) {
        $data2 = json_decode($result2['body'], true);
        $raw2 = $data2['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed2 = me_extract_json($raw2);
        if (is_array($parsed2)) {
            if (!empty($parsed2['subject'])) {
                $subject = trim((string) $parsed2['subject']);
            }
            if (!empty($parsed2['html_body'])) {
                $html = trim((string) $parsed2['html_body']);
            }
            if (!empty($parsed2['text_body'])) {
                $text = trim((string) $parsed2['text_body']);
            }
        }
    }
}

if ($subject === '' || $html === '') {
    me_sse_done_error('המודל לא החזיר נושא או HTML מלא.');
}

if (function_exists('mb_strlen')) {
    if (mb_strlen($subject, 'UTF-8') > 500) {
        $subject = mb_substr($subject, 0, 497, 'UTF-8') . '…';
    }
    if (mb_strlen($html, 'UTF-8') > 120000) {
        $html = mb_substr($html, 0, 119997, 'UTF-8') . '…';
    }
    if ($text !== '' && mb_strlen($text, 'UTF-8') > 50000) {
        $text = mb_substr($text, 0, 49997, 'UTF-8') . '…';
    }
}

me_sse('done', [
    'status' => 'ok',
    'subject' => $subject,
    'html_body' => $html,
    'text_body' => $text,
]);

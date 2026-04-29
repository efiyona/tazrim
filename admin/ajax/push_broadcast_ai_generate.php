<?php
/**
 * יצירת כותרת + תוכן להתראת Push/פעמון באמצעות Gemini — SSE stream.
 * תומך בשאלות חוזרות מה-AI, חשיבה חכמה, retries, ו-fallback בין מודלים.
 */
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

require_once ROOT_PATH . '/app/functions/user_gemini_key.php';
$pushGeminiUid = (int) ($_SESSION['id'] ?? 0);
$pushGeminiOrderedKeys = tazrim_user_gemini_plain_keys_ordered($conn, $pushGeminiUid);
if ($pushGeminiOrderedKeys === []) {
    tazrim_admin_json_response([
        'status' => 'error',
        'code' => 'gemini_key_missing',
        'message' => 'נדרש מפתח Gemini אישי — הוסיפו מהחשבון שלכם או מפופאפ הבינה.',
    ], 403);
}

// --- SSE setup ---
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function pb_sse(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

function pb_sse_done_error(string $message): void
{
    pb_sse('done', ['status' => 'error', 'message' => $message]);
    exit;
}

// --- System prompt (push notification — short plain text) ---
$systemPrompt = <<<'PROMPT'
אתה קופירייטר לאפליקציה בעברית בשם «התזרים» — ניהול תקציב משפחתי.
עליך לכתוב **התראת Push / פעמון** — טקסט קצר שמופיע בהתראה במכשיר או ברשימת פעמון באפליקציה.

### פורמט תשובה (JSON בלבד — ללא טקסט לפני/אחרי, ללא ```)

אם **יש לך שאלות** לגבי ההנחיות לפני יצירת התוכן (נתון חסר, טון, קהל יעד, וכו׳):
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
כללי שאלות: שאל **רק** כשבאמת חסר מידע קריטי; עד 3 שאלות; 2–4 אפשרויות לכל שאלה. אל תשאל שאלות מיותרות — אם יש מספיק מידע, צור ישר.

אם **אין שאלות** (או שכבר קיבלת תשובות):
החזר JSON עם שני מפתחות בלבד:
{
  "title": "כותרת קצרה (2–5 מילים, עד 40 תווים)",
  "body": "תוכן ההתראה — 1–2 משפטים קצרים, עד 120 תווים"
}

### כללי תוכן
- **כותרת**: 2–5 מילים, ברורה ותמציתית. דוגמאות: «עדכון חשוב», «פיצ׳ר חדש זמין», «תזכורת חודשית».
- **תוכן**: טקסט **פשוט (plain text)**, ללא HTML, ללא markdown. משפט אחד עד שניים. ידידותי, ברור, קצר.
- עברית ברורה; ניסוח **נייטרלי מבחינת מגדר** — העדף לשון רבים או ניסוח כללי.
- זו התראת Push — הטקסט צריך להיות **קצר מאוד**, שנקרא ברגע אחד.

ההנחיות מהמנהל:
PROMPT;

// --- Build conversation history ---
$conversationHistory = [];
$answersFromUser = isset($body['answers']) && is_array($body['answers']) ? $body['answers'] : [];

if ($phase === 'answer' && !empty($answersFromUser)) {
    $prevInstructions = trim((string) ($body['original_instructions'] ?? $instructions));
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt
        . "\n\n---\n" . $prevInstructions
        . "\n\n---\nזכור: החזר רק JSON (questions או title+body)."
    ]]];
    $prevQuestions = isset($body['prev_questions']) ? json_encode($body['prev_questions'], JSON_UNESCAPED_UNICODE) : '[]';
    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $prevQuestions]]];
    $answersText = "תשובות לשאלות:\n";
    foreach ($answersFromUser as $ans) {
        $qid = $ans['id'] ?? '';
        $val = $ans['value'] ?? '';
        $answersText .= "- {$qid}: {$val}\n";
    }
    $answersText .= "\n---\nעכשיו צור את ה-JSON הסופי עם title ו-body לפי ההנחיות + התשובות. אין צורך בעוד שאלות.";
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' => $answersText]]];
} else {
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt
        . "\n\n---\n" . $instructions
        . "\n\n---\nזכור: החזר רק JSON (questions או title+body)."
    ]]];
}

// --- Gemini call with retries & model fallback ---
$gemini_models = ['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash'];
$maxAttemptsPerModel = 2;
$retryableCodes = [429, 500, 502, 503, 504];
$maxJsonRetries = 2;

pb_sse('thinking', ['hint' => 'מנתח את ההנחיות ומנסח הודעה…']);

function pb_call_gemini(array $conversationHistory, array $orderedGeminiKeys, array $models, int $maxAttempts, array $retryable): array
{
    $data = [
        'contents' => $conversationHistory,
        'generationConfig' => [
            'temperature' => 0.45,
            'maxOutputTokens' => 2048,
            'responseMimeType' => 'application/json',
        ],
    ];

    $httpCode = 0;
    $response = '';
    $lastModel = '';

    foreach ($models as $modelName) {
        $lastModel = $modelName;
        $r = tazrim_user_gemini_v1beta_generate_content_with_key_rotation(
            $orderedGeminiKeys,
            $modelName,
            $data,
            90,
            false,
            $maxAttempts,
            $retryable
        );
        $httpCode = $r['http'];
        $response = $r['raw'];

        if (!empty($r['ok'])) {
            return ['http' => 200, 'body' => $response, 'model' => $modelName];
        }
    }

    return ['http' => $httpCode, 'body' => $response, 'model' => $lastModel];
}

function pb_extract_json(string $rawText): ?array
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
        pb_sse('thinking', ['hint' => 'מנסה שוב (ניסיון ' . ($jsonTry + 1) . ')…']);
        $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
            "התשובה הקודמת לא הייתה JSON תקין. אנא החזר **רק** אובייקט JSON תקין בלי שום טקסט נוסף."
        ]]];
    }

    $result = pb_call_gemini($conversationHistory, $pushGeminiOrderedKeys, $gemini_models, $maxAttemptsPerModel, $retryableCodes);

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
            } elseif ($result['http'] === 429 || stripos($msg, 'quota') !== false || stripos($msg, 'billing') !== false) {
                $friendly = 'מכסת Gemini בפרויקט Google נוצלה. מפתחות מאותו פרויקט חולקים מכסה אחת — בדקו חיוב ומגבלות ב-Google AI Studio, או נסו שוב מאוחר יותר.';
            }
        } elseif ($result['http'] === 429) {
            $friendly = 'מכסת Gemini בפרויקט Google נוצלה. מפתחות מאותו פרויקט חולקים מכסה אחת — בדקו חיוב ומגבלות ב-Google AI Studio, או נסו שוב מאוחר יותר.';
        }
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        pb_sse_done_error($friendly);
    }

    $responseData = json_decode($result['body'], true);
    $rawAiReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($rawAiReply) || $rawAiReply === '') {
        $lastError = 'תשובה ריקה מהמודל.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        pb_sse_done_error($lastError);
    }

    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $rawAiReply]]];

    $parsed = pb_extract_json($rawAiReply);
    if (!is_array($parsed)) {
        $lastError = 'לא ניתן לפענח את תשובת ה-AI כ-JSON.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        pb_sse_done_error($lastError);
    }

    $jsonResult = $parsed;
    break;
}

if (!$jsonResult) {
    pb_sse_done_error($lastError ?: 'שגיאה לא צפויה.');
}

// --- Handle questions from AI ---
if (isset($jsonResult['questions']) && is_array($jsonResult['questions']) && count($jsonResult['questions']) > 0) {
    $questions = [];
    foreach ($jsonResult['questions'] as $q) {
        if (!isset($q['text'])) continue;
        $questions[] = [
            'id' => $q['id'] ?? ('q' . count($questions)),
            'text' => (string) $q['text'],
            'options' => isset($q['options']) && is_array($q['options']) ? array_values($q['options']) : [],
        ];
    }
    if (!empty($questions)) {
        pb_sse('questions', ['questions' => $questions]);
        pb_sse('done', ['status' => 'questions', 'questions' => $questions]);
        exit;
    }
}

// --- Extract final title + body ---
$title = isset($jsonResult['title']) ? trim((string) $jsonResult['title']) : '';
$bodyText = isset($jsonResult['body']) ? trim((string) $jsonResult['body']) : '';

if ($title === '' || $bodyText === '') {
    pb_sse('thinking', ['hint' => 'חסרים נתונים, מבקש מחדש…']);

    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        'התשובה חסרה title או body. החזר JSON מלא עם שני המפתחות: title (2–5 מילים) ו-body (1–2 משפטים קצרים, plain text). אל תשאל שאלות, פשוט צור.'
    ]]];

    $result2 = pb_call_gemini($conversationHistory, $pushGeminiOrderedKeys, $gemini_models, $maxAttemptsPerModel, $retryableCodes);
    if ($result2['http'] === 200) {
        $data2 = json_decode($result2['body'], true);
        $raw2 = $data2['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed2 = pb_extract_json($raw2);
        if (is_array($parsed2)) {
            if (!empty($parsed2['title'])) $title = trim((string) $parsed2['title']);
            if (!empty($parsed2['body'])) $bodyText = trim((string) $parsed2['body']);
        }
    }
}

if ($title === '' || $bodyText === '') {
    pb_sse_done_error('המודל לא החזיר כותרת או תוכן מלא גם אחרי ניסיונות חוזרים.');
}

if (function_exists('mb_strlen')) {
    if (mb_strlen($title, 'UTF-8') > 255) {
        $title = mb_substr($title, 0, 252, 'UTF-8') . '…';
    }
    if (mb_strlen($bodyText, 'UTF-8') > 2000) {
        $bodyText = mb_substr($bodyText, 0, 1997, 'UTF-8') . '…';
    }
}

pb_sse('done', [
    'status' => 'ok',
    'title' => $title,
    'body' => $bodyText,
]);

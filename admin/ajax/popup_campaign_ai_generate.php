<?php
/**
 * יצירת כותרת + HTML לקמפיין פופאפ באמצעות Gemini — SSE stream.
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

$ctaHrefRaw = isset($body['cta_href']) ? trim((string) $body['cta_href']) : '';
$ctaHref = null;
$ctaPageLabel = '';
if ($ctaHrefRaw !== '') {
    if (strlen($ctaHrefRaw) > 2000) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'קישור ארוך מדי.'], 400);
    }
    $allowedLinks = tazrim_admin_push_link_options();
    if (isset($allowedLinks[$ctaHrefRaw])) {
        $ctaHref = $ctaHrefRaw;
        $ctaPageLabel = $allowedLinks[$ctaHrefRaw];
    } elseif (substr($ctaHrefRaw, 0, 2) === '//') {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'קישור לא תקין.'], 400);
    } elseif (strpos($ctaHrefRaw, '..') !== false) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'קישור לא תקין.'], 400);
    } elseif (preg_match('#^/[a-zA-Z0-9_./?=&%-]*$#', $ctaHrefRaw)) {
        $ctaHref = $ctaHrefRaw;
        $ctaPageLabel = 'עמוד באתר';
    } elseif (preg_match('#^https?://[^\s<>"\'`\#]+$#i', $ctaHrefRaw)) {
        $ctaHref = $ctaHrefRaw;
        $ctaPageLabel = 'קישור חיצוני';
    } else {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'קישור לא תקין.'], 400);
    }
}

require_once ROOT_PATH . '/app/functions/user_gemini_key.php';
$pcGeminiUid = (int) ($_SESSION['id'] ?? 0);
$pcGeminiOrderedKeys = tazrim_user_gemini_plain_keys_ordered($conn, $pcGeminiUid);
if ($pcGeminiOrderedKeys === []) {
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

function pc_sse(string $event, array $payload): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
}

function pc_sse_done_error(string $message): void
{
    pc_sse('done', ['status' => 'error', 'message' => $message]);
    exit;
}

// --- CTA block ---
$ctaBlock = '';
if ($ctaHref !== null) {
    if (strpos($ctaHref, 'http') === 0) {
        $ctaFullUrl = $ctaHref;
    } else {
        $ctaFullUrl = rtrim(BASE_URL, '/') . '/' . ltrim($ctaHref, '/');
    }
    $hEsc = htmlspecialchars($ctaFullUrl, ENT_QUOTES, 'UTF-8');
    $lEsc = htmlspecialchars($ctaPageLabel, ENT_QUOTES, 'UTF-8');
    $ctaBlock = "\n\n---\nקישור לכפתור (חובה לשלב ב-body_html):\n"
        . "- הכתובת המדויקת ל־href חייבת להיות **בדיוק**: {$hEsc}\n"
        . "  חשוב: אל תשנה, תקצר, או תמציא כתובת — העתק מילה במילה.\n"
        . "- הקשר ליעד: {$lEsc}\n"
        . "יש לכלול **בדיוק קישור אחד** ככפתור CTA בסוף הכרטיס: תג <a> עם ה-href שלעיל בלבד.\n"
        . "סגנון הכפתור (inline style חובה):\n"
        . "style=\"display:inline-flex;align-items:center;gap:0.4rem;justify-content:center;margin-top:1rem;padding:12px 28px;border-radius:999px;background:linear-gradient(135deg,#29b669 0%,#22a55b 100%);color:#ffffff;font-weight:700;font-size:0.95rem;text-decoration:none;font-family:inherit;box-shadow:0 4px 14px rgba(41,182,105,0.3);transition:transform 0.15s;\"\n"
        . "אפשר להוסיף אייקון Font Awesome קטן (<i class=\"fa-solid fa-arrow-left\" style=\"font-size:0.85rem;\"></i>) **אחרי** טקסט הכפתור (כי RTL — החץ שמאלה מצביע קדימה).\n"
        . "טקסט הכפתור: 2–4 מילים בלבד, פעולה ברורה בהתאם להקשר.";
}

// --- System prompt ---
$systemPrompt = <<<'PROMPT'
אתה קופירייטר ומעצב UI לאפליקציה בעברית בשם «התזרים» — ניהול תקציב משפחתי.

### פורמט תשובה (JSON בלבד — ללא טקסט לפני/אחרי, ללא ```)

אם **יש לך שאלות** לגבי ההנחיות לפני יצירת התוכן (נתון חסר, העדפת סגנון, קהל יעד ספציפי, וכו׳):
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
כללי שאלות: שאל **רק** כשבאמת חסר מידע קריטי לעיצוב; עד 3 שאלות; 2–4 אפשרויות לכל שאלה; אפשר לענות גם בחופשי. אל תשאל שאלות מיותרות — אם יש מספיק מידע, צור ישר.

אם **אין שאלות** (או שכבר קיבלת תשובות):
החזר JSON עם שני מפתחות בלבד:
{
  "title": "כותרת קצרצרה (2–4 מילים, עד 30 תווים)",
  "body_html": "<div dir=\"rtl\" ...>...</div>"
}

### כללי כותרת
- **2–4 מילים בלבד** (עד 30 תווים מקסימום). דוגמאות: «פיצ׳ר חדש זמין», «עדכון חשוב», «שדרוג המערכת». ככל שקצר יותר — עדיף.

### מבנה HTML חובה (body_html)
- עטיפה חיצונית אחת: <div dir="rtl" style="direction:rtl;text-align:right;font-family:'Assistant',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a202c;line-height:1.7;font-size:0.95rem;"> … </div>
- בתוך העטיפה: «כרטיס» ויזואלי:
  • רקע: background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 50%, #eff6ff 100%);
  • border-radius: 1rem; padding: 1.25rem 1.35rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(15,23,42,0.06);

### עיצוב ויזואלי (חשוב מאוד!)
- **כותרת פנימית בולטת** בתחילת הכרטיס: div עם font-size:1.1rem; font-weight:800; color:#1e293b; margin-bottom:0.75rem; — עם אייקון Font Awesome (<i class="fa-solid fa-XYZ" style="color:#29b669;margin-left:0.4rem;font-size:1.1rem;"></i>) לפני הטקסט. בחר אייקון מתאים לנושא.
- **פסקאות**: צבע #475569; font-size:0.9rem; margin-bottom:0.6rem; — תוכן **קצר וברור**, 1–2 משפטים בכל פסקה.
- **רשימה (ul/li) אם מתאים**: עם אייקוני ✓ בצבע ירוק כנקודות, list-style:none, כל li עם padding:0.35rem 0; border-bottom:1px solid #f1f5f9;
- **הפרדות ויזואליות**: קו מפריד עדין (<hr style="border:none;border-top:1px solid #e2e8f0;margin:0.75rem 0;">) בין סקשנים אם יש יותר מרעיון אחד.
- **הדגשות**: השתמש ב-<strong style="color:#1e293b;"> ו-<span style="color:#29b669;font-weight:700;"> לצבע ירוק על מילות מפתח.
- תוכן **קצר וברור** — עד 2 פסקאות קצרות או רשימת ul עם 2–4 פריטים. ללא חפירות.
- בלי תגי html/head/body; בלי סקריפטים; בלי iframe; בלי javascript: בקישורים.

### פעולות מובנות (בלי <script>)
אם ההנחיות דורשות שמירת יתרת בנק מתוך הפופאפ — השתמש ב-**data-tazrim-popup-action="save_bank_balance"** על כפתור type="button" (או על form), ובשדה input עם **name="bank_balance"**. אסור להוסיף <script> או fetch — המערכת מחברת את זה לשרת. דוגמה לכפתור: data-tazrim-popup-action="save_bank_balance"

### כללי תוכן
- עברית ברורה וידידותית; ניסוח **נייטרלי מבחינת מגדר** — העדף לשון רבים או ניסוח כללי.
- עקביות עיצובית — סגנון עדין ומקצועי, תואם מערכת פיננסית אמינה.
PROMPT;

// --- Build conversation history ---
$conversationHistory = [];
$answersFromUser = isset($body['answers']) && is_array($body['answers']) ? $body['answers'] : [];

if ($phase === 'answer' && !empty($answersFromUser)) {
    $prevInstructions = trim((string) ($body['original_instructions'] ?? $instructions));
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt . $ctaBlock
        . "\n\n---\nההנחיות מהמנהל:\n" . $prevInstructions
        . "\n\n---\nזכור: החזר רק JSON (questions או title+body_html)."
    ]]];
    $prevQuestions = isset($body['prev_questions']) ? json_encode($body['prev_questions'], JSON_UNESCAPED_UNICODE) : '[]';
    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $prevQuestions]]];
    $answersText = "תשובות לשאלות:\n";
    foreach ($answersFromUser as $ans) {
        $qid = $ans['id'] ?? '';
        $val = $ans['value'] ?? '';
        $answersText .= "- {$qid}: {$val}\n";
    }
    $answersText .= "\n---\nעכשיו צור את ה-JSON הסופי עם title ו-body_html לפי ההנחיות + התשובות. אין צורך בעוד שאלות.";
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' => $answersText]]];
} else {
    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        $systemPrompt . $ctaBlock
        . "\n\n---\nההנחיות מהמנהל:\n" . $instructions
        . "\n\n---\nזכור: החזר רק JSON (questions או title+body_html)."
    ]]];
}

// --- Gemini call with retries & model fallback ---
// lite קודם — לרוב מכסה נפרדת מ-flash; 2.0 אחרון (לעיתים מכסה אגרסיבית יותר בפרויקטים חינמיים).
$gemini_models = ['gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash'];
$maxAttemptsPerModel = 2;
$retryableCodes = [429, 500, 502, 503, 504];
$maxJsonRetries = 2;

pc_sse('thinking', ['hint' => 'מנתח את ההנחיות ומעצב תוכן…']);

function pc_call_gemini(array $conversationHistory, array $orderedGeminiKeys, array $models, int $maxAttempts, array $retryable): array
{
    $data = [
        'contents' => $conversationHistory,
        'generationConfig' => [
            'temperature' => 0.5,
            'maxOutputTokens' => 8192,
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
            120,
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

function pc_extract_json(string $rawText): ?array
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
        pc_sse('thinking', ['hint' => 'מנסה שוב (ניסיון ' . ($jsonTry + 1) . ')…']);
        $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
            "התשובה הקודמת לא הייתה JSON תקין. אנא החזר **רק** אובייקט JSON תקין בלי שום טקסט נוסף."
        ]]];
    }

    $result = pc_call_gemini($conversationHistory, $pcGeminiOrderedKeys, $gemini_models, $maxAttemptsPerModel, $retryableCodes);

    if ($result['http'] !== 200) {
        $friendly = 'שגיאת תקשורת עם שירות הבינה';
        $decoded = json_decode($result['body'], true);
        $msg = '';
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
        pc_sse_done_error($friendly);
    }

    $responseData = json_decode($result['body'], true);
    $rawAiReply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($rawAiReply) || $rawAiReply === '') {
        $lastError = 'תשובה ריקה מהמודל.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        pc_sse_done_error($lastError);
    }

    $conversationHistory[] = ['role' => 'model', 'parts' => [['text' => $rawAiReply]]];

    $parsed = pc_extract_json($rawAiReply);
    if (!is_array($parsed)) {
        $lastError = 'לא ניתן לפענח את תשובת ה-AI כ-JSON.';
        if ($jsonTry < $maxJsonRetries) {
            continue;
        }
        pc_sse_done_error($lastError);
    }

    $jsonResult = $parsed;
    break;
}

if (!$jsonResult) {
    pc_sse_done_error($lastError ?: 'שגיאה לא צפויה.');
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
        pc_sse('questions', ['questions' => $questions]);
        pc_sse('done', ['status' => 'questions', 'questions' => $questions]);
        exit;
    }
}

// --- Extract final title + body_html ---
$title = isset($jsonResult['title']) ? trim((string) $jsonResult['title']) : '';
$bodyHtml = isset($jsonResult['body_html']) ? (string) $jsonResult['body_html'] : '';

if ($title === '' || trim($bodyHtml) === '') {
    pc_sse('thinking', ['hint' => 'חסרים נתונים, מבקש מחדש…']);

    $conversationHistory[] = ['role' => 'user', 'parts' => [['text' =>
        'התשובה חסרה title או body_html. החזר JSON מלא עם שני המפתחות: title (2–4 מילים) ו-body_html (HTML מעוצב). אל תשאל שאלות, פשוט צור.'
    ]]];

    $result2 = pc_call_gemini($conversationHistory, $pcGeminiOrderedKeys, $gemini_models, $maxAttemptsPerModel, $retryableCodes);
    if ($result2['http'] === 200) {
        $data2 = json_decode($result2['body'], true);
        $raw2 = $data2['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed2 = pc_extract_json($raw2);
        if (is_array($parsed2)) {
            if (!empty($parsed2['title'])) $title = trim((string) $parsed2['title']);
            if (!empty($parsed2['body_html'])) $bodyHtml = (string) $parsed2['body_html'];
        }
    }
}

if ($title === '' || trim($bodyHtml) === '') {
    pc_sse_done_error('המודל לא החזיר כותרת או תוכן מלא גם אחרי ניסיונות חוזרים.');
}

if (function_exists('mb_strlen')) {
    if (mb_strlen($title, 'UTF-8') > 255) {
        $title = mb_substr($title, 0, 252, 'UTF-8') . '…';
    }
    if (mb_strlen($bodyHtml, 'UTF-8') > 120000) {
        $bodyHtml = mb_substr($bodyHtml, 0, 119000, 'UTF-8') . "\n<!-- trimmed -->";
    }
} else {
    if (strlen($title) > 255) {
        $title = substr($title, 0, 252) . '...';
    }
    if (strlen($bodyHtml) > 120000) {
        $bodyHtml = substr($bodyHtml, 0, 119000) . "\n<!-- trimmed -->";
    }
}

pc_sse('done', [
    'status' => 'ok',
    'title' => $title,
    'body_html' => $bodyHtml,
]);

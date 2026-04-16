<?php
/**
 * יצירת כותרת + HTML לקמפיין פופאפ באמצעות Gemini — program_admin בלבד.
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

$instructions = trim((string) ($body['instructions'] ?? ''));
if ($instructions === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'נא למלא הוראות לבינה.'], 400);
}
if (function_exists('mb_strlen') && mb_strlen($instructions, 'UTF-8') > 6000) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'הוראות ארוכות מדי.'], 400);
} elseif (!function_exists('mb_strlen') && strlen($instructions) > 12000) {
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

if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'מפתח AI לא מוגדר בשרת (GEMINI_API_KEY).'], 503);
}

$systemPrompt = <<<'PROMPT'
אתה קופירייטר ומעצב UI לאפליקציה בעברית בשם «התזרים» — ניהול תקציב משפחתי.
עליך להחזיר **רק** אובייקט JSON תקין (בלי טקסט לפני או אחרי, בלי ```), בדיוק עם שני המפתחות הבאים:
- "title": מחרוזת קצרצרה — כותרת לפופאפ, **2–4 מילים בלבד** (עד 30 תווים מקסימום). דוגמאות: «פיצ׳ר חדש זמין», «עדכון חשוב», «שדרוג המערכת». ככל שקצר יותר — עדיף.
- "body_html": מחרוזת HTML אחת להצגה בתוך מודאל באפליקציה — **RTL**, עיצוב ויזואלי עשיר ומודרני.

מבנה HTML חובה (עטיפה חיצונית):
- עטיפה חיצונית אחת: <div dir="rtl" style="direction:rtl;text-align:right;font-family:'Assistant',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a202c;line-height:1.7;font-size:0.95rem;"> … </div>
- בתוך העטיפה: «כרטיס» ויזואלי:
  • רקע: background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 50%, #eff6ff 100%); (גרדיאנט עדין בגוון ירוק-כחול)
  • border-radius: 1rem; padding: 1.25rem 1.35rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(15,23,42,0.06);

עיצוב ויזואלי (חשוב מאוד!):
- **כותרת פנימית בולטת** בתחילת הכרטיס: div עם font-size:1.1rem; font-weight:800; color:#1e293b; margin-bottom:0.75rem; — עם אייקון Font Awesome (<i class="fa-solid fa-XYZ" style="color:#29b669;margin-left:0.4rem;font-size:1.1rem;"></i>) לפני הטקסט. בחר אייקון מתאים לנושא (fa-circle-check, fa-star, fa-bolt, fa-gift, fa-rocket, fa-shield-check, fa-sparkles, fa-chart-line וכו׳).
- **פסקאות**: צבע #475569; font-size:0.9rem; margin-bottom:0.6rem; — תוכן **קצר וברור**, 1–2 משפטים בכל פסקה.
- **רשימה (ul/li) אם מתאים**: עם אייקוני ✓ בצבע ירוק כנקודות, list-style:none, כל li עם padding:0.35rem 0; border-bottom:1px solid #f1f5f9;
- **הפרדות ויזואליות**: קו מפריד עדין (<hr style="border:none;border-top:1px solid #e2e8f0;margin:0.75rem 0;">) בין סקשנים אם יש יותר מרעיון אחד.
- **הדגשות**: השתמש ב-<strong style="color:#1e293b;"> ו-<span style="color:#29b669;font-weight:700;"> לצבע ירוק על מילות מפתח.
- תוכן **קצר וברור** — עד 2 פסקאות קצרות או רשימת ul עם 2–4 פריטים. ללא חפירות.
- בלי תגי html/head/body; בלי סקריפטים; בלי iframe; בלי javascript: בקישורים.

כללים לתוכן:
- עברית ברורה וידידותית; ניסוח **נייטרלי מבחינת מגדר** — הימנע מפנייה בלשון זכר או נקבה; העדף לשון רבים («אפשר ל…», «כאן מוצג…») או ניסוח כללי.
- עקביות עיצובית — סגנון עדין ומקצועי, תואם מערכת פיננסית אמינה.

ההנחיות מהמנהל (מה לפרסם ומה הדגשים):
PROMPT;

$userBlock = $instructions;

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

$fullPrompt = $systemPrompt . $ctaBlock . "\n\n---\n" . $userBlock . "\n\n---\nזכור: החזר רק JSON עם המפתחות title ו-body_html.";

$data = [
    'contents' => [['parts' => [['text' => $fullPrompt]]]],
    'generationConfig' => [
        'temperature' => 0.45,
        'maxOutputTokens' => 4096,
    ],
];

$api_key = GEMINI_API_KEY;
$gemini_models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];

$http_code = 0;
$response = '';
$curl_err = '';
$max_attempts_per_model = 2;
$retryable = [429, 500, 503];

foreach ($gemini_models as $model_name) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model_name . ':generateContent?key=' . $api_key;
    for ($attempt = 0; $attempt < $max_attempts_per_model; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 90,
        ]);
        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200) {
            break 2;
        }
        if (in_array($http_code, $retryable, true)) {
            usleep(500000);
            continue;
        }
        break;
    }
}

if ($http_code !== 200) {
    $friendly = 'שגיאת תקשורת עם שירות הבינה';
    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['error']['message'])) {
        $msg = (string) $decoded['error']['message'];
        $status = $decoded['error']['status'] ?? '';
        if (stripos($msg, 'high demand') !== false || $status === 'UNAVAILABLE') {
            $friendly = 'שירות הבינה עמוס כרגע. נסו שוב בעוד דקה–שתיים.';
        } elseif ($http_code === 404 || $status === 'NOT_FOUND') {
            $friendly = 'המודל לא זמין ב-API. נסו שוב מאוחר יותר.';
        }
    }
    tazrim_admin_json_response(['status' => 'error', 'message' => $friendly], 502);
}

$responseData = json_decode($response, true);
$raw_ai_reply = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
if (!is_string($raw_ai_reply) || $raw_ai_reply === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'תשובה ריקה מהמודל.'], 502);
}

$jsonStr = $raw_ai_reply;
if (preg_match('/^```(?:json)?\s*\R(.*?)\R```/s', trim($raw_ai_reply), $m)) {
    $jsonStr = trim($m[1]);
} elseif (preg_match('/\{[\s\S]*\}/s', $raw_ai_reply, $m)) {
    $jsonStr = $m[0];
}

$parsed = json_decode($jsonStr, true);
if (!is_array($parsed)) {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'לא ניתן לפענח את תשובת ה-AI כ-JSON.'], 502);
}

$title = isset($parsed['title']) ? trim((string) $parsed['title']) : '';
$bodyHtml = isset($parsed['body_html']) ? (string) $parsed['body_html'] : '';

if ($title === '' || trim($bodyHtml) === '') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'המודל לא החזיר כותרת או תוכן מלא.'], 502);
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

tazrim_admin_json_response([
    'status' => 'ok',
    'title' => $title,
    'body_html' => $bodyHtml,
]);

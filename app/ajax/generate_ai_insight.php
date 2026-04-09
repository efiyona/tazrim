<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once('../../secrets.php'); 

// הגדרות בסיס
$home_id = $_SESSION['home_id'];
$user_id = $_SESSION['id']; 
$api_key = GEMINI_API_KEY;
// מודלים תקפים ל-v1beta generateContent עם fallback במקרה עומס/זמינות
$gemini_models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];

$selected_month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$selected_year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// 1. בדיקת Cache - האם כבר יצרנו תובנה ב-24 שעות האחרונות?
// יצירת מפתח ייחודי לחודש והשנה הספציפיים
$insight_key = "burn_rate_" . $selected_month . "_" . $selected_year;

// 1. בדיקת Cache - האם יש תובנה לאותו חודש ב-24 שעות האחרונות?
$cache_query = "SELECT insight_text FROM ai_insights_cache 
                WHERE home_id = $home_id 
                AND insight_type = '$insight_key' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                ORDER BY id DESC LIMIT 1";

$cache_result = mysqli_query($conn, $cache_query);

if (mysqli_num_rows($cache_result) > 0) {
    // מצאנו תובנה עדכנית בזיכרון! מחזירים אותה בלי לפנות לגוגל
    $row = mysqli_fetch_assoc($cache_result);
    echo "<strong>תובנה יומית:</strong><br>" . nl2br($row['insight_text']);
    exit();
}

// 2. איסוף נתונים ל-Prompt (רק קטגוריות עם תקציב מוגדר)
// חישוב ימים בחודש שנבחר
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

// אם אנחנו מסתכלים על חודש בעבר, אנחנו ביום האחרון של החודש מבחינת הקצב
if ($selected_month == date('m') && $selected_year == date('Y')) {
    $current_day = date('j');
} else {
    $current_day = $days_in_month;
}

$stats_query = "
    SELECT c.name, c.budget_limit, 
           COALESCE(SUM(t.amount), 0) as spent
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category 
         AND t.type = 'expense' 
         AND MONTH(t.transaction_date) = $selected_month
         AND YEAR(t.transaction_date) = $selected_year
    WHERE c.home_id = $home_id AND c.budget_limit > 0
    GROUP BY c.id
";
$stats_result = mysqli_query($conn, $stats_query);

// בדיקה כמה פעולות הוצאה באמת קיימות (מעודכן לחודש הנבחר)
$count_query = "SELECT COUNT(id) as total_actions FROM transactions 
                WHERE home_id = $home_id 
                AND type = 'expense' 
                AND MONTH(transaction_date) = $selected_month
                AND YEAR(transaction_date) = $selected_year";
$stats_result = mysqli_query($conn, $stats_query);

$budget_data_text = "היום אנחנו ביום $current_day מתוך $days_in_month בחודש.\nנתוני התקציב:\n";
while($row = mysqli_fetch_assoc($stats_result)) {
    $percent = round(($row['spent'] / $row['budget_limit']) * 100);
    $budget_data_text .= "- " . $row['name'] . ": נוצל " . $percent . "% (" . $row['spent'] . "/" . $row['budget_limit'] . ")\n";
}

// בדיקה כמה פעולות הוצאה באמת קיימות החודש לבית הזה
$count_query = "SELECT COUNT(id) as total_actions FROM transactions 
                WHERE home_id = $home_id 
                AND type = 'expense' 
                AND MONTH(transaction_date) = MONTH(CURRENT_DATE())
                AND YEAR(transaction_date) = YEAR(CURRENT_DATE())";
$count_result = mysqli_query($conn, $count_query);
$count_data = mysqli_fetch_assoc($count_result);

if ($count_data['total_actions'] < 3) {
    echo "<strong>היועץ החכם ממתין לכם:</strong><br>כדי לתת ניתוח מדויק, אני צריך לפחות 3 פעולות הוצאה רשומות החודש. כרגע חסרים לי נתונים.";
    exit();
}

// 3. בניית ה-Prompt
$prompt = "אתה יועץ כלכלי חכם. נתח את קצב הוצאת התקציב (Burn Rate) לפי הנתונים הבאים בלבד.
חשוב מאוד: 
1. התבסס אך ורק על המספרים המופיעים תחת 'נוצל'. 
2. אם קטגוריה מופיעה עם 0% ניצול, התייחס אליה כאל קטגוריה שטרם בוצעה בה פעילות. אל תמציא הוצאות שאינן קיימות.
3. אם אין מספיק נתונים אמיתיים (רוב הקטגוריות על 0), כתוב שאתה ממתין לפעולות הראשונות החודש כדי לתת ניתוח מדויק.
4. אל תניח הנחות ואל תמציא מספרים.
5. דבר בגוף שני רבים ('הוצאתם', 'כדאי לכם'). פלט רק טקסט נקי בעברית.

הנתונים:
$budget_data_text

המשימה:
1. כתוב למשתמש פסקה אחת קצרה (עד 3 משפטים) בעברית.
2. ציין לטובה קטגוריה שבה הם מתנהלים לאט וטוב מהקצב המצופה.
3. הזהר בעדינות על קטגוריה שבה הקצב מהיר מדי, ותן טיפ קצרצר.
4. דבר בגוף שני רבים ('הוצאתם', 'כדאי לכם'). פלט רק טקסט נקי, בלי כותרות, בלי כוכביות ובלי מילים באנגלית.";

// 4. שליחת בקשה ל-Gemini API (דרך cURL) עם fallback בין מודלים
$data = [
    "contents" => [
        ["parts" => [ ["text" => $prompt] ]]
    ]
];

$http_code = 0;
$response = '';
$curl_error = '';
$retryable = [429, 500, 503];
$max_attempts_per_model = 2;
$used_model = '';

foreach ($gemini_models as $model_name) {
    $used_model = $model_name;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
    for ($attempt = 0; $attempt < $max_attempts_per_model; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // מעקף SSL ל-Localhost
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
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

// --- תיעוד הלוג (מתבצע תמיד למעקב קריאות) ---
$status_text = ($http_code == 200) ? 'Success (Model: ' . $used_model . ')' : 'Failed (Code: ' . $http_code . ', Model: ' . $used_model . ')';
if ($curl_error) $status_text = 'cURL Error: ' . $curl_error;

$action_desc = mysqli_real_escape_string($conn, "AI Burn Rate Insight - $status_text");
$insert_log = "INSERT INTO ai_api_logs (home_id, user_id, action_type) 
               VALUES ($home_id, $user_id, '$action_desc')";
mysqli_query($conn, $insert_log);
// ------------------------------------------

$responseData = json_decode($response, true);

// 5. חילוץ התשובה ושמירה ב-Cache
if ($http_code == 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $insight = mysqli_real_escape_string($conn, trim($responseData['candidates'][0]['content']['parts'][0]['text']));
    
    // שמירה ב-Cache ל-24 שעות עם המפתח הייחודי לחודש
    $insert_cache = "INSERT INTO ai_insights_cache (home_id, insight_type, insight_text) 
                     VALUES ($home_id, '$insight_key', '$insight')";
    mysqli_query($conn, $insert_cache);

    echo "<strong>תובנה יומית מ-Gemini:</strong><br>" . nl2br($insight);
} else {
    // הצגת הודעת שגיאה מפורטת במידה ונכשל
    $friendly_error = '';
    if (isset($responseData['error']['message'])) {
        $status = $responseData['error']['status'] ?? '';
        $raw_message = $responseData['error']['message'];
        if (stripos($raw_message, 'high demand') !== false || $status === 'UNAVAILABLE') {
            $friendly_error = "שירות ה-AI של גוגל עמוס כרגע. נסו שוב בעוד דקה-שתיים.";
        } elseif ($http_code === 404 || $status === 'NOT_FOUND') {
            $friendly_error = "המודל לא זמין כרגע ב-API. עודכן fallback אוטומטי, ואם זה ממשיך יש לבדוק את רשימת המודלים הזמינים למפתח.";
        }
    }

    echo "<strong>שגיאת חיבור ליועץ החכם:</strong><br>";
    if ($curl_error) {
        echo "שגיאת רשת: " . $curl_error;
    } else {
        echo "קוד שגיאה מהשרת: " . $http_code . "<br>";
        if ($used_model !== '') {
            echo "מודל אחרון שנוסה: " . htmlspecialchars($used_model) . "<br>";
        }
        if ($friendly_error !== '') {
            echo "פירוט: " . $friendly_error;
        } elseif (isset($responseData['error']['message'])) {
            echo "פירוט: " . $responseData['error']['message'];
        }
    }
}
?>
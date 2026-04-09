<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Origin, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require('../../../../path.php');
    include(ROOT_PATH . '/app/database/db.php');
    require_once(ROOT_PATH . '/secrets.php');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    $selected_month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');
    $selected_year = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');

    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => 'לא התקבל טוקן זיהוי.']);
        exit();
    }

    $user = selectOne('users', ['api_token' => $token]);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'טוקן פג תוקף או לא חוקי.']);
        exit();
    }
    $home_id = (int) ($user['home_id'] ?? 0);
    $user_id = (int) ($user['id'] ?? 0);
    if ($home_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'לא נמצא בית למשתמש.']);
        exit();
    }

    $insight_key = "burn_rate_" . $selected_month . "_" . $selected_year;
    $cache_query = "SELECT insight_text FROM ai_insights_cache
                    WHERE home_id = $home_id
                    AND insight_type = '$insight_key'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY id DESC LIMIT 1";
    $cache_result = mysqli_query($conn, $cache_query);
    if (mysqli_num_rows($cache_result) > 0) {
        $row = mysqli_fetch_assoc($cache_result);
        echo json_encode(['status' => 'success', 'data' => ['insight' => trim($row['insight_text'])]]);
        exit();
    }

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
    $current_day = ($selected_month == (int) date('m') && $selected_year == (int) date('Y')) ? (int) date('j') : $days_in_month;

    $stats_query = "SELECT c.name, c.budget_limit,
                           COALESCE(SUM(t.amount), 0) as spent
                    FROM categories c
                    LEFT JOIN transactions t ON c.id = t.category
                         AND t.type = 'expense'
                         AND MONTH(t.transaction_date) = $selected_month
                         AND YEAR(t.transaction_date) = $selected_year
                    WHERE c.home_id = $home_id AND c.budget_limit > 0
                    GROUP BY c.id";
    $stats_result = mysqli_query($conn, $stats_query);

    $count_query = "SELECT COUNT(id) as total_actions FROM transactions
                    WHERE home_id = $home_id
                    AND type = 'expense'
                    AND MONTH(transaction_date) = $selected_month
                    AND YEAR(transaction_date) = $selected_year";
    $count_result = mysqli_query($conn, $count_query);
    $count_data = mysqli_fetch_assoc($count_result);
    if ((int) ($count_data['total_actions'] ?? 0) < 3) {
        $msg = "היועץ החכם ממתין לכם: כדי לתת ניתוח מדויק, צריך לפחות 3 פעולות הוצאה בחודש הנבחר.";
        echo json_encode(['status' => 'success', 'data' => ['insight' => $msg]]);
        exit();
    }

    $budget_data_text = "היום אתם ביום $current_day מתוך $days_in_month בחודש.\nנתוני התקציב:\n";
    while ($row = mysqli_fetch_assoc($stats_result)) {
        $budget = (float) ($row['budget_limit'] ?? 0);
        $spent = (float) ($row['spent'] ?? 0);
        $percent = $budget > 0 ? round(($spent / $budget) * 100) : 0;
        $budget_data_text .= "- " . $row['name'] . ": נוצל " . $percent . "% (" . $spent . "/" . $budget . ")\n";
    }

    $prompt = "אתה יועץ כלכלי חכם. נתח את קצב הוצאת התקציב (Burn Rate) לפי הנתונים הבאים בלבד.\n" .
              "התבסס רק על המספרים שניתנו, בלי להמציא נתונים. כתוב עד 3 משפטים בעברית בגוף שני רבים.\n\n" .
              "הנתונים:\n" . $budget_data_text;

    $api_key = GEMINI_API_KEY;
    $models = ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash'];
    $http_code = 0;
    $response = '';
    $used_model = '';
    foreach ($models as $model_name) {
        $used_model = $model_name;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
        $ch = curl_init($url);
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 200) {
            break;
        }
    }

    $responseData = json_decode($response, true);
    if ($http_code === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $insight = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
        $insight_esc = mysqli_real_escape_string($conn, $insight);
        mysqli_query($conn, "INSERT INTO ai_insights_cache (home_id, insight_type, insight_text) VALUES ($home_id, '$insight_key', '$insight_esc')");

        $action_desc = mysqli_real_escape_string($conn, "AI Burn Rate Insight - Success (Model: $used_model)");
        mysqli_query($conn, "INSERT INTO ai_api_logs (home_id, user_id, action_type) VALUES ($home_id, $user_id, '$action_desc')");

        echo json_encode(['status' => 'success', 'data' => ['insight' => $insight]]);
        exit();
    }

    $fallback = "לא ניתן היה להפיק תובנה כרגע. נסו שוב בעוד כמה רגעים.";
    echo json_encode(['status' => 'success', 'data' => ['insight' => $fallback]]);
    exit();
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'שגיאת מערכת בשרת: ' . $e->getMessage(),
    ]);
    exit();
}

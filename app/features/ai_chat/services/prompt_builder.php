<?php
declare(strict_types=1);

if (!function_exists('ai_chat_load_product_knowledge')) {
    function ai_chat_load_product_knowledge(): string
    {
        $path = dirname(__DIR__) . '/docs/product_knowledge.md';
        if (!is_file($path)) {
            return '';
        }
        $content = file_get_contents($path);
        return is_string($content) ? $content : '';
    }
}

/** שם פרטי לצורך פנייה בהוראות המערכת — מנוקה ומקוצר */
if (!function_exists('ai_chat_format_user_first_name')) {
    function ai_chat_format_user_first_name(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/[^\p{L}\s\-\']+/u', '', $s);
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, 40);
        }

        return substr($s, 0, 40);
    }
}

/** נושא שיחה: financial | system */
if (!function_exists('ai_chat_scope_topic')) {
    function ai_chat_scope_topic(array $scope): string
    {
        $t = (string) ($scope['topic'] ?? 'system');
        return $t === 'financial' ? 'financial' : 'system';
    }
}

if (!function_exists('ai_chat_build_system_instruction')) {
    function ai_chat_build_system_instruction(array $scope, string $userFirstName = ''): string
    {
        require_once __DIR__ . '/allowed_chat_pages.php';
        $topic = ai_chat_scope_topic($scope);
        $pageFormat = "כשאתה מפנה למסך או לעמוד באתר, השתמש בפורמט המדויק:\n"
            . "[[PAGE:/נתיב/מלא|טקסט קצר לכפתור]]\n"
            . "דוגמה: [[PAGE:/pages/reports.php|מעבר לדוחות]]\n"
            . "הנתיב חייב להתחיל ב־/. **אסור** להמציא דפים; מותרים **אך ורק** הנתיבים הבאים:\n"
            . ai_chat_format_allowed_pages_for_prompt() . "\n\n";

        $personal = '';
        if ($userFirstName !== '') {
            $personal = "פנייה למשתמש: השם הפרטי הוא «{$userFirstName}». "
                . "מדי פעם, כשזה טבעי (פתיחה קצרה, עידוד או סיכום) — אפשר לפנות בשם הפרטי. "
                . "לא בכל משפט ולא באופן חוזר מדי.\n\n";
        }

        $assistantName = defined('AI_CHAT_ASSISTANT_NAME') ? AI_CHAT_ASSISTANT_NAME : 'התזרים החכם';
        $common = "שמך הוא {$assistantName}. תפקידך לסייע למשתמשים במערכת \"התזרים\" בלבד.\n"
            . $personal
            . "מותר לענות רק על שימוש במערכת, נתונים פיננסיים של הבית (כשנשלחו), או הכוונה פיננסית זהירה לפי נתונים.\n"
            . "אסור לענות על נושאים לא קשורים. אם השאלה מחוץ לתחום — סרב בנימוס במשפט אחד.\n"
            . "אל תמציא נתונים שלא נשלחו אליך. אם חסר מידע, אמור מה חסר.\n"
            . "תשובות בעברית, קצרות ופרקטיות.\n"
            . "להדגשת סכומים או מונחים (למשל סכום בשקלים) אפשר להשתמש ב־** כמו ב־Markdown: **טקסט להדגשה** — הממשק יציג זאת מודגש.\n\n";

        if ($topic === 'financial') {
            return $common
                . "### מצב שיחה: שאלות פיננסיות\n"
                . "התמקד בניתוח ובהסבר על בסיס **בלוק הנתונים הפיננסיים** שמופיע למטה (חודש נוכחי + חודש קודם, לפי הבית).\n"
                . "אין לך כאן את מדריך המסכים המלא — הפנה לעמוד רלוונטי רק אם המשתמש שואל \"איפה במערכת\" או דורש ניווט.\n\n"
                . $pageFormat
                . "הבהרה: זה לא ייעוץ פיננסי מחייב.";
        }

        $knowledge = ai_chat_load_product_knowledge();
        return $common
            . "### מצב שיחה: שאלות על המערכת (איך עושים, איפה נמצא)\n"
            . "התמקד בהסברי ממשק, מסכים ונתיבים. אל תניח נתוני הוצאה/הכנסה שלא סופקו.\n\n"
            . $pageFormat
            . "### מדריך מערכת (מקור עזרה):\n{$knowledge}\n\n"
            . "הבהרה: זה לא ייעוץ פיננסי מחייב.";
    }
}

/** הוראות לשלב ניתוב — JSON בלבד, בלי קריאה לנתונים */
if (!function_exists('ai_chat_build_router_system_instruction')) {
    function ai_chat_build_router_system_instruction(array $scope): string
    {
        $topic = ai_chat_scope_topic($scope);
        $topicLabel = $topic === 'financial' ? 'פיננסי/תזרים (נתונים יישלחו בשלב הבא)' : 'מערכת/ממשק';

        return "אתה מסווג בקשות בלבד למערכת «התזרים». נושא השיחה: {$topicLabel}.\n\n"
            . "החזר **אובייקט JSON בלבד** — בלי markdown, בלי טקסט לפני או אחרי, בלי ```.\n"
            . "סכמה:\n"
            . '{"needs_deep":boolean,"needs_full_transactions":boolean,"reason_code":"simple|compare_periods|trend|budget_scenario|scenario|multi_part|ambiguous|other","user_hint":"עברית עד 48 תווים"}' . "\n\n"
            . "כללים:\n"
            . "- needs_deep=true כשהשאלה דורשת ניתוח מרובה שלבים, השוואה בין תקופות/חודשים, מגמות, תרחישי תקציב, פירוק מורכב, או כמה חלקים בשאלה אחת.\n"
            . "- needs_deep=false לשאלה ישירה: איפה מסך, איך פעולה, ערך בודד, הסבר קצר, או שאלה פשוטה על נתון אחד.\n"
            . "- needs_full_transactions=true רק אם צריך לעבור על פעולות ברמת שורה (לדוגמה: איתור פעולה חריגה/כפולה, התאמה בין פעולות, ביקורת מפורטת). אחרת false.\n"
            . "- reason_code: בחר את המתאים ביותר; לשאלה פשוטה השתמש ב־simple.\n"
            . "- user_hint: משפט קצר וברור למשתמש (יוצג בממשק) — למה מפעילים חשיבה מעמיקה. רק כש־needs_deep=true; אחרת מחרוזת ריקה \"\".\n";
    }
}

/** שכבת מערכת נוספת לשלב תשובה מעמיקה (אחרי הוראות הבסיס + הקשר) */
if (!function_exists('ai_chat_build_deep_system_layer_suffix')) {
    function ai_chat_build_deep_system_layer_suffix(): string
    {
        return "### מצב חשיבה מעמיקה (מופעל אוטומטית)\n"
            . "מטרה: ניתוח מדויק ופרקטי — לא חיבור ארוך.\n"
            . "מבנה מומלץ לתשובה:\n"
            . "1) **תקציר** — משפט אחד עד שניים.\n"
            . "2) **ניתוח** — בולטים קצרים (נתונים/השוואות/הנחות). אם חסר מידע — ציין במפורש.\n"
            . "3) **מה לעשות** — צעדים או מסקנה מעשית.\n"
            . "אל תחשוף מזהים טכניים (id, category_id) או שדות JSON למשתמש — רק שמות וסכומים קריאים.\n"
            . "**סיום פלט:** חובה לסיים כל משפט במלואו — מספרים, תאריכים וסוגריים. אם נשאר מעט מקום — תעדף תקציר וניתוח תמציתי, ואל תחתוך באמצע מילה או באמצע תאריך (למשל אל תסיים ב־«20» או ב־«30 ביוני 20»).\n"
            . "**עיצוב לתשובות ארוכות:** כותרות משנה בשורה עם **הכותרת**; פסקאות מופרדות בשורה ריקה; רשימות עם * או - בתחילת שורה — לא גוש טקסט רציף.\n"
            . "אם רלוונטי לניווט, השתמש ב־[[PAGE:...|...]] **רק** לנתיבים מהרשימה המותרת בהוראות הבסיס.\n";
    }
}

/**
 * נתונים פיננסיים: חודש קלנדרי נוכחי + חודש אחד אחורה, לפי home_id.
 */
if (!function_exists('ai_chat_build_financial_snapshot')) {
    function ai_chat_build_financial_snapshot(mysqli $conn, int $homeId, array $scope, array $options = []): string
    {
        if (ai_chat_scope_topic($scope) !== 'financial') {
            return '';
        }
        if ($homeId <= 0) {
            return 'אין home_id — לא ניתן לטעון נתונים פיננסיים.';
        }

        $today = date('Y-m-d');

        $disp = tazrim_home_display_bank_balance($conn, $homeId, $today);

        $lines = [];
        $lines[] = 'נתוני תזרים (בית משותף — כל הפעולות של הבית):';
        $lines[] = '- יתרה ממומשת (מתנועות, תאריכים עד היום): ' . round($disp['ledger_dec'], 2) . ' ₪';
        $lines[] = '- יישור ידני (כולל יתרה התחלתית שהועברה במיגרציה): ' . round($disp['adjustment_dec'], 2) . ' ₪';
        $lines[] = '- סכום הוצאות עתידיות (מעל היום): ' . round($disp['future_expenses_sum'], 2) . ' ₪';
        $lines[] = '- יתרה מוצגת (מוערכת): ledger + יישור − הוצאות עתידיות = ' . round($disp['display'], 2) . ' ₪';
        $lines[] = '';

        $currentMonthStart = new DateTimeImmutable('first day of this month');
        $prevMonthStart = $currentMonthStart->modify('-1 month');
        $periods = [
            [
                'label' => 'חודש נוכחי',
                'start' => $currentMonthStart->format('Y-m-01'),
                'end' => $currentMonthStart->format('Y-m-t'),
            ],
            [
                'label' => 'חודש קודם',
                'start' => $prevMonthStart->format('Y-m-01'),
                'end' => $prevMonthStart->format('Y-m-t'),
            ],
        ];

        $needFullTx = !empty($options['need_full_transactions']);
        $defaultTxLimit = 45;
        $safeFullTxCap = 300;
        $txLimit = $needFullTx ? $safeFullTxCap : $defaultTxLimit;

        foreach ($periods as $p) {
            $start = $p['start'];
            $end = $p['end'];
            $label = $p['label'];

            $totSql = "SELECT
                COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
                COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense_total
                FROM transactions
                WHERE home_id = ? AND transaction_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($totSql);
            $stmt->bind_param('iss', $homeId, $start, $end);
            $stmt->execute();
            $tot = $stmt->get_result()->fetch_assoc() ?: ['income_total' => 0, 'expense_total' => 0];
            $stmt->close();

            $lines[] = "### {$label} ({$start} — {$end})";
            $lines[] = '- הכנסות: ' . $tot['income_total'] . ' ₪';
            $lines[] = '- הוצאות: ' . $tot['expense_total'] . ' ₪';
            $lines[] = '- נטו (בתקופה): ' . round((float) $tot['income_total'] - (float) $tot['expense_total'], 2) . ' ₪';

            $expCatSql = "SELECT c.name, COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category
                WHERE t.home_id = ? AND t.type='expense' AND t.transaction_date BETWEEN ? AND ?
                GROUP BY t.category, c.name
                ORDER BY total DESC";
            $stmt = $conn->prepare($expCatSql);
            $stmt->bind_param('iss', $homeId, $start, $end);
            $stmt->execute();
            $cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $stmt->close();
            if ($cats) {
                $lines[] = '- פילוח הוצאות לפי קטגוריה:';
                foreach ($cats as $row) {
                    $lines[] = '  • ' . $row['name'] . ': ' . $row['total'] . ' ₪';
                }
            }

            $incCatSql = "SELECT c.name, COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category
                WHERE t.home_id = ? AND t.type='income' AND t.transaction_date BETWEEN ? AND ?
                GROUP BY t.category, c.name
                ORDER BY total DESC";
            $stmt = $conn->prepare($incCatSql);
            $stmt->bind_param('iss', $homeId, $start, $end);
            $stmt->execute();
            $incCats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $stmt->close();
            if ($incCats) {
                $lines[] = '- פילוח הכנסות לפי קטגוריה:';
                foreach ($incCats as $row) {
                    $lines[] = '  • ' . $row['name'] . ': ' . $row['total'] . ' ₪';
                }
            }

            $budgetSql = "SELECT c.name, c.budget_limit, COALESCE(SUM(t.amount),0) AS spent
                FROM categories c
                LEFT JOIN transactions t ON t.category = c.id AND t.home_id = c.home_id
                    AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
                WHERE c.home_id = ? AND c.type = 'expense' AND c.is_active = 1 AND c.budget_limit > 0
                GROUP BY c.id, c.name, c.budget_limit";
            $stmt = $conn->prepare($budgetSql);
            $stmt->bind_param('ssi', $start, $end, $homeId);
            $stmt->execute();
            $budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $stmt->close();
            if ($budgets) {
                $lines[] = '- תקציבים מול ביצוע בחודש זה (קטגוריות עם תקציב):';
                foreach ($budgets as $b) {
                    $lim = (float) $b['budget_limit'];
                    $spent = (float) $b['spent'];
                    $pct = $lim > 0 ? round(100 * $spent / $lim, 1) : 0;
                    $lines[] = '  • ' . $b['name'] . ': נוצל ' . $pct . '% (' . $spent . ' / ' . $lim . ' ₪)';
                }
            }

            $txSql = "SELECT t.transaction_date, t.type, t.amount, t.description, c.name AS cat_name, u.first_name AS user_name
                FROM transactions t
                LEFT JOIN categories c ON c.id = t.category
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.home_id = ? AND t.transaction_date BETWEEN ? AND ?
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT ?";
            $stmt = $conn->prepare($txSql);
            $stmt->bind_param('issi', $homeId, $start, $end, $txLimit);
            $stmt->execute();
            $txs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $stmt->close();
            if ($txs) {
                if ($needFullTx) {
                    $lines[] = '- פירוט פעולות (מצב מורחב — עד ' . $txLimit . ' אחרונות בחודש, ייתכן קיטום):';
                } else {
                    $lines[] = '- דוגמאות פעולות (עד ' . $txLimit . ' אחרונות בחודש):';
                }
                foreach ($txs as $trow) {
                    $who = isset($trow['user_name']) && $trow['user_name'] !== '' ? ' [' . $trow['user_name'] . ']' : '';
                    $lines[] = '  • ' . $trow['transaction_date'] . ' | ' . $trow['type'] . ' | ' . $trow['amount'] . ' ₪ | '
                        . ($trow['cat_name'] ?? '') . ' | ' . ($trow['description'] ?? '') . $who;
                }
                if ($needFullTx && count($txs) >= $txLimit) {
                    $lines[] = '  • ... ייתכנו פעולות נוספות מעבר לגבול השליפה';
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}

/** נתיב + כותרת דף מהדפדפן — לשיחות «המערכת» בלבד (בלי HTML) */
if (!function_exists('ai_chat_format_client_page_context')) {
    function ai_chat_format_client_page_context(string $path, string $title): string
    {
        $path = trim($path);
        $title = trim(strip_tags($title));
        if (function_exists('mb_substr')) {
            $path = mb_substr($path, 0, 240);
            $title = mb_substr($title, 0, 200);
        } else {
            $path = substr($path, 0, 240);
            $title = substr($title, 0, 200);
        }
        if ($path === '' && $title === '') {
            return '';
        }

        return "### מסך נוכחי בדפדפן (נשלח מהממשק)\n"
            . ($path !== '' ? "- נתיב: {$path}\n" : '')
            . ($title !== '' ? "- כותרת הדף: {$title}\n" : '')
            . "אם השאלה נוגעת למסך הזה — התמקד בו; אם לא — התעלם מהנתיב.\n";
    }
}

if (!function_exists('ai_chat_build_model_context_block')) {
    /**
     * טקסט אחד ל-system instruction: לפי נושא — עם או בלי נתונים פיננסיים / מדריך מלא.
     */
    function ai_chat_build_model_context_block(mysqli $conn, int $homeId, array $scope, string $userFirstName = '', array $options = []): string
    {
        $instruction = ai_chat_build_system_instruction($scope, $userFirstName);
        $topic = ai_chat_scope_topic($scope);

        if ($topic === 'financial') {
            $data = ai_chat_build_financial_snapshot($conn, $homeId, $scope, $options);
            return $instruction . "\n\n---\nנתוני תזרים (לשימושך בלבד):\n" . ($data !== '' ? $data : '(ריק)');
        }

        return $instruction . "\n\n(במצב זה לא נשלחו נתוני פעולות גולמיים — ענה לפי מדריך המערכת למעלה.)";
    }
}

if (!function_exists('ai_chat_build_session_identity_block')) {
    /**
     * פרטי משתמש מהסשן בלבד (ללא טלפון/אימייל) — כדי לענות על «מה הפרטים שלי» בלי להמציא.
     */
    function ai_chat_build_session_identity_block(string $userFirstName, string $nickname): string
    {
        $nick = trim($nickname);
        if (function_exists('mb_substr')) {
            $nick = mb_substr($nick, 0, 80, 'UTF-8');
        } else {
            $nick = substr($nick, 0, 80);
        }
        $fn = trim($userFirstName);
        if ($fn === '' && $nick === '') {
            return '';
        }
        $lines = [
            '### פרטי המשתמש המחובר (מהסשן — לקריאה בלבד)',
            'אלה הנתונים שמותר להציג בצ\'אט לפי בקשת המשתמש. **אין** כאן טלפון, אימייל או מזהים רגישים — להפניה לשדות אלה השתמש ב־[[PAGE:/pages/settings/user_profile.php|הגדרות החשבון]].',
        ];
        if ($fn !== '') {
            $lines[] = '- שם פרטי (לפנייה): ' . $fn;
        }
        if ($nick !== '') {
            $lines[] = '- כינוי במערכת: ' . $nick;
        }
        $lines[] = 'לשינוי כינוי — רק אחרי בקשה מפורשת: הצע ACTION מסוג `update_user_nickname` (יאושר ע"י המשתמש לפני ביצוע).';

        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('ai_chat_build_server_time_anchor_block')) {
    function ai_chat_build_server_time_anchor_block(): string
    {
        $tz = new DateTimeZone('Asia/Jerusalem');
        $now = new DateTimeImmutable('now', $tz);
        $dow = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'][(int) $now->format('w')];
        $dateHe = $now->format('d/m/Y');
        $time = $now->format('H:i');

        return "### עוגן זמן (שרת)\n"
            . "היום הוא יום {$dow}, {$dateHe}, השעה {$time}, אזור זמן: Asia/Jerusalem.\n"
            . "השתמש בזה לביטויים יחסיים כמו «בחודש שעבר» או «שבוע הבא».\n";
    }
}

if (!function_exists('ai_chat_format_current_view_for_router')) {
    /**
     * @param array<string,mixed>|null $cv
     */
    function ai_chat_format_current_view_for_router(?array $cv): string
    {
        if (!is_array($cv) || $cv === []) {
            return '';
        }
        $path = trim((string) ($cv['path'] ?? ''));
        $title = trim(strip_tags((string) ($cv['title'] ?? '')));
        $vm = isset($cv['view_month']) ? (int) $cv['view_month'] : 0;
        $vy = isset($cv['view_year']) ? (int) $cv['view_year'] : 0;
        $lines = ["### מסך ותצוגה נוכחיים (לניתוב בלבד — לא מחליף נתוני בית)"];
        if ($path !== '') {
            $lines[] = '- נתיב: ' . mb_substr($path, 0, 240, 'UTF-8');
        }
        if ($title !== '') {
            $lines[] = '- כותרת דף: ' . mb_substr($title, 0, 200, 'UTF-8');
        }
        if ($vm >= 1 && $vm <= 12 && $vy >= 2000 && $vy <= 2100) {
            $lines[] = "- חודש/שנה בתצוגה בממשק: {$vm}/{$vy}";
        }
        if (isset($cv['active_tab']) && is_string($cv['active_tab'])) {
            $lines[] = '- טאב פעיל: ' . mb_substr(trim($cv['active_tab']), 0, 80, 'UTF-8');
        }

        return implode("\n", $lines) . "\n";
    }
}

/**
 * רשימת מדדים בטוחה לניתוב — משפיעה על עומס בלוק הפיננסי (לא על אבטחה).
 *
 * @return list<string>
 */
if (!function_exists('ai_chat_normalize_route_metrics')) {
    function ai_chat_normalize_route_metrics(mixed $raw): array
    {
        $allowed = ['balances', 'period_totals', 'category_breakdown', 'budgets', 'sample_transactions'];
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $s = is_string($item) ? trim($item) : '';
                if ($s !== '' && in_array($s, $allowed, true) && !in_array($s, $out, true)) {
                    $out[] = $s;
                }
            }
        }

        return $out === [] ? $allowed : $out;
    }
}

if (!function_exists('ai_chat_router_defaults')) {
    /** @return array<string,mixed> */
    function ai_chat_router_defaults(): array
    {
        return [
            'query_focus' => 'mixed',
            'date_intent' => 'last_two_calendar_months',
            'range_start' => '',
            'range_end' => '',
            'analysis_mode' => 'historical',
            'metrics' => ai_chat_normalize_route_metrics(null),
            'needs_deep' => false,
            'needs_full_transactions' => false,
            'reason_code' => 'simple',
            'user_hint' => '',
            'suggest_goal_followup' => false,
        ];
    }
}

if (!function_exists('ai_chat_router_normalize')) {
    /**
     * @param array<string,mixed>|null $parsed
     * @param array<string,mixed>|null $currentView
     * @return array<string,mixed>
     */
    function ai_chat_router_normalize(?array $parsed, ?array $currentView): array
    {
        $d = ai_chat_router_defaults();
        if (!is_array($parsed)) {
            return $d;
        }
        $qf = (string) ($parsed['query_focus'] ?? '');
        if (in_array($qf, ['product_help', 'financial', 'mixed'], true)) {
            $d['query_focus'] = $qf;
        }
        $di = (string) ($parsed['date_intent'] ?? '');
        if (in_array($di, ['last_two_calendar_months', 'ytd', 'all_time', 'custom_range', 'single_month'], true)) {
            $d['date_intent'] = $di;
        }
        $rs = trim((string) ($parsed['range_start'] ?? ''));
        $re = trim((string) ($parsed['range_end'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rs)) {
            $d['range_start'] = $rs;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $re)) {
            $d['range_end'] = $re;
        }
        $am = (string) ($parsed['analysis_mode'] ?? '');
        if (in_array($am, ['historical', 'what_if_simulation'], true)) {
            $d['analysis_mode'] = $am;
        }
        $d['metrics'] = ai_chat_normalize_route_metrics($parsed['metrics'] ?? null);
        foreach (['needs_deep', 'needs_full_transactions', 'suggest_goal_followup'] as $bk) {
            if (array_key_exists($bk, $parsed)) {
                $v = $parsed[$bk];
                if (is_bool($v)) {
                    $d[$bk] = $v;
                } elseif (is_int($v) || is_float($v)) {
                    $d[$bk] = ((int) $v) === 1;
                } elseif (is_string($v)) {
                    $d[$bk] = in_array(strtolower(trim($v)), ['1', 'true', 'yes'], true);
                }
            }
        }
        $d['reason_code'] = trim((string) ($parsed['reason_code'] ?? 'simple')) ?: 'simple';
        $hint = trim((string) ($parsed['user_hint'] ?? ''));
        $d['user_hint'] = function_exists('mb_substr') ? mb_substr($hint, 0, 48, 'UTF-8') : substr($hint, 0, 48);
        if (!empty($d['needs_deep']) && $d['user_hint'] === '') {
            $d['user_hint'] = 'מנתח את השאלה לעומק';
        }

        return $d;
    }
}

if (!function_exists('ai_chat_build_router_instruction_v2')) {
    function ai_chat_build_router_instruction_v2(string $timeAnchor, string $currentViewBlock): string
    {
        $schema = '{"query_focus":"product_help|financial|mixed",'
            . '"date_intent":"last_two_calendar_months|ytd|all_time|custom_range|single_month",'
            . '"range_start":"YYYY-MM-DD או ריק","range_end":"YYYY-MM-DD או ריק",'
            . '"analysis_mode":"historical|what_if_simulation",'
            . '"metrics":["balances","period_totals","category_breakdown","budgets","sample_transactions"],'
            . '"needs_deep":boolean,"needs_full_transactions":boolean,'
            . '"reason_code":"simple|compare_periods|trend|budget_scenario|scenario|multi_part|ambiguous|other",'
            . '"user_hint":"עברית עד 48 תווים","suggest_goal_followup":boolean}';

        return $timeAnchor . "\n"
            . ($currentViewBlock !== '' ? $currentViewBlock . "\n" : '')
            . "אתה מסווג בקשות למערכת «התזרים». החזר **אובייקט JSON בלבד** — בלי markdown, בלי ```.\n"
            . "סכמה:\n{$schema}\n\n"
            . "כללים:\n"
            . "- query_focus: product_help = איך עושים/איפה במערכת; financial = נתונים/תזרים/תקציב; mixed = שניהם.\n"
            . "- אם המשתמש מבקש **להוסיף** הוצאה/הכנסה/פעולה — בחר financial או mixed (לא product_help בלבד), כדי שיישלחו נתוני תזרים.\n"
            . "- analysis_mode: what_if_simulation כשהמשתמש מתאר הוצאות/הכנסות **היפותטיות** («מה אם», «אם אקנה») — בלי לבקש כתיבה לדאטהבייס.\n"
            . "- metrics: מערך מחרוזות מתוך balances | period_totals | category_breakdown | budgets | sample_transactions — מה לכלול בבלוק הנתונים. לשאלה כללית השאר את כולן; לשאלת «יתרה בלבד» אפשר רק balances ו-period_totals; אם חסר או לא תקין — המערכת תשתמש בברירת מחדל מלאה.\n"
            . "- date_intent: last_two_calendar_months ברירת מחדל; single_month כשהכוונה לחודש תצוגה או חודש ספציפי; ytd; all_time; custom_range עם טווחים.\n"
            . "- needs_deep / needs_full_transactions / user_hint: כמו קודם (user_hint רק כש־needs_deep=true).\n"
            . "- suggest_goal_followup: true רק אם יש כאן הקשר פיננסי שמצדיק להזכיר יעד שמור (אל תשתמש true בלי סיבה).\n";
    }
}

if (!function_exists('ai_chat_build_goals_memory_block')) {
    /**
     * @param list<array{pref_key:string,pref_value:string}> $prefs
     */
    function ai_chat_build_goals_memory_block(array $prefs): string
    {
        if ($prefs === []) {
            return '';
        }
        $lines = ["### עובדות ויעדים שמורים (של המשתמש בלבד)"];
        foreach ($prefs as $row) {
            $lines[] = '- ' . $row['pref_key'] . ': ' . mb_substr($row['pref_value'], 0, 500, 'UTF-8');
        }
        $lines[] = "אל תזכיר יעדים בכל תשובה — רק כשההקשר ממש מצדיק (יתרה עודפת, חיסכון, שאלה על יעד).\n";

        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('ai_chat_build_unified_agent_instruction')) {
    /**
     * הוראות מערכת לשלב תשובה (אחרי ניתוב).
     *
     * @param array<string,mixed> $route
     */
    function ai_chat_build_unified_agent_instruction(array $route, string $userFirstName = ''): string
    {
        require_once __DIR__ . '/allowed_chat_pages.php';
        $assistantName = defined('AI_CHAT_ASSISTANT_NAME') ? AI_CHAT_ASSISTANT_NAME : 'התזרים החכם';
        $pageFormat = "כשאתה מפנה למסך או לעמוד באתר, השתמש בפורמט המדויק:\n"
            . "[[PAGE:/נתיב/מלא|טקסט קצר לכפתור]]\n"
            . "הנתיב חייב להתחיל ב־/. **אסור** להמציא דפים או נתיבי הגדרות שלא קיימים; מותרים **אך ורק** הנתיבים הבאים:\n"
            . ai_chat_format_allowed_pages_for_prompt() . "\n\n";

        $personal = '';
        if ($userFirstName !== '') {
            $personal = "פנייה למשתמש: השם הפרטי הוא «{$userFirstName}». מדי פעם, כשזה טבעי — אפשר לפנות בשם הפרטי.\n\n";
        }

        $base = "שמך הוא {$assistantName}. תפקידך לסייע במערכת \"התזרים\" בלבד.\n"
            . $personal
            . "מותר לענות על שימוש במערכת, נתונים פיננסיים של הבית (כשנשלחו), או הכוונה זהירה לפי נתונים.\n"
            . "אסור לענות על נושאים לא קשורים. אם השאלה מחוץ לתחום — סרב בנימוס במשפט אחד.\n"
            . "אל תמציא נתונים שלא נשלחו אליך.\n"
            . "**אסור למשתמש:** מזהי קטגוריה, מספרי id, המילה category_id, JSON טכני או שמות שדות מסד — גם כששואלים \"אילו קטגוריות יש\". הצג רק שמות קריאים וממוינים.\n"
            . "תשובות בעברית, קצרות ופרקטיות אלא אם נדרש אחרת.\n"
            . "להדגשה: **טקסט** (Markdown). מבנה: קטעים עם כותרות משנה מודגשות, רשימות עם * בשורה נפרדת, ריווח בין נושאים.\n"
            . $pageFormat
            . "הבהרה: זה לא ייעוץ פיננסי מחייב.\n\n";

        $qf = (string) ($route['query_focus'] ?? 'mixed');
        $am = (string) ($route['analysis_mode'] ?? 'historical');
        $modeLines = "### ניתוב אוטומטי\n- מיקוד: {$qf}\n- מצב ניתוח: {$am}\n";
        if ($am === 'what_if_simulation') {
            $modeLines .= "מצב **מה אם**: השתמש בנתוני האמת שסופקו ובחשבון וירטואלי בלבד. הדגש שמדובר בהערכה. **אל** תכתוב לדאטהבייס; אל תציג בלוקי ACTION אלא אם המשתמש ביקש במפורש לבצע פעולה במערכת.\n";
        }
        if (!empty($route['suggest_goal_followup'])) {
            $modeLines .= "הניתוב מאשר אזכור עדין ליעד שמור (משפט אחד לכל היותר) **רק** אם הוא באמת משתלב בתשובה; אל תחזור על יעדים שלא נשאלת עליהם.\n";
        }

        $toolRules = "### כלים לפלט מובנה (רק כשחייבים)\n"
            . "אם חסר מידע קריטי — בלוק:\n"
            . "[[QUESTIONS]]\n"
            . "[{\"id\":\"q1\",\"text\":\"...\",\"options\":[\"אופציה א\",\"אופציה ב\"]}]\n"
            . "[[/QUESTIONS]]\n\n"
            . "פעולות במערכת (אחרי אישור משתמש) — בלוק יחיד:\n"
            . "[[ACTION]]\n"
            . "{\"kind\":\"create_category\",\"name\":\"שם הקטגוריה\",\"type\":\"expense\",\"icon\":\"fa-bolt\",\"budget_limit\":0,\"initial_transaction\":{\"amount\":50,\"description\":\"טלפון\",\"transaction_date\":\"YYYY-MM-DD\"}}\n"
            . "(אפשר `create_category` בלי `initial_transaction` — רק קטגוריה חדשה. אייקון אופציונלי בפורמט fa-xxx.)\n"
            . "או {\"kind\":\"create_transaction\",\"type\":\"expense\",\"amount\":120.5,\"category_id\":<מספר מהמיפוי הפנימי>,\"description\":\"תיאור\",\"transaction_date\":\"YYYY-MM-DD\"}\n"
            . "או {\"kind\":\"save_user_preference\",\"pref_key\":\"goal_example\",\"pref_value\":\"JSON או טקסט\"}\n"
            . "או {\"kind\":\"update_user_nickname\",\"nickname\":\"הכינוי החדש\"}\n"
            . "[[/ACTION]]\n"
            . "כללים ל־create_transaction: `category_id` חייב להופיע במיפוי הפנימי של הקטגוריות; `type` חייב להתאים לסוג הקטגוריה (expense/income). אל תציג מזהים אלה בטקסט למשתמש.\n"
            . "מפתחות save_user_preference: רק goal_* או fact_* (אותיות קטנות, מספרים, קו תחתון).\n"
            . "גרפים: אין רינדור גרף בצ'אט — הפנה לדוחות/דף הבית והסבר בטקסט.\n"
            . "אם אין צורך בכלים — ענה בעברית רגילה בלבד.\n";

        return $base . $modeLines . $toolRules;
    }
}

if (!function_exists('ai_chat_compose_system_context')) {
    /**
     * @param array<string,mixed> $route
     * @param array<string,mixed>|null $currentView
     * @param list<array{pref_key:string,pref_value:string}> $prefs
     */
    function ai_chat_compose_system_context(
        mysqli $conn,
        int $homeId,
        array $route,
        ?array $currentView,
        array $prefs,
        string $userFirstName,
        array $options = []
    ): string {
        require_once __DIR__ . '/financial_context_builder.php';
        require_once __DIR__ . '/help_retrieval.php';

        $anchor = ai_chat_build_server_time_anchor_block();
        $parts = [$anchor];

        $idBlock = trim((string) ($options['identity_context_block'] ?? ''));
        if ($idBlock !== '') {
            $parts[] = $idBlock;
        }

        $cvRouter = ai_chat_format_current_view_for_router($currentView);
        if ($cvRouter !== '') {
            $parts[] = $cvRouter;
        }

        $goals = ai_chat_build_goals_memory_block($prefs);
        if ($goals !== '') {
            $parts[] = $goals;
        }

        $parts[] = ai_chat_build_unified_agent_instruction($route, $userFirstName);

        $qf = (string) ($route['query_focus'] ?? 'mixed');
        $userMsg = (string) ($options['user_message_for_rag'] ?? '');

        if ($qf === 'product_help' || $qf === 'mixed') {
            if ($userMsg !== '') {
                $parts[] = ai_chat_help_retrieve_top_k($userMsg, 5, 28000, $conn);
            } else {
                $parts[] = "### מדריך מערכת\n" . ai_chat_load_product_knowledge();
            }
        }

        if ($qf === 'financial' || $qf === 'mixed') {
            $fin = ai_chat_financial_build_snapshot($conn, $homeId, $route, $currentView, $options);
            $parts[] = "---\nנתוני תזרים (לשימושך בלבד):\n" . $fin;
        }

        if ($homeId > 0) {
            $catB = ai_chat_financial_categories_catalog_block($conn, $homeId);
            if ($catB !== '') {
                $parts[] = $catB;
            }
        }

        if (($route['analysis_mode'] ?? '') === 'what_if_simulation') {
            $parts[] = "### הנחיות נוספות למצב מה אם\n"
                . "חשב בזהירות השפעה על יתרה ותזרים עתידי; ציין הנחות. אין לבצע שינוי במסד.\n";
        }

        $pageBlock = '';
        if (is_array($currentView)) {
            $pp = trim((string) ($currentView['path'] ?? ''));
            $tt = trim(strip_tags((string) ($currentView['title'] ?? '')));
            if ($pp !== '' || $tt !== '') {
                $pageBlock = ai_chat_format_client_page_context($pp, $tt);
            }
        }
        if ($pageBlock !== '') {
            $parts[] = $pageBlock;
        }

        return implode("\n\n", array_filter($parts, static fn ($x) => trim((string) $x) !== ''));
    }
}

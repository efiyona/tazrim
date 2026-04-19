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
        $topic = ai_chat_scope_topic($scope);
        $pageFormat = "כשאתה מפנה למסך או לעמוד באתר, השתמש בפורמט המדויק:\n"
            . "[[PAGE:/נתיב/מלא|טקסט קצר לכפתור]]\n"
            . "דוגמה: [[PAGE:/pages/reports.php|מעבר לדוחות]]\n"
            . "הנתיב חייב להתחיל ב־/.\n\n";

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
            . "שמור על עברית ברורה. אם רלוונטי, השתמש ב־[[PAGE:...|...]] לניווט.\n";
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

        $home = selectOne('homes', ['id' => $homeId]);
        $initialBalance = isset($home['initial_balance']) ? (float) decryptBalance($home['initial_balance']) : 0.0;

        $netQuery = "SELECT 
            COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date <= ? THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS net_balance
            FROM transactions WHERE home_id = ?";
        $stmt = $conn->prepare($netQuery);
        $stmt->bind_param('si', $today, $homeId);
        $stmt->execute();
        $netRow = $stmt->get_result()->fetch_assoc() ?: ['net_balance' => 0];
        $stmt->close();
        $currentBalance = $initialBalance + (float) ($netRow['net_balance'] ?? 0);

        $lines = [];
        $lines[] = 'נתוני תזרים (בית משותף — כל הפעולות של הבית):';
        $lines[] = '- יתרה התחלתית שהוגדרה לבית: ' . $initialBalance . ' ₪';
        $lines[] = '- יתרה מחושבת (עד היום, כולל הכנסות עד היום והוצאות כולל עבר): ' . round($currentBalance, 2) . ' ₪';
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

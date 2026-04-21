<?php
declare(strict_types=1);

if (!defined('AI_CHAT_FINANCIAL_TX_CAP')) {
    define('AI_CHAT_FINANCIAL_TX_CAP', 100);
}

if (!function_exists('ai_chat_financial_resolve_range')) {
    /**
     * @param array<string,mixed> $route
     * @param array<string,mixed>|null $currentView
     * @return array{0:string,1:string,2:string} start, end, label
     */
    function ai_chat_financial_resolve_range(array $route, ?array $currentView, DateTimeImmutable $todayIl): array
    {
        $intent = (string) ($route['date_intent'] ?? 'last_two_calendar_months');
        $tz = new DateTimeZone('Asia/Jerusalem');

        if ($intent === 'ytd') {
            $start = $todayIl->modify('first day of january this year');

            return [$start->format('Y-m-d'), $todayIl->format('Y-m-d'), 'מתחילת השנה'];
        }

        if ($intent === 'all_time') {
            return ['1970-01-01', $todayIl->format('Y-m-d'), 'כל התקופה (מסוכן לשליפה — אגרגציות בלבד)'];
        }

        if ($intent === 'single_month') {
            $rs = trim((string) ($route['range_start'] ?? ''));
            if ($rs !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rs)) {
                $d = DateTimeImmutable::createFromFormat('Y-m-d', $rs, $tz);
                if ($d instanceof DateTimeImmutable) {
                    $first = $d->modify('first day of this month');
                    $last = $d->modify('last day of this month');

                    return [$first->format('Y-m-01'), $last->format('Y-m-t'), 'חודש ' . $first->format('Y-m')];
                }
            }
            $vm = (int) ($currentView['view_month'] ?? 0);
            $vy = (int) ($currentView['view_year'] ?? 0);
            if ($vm >= 1 && $vm <= 12 && $vy >= 2000 && $vy <= 2100) {
                $d = DateTimeImmutable::createFromFormat('Y-n-j', "{$vy}-{$vm}-1", $tz) ?: $todayIl;

                return [$d->format('Y-m-01'), $d->format('Y-m-t'), 'חודש תצוגה'];
            }
            $first = $todayIl->modify('first day of this month');

            return [$first->format('Y-m-01'), $first->format('Y-m-t'), 'חודש נוכחי'];
        }

        if ($intent === 'custom_range') {
            $a = trim((string) ($route['range_start'] ?? ''));
            $b = trim((string) ($route['range_end'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b)) {
                $da = DateTimeImmutable::createFromFormat('Y-m-d', $a, $tz);
                $db = DateTimeImmutable::createFromFormat('Y-m-d', $b, $tz);
                if ($da && $db && $da <= $db) {
                    $days = (int) $da->diff($db)->format('%a') + 1;
                    if ($days > 731) {
                        $db = $da->modify('+730 days');
                    }

                    return [$da->format('Y-m-d'), $db->format('Y-m-d'), 'טווח מותאם'];
                }
            }
        }

        $cur = $todayIl->modify('first day of this month');
        $prev = $cur->modify('-1 month');

        return [
            $prev->format('Y-m-01'),
            $cur->format('Y-m-t'),
            'שני חודשים קלנדריים (נוכחי+קודם)',
        ];
    }
}

if (!function_exists('ai_chat_financial_span_days')) {
    function ai_chat_financial_span_days(string $start, string $end): int
    {
        $a = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $b = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if (!$a || !$b || $a > $b) {
            return 0;
        }

        return (int) $a->diff($b)->format('%a') + 1;
    }
}

if (!function_exists('ai_chat_financial_build_snapshot')) {
    /**
     * בונה בלוק טקסט פיננסי לפי ניתוב (read-only).
     *
     * @param array<string,mixed> $route
     * @param array<string,mixed>|null $currentView
     */
    function ai_chat_financial_build_snapshot(mysqli $conn, int $homeId, array $route, ?array $currentView, array $options = []): string
    {
        if ($homeId <= 0) {
            return 'אין home_id — לא ניתן לטעון נתונים פיננסיים.';
        }

        if (!function_exists('tazrim_home_display_bank_balance')) {
            require_once dirname(__DIR__, 3) . '/functions/home_bank_balance.php';
        }

        $tz = new DateTimeZone('Asia/Jerusalem');
        $todayIl = new DateTimeImmutable('now', $tz);
        $todayStr = $todayIl->format('Y-m-d');

        $intent = (string) ($route['date_intent'] ?? 'last_two_calendar_months');
        $metrics = function_exists('ai_chat_normalize_route_metrics')
            ? ai_chat_normalize_route_metrics($route['metrics'] ?? null)
            : ['balances', 'period_totals', 'category_breakdown', 'budgets', 'sample_transactions'];
        $hasBalances = in_array('balances', $metrics, true);
        $hasPeriodTotals = in_array('period_totals', $metrics, true);
        $hasCategoryBreakdown = in_array('category_breakdown', $metrics, true);
        $hasBudgets = in_array('budgets', $metrics, true);
        $hasSampleTx = in_array('sample_transactions', $metrics, true);
        $lines = [];

        if ($intent === 'last_two_calendar_months') {
            $currentMonthStart = $todayIl->modify('first day of this month');
            $prevMonthStart = $currentMonthStart->modify('-1 month');
            $periods = [
                ['label' => 'חודש נוכחי', 'start' => $currentMonthStart->format('Y-m-01'), 'end' => $currentMonthStart->format('Y-m-t')],
                ['label' => 'חודש קודם', 'start' => $prevMonthStart->format('Y-m-01'), 'end' => $prevMonthStart->format('Y-m-t')],
            ];
        } else {
            [$rs, $re, $label] = ai_chat_financial_resolve_range($route, $currentView, $todayIl);
            $periods = [['label' => $label, 'start' => $rs, 'end' => $re]];
        }

        $lines[] = 'נתוני תזרים (בית — כל הפעולות של הבית):';
        if ($hasBalances) {
            $disp = tazrim_home_display_bank_balance($conn, $homeId, $todayStr);
            $lines[] = '- יתרה ממומשת (מתנועות, תאריכים עד היום): ' . round($disp['ledger_dec'], 2) . ' ₪';
            $lines[] = '- יישור ידני: ' . round($disp['adjustment_dec'], 2) . ' ₪';
            $lines[] = '- סכום הוצאות עתידיות (מעל היום): ' . round($disp['future_expenses_sum'], 2) . ' ₪';
            $lines[] = '- יתרה מוצגת (מוערכת): ' . round($disp['display'], 2) . ' ₪';
        } else {
            $lines[] = '- (פירוט יתרות בית מלא לא נכלל לפי ניתוב metrics.)';
        }
        $lines[] = '';

        $needFullTx = !empty($options['need_full_transactions']);
        $spanForAggOnly = false;
        foreach ($periods as $p) {
            if (ai_chat_financial_span_days($p['start'], $p['end']) > 62) {
                $spanForAggOnly = true;
            }
        }
        if (in_array($intent, ['all_time', 'ytd'], true)) {
            $spanForAggOnly = true;
        }

        $txCap = AI_CHAT_FINANCIAL_TX_CAP;
        $remainingLines = $txCap;

        foreach ($periods as $p) {
            $start = $p['start'];
            $end = $p['end'];
            $label = $p['label'];

            $lines[] = "### {$label} ({$start} — {$end})";
            $tot = ['income_total' => 0, 'expense_total' => 0];
            if ($hasPeriodTotals) {
                $totSql = "SELECT
                    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
                    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS expense_total
                    FROM transactions
                    WHERE home_id = ? AND transaction_date BETWEEN ? AND ?";
                $stmt = $conn->prepare($totSql);
                if (!$stmt) {
                    $lines[] = '- לא ניתן לטעון סיכומים לתקופה זו.';
                    $lines[] = '';

                    continue;
                }
                $stmt->bind_param('iss', $homeId, $start, $end);
                $stmt->execute();
                $tot = $stmt->get_result()->fetch_assoc() ?: ['income_total' => 0, 'expense_total' => 0];
                $stmt->close();
                $lines[] = '- הכנסות: ' . $tot['income_total'] . ' ₪';
                $lines[] = '- הוצאות: ' . $tot['expense_total'] . ' ₪';
                $lines[] = '- נטו (בתקופה): ' . round((float) $tot['income_total'] - (float) $tot['expense_total'], 2) . ' ₪';
            } else {
                $lines[] = '- סיכומי הכנסות/הוצאות לתקופה: לא נכללו לפי ניתוב metrics.';
            }

            $expCatSql = "SELECT c.name, COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category
                WHERE t.home_id = ? AND t.type='expense' AND t.transaction_date BETWEEN ? AND ?
                GROUP BY t.category, c.name
                ORDER BY total DESC";
            $cats = [];
            if ($hasCategoryBreakdown) {
                $stmt = $conn->prepare($expCatSql);
                if (!$stmt) {
                    $lines[] = '- לא ניתן לטעון פילוח הוצאות.';
                } else {
                    $stmt->bind_param('iss', $homeId, $start, $end);
                    $stmt->execute();
                    $cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                    $stmt->close();
                }
                if ($cats) {
                    $lines[] = '- פילוח הוצאות לפי קטגוריה:';
                    foreach ($cats as $row) {
                        $lines[] = '  • ' . $row['name'] . ': ' . $row['total'] . ' ₪';
                    }
                }
            }

            $incCatSql = "SELECT c.name, COALESCE(SUM(t.amount),0) AS total
                FROM transactions t
                JOIN categories c ON c.id = t.category
                WHERE t.home_id = ? AND t.type='income' AND t.transaction_date BETWEEN ? AND ?
                GROUP BY t.category, c.name
                ORDER BY total DESC";
            $incCats = [];
            if ($hasCategoryBreakdown) {
                $stmt = $conn->prepare($incCatSql);
                if (!$stmt) {
                    $lines[] = '- לא ניתן לטעון פילוח הכנסות.';
                } else {
                    $stmt->bind_param('iss', $homeId, $start, $end);
                    $stmt->execute();
                    $incCats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                    $stmt->close();
                }
                if ($incCats) {
                    $lines[] = '- פילוח הכנסות לפי קטגוריה:';
                    foreach ($incCats as $row) {
                        $lines[] = '  • ' . $row['name'] . ': ' . $row['total'] . ' ₪';
                    }
                }
            }

            $budgetSql = "SELECT c.name, c.budget_limit, COALESCE(SUM(t.amount),0) AS spent
                FROM categories c
                LEFT JOIN transactions t ON t.category = c.id AND t.home_id = c.home_id
                    AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
                WHERE c.home_id = ? AND c.type = 'expense' AND c.is_active = 1 AND c.budget_limit > 0
                GROUP BY c.id, c.name, c.budget_limit";
            $budgets = [];
            if ($hasBudgets) {
                $stmt = $conn->prepare($budgetSql);
                if (!$stmt) {
                    $lines[] = '- לא ניתן לטעון תקציבים.';
                } else {
                    $stmt->bind_param('ssi', $start, $end, $homeId);
                    $stmt->execute();
                    $budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                    $stmt->close();
                }
                if ($budgets) {
                    $lines[] = '- תקציבים מול ביצוע (קטגוריות עם תקציב):';
                    foreach ($budgets as $b) {
                        $lim = (float) $b['budget_limit'];
                        $spent = (float) $b['spent'];
                        $pct = $lim > 0 ? round(100 * $spent / $lim, 1) : 0;
                        $lines[] = '  • ' . $b['name'] . ': נוצל ' . $pct . '% (' . $spent . ' / ' . $lim . ' ₪)';
                    }
                }
            }

            $includeTxLines = (!$spanForAggOnly || $needFullTx) && $hasSampleTx;
            if (!$includeTxLines) {
                if (!$hasSampleTx) {
                    $lines[] = '- דוגמאות שורות פעולות: לא נכללו לפי ניתוב (חיסכון בנפח).';
                } elseif ($spanForAggOnly && !$needFullTx) {
                    $lines[] = '- פירוט שורות פעולות: הוחלף באגרגציות בלבד (טווח ארוך או מצב סיכום).';
                }
                $lines[] = '';

                continue;
            }

            $perPeriodLimit = min(45, max(0, $remainingLines));
            if ($needFullTx) {
                $perPeriodLimit = min(300, max(0, $remainingLines));
            }
            if ($perPeriodLimit <= 0) {
                $lines[] = '- פירוט פעולות: הגענו למגבלת השורות הכוללת (' . $txCap . ').';
                $lines[] = '';

                continue;
            }

            $txSql = "SELECT t.transaction_date, t.type, t.amount, t.description, c.name AS cat_name, u.first_name AS user_name
                FROM transactions t
                LEFT JOIN categories c ON c.id = t.category
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.home_id = ? AND t.transaction_date BETWEEN ? AND ?
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT ?";
            $txs = [];
            $stmt = $conn->prepare($txSql);
            if (!$stmt) {
                $lines[] = '- לא ניתן לטעון דוגמאות פעולות.';
            } else {
                $stmt->bind_param('issi', $homeId, $start, $end, $perPeriodLimit);
                $stmt->execute();
                $txs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                $stmt->close();
            }
            if ($txs) {
                $lines[] = '- דוגמאות פעולות (עד ' . $perPeriodLimit . ' אחרונות בתקופה):';
                foreach ($txs as $trow) {
                    $who = isset($trow['user_name']) && $trow['user_name'] !== '' ? ' [' . $trow['user_name'] . ']' : '';
                    $lines[] = '  • ' . $trow['transaction_date'] . ' | ' . $trow['type'] . ' | ' . $trow['amount'] . ' ₪ | '
                        . ($trow['cat_name'] ?? '') . ' | ' . ($trow['description'] ?? '') . $who;
                }
                $remainingLines -= count($txs);
                if (count($txs) >= $perPeriodLimit) {
                    $lines[] = '  • ... ייתכנו פעולות נוספות מעבר לגבול השליפה';
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('ai_chat_financial_categories_catalog_block')) {
    /**
     * רשימת קטגוריות לבית — נדרש למודל כדי להציע ACTION עם category_id אמיתי.
     */
    function ai_chat_financial_categories_catalog_block(mysqli $conn, int $homeId): string
    {
        if ($homeId <= 0) {
            return '';
        }
        $sql = 'SELECT id, name, type FROM categories WHERE home_id = ? AND is_active = 1 ORDER BY type ASC, name ASC LIMIT 150';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $homeId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();
        if ($rows === []) {
            return "### קטגוריות\n(אין קטגוריות פעילות — לא ניתן להוסיף פעולה בלי הגדרת קטגוריות בניהול הבית.)";
        }
        $income = [];
        $expense = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            $name = trim((string) ($r['name'] ?? ''));
            $type = trim((string) ($r['type'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            if ($type === 'income') {
                $income[] = ['id' => $id, 'name' => $name];
            } else {
                $expense[] = ['id' => $id, 'name' => $name];
            }
        }
        $out = [
            '### קטגוריות במערכת',
            '**חובה:** בכל טקסט שמוצג למשתמש — רק **שמות** קטגוריות (רשימות מסודרות). אסור להציג מספרי מזהה, אסור «(id …)», אסור `category_id`, אסור טבלאות מיפוי למשתמש.',
            '',
            '#### לתצוגה למשתמש (שמות בלבד)',
        ];
        if ($income !== []) {
            $out[] = '**הכנסות**';
            foreach ($income as $x) {
                $out[] = '- ' . $x['name'];
            }
        }
        if ($expense !== []) {
            $out[] = '**הוצאות**';
            foreach ($expense as $x) {
                $out[] = '- ' . $x['name'];
            }
        }
        $out[] = '';
        $out[] = '#### מיפוי פנימי (רק למילוי בבלוק [[ACTION]] — לא להעתיק לטקסט המשתמש)';
        $out[] = 'שורה אחת לכל קטגוריה; השתמש ב־`category_id` המתאים רק בתוך JSON של [[ACTION]], לא בהסבר חופשי.';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            $name = trim((string) ($r['name'] ?? ''));
            $type = trim((string) ($r['type'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $out[] = '- סוג `' . $type . '` | שם: ' . $name . ' | category_id למילוי JSON: ' . $id;
        }

        return implode("\n", $out);
    }
}

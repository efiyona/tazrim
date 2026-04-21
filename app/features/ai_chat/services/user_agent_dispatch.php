<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/user_preferences_repository.php';

if (!function_exists('ai_user_agent_touch_ledger_after_insert')) {
    function ai_user_agent_touch_ledger_after_insert(mysqli $conn, int $homeId, string $type, float $amountIls, string $transactionDate): void
    {
        if (!defined('ROOT_PATH')) {
            require_once dirname(__DIR__, 4) . '/path.php';
        }
        require_once ROOT_PATH . '/app/functions/home_bank_balance.php';
        global $today_il;
        $today = isset($today_il) ? (string) $today_il : date('Y-m-d');
        $newRow = [
            'type' => $type,
            'amount' => $amountIls,
            'transaction_date' => $transactionDate,
        ];
        tazrim_after_transaction_row_change($conn, $homeId, null, $newRow, $today);
    }
}

if (!function_exists('ai_user_agent_maybe_budget_push')) {
    function ai_user_agent_maybe_budget_push(int $homeId, int $categoryId, string $type): void
    {
        if ($type !== 'expense' || $categoryId <= 0) {
            return;
        }
        if (!defined('ROOT_PATH')) {
            require_once dirname(__DIR__, 4) . '/path.php';
        }
        require_once ROOT_PATH . '/app/functions/budget_overrun_push.php';
        maybeSendBudgetOverrunPush($homeId, $categoryId);
    }
}

if (!function_exists('ai_user_agent_dispatch')) {
    /**
     * @param array<string,mixed> $action
     * @return array{ok:bool,message?:string}
     */
    function ai_user_agent_dispatch(mysqli $conn, int $homeId, int $userId, array $action): array
    {
        $kind = strtolower(trim((string) ($action['kind'] ?? $action['action'] ?? '')));
        if ($kind === '') {
            return ['ok' => false, 'message' => 'סוג פעולה חסר'];
        }

        if ($kind === 'save_user_preference') {
            $key = trim((string) ($action['pref_key'] ?? $action['key'] ?? ''));
            $val = trim((string) ($action['pref_value'] ?? $action['value'] ?? ''));
            if (!ai_user_pref_allowed_key($key)) {
                return ['ok' => false, 'message' => 'מפתח לא מאושר לשמירה'];
            }
            if ($val === '') {
                return ['ok' => false, 'message' => 'ערך ריק'];
            }
            if (!ai_user_pref_upsert($conn, $userId, $key, $val)) {
                return ['ok' => false, 'message' => 'לא ניתן לשמור את ההעדפה'];
            }

            return ['ok' => true, 'message' => 'נשמר בהצלחה'];
        }

        if ($kind === 'update_user_nickname') {
            $nick = trim((string) ($action['nickname'] ?? ''));
            if ($nick === '') {
                return ['ok' => false, 'message' => 'כינוי ריק'];
            }
            $len = function_exists('mb_strlen') ? mb_strlen($nick, 'UTF-8') : strlen($nick);
            if ($len > 80) {
                return ['ok' => false, 'message' => 'כינוי ארוך מדי'];
            }
            $stmt = $conn->prepare('UPDATE users SET nickname = ? WHERE id = ? LIMIT 1');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'שגיאת מסד'];
            }
            $stmt->bind_param('si', $nick, $userId);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['ok' => false, 'message' => 'לא ניתן לעדכן את הכינוי'];
            }
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['nickname'] = $nick;

            return ['ok' => true, 'message' => 'הכינוי עודכן במערכת'];
        }

        if ($kind === 'create_category') {
            $name = trim((string) ($action['name'] ?? ''));
            $type = (string) ($action['type'] ?? 'expense');
            if ($type !== 'expense' && $type !== 'income') {
                return ['ok' => false, 'message' => 'סוג קטגוריה לא תקין'];
            }
            $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
            if ($len < 1 || $len > 100) {
                return ['ok' => false, 'message' => 'שם הקטגוריה לא תקין'];
            }
            $budget = isset($action['budget_limit']) ? (float) $action['budget_limit'] : 0.0;
            if ($budget < 0 || $budget > 99999999) {
                return ['ok' => false, 'message' => 'תקציב לא תקין'];
            }
            $icon = trim((string) ($action['icon'] ?? ''));
            if ($icon === '' || !preg_match('/^fa-[a-z0-9-]{1,40}$/', $icon)) {
                $icon = 'fa-tag';
            }
            $dupStmt = $conn->prepare('SELECT id FROM categories WHERE home_id = ? AND name = ? AND is_active = 1 LIMIT 1');
            if (!$dupStmt) {
                return ['ok' => false, 'message' => 'שגיאת מסד'];
            }
            $dupStmt->bind_param('is', $homeId, $name);
            $dupStmt->execute();
            $dupRes = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
            if ($dupRes) {
                return ['ok' => false, 'message' => 'כבר קיימת קטגוריה בשם הזה. בחרו שם אחר או השתמשו בקטגוריה הקיימת.'];
            }

            $init = $action['initial_transaction'] ?? null;
            $hasInit = is_array($init);

            $conn->begin_transaction();
            try {
                $insCat = $conn->prepare('INSERT INTO categories (home_id, name, type, budget_limit, icon, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                if (!$insCat) {
                    throw new RuntimeException('prepare_category');
                }
                $insCat->bind_param('issds', $homeId, $name, $type, $budget, $icon);
                if (!$insCat->execute()) {
                    $insCat->close();
                    throw new RuntimeException('insert_category');
                }
                $insCat->close();
                $newCatId = (int) $conn->insert_id;
                if ($newCatId <= 0) {
                    throw new RuntimeException('no_category_id');
                }

                if ($hasInit) {
                    $amount = (float) ($init['amount'] ?? 0);
                    $description = trim((string) ($init['description'] ?? ''));
                    $transactionDate = trim((string) ($init['transaction_date'] ?? date('Y-m-d')));
                    if ($amount <= 0 || $description === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
                        throw new RuntimeException('bad_init_tx');
                    }
                    $currency = 'ILS';
                    $txStmt = $conn->prepare('INSERT INTO transactions (home_id, user_id, type, amount, currency_code, category, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    if (!$txStmt) {
                        throw new RuntimeException('prepare_tx');
                    }
                    $txStmt->bind_param('iisdsiss', $homeId, $userId, $type, $amount, $currency, $newCatId, $description, $transactionDate);
                    if (!$txStmt->execute()) {
                        $txStmt->close();
                        throw new RuntimeException('insert_tx');
                    }
                    $txStmt->close();
                }

                $conn->commit();
            } catch (Throwable $e) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                }

                return ['ok' => false, 'message' => 'לא ניתן ליצור את הקטגוריה. נסו שוב.'];
            }

            if ($hasInit) {
                $amount = (float) ($init['amount'] ?? 0);
                $transactionDate = trim((string) ($init['transaction_date'] ?? date('Y-m-d')));
                ai_user_agent_touch_ledger_after_insert($conn, $homeId, $type, $amount, $transactionDate);
                ai_user_agent_maybe_budget_push($homeId, $newCatId, $type);

                return ['ok' => true, 'message' => 'נוצרה קטגוריה חדשה ונרשמה פעולה ראשונה'];
            }

            return ['ok' => true, 'message' => 'נוצרה קטגוריה חדשה'];
        }

        if ($kind === 'create_transaction') {
            $type = (string) ($action['type'] ?? 'expense');
            if ($type !== 'expense' && $type !== 'income') {
                return ['ok' => false, 'message' => 'סוג פעולה לא תקין'];
            }
            $amount = (float) ($action['amount'] ?? 0);
            $categoryId = (int) ($action['category_id'] ?? 0);
            $description = trim((string) ($action['description'] ?? ''));
            $transactionDate = trim((string) ($action['transaction_date'] ?? date('Y-m-d')));
            if ($amount <= 0 || $categoryId <= 0 || $description === '') {
                return ['ok' => false, 'message' => 'נתונים חסרים לפעולה'];
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
                return ['ok' => false, 'message' => 'תאריך לא תקין'];
            }
            $catStmt = $conn->prepare('SELECT id, type FROM categories WHERE id = ? AND home_id = ? AND is_active = 1 LIMIT 1');
            if (!$catStmt) {
                return ['ok' => false, 'message' => 'שגיאת מסד'];
            }
            $catStmt->bind_param('ii', $categoryId, $homeId);
            $catStmt->execute();
            $catRow = $catStmt->get_result()->fetch_assoc();
            $catStmt->close();
            if (!$catRow || (string) $catRow['type'] !== $type) {
                return ['ok' => false, 'message' => 'קטגוריה לא תואמת לסוג הפעולה'];
            }
            $currency = 'ILS';
            $stmt = $conn->prepare('INSERT INTO transactions (home_id, user_id, type, amount, currency_code, category, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'שגיאת מסד'];
            }
            $stmt->bind_param('iisdsiss', $homeId, $userId, $type, $amount, $currency, $categoryId, $description, $transactionDate);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['ok' => false, 'message' => 'לא ניתן ליצור את הפעולה'];
            }

            ai_user_agent_touch_ledger_after_insert($conn, $homeId, $type, (float) $amount, $transactionDate);
            ai_user_agent_maybe_budget_push($homeId, $categoryId, $type);

            return ['ok' => true, 'message' => 'הפעולה נרשמה'];
        }

        return ['ok' => false, 'message' => 'פעולה לא נתמכת'];
    }
}

if (!function_exists('ai_user_agent_log_hitl')) {
    function ai_user_agent_log_hitl(mysqli $conn, int $userId, int $homeId, int $chatId, string $proposalType, string $outcome): void
    {
        if (!in_array($outcome, ['ACCEPTED', 'REJECTED'], true)) {
            return;
        }
        $stmt = $conn->prepare('INSERT INTO ai_user_hitl_events (user_id, home_id, chat_id, proposal_type, outcome) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('iiiss', $userId, $homeId, $chatId, $proposalType, $outcome);
        $stmt->execute();
        $stmt->close();
    }
}

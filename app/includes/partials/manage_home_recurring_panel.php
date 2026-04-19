<?php
/**
 * תוכן פנימי של #manage-home-recurring-panel — פעולות קבועות בעמוד ניהול הבית.
 * דורש: $recurring_expenses, $recurring_income (מערכים של שורות recurring_transactions עם cat_name, cat_icon).
 */
require_once ROOT_PATH . '/app/functions/currency.php';
$recurring_expenses = $recurring_expenses ?? [];
$recurring_income = $recurring_income ?? [];
?>
<div class="manage-categories-toolbar">
    <h2 class="section-subtitle" style="margin: 0;">פעולות קבועות</h2>
    <button type="button" class="btn-primary" style="width: max-content; margin: 0; padding: 8px 20px; font-size: 0.95rem; box-shadow: 0 4px 10px rgba(35, 114, 39, 0.2);" onclick="openAddRecurringModal()">
        הוספה <i class="fa-solid fa-plus"></i>
    </button>
</div>

<div class="card-header" style="margin-bottom: 12px;">
    <h3>הוצאות</h3>
</div>
<div id="manage-recurring-expense-list">
    <?php if (count($recurring_expenses) === 0): ?>
        <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
            <i class="fa-solid fa-rotate" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-light); margin: 0;">אין הוצאות קבועות. הוסיפו פעולה או סמנו פעולה חדשה כקבועה מהמסך הראשי.</p>
        </div>
    <?php else: ?>
        <?php foreach ($recurring_expenses as $rec):
            $cat_icon = !empty($rec['cat_icon']) ? $rec['cat_icon'] : 'fa-tag';
            $desc = $rec['description'];
            $cat_name = $rec['cat_name'] ?? '';
            $currency_code = tazrim_normalize_currency_code($rec['currency_code'] ?? 'ILS');
        ?>
            <div class="transaction-item expense"
                onclick='openEditRecurringModal(<?php echo (int) $rec['id']; ?>, <?php echo json_encode($desc, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode((float) $rec['amount']); ?>, <?php echo json_encode($rec['type']); ?>, <?php echo (int) $rec['category']; ?>, <?php echo (int) $rec['day_of_month']; ?>, <?php echo json_encode($currency_code); ?>)'
                style="cursor: pointer;">
                <div class="transaction-info">
                    <div class="cat-icon-wrapper">
                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="date"><?php echo 'כל ' . (int) $rec['day_of_month'] . ' בחודש'; ?><?php echo $cat_name !== '' ? ' · ' . htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8') : ''; ?></span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div class="transaction-amount" style="color: var(--error); font-weight: 700;">
                        <?php echo htmlspecialchars(tazrim_format_money((float) $rec['amount'], $currency_code, 0), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="transaction-row-actions">
                        <div class="transaction-action-pill" title="ערוך">
                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteRecurring(<?php echo (int) $rec['id']; ?>)" class="transaction-action-pill transaction-action-pill--danger" title="מחק">
                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card-header" style="margin-top: 24px; margin-bottom: 12px;">
    <h3>הכנסות</h3>
</div>
<div id="manage-recurring-income-list">
    <?php if (count($recurring_income) === 0): ?>
        <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
            <i class="fa-solid fa-rotate" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-light); margin: 0;">אין הכנסות קבועות.</p>
        </div>
    <?php else: ?>
        <?php foreach ($recurring_income as $rec):
            $cat_icon = !empty($rec['cat_icon']) ? $rec['cat_icon'] : 'fa-tag';
            $desc = $rec['description'];
            $cat_name = $rec['cat_name'] ?? '';
            $currency_code = tazrim_normalize_currency_code($rec['currency_code'] ?? 'ILS');
        ?>
            <div class="transaction-item income"
                onclick='openEditRecurringModal(<?php echo (int) $rec['id']; ?>, <?php echo json_encode($desc, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode((float) $rec['amount']); ?>, <?php echo json_encode($rec['type']); ?>, <?php echo (int) $rec['category']; ?>, <?php echo (int) $rec['day_of_month']; ?>, <?php echo json_encode($currency_code); ?>)'
                style="cursor: pointer;">
                <div class="transaction-info" style="flex: 1; min-width: 0;">
                    <div class="cat-icon-wrapper">
                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="date"><?php echo 'כל ' . (int) $rec['day_of_month'] . ' בחודש'; ?><?php echo $cat_name !== '' ? ' · ' . htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8') : ''; ?></span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div class="transaction-amount" style="color: var(--success); font-weight: 700;">
                        <?php echo htmlspecialchars(tazrim_format_money((float) $rec['amount'], $currency_code, 0), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="transaction-row-actions">
                        <div class="transaction-action-pill" title="ערוך">
                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteRecurring(<?php echo (int) $rec['id']; ?>)" class="transaction-action-pill transaction-action-pill--danger" title="מחק">
                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

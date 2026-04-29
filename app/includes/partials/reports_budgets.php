<?php
/**
 * תוכן פנימי של #budgets-progress-container — מצב ריק או רשימת תקציבים.
 * דורש: $budgets (מערך, אפשר ריק).
 */
?>
<?php if (empty($budgets)): ?>
    <div style="text-align: center; color: #888; padding: 30px 0;">
        <i class="fa-solid fa-clipboard-check" style="font-size: 2rem; margin-bottom: 10px; color: #ccc;"></i><br>
        לא הוגדרו יעדי תקציב לחודש זה.
    </div>
<?php else: ?>
    <?php foreach ($budgets as $b):
        $limit = (float) ($b['budget_limit'] ?? 0);
        $spent = (float) ($b['spent'] ?? 0);
        $percent = $limit > 0 ? ($spent / $limit) * 100 : 0;
        $percent_clamped = min($percent, 100);
        $color = 'var(--success)';
        if ($percent >= 90) {
            $color = 'var(--error)';
        } elseif ($percent >= 75) {
            $color = '#f59e0b';
        }
    ?>
        <div class="budget-item">
            <div class="budget-header">
                <span><i class="fa-solid <?php echo htmlspecialchars($b['icon'] ?: 'fa-tag', ENT_QUOTES, 'UTF-8'); ?>" style="color: #888; margin-left: 5px;"></i> <?php echo htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span style="font-size: 0.85rem; color: #666;"><strong style="color: var(--text);"><?php echo number_format($spent); ?></strong> / <?php echo number_format($limit); ?> ₪</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?php echo $percent_clamped; ?>%; background-color: <?php echo $color; ?>;"></div>
            </div>
            <div style="font-size: 0.75rem; color: <?php echo $color; ?>; font-weight: 700; margin-top: 4px;">
                <?php echo number_format($percent, 1); ?>% נוצל
                <?php if ($percent > 100) {
                    echo ' (חריגה!)';
                } ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

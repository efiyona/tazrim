<?php
/**
 * תוכן פנימי של #manage-home-categories-panel — ניהול קטגוריות בעמוד ניהול הבית.
 * דורש: $expenses_cats, $income_cats (מערכים אסוציאטיביים של שורות categories).
 */
$expenses_cats = $expenses_cats ?? [];
$income_cats = $income_cats ?? [];
?>
<div class="manage-categories-toolbar">
    <h2 class="section-subtitle" style="margin: 0;">קטגוריות ותקציב</h2>
    <button type="button" class="btn-primary" style="width: max-content; margin: 0; padding: 8px 20px; font-size: 0.95rem; box-shadow: 0 4px 10px rgba(35, 114, 39, 0.2);" onclick="openAddCategoryModal()">
    הוספה <i class="fa-solid fa-plus"></i>
    </button>
</div>

<div class="card-header" style="margin-bottom: 12px;">
    <h3>הוצאות</h3>
</div>
<div id="manage-expense-categories-list">
    <?php if (count($expenses_cats) === 0): ?>
        <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-light); margin: 0;">אין קטגוריות הוצאה. הוסיפו קטגוריה חדשה.</p>
        </div>
    <?php else: ?>
        <?php foreach ($expenses_cats as $cat):
            $cat_icon = $cat['icon'] ?: 'fa-tag';
            $has_budget = ((float) $cat['budget_limit']) > 0;
        ?>
            <div class="transaction-item expense"
                onclick='openEditCategoryModal(<?php echo (int) $cat['id']; ?>, <?php echo json_encode($cat['name'], JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode((float) $cat['budget_limit']); ?>, <?php echo json_encode($cat['type']); ?>, <?php echo json_encode($cat_icon); ?>)'
                style="cursor: pointer;">
                <div class="transaction-info">
                    <div class="cat-icon-wrapper">
                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="date"><?php echo $has_budget ? 'מוגבל לחודש' : 'ללא תקציב מוגדר'; ?></span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div class="transaction-amount"<?php if (!$has_budget) {
                        echo ' style="color: var(--text-light); font-weight: 600; font-size: 1rem;"';
                    } ?>>
                        <?php if ($has_budget): ?>
                            <?php echo number_format((float) $cat['budget_limit'], 0); ?> ₪
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="transaction-row-actions">
                        <div class="transaction-action-pill" title="ערוך קטגוריה">
                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteCategory(<?php echo (int) $cat['id']; ?>)" class="transaction-action-pill transaction-action-pill--danger" title="מחק קטגוריה">
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
<div id="manage-income-categories-list">
    <?php if (count($income_cats) === 0): ?>
        <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-light); margin: 0;">אין קטגוריות הכנסה. הוסיפו קטגוריה חדשה.</p>
        </div>
    <?php else: ?>
        <?php foreach ($income_cats as $cat):
            $cat_icon = $cat['icon'] ?: 'fa-tag';
        ?>
            <div class="transaction-item income"
                onclick='openEditCategoryModal(<?php echo (int) $cat['id']; ?>, <?php echo json_encode($cat['name'], JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode((float) $cat['budget_limit']); ?>, <?php echo json_encode($cat['type']); ?>, <?php echo json_encode($cat_icon); ?>)'
                style="cursor: pointer;">
                <div class="transaction-info" style="flex: 1; min-width: 0;">
                    <div class="cat-icon-wrapper">
                        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div class="transaction-row-actions">
                        <div class="transaction-action-pill" title="ערוך קטגוריה">
                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteCategory(<?php echo (int) $cat['id']; ?>)" class="transaction-action-pill transaction-action-pill--danger" title="מחק קטגוריה">
                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

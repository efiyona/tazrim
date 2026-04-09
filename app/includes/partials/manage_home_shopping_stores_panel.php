<?php
/**
 * תוכן פנימי של #manage-home-shopping-stores-panel — ניהול חנויות רשימת קניות.
 * דורש: $shopping_stores (מערך של שורות shopping_categories).
 */
$shopping_stores = $shopping_stores ?? [];
?>
<div class="manage-categories-toolbar">
    <h2 class="section-subtitle" style="font-weight: 800; font-size: 1.4rem; margin: 0; color: var(--text);">חנויות לרשימת קניות</h2>
    <button type="button" class="btn-primary" style="width: max-content; margin: 0; padding: 8px 20px; font-size: 0.95rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(35, 114, 39, 0.2);" onclick="openAddShoppingStoreModal()">
        הוספה <i class="fa-solid fa-plus"></i>
    </button>
</div>

<div class="card-header" style="margin-bottom: 12px;">
    <h3>ניהול חנויות</h3>
</div>
<div id="manage-shopping-stores-list">
    <?php if (count($shopping_stores) === 0): ?>
        <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
            <i class="fa-solid fa-store-slash" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-light); margin: 0;">אין חנויות עדיין. הוסיפו חנות ראשונה לרשימת הקניות.</p>
        </div>
    <?php else: ?>
        <?php foreach ($shopping_stores as $store):
            $store_icon = $store['icon'] ?: 'fa-cart-shopping';
        ?>
            <div class="transaction-item expense"
                onclick='openEditShoppingStoreModal(<?php echo (int) $store['id']; ?>, <?php echo json_encode($store['name'], JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($store_icon); ?>)'
                style="cursor: pointer;">
                <div class="transaction-info">
                    <div class="cat-icon-wrapper">
                        <i class="fa-solid <?php echo htmlspecialchars($store_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div style="display:flex; gap: 5px;">
                        <div style="background: var(--gray); color: var(--text); padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;" title="ערוך חנות">
                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); deleteShoppingStore(<?php echo (int) $store['id']; ?>)" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק חנות">
                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

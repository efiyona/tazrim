<?php
/** @var mysqli_result|false $pending_result */
/** @var mysqli_result|false $recent_result */
/** @var mysqli_result|false $result_categories */
?>
                <div class="kpi-grid kpi-grid--home">
                    <div class="kpi-card kpi-card--home kpi-card--income">
                        <div class="kpi-card__info-corner">
                            <?php
                                $info_label = "הכנסות החודש";
                                $info_key = "month_income";
                                include ROOT_PATH . '/assets/includes/info_label.php';
                            ?>
                        </div>
                        <div class="kpi-card__body">
                            <div class="kpi-card__head">
                                <span class="kpi-card__icon-wrap" aria-hidden="true">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                </span>
                                <span class="kpi-card__label">הכנסות</span>
                            </div>
                            <div class="kpi-amount kpi-card__value success-text"><?php echo number_format($total_income) . '₪'; ?>+</div>
                            <div class="cat-card-footer kpi-card__footer">
                                <button type="button" class="btn-cat-details" onclick="loadTypeDetails('income')">
                                    פירוט <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="kpi-card kpi-card--home kpi-card--expense">
                        <div class="kpi-card__info-corner">
                            <?php
                                $info_label = "הוצאות החודש";
                                $info_key = "month_expenses";
                                include ROOT_PATH . '/assets/includes/info_label.php';
                            ?>
                        </div>
                        <div class="kpi-card__body">
                            <div class="kpi-card__head">
                                <span class="kpi-card__icon-wrap" aria-hidden="true">
                                    <i class="fa-solid fa-arrow-trend-down"></i>
                                </span>
                                <span class="kpi-card__label">הוצאות</span>
                            </div>
                            <div class="kpi-amount kpi-card__value error-text"><?php echo number_format($total_expense) . '₪'; ?>-</div>
                            <div class="cat-card-footer kpi-card__footer">
                                <button type="button" class="btn-cat-details" onclick="loadTypeDetails('expense')">
                                    פירוט <i class="fa-solid fa-chevron-left"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if ($initial_balance != 0): ?>
                        <div class="kpi-card kpi-card--home kpi-card--balance kpi-card--span-full">
                            <div class="kpi-card__info-corner">
                                <?php
                                    $info_label = "יתרה בחשבון";
                                    $info_key = "real_balance";
                                    include ROOT_PATH . '/assets/includes/info_label.php';
                                ?>
                            </div>
                            <div class="kpi-card__body">
                                <div class="kpi-card__head">
                                    <span class="kpi-card__icon-wrap" aria-hidden="true">
                                        <i class="fa-solid fa-wallet"></i>
                                    </span>
                                    <span class="kpi-card__label">יתרה בחשבון</span>
                                </div>
                                <div class="kpi-amount kpi-card__value <?php echo $current_bank_balance >= 0 ? 'success-text' : 'error-text'; ?>"><?php echo number_format($current_bank_balance, 0) . '₪'; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($pending_result && mysqli_num_rows($pending_result) > 0): ?>
                <div class="transactions-section" style="margin-bottom: 30px;">
                    <h2 class="section-subtitle" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        פעולות ממתינות
                    </h2>

                    <div id="pending-transactions-list">
                        <?php while ($row = mysqli_fetch_assoc($pending_result)): ?>
                           <div class="transaction-item <?php echo $row['type']; ?> <?php echo (strtotime($row['transaction_date']) > strtotime($today_il)) ? 'pending-trans' : ''; ?>"
                            onclick="openEditTransModal(<?php echo (int) $row['id']; ?>, <?php echo (float) $row['amount']; ?>, <?php echo (int) $row['category']; ?>, '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>', '<?php echo $row['type']; ?>', 'main')"
                            style="cursor: pointer;">
                                <div class="transaction-info">
                                    <div class="cat-icon-wrapper">
                                        <i class="fa-regular fa-clock"></i> </div>
                                    <div class="details">
                                        <span class="desc">
                                            <?php echo htmlspecialchars($row['description']); ?>
                                            <?php if ($row['user_name']) {
                                                echo "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>(" . htmlspecialchars($row['user_name']) . ")</span>";
                                            } ?>
                                        </span>
                                        <span class="date"><?php echo date('d/m/Y', strtotime($row['transaction_date'])); ?></span>
                                    </div>
                                </div>
                                <div class="transaction-actions">
                                    <div class="transaction-amount">
                                        <?php echo ($row['type'] == 'income') ? '+' : '-'; ?> <?php echo number_format($row['amount'], 0); ?> ₪
                                    </div>
                                    <div style="display:flex; gap: 5px;">
                                        <div style="background: var(--gray); color: var(--text); padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;" title="ערוך פעולה">
                                            <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                                        </div>
                                        <button type="button" onclick="event.stopPropagation(); deleteTransaction(<?php echo (int) $row['id']; ?>, 'main')" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק פעולה">
                                            <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if (!empty($has_more_pending)): ?>
                        <button type="button" id="loadMorePendingBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                        עוד ממתינות
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>


                <div class="transactions-section">
                    <h2 class="section-subtitle" style="margin-bottom: 20px;">פעולות אחרונות</h2>

                    <div id="recent-transactions-list">
                        <?php if ($recent_result && mysqli_num_rows($recent_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                                <div class="transaction-item <?php echo $row['type']; ?> <?php echo (strtotime($row['transaction_date']) > strtotime($today_il)) ? 'pending-trans' : ''; ?>"
                                onclick="openEditTransModal(<?php echo (int) $row['id']; ?>, <?php echo (float) $row['amount']; ?>, <?php echo (int) $row['category']; ?>, '<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>', '<?php echo $row['type']; ?>', 'main')"
                                style="cursor: pointer;">
                                    <div class="transaction-info">
                                        <div class="cat-icon-wrapper">
                                            <i class="fa-solid <?php echo $row['cat_icon'] ?: 'fa-tag'; ?>"></i> </div>
                                        <div class="details">
                                            <span class="desc">
                                                <?php echo htmlspecialchars($row['description']); ?>
                                                <?php if ($row['user_name']) {
                                                    echo "<span style='font-size: 0.75rem; color: #888; font-weight: normal; margin-right: 5px;'>(" . htmlspecialchars($row['user_name']) . ")</span>";
                                                } ?>
                                            </span>
                                            <span class="date"><?php echo date('d/m/Y', strtotime($row['transaction_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="transaction-actions">
                                        <div class="transaction-amount">
                                            <?php echo ($row['type'] == 'income') ? '+' : '-'; ?> <?php echo number_format($row['amount'], 0); ?> ₪
                                        </div>
                                        <div style="display:flex; gap: 5px;">
                                            <div style="background: var(--gray); color: var(--text); padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;" title="ערוך פעולה">
                                                <i class="fa-solid fa-pen" style="font-size: 0.9rem;"></i>
                                            </div>
                                            <button type="button" onclick="event.stopPropagation(); deleteTransaction(<?php echo (int) $row['id']; ?>, 'main')" style="background: #fee2e2; border: none; color: #dc2626; cursor: pointer; padding: 8px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center;" title="מחק פעולה">
                                                <i class="fa-solid fa-trash-can" style="font-size: 1rem;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state text-center" style="padding: 40px; background: var(--white); border-radius: 15px;">
                                <i class="fa-solid fa-receipt" style="font-size: 3rem; color: var(--gray); margin-bottom: 15px; display: block;"></i>
                                <p style="color: var(--text-light);">עדיין אין פעולות. לחצו על ה- + כדי להתחיל.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($has_more_recent)): ?>
                        <button type="button" id="loadMoreBtn" class="btn-load-more w-full" style="margin-top: 20px;">
                        עוד אחרונות
                        </button>
                    <?php endif; ?>
                </div>

                <section class="budget-section">
                    <h2 class="section-subtitle" style="margin: 30px 0 20px;">קטגוריות</h2>

                    <div class="category-grid">
                        <?php if ($result_categories) {
                            while ($cat = mysqli_fetch_assoc($result_categories)):
                            $budget = $cat['budget_limit'];
                            $spent = $cat['current_spending'];

                            if ($spent == 0) {
                                continue;
                            }

                            $percent = ($budget > 0) ? min(($spent / $budget) * 100, 100) : 0;
                            $is_over_budget = ($budget > 0 && $spent > $budget);
                        ?>
                            <div class="category-card <?php echo $is_over_budget ? 'over-budget' : ''; ?>">
                                <div class="cat-card-header">
                                    <div class="cat-icon-circle">
                                        <i class="fa-solid <?php echo $cat['icon'] ?: 'fa-tag'; ?>"></i>
                                    </div>
                                    <span class="cat-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                                </div>

                                <div class="cat-card-body">

                                    <div class="spending-info">
                                        <span class="spent-amount"><?php echo number_format($spent, 0); ?> ₪</span>
                                        <span class="budget-total">
                                            <?php echo ($budget > 0) ? "מתוך " . number_format($budget, 0) . " ₪" : "אין תקציב מוגדר"; ?>
                                        </span>
                                    </div>

                                    <?php if ($budget > 0): ?>
                                        <div class="percent-label" style="text-align: left; font-size: 0.8rem; font-weight: 700; margin-bottom: 4px; color: <?php echo $is_over_budget ? 'var(--error)' : 'var(--main)'; ?>;">
                                            <?php
                                                $real_percent = round(($spent / $budget) * 100);
                                                echo $real_percent . "%";
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?php echo ($budget > 0) ? min($percent, 100) : '0'; ?>%;"></div>
                                    </div>
                                </div>

                                <div class="cat-card-footer" style="margin-top: 15px; border-top: 1px solid #f0f0f0; padding-top: 10px;">
                                    <button type="button" class="btn-cat-details" onclick="loadCategoryDetails(<?php echo (int) $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">
                                        פירוט <i class="fa-solid fa-chevron-left" style="font-size: 0.7rem; margin-right: 5px;"></i>
                                    </button>
                                </div>

                            </div>
                        <?php endwhile;
                        } ?>
                    </div>
                </section>

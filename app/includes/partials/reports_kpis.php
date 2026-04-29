<?php
/**
 * KPI Cards לדוחות — סה"כ הכנסות / הוצאות / מאזן / ממוצע יומי.
 * דורש: $total_income, $total_expenses, $balance, $daily_avg.
 */
?>
<div class="kpi-grid kpi-grid--home" id="reports-kpis">
    <div class="kpi-card kpi-card--home kpi-card--income">
        <div class="kpi-card__body">
            <div class="kpi-card__head">
                <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-arrow-trend-up"></i></span>
                <span class="kpi-card__label">סה"כ הכנסות</span>
            </div>
            <div class="kpi-amount kpi-card__value success-text"><?php echo number_format((float) $total_income) . '₪'; ?>+</div>
        </div>
    </div>

    <div class="kpi-card kpi-card--home kpi-card--expense">
        <div class="kpi-card__body">
            <div class="kpi-card__head">
                <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
                <span class="kpi-card__label">סה"כ הוצאות</span>
            </div>
            <div class="kpi-amount kpi-card__value error-text"><?php echo number_format((float) $total_expenses) . '₪'; ?>-</div>
        </div>
    </div>

    <div class="kpi-card kpi-card--home kpi-card--reports-balance">
        <div class="kpi-card__body">
            <div class="kpi-card__head">
                <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                <span class="kpi-card__label">מאזן החודש</span>
            </div>
            <div class="kpi-amount kpi-card__value <?php echo $balance >= 0 ? 'success-text' : 'error-text'; ?>">
                <span dir="ltr"><?php echo $balance < 0 ? '-' : '+'; ?><?php echo number_format(abs((float) $balance)); ?>₪</span>
            </div>
        </div>
    </div>

    <div class="kpi-card kpi-card--home kpi-card--daily-avg">
        <div class="kpi-card__body">
            <div class="kpi-card__head">
                <span class="kpi-card__icon-wrap" aria-hidden="true"><i class="fa-regular fa-calendar-days"></i></span>
                <span class="kpi-card__label">ממוצע הוצאה יומית</span>
            </div>
            <div class="kpi-amount kpi-card__value kpi-card__value--amber"><?php echo number_format((float) $daily_avg) . '₪'; ?></div>
        </div>
    </div>
</div>

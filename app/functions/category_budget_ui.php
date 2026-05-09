<?php
/**
 * צבעי UI ליחס בין סכום בפועל ליעד — עבור קטגוריות הכנסה (ככל שהרחק מיעד = בעייתי).
 */
function tazrim_income_goal_progress_color(float $amount, float $budget_limit): string
{
    if ($budget_limit <= 0) {
        return 'var(--main)';
    }
    $ratio = $amount / $budget_limit;
    if ($ratio < 0.75) {
        return 'var(--error)';
    }
    if ($ratio < 0.90) {
        return '#f59e0b';
    }
    return 'var(--success)';
}

<?php

/**
 * נירמול טלפון להשוואת ייחודיות (ספרות בלבד; תחילת 972 → 0).
 */
function tazrim_normalize_phone_key($phone)
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '';
    }
    $digits = preg_replace('/\D+/u', '', $phone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) >= 11 && strpos($digits, '972') === 0) {
        $digits = '0' . substr($digits, 3);
    }

    return $digits;
}

/**
 * מחזיר מזהה משתמש אחר עם אותו טלפון מנורמל, או null אם אין סתירה.
 *
 * @param string $normalizedKey תוצאת tazrim_normalize_phone_key
 * @param int|null $excludeUserId לעדכון פרופיל — להתעלם מהמשתמש הנוכחי
 * @return int|null
 */
function tazrim_user_id_with_normalized_phone($normalizedKey, $excludeUserId = null)
{
    if ($normalizedKey === '' || !function_exists('selectAll')) {
        return null;
    }

    $users = selectAll('users');
    if (!is_array($users) || $users === []) {
        return null;
    }

    foreach ($users as $u) {
        $p = isset($u['phone']) ? trim((string) $u['phone']) : '';
        if ($p === '') {
            continue;
        }
        if (tazrim_normalize_phone_key($p) !== $normalizedKey) {
            continue;
        }
        $uid = (int) ($u['id'] ?? 0);
        if ($excludeUserId !== null && $uid === (int) $excludeUserId) {
            continue;
        }
        if ($uid > 0) {
            return $uid;
        }
    }

    return null;
}

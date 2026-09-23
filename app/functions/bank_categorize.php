<?php
// Smart category guess for bank-imported rows -> category id for the home, or null.
function tazrim_bank_suggest_category(mysqli $conn, int $home_id, string $desc, string $type): ?int {
    static $cache = [];
    $find = function (string $name) use ($conn, $home_id, $type, &$cache): ?int {
        $key = $home_id . '|' . $type . '|' . $name;
        if (!array_key_exists($key, $cache)) {
            $q = mysqli_prepare($conn, "SELECT id FROM categories WHERE home_id = ? AND name = ? AND type = ? AND is_active = 1 LIMIT 1");
            mysqli_stmt_bind_param($q, 'iss', $home_id, $name, $type);
            mysqli_stmt_execute($q);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
            $cache[$key] = $r ? (int)$r['id'] : null;
        }
        return $cache[$key];
    };
    if ($type === 'income') return $find('החזרים');
    if (mb_strpos($desc, 'ביטוח') !== false) return $find('ביטוחים');
    if (mb_strpos($desc, 'משכנתא') !== false) return $find('חשבונות הבית');
    if (mb_strpos($desc, 'דירקט') !== false) return $find('שונות');
    return null;
}

<?php
/**
 * הרצה חד-פעמית: מיגרציית יתרת בנק מ־initial_balance ל־bank_balance_* + DROP initial_balance.
 *
 * מתוך שורש הפרויקט:
 *   php scripts/migrate_homes_bank_balance_once.php
 *
 * או העלה זמנית לשרת ופתח בדפדפן (מחק אחרי שימוש).
 */
declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/secrets.php';
require_once ROOT_PATH . '/app/database/db.php';

global $conn;
if (!$conn) {
    fwrite(STDERR, "שגיאה: אין חיבור למסד.\n");
    exit(1);
}

$result = tazrim_run_homes_bank_balance_data_migration($conn);
$out = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
if (PHP_SAPI === 'cli') {
    echo $out;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $out;
}
exit(empty($result['ok']) ? 1 : 0);

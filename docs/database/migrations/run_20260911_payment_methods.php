<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 3) . '/path.php';
require ROOT_PATH . '/app/database/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$sqlFile = __DIR__ . '/20260911_payment_methods.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') throw new RuntimeException('migration_file_unreadable');
$before = [];
foreach (['transactions','recurring_transactions'] as $table) {
    $r=$conn->query("SELECT COUNT(*) c FROM `$table`"); $before[$table]=(int)$r->fetch_assoc()['c'];
}
$conn->multi_query($sql);
do { if ($result=$conn->store_result()) $result->free(); } while ($conn->more_results() && $conn->next_result());
$verify=[];
$r=$conn->query("SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_methods'");$verify['table']=(int)$r->fetch_assoc()['c'];
$r=$conn->query("SELECT TABLE_NAME,COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('transactions','recurring_transactions') AND COLUMN_NAME='payment_method_id' GROUP BY TABLE_NAME");while($row=$r->fetch_assoc())$verify['columns'][$row['TABLE_NAME']]=(int)$row['c'];
$r=$conn->query("SELECT COUNT(*) homes FROM homes");$verify['homes']=(int)$r->fetch_assoc()['homes'];
$r=$conn->query("SELECT COUNT(DISTINCT home_id) homes_with_active FROM payment_methods WHERE is_active=1");$verify['homes_with_active']=(int)$r->fetch_assoc()['homes_with_active'];
$r=$conn->query("SELECT COUNT(*) bank_rows FROM payment_methods WHERE type='bank_transfer' AND name='בנק' AND is_active=1");$verify['bank_rows']=(int)$r->fetch_assoc()['bank_rows'];
$r=$conn->query("SELECT SUM(payment_method_id IS NOT NULL) assigned FROM transactions");$verify['historical_assigned']=(int)$r->fetch_assoc()['assigned'];
foreach (['transactions','recurring_transactions'] as $table) {$r=$conn->query("SELECT COUNT(*) c FROM `$table`");$verify[$table]=(int)$r->fetch_assoc()['c'];}
$ok=$verify['table']===1&&($verify['columns']['transactions']??0)===1&&($verify['columns']['recurring_transactions']??0)===1&&$verify['homes']===$verify['homes_with_active']&&$verify['historical_assigned']===0&&$before['transactions']===$verify['transactions']&&$before['recurring_transactions']===$verify['recurring_transactions'];
echo json_encode(['ok'=>$ok,'before'=>$before,'verify'=>$verify],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
if (!$ok) exit(2);
@unlink($sqlFile);
@unlink(__FILE__);

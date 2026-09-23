<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');
$home_id = (int)$_SESSION['home_id'];
$user_id = (int)$_SESSION['id'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$msg = ''; $msg_ok = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $pid = (int)($_POST['id'] ?? 0);
    $q = mysqli_prepare($conn, "SELECT * FROM bank_pending_transactions WHERE id = ? AND home_id = ? AND status = 'pending' LIMIT 1");
    mysqli_stmt_bind_param($q, 'ii', $pid, $home_id);
    mysqli_stmt_execute($q);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    if (!$row) {
        $msg = 'הפעולה לא נמצאה או שכבר טופלה.'; $msg_ok = false;
    } elseif ($action === 'reject') {
        $u = mysqli_prepare($conn, "UPDATE bank_pending_transactions SET status='rejected', decided_at=NOW() WHERE id = ?");
        mysqli_stmt_bind_param($u, 'i', $pid);
        mysqli_stmt_execute($u);
        header('Location: bank_review.php?ok=' . urlencode('הפעולה נדחתה')); exit();
    } elseif ($action === 'approve') {
        $category = (int)($_POST['category'] ?? 0);
        $ti = mysqli_prepare($conn,
            "INSERT INTO transactions (home_id, user_id, amount, currency_code, type, category, description, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ti, 'iidsssss',
            $home_id, $user_id, $row['amount'], $row['currency_code'], $row['type'], $category, $row['description'], $row['txn_date']);
        mysqli_stmt_execute($ti);
        $u = mysqli_prepare($conn, "UPDATE bank_pending_transactions SET status='approved', decided_at=NOW() WHERE id = ?");
        mysqli_stmt_bind_param($u, 'i', $pid);
        mysqli_stmt_execute($u);
        header('Location: bank_review.php?ok=' . urlencode('נוסף לתזרים: ' . $row['description'])); exit();
    }
}
if (isset($_GET['ok'])) { $msg = (string)$_GET['ok']; $msg_ok = true; }

$categories = [];
$r = mysqli_query($conn, "SELECT id,name,type,icon FROM categories WHERE home_id=$home_id AND is_active=1 ORDER BY type,name");
while ($x = mysqli_fetch_assoc($r)) $categories[] = $x;

$pending = [];
$r = mysqli_query($conn, "SELECT * FROM bank_pending_transactions WHERE home_id=$home_id AND status='pending' ORDER BY txn_date DESC, id DESC");
while ($x = mysqli_fetch_assoc($r)) $pending[] = $x;

$snap = null;
$r = mysqli_query($conn, "SELECT * FROM bank_balance_snapshots WHERE home_id=$home_id ORDER BY captured_at DESC LIMIT 1");
if ($r) $snap = mysqli_fetch_assoc($r);
?>
<!DOCTYPE html><html lang="he" dir="rtl"><head><?php include(ROOT_PATH.'/assets/includes/setup_meta_data.php');?><title>אישור פעולות בנק | התזרים</title></head><body class="bg-gray"><div class="dashboard-container"><?php include(ROOT_PATH.'/assets/includes/sidebar_bavbar.php');?><?php include(ROOT_PATH.'/assets/includes/banner_alert.php');?><div class="content-wrapper all-transactions-page">
<div class="all-transactions-title"><div><h1 class="section-title">פעולות בנק לאישור</h1><p>נמשך מבנק הפועלים · מאושרות נכנסות לתזרים, נדחות נעלמות</p></div><div class="all-page-actions"><a href="<?php echo BASE_URL;?>index.php" class="transactions-all-link"><i class="fa-solid fa-arrow-right"></i>חזרה לראשי</a></div></div>
<?php if ($msg): ?><div class="all-summary" style="margin-bottom:12px"><div><strong style="<?php echo $msg_ok?'':'color:#c00';?>"><?php echo h($msg);?></strong></div></div><?php endif; ?>
<?php if ($snap): ?><div class="all-summary"><div><span>יתרה בבנק (<?php echo h($snap['bank'] === 'hapoalim' ? 'הפועלים' : $snap['bank']); ?>)</span><strong><?php echo number_format((float)$snap['balance'], 2); ?> ₪</strong></div><div><span>עודכן</span><strong><?php echo date('d/m H:i', strtotime($snap['captured_at'])); ?></strong></div><div><span>ממתינות</span><strong><?php echo count($pending); ?></strong></div></div><?php endif; ?>
<section class="all-results">
<?php if (!$pending): ?><div class="empty-state text-center"><i class="fa-solid fa-building-columns"></i><p>אין פעולות בנק ממתינות. המוח מושך מהבנק רק כשאפי מבקש.</p></div><?php endif; ?>
<?php foreach ($pending as $row): ?>
<article class="transaction-item all-transaction-row <?php echo h($row['type']); ?>">
  <div class="transaction-info"><div class="cat-icon-wrapper"><i class="fa-solid fa-building-columns"></i></div><div class="details"><span class="desc"><?php echo h($row['description']); ?></span><span class="date"><?php echo date('d/m/Y', strtotime($row['txn_date'])); ?> · אסמכתא <?php echo h($row['reference']); ?></span></div></div>
  <div class="transaction-actions">
    <div class="transaction-amount"><?php echo $row['type'] === 'income' ? '+' : '-'; ?> <?php echo number_format((float)$row['amount'], 2); ?> ₪</div>
    <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
      <select name="category" style="max-width:130px"><?php foreach ($categories as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo h($c['name']); ?></option><?php endforeach; ?></select>
      <button type="submit" name="action" value="approve" class="transaction-action-pill" title="אשר והוסף לתזרים"><i class="fa-solid fa-check"></i></button>
      <button type="submit" name="action" value="reject" class="transaction-action-pill transaction-action-pill--danger" title="דחה" onclick="return confirm('לדחות את הפעולה?');"><i class="fa-solid fa-xmark"></i></button>
    </form>
  </div>
</article>
<?php endforeach; ?>
</section>
</div></main></div>
</body></html>

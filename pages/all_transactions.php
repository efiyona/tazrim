<?php
require_once('../path.php');
include(ROOT_PATH . '/app/database/db.php');
include(ROOT_PATH . '/assets/includes/auth_check.php');
require_once ROOT_PATH . '/app/functions/payment_methods.php';
$home_id=(int)$_SESSION['home_id'];
$home_data=selectOne('homes',['id'=>$home_id]) ?: ['name'=>''];
$payment_methods=tazrim_payment_methods($home_id,true);
$categories=[];$r=mysqli_query($conn,"SELECT id,name,type,icon FROM categories WHERE home_id=$home_id AND is_active=1 ORDER BY type,name");while($x=mysqli_fetch_assoc($r))$categories[]=$x;
$type=in_array($_GET['type']??'', ['income','expense'],true)?$_GET['type']:'';
$payment_id=max(0,(int)($_GET['payment_method_id']??0));
$category_id=max(0,(int)($_GET['category_id']??0));
$date_from=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['date_from']??'')?$_GET['date_from']:'';
$date_to=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['date_to']??'')?$_GET['date_to']:'';
$amount_min=is_numeric($_GET['amount_min']??null)?max(0,(float)$_GET['amount_min']):null;
$amount_max=is_numeric($_GET['amount_max']??null)?max(0,(float)$_GET['amount_max']):null;
$q=trim((string)($_GET['q']??''));
$where=["t.home_id=$home_id"];
if($type)$where[]="t.type='".mysqli_real_escape_string($conn,$type)."'";
if($payment_id)$where[]="t.payment_method_id=$payment_id";
if($category_id)$where[]="t.category=$category_id";
if($date_from)$where[]="t.transaction_date>='".mysqli_real_escape_string($conn,$date_from)."'";
if($date_to)$where[]="t.transaction_date<='".mysqli_real_escape_string($conn,$date_to)."'";
if($amount_min!==null)$where[]='t.amount>='.sprintf('%.2F',$amount_min);
if($amount_max!==null)$where[]='t.amount<='.sprintf('%.2F',$amount_max);
if($q!=='')$where[]="t.description LIKE '%".mysqli_real_escape_string($conn,$q)."%'";
$sql="SELECT t.*,c.name category_name,c.icon cat_icon,u.first_name user_name,pm.name payment_name,pm.type payment_type FROM transactions t LEFT JOIN categories c ON c.id=t.category LEFT JOIN users u ON u.id=t.user_id LEFT JOIN payment_methods pm ON pm.id=t.payment_method_id WHERE ".implode(' AND ',$where)." ORDER BY t.transaction_date DESC,t.created_at DESC";
$res=mysqli_query($conn,$sql);$rows=[];$income=0;$expense=0;while($row=mysqli_fetch_assoc($res)){ $rows[]=$row;if($row['type']==='income')$income+=(float)$row['amount'];else $expense+=(float)$row['amount']; }
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function chosen_name($items,$id,$fallback){foreach($items as $x)if((int)$x['id']===$id)return $x['name'];return $fallback;}
?>
<!DOCTYPE html><html lang="he" dir="rtl"><head><?php include(ROOT_PATH.'/assets/includes/setup_meta_data.php');?><title>כל הפעולות | התזרים</title></head><body class="bg-gray"><div class="dashboard-container"><?php include(ROOT_PATH.'/assets/includes/sidebar_bavbar.php');?><?php include(ROOT_PATH.'/assets/includes/banner_alert.php');?><div class="content-wrapper all-transactions-page">
<div class="all-transactions-title"><div><h1 class="section-title">כל הפעולות</h1><p>היסטוריית הפעולות המלאה של הבית</p></div><a href="<?php echo BASE_URL;?>index.php" class="transactions-all-link"><i class="fa-solid fa-arrow-right"></i>חזרה לראשי</a></div>
<form class="all-filters" method="get" id="all-filters-form">
<div class="all-filter-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo h($q);?>" placeholder="חיפוש לפי תיאור"></div>
<div class="all-filter-grid">
<div class="all-filter"><label>סוג פעולה</label><details class="filter-picker"><summary><span><?php echo $type==='income'?'הכנסות':($type==='expense'?'הוצאות':'הכול');?></span><i class="fa-solid fa-chevron-down"></i></summary><div class="filter-picker__menu"><button type="button" data-name="type" data-value="">הכול</button><button type="button" data-name="type" data-value="expense">הוצאות</button><button type="button" data-name="type" data-value="income">הכנסות</button></div></details><input type="hidden" name="type" value="<?php echo h($type);?>"></div>
<div class="all-filter"><label>אמצעי תשלום</label><details class="filter-picker"><summary><span><?php echo h(chosen_name($payment_methods,$payment_id,'הכול'));?></span><i class="fa-solid fa-chevron-down"></i></summary><div class="filter-picker__menu"><button type="button" data-name="payment_method_id" data-value="">הכול</button><?php foreach($payment_methods as $pm):?><button type="button" data-name="payment_method_id" data-value="<?php echo (int)$pm['id'];?>"><?php echo h(tazrim_payment_method_public_name($pm));?></button><?php endforeach;?></div></details><input type="hidden" name="payment_method_id" value="<?php echo $payment_id?:'';?>"></div>
<div class="all-filter"><label>קטגוריה</label><details class="filter-picker"><summary><span><?php echo h(chosen_name($categories,$category_id,'הכול'));?></span><i class="fa-solid fa-chevron-down"></i></summary><div class="filter-picker__menu"><button type="button" data-name="category_id" data-value="">הכול</button><?php foreach($categories as $c):?><button type="button" data-name="category_id" data-value="<?php echo (int)$c['id'];?>"><?php echo h($c['name']);?></button><?php endforeach;?></div></details><input type="hidden" name="category_id" value="<?php echo $category_id?:'';?>"></div>
<div class="all-filter"><label>מתאריך</label><input type="date" name="date_from" value="<?php echo h($date_from);"></div><div class="all-filter"><label>עד תאריך</label><input type="date" name="date_to" value="<?php echo h($date_to);"></div><div class="all-filter"><label>סכום מינימלי</label><input type="number" min="0" step="0.01" name="amount_min" value="<?php echo $amount_min===null?'':h($amount_min);?>" placeholder="0"></div><div class="all-filter"><label>סכום מקסימלי</label><input type="number" min="0" step="0.01" name="amount_max" value="<?php echo $amount_max===null?'':h($amount_max);?>" placeholder="ללא הגבלה"></div>
</div><div class="all-filter-actions"><button class="all-filter-submit" type="submit"><i class="fa-solid fa-filter"></i>הצג תוצאות</button><a href="all_transactions.php" class="all-filter-reset">ניקוי סינון</a></div></form>
<div class="all-summary"><div><span>נמצאו</span><strong><?php echo count($rows);?> פעולות</strong></div><div class="income"><span>הכנסות</span><strong>+ <?php echo number_format($income,0);?> ₪</strong></div><div class="expense"><span>הוצאות</span><strong>- <?php echo number_format($expense,0);?> ₪</strong></div></div>
<section class="all-results"><?php if(!$rows):?><div class="empty-state text-center"><i class="fa-solid fa-receipt"></i><p>לא נמצאו פעולות שמתאימות לסינון.</p></div><?php endif;?><?php foreach($rows as $row):?><article class="transaction-item <?php echo h($row['type']);?>"><div class="transaction-info"><div class="cat-icon-wrapper"><i class="fa-solid <?php echo h($row['cat_icon']?:'fa-tag');?>"></i></div><div class="details"><span class="desc"><?php echo h($row['description']);?><?php if($row['user_name']):?><small>(<?php echo h($row['user_name']);?>)</small><?php endif;?></span><span class="date"><?php echo date('d/m/Y',strtotime($row['transaction_date']));?> · <?php echo h($row['category_name']?:'ללא קטגוריה');?><?php if($row['payment_name']):?> · <?php echo h($row['payment_name']);?><?php endif;?></span></div></div><div class="transaction-amount"><?php echo $row['type']==='income'?'+':'-';?> <?php echo number_format((float)$row['amount'],2);?> ₪</div></article><?php endforeach;?></section>
</div></main></div><script>document.querySelectorAll('.filter-picker button').forEach(function(b){b.addEventListener('click',function(){var d=b.closest('details'),n=b.dataset.name;document.querySelector('input[name="'+n+'"]').value=b.dataset.value;d.querySelector('summary span').textContent=b.textContent.trim();d.open=false;});});document.addEventListener('click',function(e){document.querySelectorAll('.filter-picker[open]').forEach(function(d){if(!d.contains(e.target))d.open=false;});});</script></body></html>

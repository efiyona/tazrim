<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/app/functions/payment_methods.php';
require_once ROOT_PATH . '/app/functions/budget_overrun_push.php';
header('Content-Type: application/json; charset=utf-8');
$home = (int)($_SESSION['home_id'] ?? 0);
if ($home <= 0) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'משתמש לא מחובר']); exit; }
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo json_encode(['status'=>'success','data'=>tazrim_payment_methods($home,false)]); exit; }
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $mode = $_POST['delete_mode'] ?? '';
        if (!in_array($mode, ['delete_transactions', 'move_to_default'], true)) throw new InvalidArgumentException('יש לבחור מה לעשות עם הפעולות המשויכות.');
        $conn->begin_transaction();
        $s=$conn->prepare('SELECT id,is_active,is_default FROM payment_methods WHERE id=? AND home_id=? FOR UPDATE');$s->bind_param('ii',$id,$home);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();if(!$row)throw new RuntimeException('אמצעי התשלום לא נמצא.');
        $s=$conn->prepare('SELECT COUNT(*) c FROM payment_methods WHERE home_id=? AND is_active=1');$s->bind_param('i',$home);$s->execute();$active=(int)$s->get_result()->fetch_assoc()['c'];$s->close();
        if((int)$row['is_active']===1 && $active<=1)throw new RuntimeException('אי אפשר למחוק את אמצעי התשלום הפעיל האחרון בבית.');
        if($mode==='move_to_default'){
            $s=$conn->prepare('SELECT id FROM payment_methods WHERE home_id=? AND is_active=1 AND is_default=1 AND id<>? LIMIT 1');$s->bind_param('ii',$home,$id);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();
            if(!$d){$s=$conn->prepare('SELECT id FROM payment_methods WHERE home_id=? AND is_active=1 AND id<>? ORDER BY sort_order,id LIMIT 1');$s->bind_param('ii',$home,$id);$s->execute();$d=$s->get_result()->fetch_assoc();$s->close();}
            if(!$d)throw new RuntimeException('לא נמצא אמצעי תשלום חלופי פעיל.');$default=(int)$d['id'];
            $s=$conn->prepare('UPDATE transactions SET payment_method_id=? WHERE home_id=? AND payment_method_id=?');$s->bind_param('iii',$default,$home,$id);$s->execute();$s->close();
            $s=$conn->prepare('UPDATE recurring_transactions SET payment_method_id=? WHERE home_id=? AND payment_method_id=?');$s->bind_param('iii',$default,$home,$id);$s->execute();$s->close();
        }else{
            $s=$conn->prepare('SELECT type,amount,transaction_date FROM transactions WHERE home_id=? AND payment_method_id=?');$s->bind_param('ii',$home,$id);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
            $s=$conn->prepare('DELETE FROM transactions WHERE home_id=? AND payment_method_id=?');$s->bind_param('ii',$home,$id);$s->execute();$s->close();
            $s=$conn->prepare('DELETE FROM recurring_transactions WHERE home_id=? AND payment_method_id=?');$s->bind_param('ii',$home,$id);$s->execute();$s->close();
            foreach($rows as $old)tazrim_after_transaction_row_change($conn,$home,$old,null,date('Y-m-d'));
        }
        $s=$conn->prepare('DELETE FROM payment_methods WHERE id=? AND home_id=?');$s->bind_param('ii',$id,$home);$s->execute();if($s->affected_rows!==1)throw new RuntimeException('המחיקה נכשלה.');$s->close();
        if(!empty($row['is_default'])){$s=$conn->prepare('UPDATE payment_methods SET is_default=1 WHERE home_id=? AND is_active=1 ORDER BY sort_order,id LIMIT 1');$s->bind_param('i',$home);$s->execute();$s->close();}
        $conn->commit();
    } elseif ($action === 'deactivate') {
        $s=$conn->prepare('SELECT COUNT(*) c FROM payment_methods WHERE home_id=? AND is_active=1'); $s->bind_param('i',$home); $s->execute(); $count=(int)$s->get_result()->fetch_assoc()['c']; $s->close();
        if ($count <= 1) throw new RuntimeException('אי אפשר למחוק את אמצעי התשלום הפעיל האחרון בבית.');
        $s=$conn->prepare('SELECT is_default FROM payment_methods WHERE id=? AND home_id=? AND is_active=1');$s->bind_param('ii',$id,$home);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close(); if(!$row)throw new RuntimeException('אמצעי התשלום לא נמצא.');
        $conn->begin_transaction();
        $s=$conn->prepare('UPDATE payment_methods SET is_active=0,is_default=0 WHERE id=? AND home_id=?');$s->bind_param('ii',$id,$home);$s->execute();$s->close();
        if(!empty($row['is_default'])){$s=$conn->prepare('UPDATE payment_methods SET is_default=1 WHERE home_id=? AND is_active=1 ORDER BY sort_order,id LIMIT 1');$s->bind_param('i',$home);$s->execute();$s->close();}
        $conn->commit();
    } elseif ($action === 'set_default') {
        tazrim_resolve_payment_method_id($home,$id); $conn->begin_transaction();
        $s=$conn->prepare('UPDATE payment_methods SET is_default=0 WHERE home_id=?');$s->bind_param('i',$home);$s->execute();$s->close();
        $s=$conn->prepare('UPDATE payment_methods SET is_default=1 WHERE id=? AND home_id=? AND is_active=1');$s->bind_param('ii',$id,$home);$s->execute();$s->close();$conn->commit();
    } else {
        $type=trim($_POST['type']??'');$name=trim($_POST['name']??'');$last4=trim($_POST['last4']??'');$issuer=trim($_POST['issuer']??'');
        if(!in_array($type,['cash','credit_card','check','bank_transfer','bank_debit'],true)||$name==='')throw new InvalidArgumentException('נתונים לא תקינים.');
        if($type==='credit_card'&&!preg_match('/^\d{4}$/',$last4))throw new InvalidArgumentException('יש להזין 4 ספרות אחרונות.');
        if($type!=='credit_card'){$last4='';$issuer='';}
        $last4 = $last4 === '' ? null : $last4; $issuer = $issuer === '' ? null : $issuer;
        if($id>0){$s=$conn->prepare("UPDATE payment_methods SET type=?,name=?,last4=?,issuer=? WHERE id=? AND home_id=?");$s->bind_param('ssssii',$type,$name,$last4,$issuer,$id,$home);}
        else{$s=$conn->prepare("INSERT INTO payment_methods(home_id,type,name,last4,issuer) VALUES(?,?,?,?,?)");$s->bind_param('issss',$home,$type,$name,$last4,$issuer);}
        $s->execute();$s->close();
    }
    echo json_encode(['status'=>'success','data'=>tazrim_payment_methods($home,false)]);
} catch(Throwable $e) { if($conn->errno===0 && method_exists($conn,'rollback')) @mysqli_rollback($conn); http_response_code(422); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }

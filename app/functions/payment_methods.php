<?php

function tazrim_payment_method_public_name(array $method): string {
    $name = trim((string)($method['name'] ?? ''));
    return $name !== '' ? $name : 'אמצעי תשלום';
}
function tazrim_payment_methods(int $homeId, bool $activeOnly=true): array { global $conn; $sql='SELECT id,type,name,last4,issuer,is_default,is_active FROM payment_methods WHERE home_id=?'.($activeOnly?' AND is_active=1':'').' ORDER BY is_default DESC,sort_order,name'; $s=$conn->prepare($sql);$s->bind_param('i',$homeId);$s->execute();$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r; }
function tazrim_default_payment_method_id(int $homeId): int { global $conn; $s=$conn->prepare('SELECT id FROM payment_methods WHERE home_id=? AND is_active=1 ORDER BY is_default DESC,sort_order,id LIMIT 1');$s->bind_param('i',$homeId);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r) throw new RuntimeException('לבית חייב להיות לפחות אמצעי תשלום פעיל.');return (int)$r['id']; }
function tazrim_resolve_payment_method_id(int $homeId,$value): int { global $conn;if($value===null||$value===''||(int)$value===0)return tazrim_default_payment_method_id($homeId);$id=(int)$value;$s=$conn->prepare('SELECT id FROM payment_methods WHERE id=? AND home_id=? AND is_active=1');$s->bind_param('ii',$id,$homeId);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if(!$r)throw new InvalidArgumentException('אמצעי התשלום אינו תקין.');return $id; }

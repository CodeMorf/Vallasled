<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../../config/db.php';
start_session_safe();
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

function hasCol(mysqli $c,string $t,string $col):bool{
  $t=preg_replace('/[^a-zA-Z0-9_]/','',$t); $col=preg_replace('/[^a-zA-Z0-9_]/','',$col);
  $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '$col'"); return $r && $r->num_rows>0;
}
$act=0;$ina=0;$mto=0;
if(hasCol($conn,'vallas','estado_valla')){
  $q=$conn->query("SELECT LOWER(estado_valla) s, COUNT(*) c FROM vallas GROUP BY s");
  while($r=$q->fetch_assoc()){
    $s=$r['s']; $c=(int)$r['c'];
    if($s==='activa'||$s==='activo')$act+=$c; elseif(in_array($s,['mantenimiento','mto','mtto'],true))$mto+=$c; else $ina+=$c;
  }
}else{
  $act=(int)($conn->query("SELECT COUNT(*) FROM vallas WHERE COALESCE(en_vivo,0)=1")->fetch_row()[0]??0);
  $ina=(int)($conn->query("SELECT COUNT(*) FROM vallas WHERE COALESCE(en_vivo,0)=0")->fetch_row()[0]??0);
}
echo json_encode(['ok'=>true,'data'=>['activa'=>$act,'inactiva'=>$ina,'mantenimiento'=>$mto]]);

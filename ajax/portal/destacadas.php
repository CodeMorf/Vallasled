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
$conds=[]; if(hasCol($conn,'vallas','destacado'))$conds[]="destacado=1";
if(hasCol($conn,'vallas','es_destacado'))$conds[]="es_destacado=1";
if(hasCol($conn,'vallas','featured'))$conds[]="featured=1";
if(hasCol($conn,'vallas','destacado_orden'))$conds[]="COALESCE(destacado_orden,0)>0";

$count=0; if($conds){ $r=$conn->query("SELECT COUNT(*) FROM vallas WHERE ".implode(' OR ',$conds)); $count=(int)$r->fetch_row()[0]; }
echo json_encode(['ok'=>true,'data'=>['count'=>$count]]);

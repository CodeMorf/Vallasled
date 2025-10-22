<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

function out($a){ echo json_encode(['ok'=>true,'data'=>$a], JSON_UNESCAPED_UNICODE); exit; }
function tables_like(PDO $db, array $likes){ $w=implode(' OR ',array_fill(0,count($likes),'table_name LIKE ?')); $s=$db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND ($w)"); $s->execute(array_map(fn($x)=>"%$x%",$likes)); return $s->fetchAll(PDO::FETCH_COLUMN) ?: []; }
function cols(PDO $db, string $t){ $q=$db->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?"); $q->execute([$t]); return $q->fetchAll(PDO::FETCH_COLUMN); }
function pick(array $cols, array $cands){ foreach($cands as $c) if (in_array($c,$cols,true)) return $c; return null; }
function ident($x){ if(!preg_match('/^[A-Za-z0-9_]+$/',$x)) throw new RuntimeException('ident'); return "`$x`"; }

try{
  $db=$conn;
  $tL = (tables_like($db,['licenc','license'])[0] ?? null);
  if(!$tL) out([]);

  $C=cols($db,$tL);
  $nom= pick($C,['nombre','licencia','codigo','name']);
  $ven= pick($C,['vencimiento','fecha_vencimiento','expire_at','expiry_date']);
  if(!$ven) out([]);

  $sql="SELECT ".
       ($nom?ident($nom)." nombre,":" 'Licencia' nombre,").
       " DATE_FORMAT(".ident($ven).",'%Y-%m-%d') vencimiento,
         DATEDIFF(".ident($ven).", CURDATE()) dias
       FROM ".ident($tL)."
       WHERE ".ident($ven)." BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
       ORDER BY ".ident($ven)." ASC LIMIT 12";
  $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  out($rows);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

function out($a){ echo json_encode(['ok'=>true,'data'=>$a], JSON_UNESCAPED_UNICODE); exit; }
function tables_like(PDO $db, array $likes){ $w=implode(' OR ',array_fill(0,count($likes),'table_name LIKE ?')); $s=$db->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND ($w)"); $s->execute(array_map(fn($x)=>"%$x%",$likes)); return $s->fetchAll(PDO::FETCH_COLUMN) ?: []; }
function cols(PDO $db, string $t){ $q=$db->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?"); $q->execute([$t]); return $q->fetchAll(PDO::FETCH_COLUMN); }
function pick(array $cols, array $cands){ foreach($cands as $c) if (in_array($c,$cols,true)) return $c; return null; }
function ident($x){ if(!preg_match('/^[A-Za-z0-9_]+$/',$x)) throw new RuntimeException('ident'); return "`$x`"; }
function pill($s){ $s=strtolower((string)$s); if(preg_match('/confir|aprob|act/i',$s)) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'; if(preg_match('/pend/i',$s)) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'; return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'; }

try{
  $db=$conn;

  $out=[];

  // Prefer tabla "activity"
  $tA = (tables_like($db,['activity','evento','log'])[0] ?? null);
  if ($tA){
    $C=cols($db,$tA);
    $msg=pick($C,['msg','mensaje','descripcion','event','title']);
    $ts =pick($C,['fecha','ts','created_at','timestamp']);
    $sql="SELECT ".($msg?ident($msg):"'Evento'")." msg, ".($ts?ident($ts):"NOW()")." ts FROM ".ident($tA)." ORDER BY ".($ts?ident($ts):"1")." DESC LIMIT 8";
    foreach($db->query($sql) as $r){ $out[]=['valla'=>'—','cliente'=>'—','fechas'=>$r['ts'],'monto'=>null,'estado'=>'','estado_class'=>'', 'msg'=>$r['msg']]; }
    out($out);
  }

  // Fallback: derivado de reservas
  $tR = (tables_like($db,['reserva','booking','contrato','orden'])[0] ?? null);
  if ($tR){
    $C=cols($db,$tR);
    $cli=pick($C,['cliente','cliente_nombre','client','customer','client_name']);
    $val=pick($C,['valla','valla_id','asset','panel','billboard']);
    $ini=pick($C,['fecha_inicio','inicio','start_date','desde']);
    $fin=pick($C,['fecha_fin','fin','end_date','hasta']);
    $est=pick($C,['estado','status']);
    $tot=pick($C,['total','monto','amount']);
    $ord=pick($C,['created_at','fecha','fecha_creacion',$ini]) ?: 'id';
    $sql="SELECT ".
         ($val?ident($val)." valla,":" '—' valla,").
         ($cli?ident($cli)." cliente,":" '—' cliente,").
         ($ini?"DATE_FORMAT(".ident($ini).",'%d %b')":"''")." ini, ".
         ($fin?"DATE_FORMAT(".ident($fin).",'%d %b')":"''")." fin, ".
         ($tot?ident($tot)." total,":" NULL total,").
         ($est?ident($est)." estado":" '' estado").
         " FROM ".ident($tR)." ORDER BY ".ident($ord)." DESC LIMIT 8";
    $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
      $out[]=[
        'valla'=>$r['valla'],'cliente'=>$r['cliente'],
        'fechas'=>trim(($r['ini']??'').' - '.($r['fin']??''),' -'),
        'monto'=>$r['total'], 'estado'=>$r['estado'], 'estado_class'=>pill($r['estado']??'')
      ];
    }
    out($out);
  }

  out([]);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

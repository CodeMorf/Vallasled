<?php
// /console/portal/ajax/licencias_proximas.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
start_session_safe();
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')!=='XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if (empty($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) { http_response_code(419); echo json_encode(['ok'=>false]); exit; }

function table_exists(mysqli $c,string $t): bool{
  $s=$c->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?"); $s->bind_param('s',$t); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}
function col_exists(mysqli $c,string $t,string $col): bool{
  $s=$c->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?"); $s->bind_param('ss',$t,$col); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}

$out=['ok'=>true,'items'=>[]];
if (!table_exists($conn,'licencias')) { echo json_encode($out); exit; }

$fv = col_exists($conn,'licencias','fecha_vencimiento')?'fecha_vencimiento':(col_exists($conn,'licencias','vence')?'vence':'fecha');
$nom= col_exists($conn,'licencias','valla')?'valla':(col_exists($conn,'licencias','valla_nombre')?'valla_nombre':'titulo');
$res=$conn->query("SELECT $nom n, $fv f FROM licencias WHERE $fv IS NOT NULL ORDER BY $fv ASC LIMIT 20");
$today = new DateTime();
while($r=$res->fetch_assoc()){
  $d = new DateTime($r['f']); $diff = (int)$today->diff($d)->format('%r%a');
  $out['items'][]=['valla'=>$r['n'],'vence_en'=>($diff>=0? $diff.' días':'vencida')];
}
echo json_encode($out);

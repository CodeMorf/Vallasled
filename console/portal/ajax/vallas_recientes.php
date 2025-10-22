<?php
// /console/portal/ajax/vallas_recientes.php
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
if (!table_exists($conn,'vallas')) { echo json_encode($out); exit; }

$name = col_exists($conn,'vallas','nombre')?'nombre':(col_exists($conn,'vallas','titulo')?'titulo':'id');
$tipo = col_exists($conn,'vallas','tipo')?'tipo':'';
$fecha= col_exists($conn,'vallas','created_at')?'created_at':(col_exists($conn,'vallas','fecha')?'fecha':'id');
$sql="SELECT $name n".($tipo?", $tipo t":"").", $fecha f FROM vallas ORDER BY $fecha DESC LIMIT 20";
$res=$conn->query($sql);
while($r=$res->fetch_assoc()){
  $out['items'][]=['nombre'=>$r['n'],'tipo'=>$r['t']??'','fecha'=>$r['f']];
}
echo json_encode($out);

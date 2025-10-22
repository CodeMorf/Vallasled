<?php
// /console/portal/ajax/types.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
start_session_safe();
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')!=='XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if (empty($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) { http_response_code(419); echo json_encode(['ok'=>false]); exit; }

function table_exists(mysqli $c, string $t): bool {
  $s=$c->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?"); $s->bind_param('s',$t); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $s=$c->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?"); $s->bind_param('ss',$t,$col); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}

$labels=['LED','Impresa','Móvil LED','Vehículo']; $counts=[0,0,0,0];
if (table_exists($conn,'vallas')) {
  if (col_exists($conn,'vallas','tipo')) {
    $rs=$conn->query("SELECT LOWER(tipo) t, COUNT(*) c FROM vallas GROUP BY 1");
    $map=['led'=>0,'impresa'=>1,'impreso'=>1,'móvil led'=>2,'movil led'=>2,'vehiculo'=>3,'vehículo'=>3,'movil'=>2];
    while($r=$rs->fetch_assoc()){
      $k=$r['t']; $idx = $map[$k] ?? null;
      if ($idx===null) continue;
      $counts[$idx] += (int)$r['c'];
    }
  } else {
    $counts[1]=(int)$conn->query("SELECT COUNT(*) FROM vallas")->fetch_row()[0];
  }
}
echo json_encode(['ok'=>true,'labels'=>$labels,'data'=>$counts]);

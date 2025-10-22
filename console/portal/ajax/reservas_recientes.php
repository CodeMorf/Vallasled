<?php
// /console/portal/ajax/reservas_recientes.php
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
function has(mysqli $c,string $t,string $col): bool {
  $s=$c->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?"); $s->bind_param('ss',$t,$col); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}

$out=['ok'=>true,'items'=>[]];
if (!table_exists($conn,'reservas')) { echo json_encode($out); exit; }

$cols=$conn->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='reservas'");
$names=[]; while($r=$cols->fetch_row()) $names[]=$r[0];
$fechaCol = in_array('fecha_inicio',$names)?'fecha_inicio':(in_array('desde',$names)?'desde':(in_array('created_at',$names)?'created_at':$names[0]));
$sql = "SELECT * FROM reservas ORDER BY $fechaCol DESC LIMIT 10";
$res = $conn->query($sql);
while($row=$res->fetch_assoc()){
  $valla = $row['valla'] ?? ($row['valla_nombre'] ?? ($row['ubicacion'] ?? ''));
  $cliente = $row['cliente'] ?? ($row['empresa'] ?? ($row['customer'] ?? ''));
  $desde = $row['fecha_inicio'] ?? ($row['desde'] ?? ($row['start_date'] ?? ''));
  $hasta = $row['fecha_fin'] ?? ($row['hasta'] ?? ($row['end_date'] ?? ''));
  $monto = $row['monto'] ?? ($row['total'] ?? ($row['precio'] ?? 0));
  $estado= $row['estado'] ?? ($row['status'] ?? '');
  $out['items'][]=[
    'valla'=>$valla,'cliente'=>$cliente,'desde'=>$desde,'hasta'=>$hasta,
    'monto'=>(float)$monto,'monto_form'=> '$'.number_format((float)$monto,2,'.',','),
    'estado'=>$estado
  ];
}
echo json_encode($out);

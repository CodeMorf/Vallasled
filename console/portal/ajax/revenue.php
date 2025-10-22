<?php
// /console/portal/ajax/revenue.php
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

$use_fact = table_exists($conn,'facturas');
$use_res  = !$use_fact && table_exists($conn,'reservas');

$labels=[]; $data=[];
for ($i=5; $i>=0; $i--) {
  $dt = (new DateTime("first day of -$i month"));
  $start = $dt->format('Y-m-01 00:00:00');
  $end   = $dt->format('Y-m-t 23:59:59');
  $labels[] = ucfirst(strftime('%b', $dt->getTimestamp())); // Ej: Oct
  $sum = 0.0;
  if ($use_fact) {
    $fcol = col_exists($conn,'facturas','fecha')?'fecha':(col_exists($conn,'facturas','created_at')?'created_at':'emitida');
    $tcol = col_exists($conn,'facturas','total')?'total':(col_exists($conn,'facturas','monto')?'monto':'importe');
    $estado = col_exists($conn,'facturas','estado')?"AND estado IN ('pagada','pagado','paid')":"";
    $st=$conn->prepare("SELECT COALESCE(SUM($tcol),0) FROM facturas WHERE $fcol BETWEEN ? AND ? $estado");
    $st->bind_param('ss',$start,$end); $st->execute(); $sum=(float)$st->get_result()->fetch_row()[0];
  } elseif ($use_res) {
    $fcol = col_exists($conn,'reservas','fecha_inicio')?'fecha_inicio':(col_exists($conn,'reservas','desde')?'desde':'created_at');
    $tcol = col_exists($conn,'reservas','monto')?'monto':(col_exists($conn,'reservas','total')?'total':'precio');
    $estado = col_exists($conn,'reservas','estado')?"AND estado IN ('confirmada','confirmado','paid')":"";
    $st=$conn->prepare("SELECT COALESCE(SUM($tcol),0) FROM reservas WHERE $fcol BETWEEN ? AND ? $estado");
    $st->bind_param('ss',$start,$end); $st->execute(); $sum=(float)$st->get_result()->fetch_row()[0];
  }
  $data[] = round($sum,2);
}

echo json_encode(['ok'=>true,'labels'=>$labels,'data'=>$data]);

<?php
// /console/portal/ajax/summary.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
start_session_safe();
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')!=='XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'xhr']); exit; }
if (empty($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }

function table_exists(mysqli $c, string $t): bool {
  $s=$c->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?"); $s->bind_param('s',$t); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}
function col_exists(mysqli $c, string $t, string $col): bool {
  $s=$c->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?"); $s->bind_param('ss',$t,$col); $s->execute();
  return (bool)$s->get_result()->fetch_row();
}

$t_vallas = table_exists($conn,'vallas');
$t_res   = table_exists($conn,'reservas');
$t_fact  = table_exists($conn,'facturas');
$t_ads   = table_exists($conn,'ads') || table_exists($conn,'destacados');

$tot_vallas = 0;
if ($t_vallas) {
  $sql = col_exists($conn,'vallas','activo') ? "SELECT COUNT(*) FROM vallas WHERE activo=1" : "SELECT COUNT(*) FROM vallas";
  $tot_vallas = (int)$conn->query($sql)->fetch_row()[0];
}

$inicioMes = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
$ahora     = (new DateTime())->format('Y-m-d H:i:s');

$ingresos = 0.0;
if ($t_fact) {
  $estado = col_exists($conn,'facturas','estado') ? "AND estado IN ('pagada','pagado','paid')" : "";
  $fcol   = col_exists($conn,'facturas','fecha') ? 'fecha' : (col_exists($conn,'facturas','created_at')?'created_at':'emitida');
  $tcol   = col_exists($conn,'facturas','total') ? 'total' : (col_exists($conn,'facturas','monto')?'monto':'importe');
  $stmt = $conn->prepare("SELECT COALESCE(SUM($tcol),0) FROM facturas WHERE $fcol BETWEEN ? AND ? $estado");
  $stmt->bind_param('ss', $inicioMes, $ahora); $stmt->execute(); $ingresos = (float)$stmt->get_result()->fetch_row()[0];
} elseif ($t_res) {
  $fcol = col_exists($conn,'reservas','fecha_inicio')?'fecha_inicio':(col_exists($conn,'reservas','desde')?'desde':'created_at');
  $tcol = col_exists($conn,'reservas','monto')?'monto':(col_exists($conn,'reservas','total')?'total':'precio');
  $estado = col_exists($conn,'reservas','estado') ? "AND estado IN ('confirmada','confirmado','paid')" : "";
  $stmt = $conn->prepare("SELECT COALESCE(SUM($tcol),0) FROM reservas WHERE $fcol BETWEEN ? AND ? $estado");
  $stmt->bind_param('ss', $inicioMes, $ahora); $stmt->execute(); $ingresos = (float)$stmt->get_result()->fetch_row()[0];
}

$reservas_mes = 0;
if ($t_res) {
  $fcol = col_exists($conn,'reservas','fecha_inicio')?'fecha_inicio':(col_exists($conn,'reservas','desde')?'desde':'created_at');
  $sql  = "SELECT COUNT(*) FROM reservas WHERE $fcol BETWEEN ? AND ?";
  $stmt = $conn->prepare($sql); $stmt->bind_param('ss',$inicioMes,$ahora); $stmt->execute();
  $reservas_mes = (int)$stmt->get_result()->fetch_row()[0];
}

$ads_dest = 0;
if (table_exists($conn,'ads') && col_exists($conn,'ads','destacado')) {
  $ads_dest = (int)$conn->query("SELECT COUNT(*) FROM ads WHERE destacado=1")->fetch_row()[0];
} elseif (table_exists($conn,'destacados')) {
  $ads_dest = (int)$conn->query("SELECT COUNT(*) FROM destacados")->fetch_row()[0];
}

echo json_encode(['ok'=>true,'totals'=>[
  'vallas'=>$tot_vallas,
  'ingresos_mes'=>$ingresos,
  'reservas_mes'=>$reservas_mes,
  'ads_destacados'=>$ads_dest
]]);

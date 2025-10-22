<?php
// /console/facturacion/ajax/kpi.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
}

function col_exists(mysqli $c, string $table, string $col): bool {
  $t = $c->real_escape_string($table);
  $q = $c->query("SHOW COLUMNS FROM `$t` LIKE '{$c->real_escape_string($col)}'");
  return $q && $q->num_rows > 0;
}

$today = date('Y-m-d');
$firstMonth = date('Y-m-01');
$prevFirst  = date('Y-m-01', strtotime('-1 month'));
$prevLast   = date('Y-m-t', strtotime('-1 month'));

$has_created  = col_exists($conn,'facturas','created_at');
$has_fvenc    = col_exists($conn,'facturas','fecha_vencimiento');
$created_col  = $has_created ? 'created_at' : 'DATE(NOW())';
$venc_cond    = $has_fvenc ? "AND `fecha_vencimiento` < CURDATE()" : "AND DATE_ADD($created_col, INTERVAL 30 DAY) < CURDATE()";

$tot = ['cobrado'=>0,'pendiente'=>0,'vencidas'=>0,'nuevas_mes'=>0,'pendientes_count'=>0];

$q1 = $conn->query("SELECT 
  SUM(CASE WHEN estado='pagado' THEN (monto-IFNULL(descuento,0)) ELSE 0 END) cobrado,
  SUM(CASE WHEN estado='pendiente' THEN (monto-IFNULL(descuento,0)) ELSE 0 END) pendiente,
  SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) pendientes_count
FROM facturas");
if ($q1 && $r=$q1->fetch_assoc()){
  $tot['cobrado'] = (float)$r['cobrado']; 
  $tot['pendiente'] = (float)$r['pendiente'];
  $tot['pendientes_count'] = (int)$r['pendientes_count'];
}

$q2 = $conn->query("SELECT COUNT(*) n FROM facturas WHERE estado='pendiente' $venc_cond");
$tot['vencidas'] = ($q2 && $r=$q2->fetch_assoc()) ? (int)$r['n'] : 0;

$q3 = $conn->query("SELECT COUNT(*) n FROM facturas WHERE $created_col BETWEEN '$firstMonth' AND '$today'");
$tot['nuevas_mes'] = ($q3 && $r=$q3->fetch_assoc()) ? (int)$r['n'] : 0;

$vs = ['cobrado'=>'', 'vencidas'=>'', 'nuevas'=>''];
// variaciones simples vs mes anterior
$qpm = $conn->query("SELECT 
  SUM(CASE WHEN estado='pagado' THEN (monto-IFNULL(descuento,0)) ELSE 0 END) v 
FROM facturas WHERE $created_col BETWEEN '$prevFirst' AND '$prevLast'");
$prevCob = ($qpm && $r=$qpm->fetch_row()) ? (float)$r[0] : 0.0;
$vs['cobrado'] = ($prevCob>0) ? sprintf('%.1f%% vs. mes anterior', (($tot['cobrado']-$prevCob)/$prevCob)*100) : '—';

$qpm2 = $conn->query("SELECT COUNT(*) n FROM facturas WHERE estado='pendiente' $venc_cond AND $created_col BETWEEN '$prevFirst' AND '$prevLast'");
$prevV = ($qpm2 && $r=$qpm2->fetch_row()) ? (int)$r[0] : 0;
$vs['vencidas'] = ($prevV>0) ? sprintf('%.1f%% vs. mes anterior', (($tot['vencidas']-$prevV)/$prevV)*100) : '—';

$qpm3 = $conn->query("SELECT COUNT(*) n FROM facturas WHERE $created_col BETWEEN '$prevFirst' AND '$prevLast'");
$prevN = ($qpm3 && $r=$qpm3->fetch_row()) ? (int)$r[0] : 0;
$vs['nuevas'] = ($prevN>0) ? sprintf('+%d vs. mes anterior', ($tot['nuevas_mes']-$prevN)) : '—';

// actividad reciente básica
$actividad = [];
$qr = $conn->query("SELECT id, estado, $created_col AS f, (monto-IFNULL(descuento,0)) v FROM facturas ORDER BY $created_col DESC LIMIT 6");
if($qr){
  while($r=$qr->fetch_assoc()){
    $actividad[] = [
      'tipo' => ($r['estado']==='pagado'?'pago':'nueva'),
      'texto'=> $r['estado']==='pagado' ? 'Pago registrado' : 'Nueva factura creada',
      'detalle'=> 'Factura #'.$r['id'].' - RD$ '.number_format((float)$r['v'],0,',','.'),
      'hace'=> $r['f']
    ];
  }
}

echo json_encode(['ok'=>true,'totals'=>$tot,'vs'=>$vs,'actividad'=>$actividad], JSON_UNESCAPED_UNICODE);

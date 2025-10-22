<?php
declare(strict_types=1);
// /console/portal/ajax/dashboard.php
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

start_session_safe();
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'xhr']); exit; }
if (empty($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) { http_response_code(419); echo json_encode(['ok'=>false,'error'=>'csrf']); exit; }

function q1(mysqli $c, string $sql, array $params=[], string $types=''): mixed {
  $st = $c->prepare($sql);
  if ($params) { $st->bind_param($types, ...$params); }
  $st->execute(); $r = $st->get_result()->fetch_row();
  return $r ? $r[0] : null;
}
function rows(mysqli $c, string $sql, array $params=[], string $types=''): array {
  $st = $c->prepare($sql);
  if ($params) { $st->bind_param($types, ...$params); }
  $st->execute(); $res = $st->get_result(); $out=[];
  while ($row = $res->fetch_assoc()) $out[] = $row;
  return $out;
}

$now = new DateTime();
$monthStart = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
$monthEnd   = $now->format('Y-m-d H:i:s');

/* === Tarjetas === */
$tot_vallas = (int) q1($conn,
  "SELECT COUNT(*) FROM vallas WHERE (estado_valla='activa' OR visible_publico=1)"
);

$ingresos_mes = (float) q1($conn,
  "SELECT COALESCE(SUM(monto),0) FROM facturas
   WHERE estado='pagado' AND fecha_pago BETWEEN ? AND ?",
  [$monthStart, $monthEnd], 'ss'
);

$reservas_mes = (int) q1($conn,
  "SELECT COUNT(*) FROM reservas WHERE fecha_inicio BETWEEN ? AND ?",
  [$monthStart, $monthEnd], 'ss'
);

/* Ads destacados: pagos vigentes en el mes */
$ads_dest = (int) q1($conn,
  "SELECT COUNT(*) FROM vallas_destacadas_pagos
   WHERE (fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE())"
);

/* === Revenue 6 meses === */
$labels=[]; $data=[];
for ($i=5; $i>=0; $i--) {
  $d1 = (new DateTime("first day of -$i month 00:00:00"));
  $d2 = (new DateTime("last day of -$i month 23:59:59"));
  $labels[] = $d1->format('M');
  $sum = (float) q1($conn,
    "SELECT COALESCE(SUM(monto),0) FROM facturas
     WHERE estado='pagado' AND fecha_pago BETWEEN ? AND ?",
    [$d1->format('Y-m-d H:i:s'), $d2->format('Y-m-d H:i:s')], 'ss'
  );
  $data[] = round($sum, 2);
}

/* === Tipos de vallas === */
$raw = rows($conn, "SELECT LOWER(tipo) t, COUNT(*) c FROM vallas GROUP BY 1");
$labels_types = ['LED','Impresa','Móvil LED','Vehículo'];
$count_types = [0,0,0,0];
$map = ['led'=>0,'impresa'=>1,'movilled'=>2,'vehiculo'=>3,'vehículo'=>3,'movil led'=>2,'móvil led'=>2];
foreach ($raw as $r) { $idx = $map[$r['t']] ?? null; if ($idx!==null) $count_types[$idx] = (int)$r['c']; }

/* === Reservas recientes (10) === */
$reservas = rows($conn, "
  SELECT r.id,
         COALESCE(v.nombre,'')  AS valla,
         r.nombre_cliente        AS cliente,
         r.fecha_inicio          AS desde,
         r.fecha_fin             AS hasta,
         r.estado,
         COALESCE(f.monto, v.precio, 0) AS monto
  FROM reservas r
  LEFT JOIN vallas v   ON v.id = r.valla_id
  LEFT JOIN facturas f ON f.id = r.factura_id
  ORDER BY r.fecha_inicio DESC LIMIT 10
");
foreach ($reservas as &$r) { $r['monto_form'] = '$'.number_format((float)$r['monto'], 2, '.', ','); }

/* === Licencias próximas (20) === */
$licencias = rows($conn, "
  SELECT COALESCE(v.nombre,'') AS valla,
         vl.fecha_vencimiento  AS vence
  FROM vallas_licencias vl
  LEFT JOIN vallas v ON v.id = vl.valla_id
  WHERE vl.fecha_vencimiento IS NOT NULL
  ORDER BY vl.fecha_vencimiento ASC
  LIMIT 20
");
$today = new DateTime();
foreach ($licencias as &$l) {
  $d = new DateTime($l['vence']);
  $diff = (int)$today->diff($d)->format('%r%a');
  $l = ['valla'=>$l['valla'], 'vence_en' => $diff>=0 ? $diff.' días' : 'vencida'];
}

/* === Vallas recientes (20) === */
$vallas = rows($conn, "
  SELECT nombre, tipo, COALESCE(fecha_creacion, NOW()) AS fecha
  FROM vallas ORDER BY fecha_creacion DESC LIMIT 20
");

echo json_encode([
  'ok'=>true,
  'totals'=>[
    'vallas'=>$tot_vallas,
    'ingresos_mes'=>$ingresos_mes,
    'reservas_mes'=>$reservas_mes,
    'ads_destacados'=>$ads_dest
  ],
  'revenue'=>['labels'=>$labels, 'data'=>$data],
  'types'  =>['labels'=>$labels_types, 'data'=>$count_types],
  'reservas'=>$reservas,
  'licencias'=>$licencias,
  'vallas'=>$vallas
], JSON_UNESCAPED_UNICODE);

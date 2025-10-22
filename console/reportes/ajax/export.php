<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';

start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); exit('forbidden');
}
$csrf = $_GET['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$csrf)) {
  http_response_code(419); exit('csrf');
}

$tipo  = $_GET['tipo']  ?? 'facturas';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

function whereFechas(string $campo, string $desde, string $hasta, array &$types, array &$params): string {
  $w = [];
  if ($desde !== '') { $w[] = "DATE($campo) >= ?"; $types[]='s'; $params[] = $desde; }
  if ($hasta !== '') { $w[] = "DATE($campo) <= ?"; $types[]='s'; $params[] = $hasta; }
  return $w ? (' WHERE '.implode(' AND ', $w)) : '';
}
function db_stream(mysqli $conn, string $sql, string $types='', array $params=[]) {
  $stmt = $conn->prepare($sql);
  if ($types && $params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  return $stmt->get_result();
}

$defs = [
  'facturas'   => ['table'=>'facturas', 'date'=>'fecha_generacion', 'cols'=>['id','valla_id','cliente_nombre','monto','comision_monto','estado','fecha_generacion']],
  'vallas'     => ['table'=>'vallas', 'date'=>'fecha_creacion',  'cols'=>['id','nombre','tipo','provincia_id','proveedor_id','precio','estado_valla','fecha_creacion']],
  'licencias'  => ['table'=>'crm_licencias', 'date'=>'fecha_emision', 'cols'=>['id','titulo','proveedor_id','valla_id','estado','periodicidad','fecha_emision','fecha_vencimiento']],
  'clientes'   => ['table'=>'crm_clientes', 'date'=>'creado', 'cols'=>['id','nombre','empresa','proveedor_id','creado']],
  'proveedores'=> ['table'=>'proveedores', 'date'=>'creado', 'cols'=>['id','nombre','contacto','estado','creado']],
];
if (!isset($defs[$tipo])) $tipo = 'facturas';
$def = $defs[$tipo];

$types=[]; $params=[];
$where = whereFechas($def['date'], $desde, $hasta, $types, $params);
$sql = "SELECT ".implode(',', $def['cols'])." FROM {$def['table']} $where ORDER BY {$def['date']} DESC, id DESC";

$fname = 'reporte_'.$tipo.'_'.date('Y-m-d').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');

$out = fopen('php://output', 'w');
fputcsv($out, $def['cols']);

$res = db_stream($conn, $sql, implode('', $types), $params);
while ($row = $res->fetch_assoc()) {
  $line = [];
  foreach ($def['cols'] as $c) { $line[] = (string)($row[$c] ?? ''); }
  fputcsv($out, $line);
}
fclose($out);

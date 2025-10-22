<?php
// /console/facturacion/facturas/ajax/listar.php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

function iso(string $s): ?string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null; }

$q            = trim((string)($_GET['q'] ?? ''));
$estado       = trim((string)($_GET['estado'] ?? ''));
$proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
$desde        = iso((string)($_GET['desde'] ?? '')) ?: null;
$hasta        = iso((string)($_GET['hasta'] ?? '')) ?: null;
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min((int)($_GET['limit'] ?? 20), 100));
$offset = ($page - 1) * $limit;

$sort  = (string)($_GET['sort'] ?? 'fecha_generada');
$order = strtolower((string)($_GET['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$sortMap = ['id'=>'f.id','fecha_generada'=>'f.fecha_generada','monto'=>'f.monto','estado'=>'f.estado'];
$sortCol = $sortMap[$sort] ?? 'f.fecha_generada';

$where = []; $params = []; $types = '';
if ($estado !== '') { $where[] = 'f.estado = ?'; $params[] = $estado; $types .= 's'; }
if ($proveedor_id > 0) { $where[] = 'f.proveedor_id = ?'; $params[] = $proveedor_id; $types .= 'i'; }
if ($desde !== null) { $where[] = 'DATE(f.fecha_generada) >= ?'; $params[] = $desde; $types .= 's'; }
if ($hasta !== null) { $where[] = 'DATE(f.fecha_generada) <= ?'; $params[] = $hasta; $types .= 's'; }
if ($q !== '') { $where[] = '(CAST(f.id AS CHAR) LIKE ? OR f.cliente_nombre LIKE ? OR f.cliente_email LIKE ?)'; $w='%'.$q.'%'; array_push($params,$w,$w,$w); $types.='sss'; }
$W = $where ? 'WHERE '.implode(' AND ',$where) : '';

/* total */
$stmt = $conn->prepare("SELECT COUNT(*) c FROM facturas f $W");
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

/* rows */
$sql = "SELECT f.id,
  CONCAT(YEAR(f.fecha_generada), '-', LPAD(f.id,3,'0')) AS numero,
  COALESCE(f.cliente_nombre, cc.nombre) AS cliente,
  pv.nombre AS proveedor, f.monto, f.comision_monto, f.estado, DATE(f.fecha_generada) AS fecha
FROM facturas f
LEFT JOIN crm_clientes cc ON cc.id=f.crm_cliente_id
LEFT JOIN proveedores pv ON pv.id=f.proveedor_id
$W
ORDER BY $sortCol $order
LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types2 = $types.'ii'; $args2 = $params; $args2[] = $limit; $args2[] = $offset;
$stmt->bind_param($types2, ...$args2);
$stmt->execute(); $res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $r['id'] = (int)$r['id'];
  $r['monto'] = (float)$r['monto'];
  $r['comision_monto'] = (float)($r['comision_monto'] ?? 0);
  $rows[] = $r;
}
$stmt->close();

/* sums */
$base = []; $bp=[]; $bt='';
if ($proveedor_id>0){$base[]='f.proveedor_id=?';$bp[]=$proveedor_id;$bt.='i';}
if ($desde!==null){$base[]='DATE(f.fecha_generada) >= ?';$bp[]=$desde;$bt.='s';}
if ($hasta!==null){$base[]='DATE(f.fecha_generada) <= ?';$bp[]=$hasta;$bt.='s';}
if ($q!==''){ $base[]='(CAST(f.id AS CHAR) LIKE ? OR f.cliente_nombre LIKE ? OR f.cliente_email LIKE ?)'; $w='%'.$q.'%'; array_push($bp,$w,$w,$w); $bt.='sss'; }
$WB = $base ? 'WHERE '.implode(' AND ',$base) : '';

$stmt = $conn->prepare("SELECT COALESCE(SUM(f.total),0) s FROM facturas f $WB ".($WB?' AND ':' WHERE ')." f.estado='pendiente'");
if ($bt) $stmt->bind_param($bt, ...$bp); $stmt->execute(); $sum_p = (float)$stmt->get_result()->fetch_assoc()['s']; $stmt->close();
$stmt = $conn->prepare("SELECT COALESCE(SUM(f.total),0) s FROM facturas f $WB ".($WB?' AND ':' WHERE ')." f.estado='pagado'");
if ($bt) $stmt->bind_param($bt, ...$bp); $stmt->execute(); $sum_c = (float)$stmt->get_result()->fetch_assoc()['s']; $stmt->close();

$pages = (int)ceil($total/max(1,$limit));
json_exit(['rows'=>$rows,'total'=>$total,'sum_pendiente'=>$sum_p,'sum_pagado'=>$sum_c,'page'=>$page,'pages'=>$pages]);

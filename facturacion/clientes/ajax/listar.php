<?php
// /console/facturacion/clientes/ajax/listar.php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q            = trim((string)($_GET['q'] ?? ''));
$proveedor_id = isset($_GET['proveedor_id']) && $_GET['proveedor_id'] !== '' ? (int)$_GET['proveedor_id'] : null;
$limit        = max(10, min(100, (int)($_GET['limit'] ?? 20)));
$offset       = max(0, (int)($_GET['offset'] ?? 0));
$want_meta    = (int)($_GET['meta'] ?? 0) === 1;

$where = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.empresa LIKE ?)';
  $like = '%' . $q . '%';
  $params[]=$like; $params[]=$like; $params[]=$like; $params[]=$like;
  $types .= 'ssss';
}
if ($proveedor_id !== null) {
  $where[] = 'c.proveedor_id = ?';
  $params[] = $proveedor_id; $types .= 'i';
}

$sql = "SELECT c.id, c.nombre, c.email, c.telefono, c.empresa,
               p.id AS proveedor_id, p.nombre AS proveedor_nombre
        FROM crm_clientes c
        LEFT JOIN proveedores p ON p.id = c.proveedor_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sqlTotal = "SELECT COUNT(1) AS n
             FROM crm_clientes c" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$sql .= " ORDER BY c.creado DESC LIMIT ? OFFSET ?";
$params[] = $limit; $types .= 'i';
$params[] = $offset; $types .= 'i';

try {
  // total
  $stmtT = $conn->prepare($sqlTotal);
  if ($where) $stmtT->bind_param(substr($types, 0, strlen($types)-2), ...array_slice($params, 0, count($params)-2));
  $stmtT->execute(); $rt = $stmtT->get_result(); $total = (int)($rt->fetch_assoc()['n'] ?? 0); $rt->free();

  // data
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute(); $res = $stmt->get_result();

  $data = [];
  while ($row = $res->fetch_assoc()) {
    $data[] = [
      'id'       => (int)$row['id'],
      'nombre'   => $row['nombre'],
      'email'    => $row['email'],
      'telefono' => $row['telefono'],
      'empresa'  => $row['empresa'],
      'proveedor'=> ['id'=>(int)($row['proveedor_id'] ?? 0), 'nombre'=>$row['proveedor_nombre'] ?? null]
    ];
  }
  $res->free();

  $out = ['ok'=>true, 'data'=>$data, 'total'=>$total, 'next_offset'=> min($total, $offset + $limit) ];
  if ($want_meta) {
    $meta = [];
    $r2 = $conn->query("SELECT id, nombre FROM proveedores WHERE estado IS NULL OR estado<>0 ORDER BY nombre ASC");
    while ($r2 && ($p = $r2->fetch_assoc())) $meta[] = ['id'=>(int)$p['id'], 'nombre'=>$p['nombre']];
    if ($r2) $r2->free();
    $out['meta'] = ['proveedores'=>$meta];
  }

  echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER_ERROR'], JSON_UNESCAPED_UNICODE);
}

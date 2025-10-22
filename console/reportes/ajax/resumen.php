<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
}
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$csrf)) {
  http_response_code(419); echo json_encode(['error'=>'csrf']); exit;
}

$tipo  = $_POST['tipo']  ?? 'facturas';
$desde = $_POST['desde'] ?? '';
$hasta = $_POST['hasta'] ?? '';

function whereFechas(string $campo, string $desde, string $hasta, array &$types, array &$params): string {
  $w = [];
  if ($desde !== '') { $w[] = "DATE($campo) >= ?"; $types[]='s'; $params[] = $desde; }
  if ($hasta !== '') { $w[] = "DATE($campo) <= ?"; $types[]='s'; $params[] = $hasta; }
  return $w ? (' WHERE '.implode(' AND ', $w)) : '';
}
function db_one(mysqli $conn, string $sql, string $types='', array $params=[]) {
  $stmt = $conn->prepare($sql);
  if ($types && $params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ?: null;
}

$types=[]; $params=[]; $row=null;
switch ($tipo) {
  case 'vallas':
    $where = whereFechas('fecha_creacion', $desde, $hasta, $types, $params);
    $sql = "SELECT COUNT(*) registros,
                   SUM(estado_valla='activa') ok,
                   SUM(estado_valla='inactiva') pendientes,
                   COUNT(*) total
            FROM vallas $where";
    $row = db_one($conn, $sql, implode('', $types), $params);
    break;
  case 'licencias':
    $where = whereFechas('fecha_emision', $desde, $hasta, $types, $params);
    $sql = "SELECT COUNT(*) registros,
                   SUM(estado='aprobada') ok,
                   SUM(estado IN ('enviada','borrador')) pendientes,
                   COUNT(*) total
            FROM crm_licencias $where";
    $row = db_one($conn, $sql, implode('', $types), $params);
    break;
  case 'clientes':
    $where = whereFechas('creado', $desde, $hasta, $types, $params);
    $sql = "SELECT COUNT(*) registros, 0 ok, 0 pendientes, COUNT(*) total
            FROM crm_clientes $where";
    $row = db_one($conn, $sql, implode('', $types), $params);
    break;
  case 'proveedores':
    $where = whereFechas('creado', $desde, $hasta, $types, $params);
    $sql = "SELECT COUNT(*) registros,
                   SUM(estado=1) ok,
                   SUM(estado=0) pendientes,
                   COUNT(*) total
            FROM proveedores $where";
    $row = db_one($conn, $sql, implode('', $types), $params);
    break;
  case 'facturas':
  default:
    $where = whereFechas('fecha_generacion', $desde, $hasta, $types, $params);
    $sql = "SELECT COUNT(*) registros,
                   SUM(estado='pagado') ok,
                   SUM(estado='pendiente') pendientes,
                   SUM(monto) total_monto
            FROM facturas $where";
    $row = db_one($conn, $sql, implode('', $types), $params);
    break;
}

echo json_encode($row ?? ['registros'=>0,'ok'=>0,'pendientes'=>0,'total'=>0,'total_monto'=>0.00], JSON_UNESCAPED_UNICODE);

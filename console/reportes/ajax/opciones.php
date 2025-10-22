<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
}

$proveedores = [];
if ($res = $conn->query("SELECT id,nombre FROM proveedores WHERE estado=1 ORDER BY nombre ASC LIMIT 1000")) {
  while ($r = $res->fetch_assoc()) $proveedores[] = $r;
}
$clientes = [];
if ($res = $conn->query("SELECT id,nombre,empresa FROM crm_clientes ORDER BY nombre ASC LIMIT 1000")) {
  while ($r = $res->fetch_assoc()) $clientes[] = $r;
}

echo json_encode(['proveedores'=>$proveedores,'clientes'=>$clientes], JSON_UNESCAPED_UNICODE);

<?php
// /console/ads/ajax/opciones.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

function out(int $code, array $payload){ http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header('Allow: POST, GET, OPTIONS');

if ($method === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  out(401, ['error'=>'No autorizado']);
}

$REQ = $method === 'POST' ? $_POST : $_GET;
// Solo lectura. No exigimos CSRF aquí.

mysqli_set_charset($conn, 'utf8mb4');

/* Proveedores activos */
$sqlProv = "SELECT id, nombre FROM proveedores WHERE COALESCE(estado,1)=1 ORDER BY nombre ASC LIMIT 1000";
$prov = [];
if ($rp = $conn->query($sqlProv)) {
  while ($row = $rp->fetch_assoc()) $prov[] = ['id'=>(int)$row['id'], 'nombre'=> (string)($row['nombre'] ?? '')];
}

/* Vallas visibles (para selector) */
$sqlVallas = "
  SELECT v.id, COALESCE(NULLIF(TRIM(v.nombre),''), CONCAT('Valla ', v.id)) AS nombre
  FROM vallas v
  WHERE COALESCE(v.estado_valla,'activa') IN ('activa')
  ORDER BY v.id DESC
  LIMIT 2000
";
$vals = [];
if ($rv = $conn->query($sqlVallas)) {
  while ($row = $rv->fetch_assoc()) $vals[] = ['id'=>(int)$row['id'], 'nombre'=> (string)$row['nombre']];
}

out(200, ['proveedores'=>$prov, 'vallas'=>$vals]);

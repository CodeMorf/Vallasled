<?php
// /console/ads/ajax/listar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

function jexit(int $code, array $payload){ http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header('Allow: POST, GET, OPTIONS');
if ($method === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  jexit(401, ['error'=>'No autorizado']);
}

$REQ = $method === 'POST' ? $_POST : $_GET;
if ($method === 'POST') {
  if (empty($REQ['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$REQ['csrf'])) {
    jexit(403, ['error'=>'CSRF inválido']);
  }
}

mysqli_set_charset($conn, 'utf8mb4');

/* base dinámica de uploads */
$host   = $_SERVER['HTTP_HOST'] ?? '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
if (stripos($host, 'dev.vallasled.com') !== false)      { $uploadsBase = 'https://dev.vallasled.com/uploads/'; }
elseif (stripos($host, 'auth.vallasled.com') !== false) { $uploadsBase = 'https://auth.vallasled.com/uploads/'; }
else                                                    { $uploadsBase = $scheme . $host . '/uploads/'; }

$q       = isset($REQ['q']) ? trim((string)$REQ['q']) : '';
$prov_id = isset($REQ['prov_id']) ? (int)$REQ['prov_id'] : 0;
$limit   = isset($REQ['limit']) ? max(1, min(100, (int)$REQ['limit'])) : 24;
$offset  = isset($REQ['offset']) ? max(0, (int)$REQ['offset']) : 0;

$where=[]; $params=[]; $types='';
$where[]='1=1';
if ($prov_id>0){ $where[]='COALESCE(dp.proveedor_id, v.proveedor_id)=?'; $types.='i'; $params[]=$prov_id; }
if ($q!==''){
  $like='%'.$q.'%'; $qw=[];
  $qw[]='v.nombre LIKE ?'; $types.='s'; $params[]=$like;
  $qw[]='p.nombre LIKE ?'; $types.='s'; $params[]=$like;
  $qnum=preg_replace('/\D+/','',$q);
  if ($qnum!==''){ $qw[]='v.id = ?'; $types.='i'; $params[]=(int)$qnum; }
  $where[]='('.implode(' OR ',$qw).')';
}
$whereSql=implode(' AND ',$where);

/* count */
$sqlCount = "SELECT COUNT(*) c
FROM vallas_destacadas_pagos dp
JOIN vallas v ON v.id=dp.valla_id
LEFT JOIN proveedores p ON p.id=COALESCE(dp.proveedor_id, v.proveedor_id)
WHERE {$whereSql}";
$stmt=mysqli_prepare($conn,$sqlCount) ?: jexit(500,['error'=>'prep count']);
if ($types!==''){ $stmt->bind_param($types, ...$params); }
$stmt->execute() ?: jexit(500,['error'=>'exec count']);
$total=(int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* list */
$sql = "SELECT
  dp.id, dp.valla_id,
  v.nombre AS valla_nombre,
  COALESCE(p.nombre,'') AS proveedor_nombre,
  DATE_FORMAT(dp.fecha_inicio,'%Y-%m-%d') AS fecha_inicio,
  DATE_FORMAT(dp.fecha_fin,'%Y-%m-%d')   AS fecha_fin,
  dp.monto_pagado, dp.orden,
  COALESCE(v.imagen_previa, v.imagen, v.imagen1, v.imagen2, '') AS valla_imagen
FROM vallas_destacadas_pagos dp
JOIN vallas v ON v.id=dp.valla_id
LEFT JOIN proveedores p ON p.id=COALESCE(dp.proveedor_id, v.proveedor_id)
WHERE {$whereSql}
ORDER BY dp.orden ASC, dp.fecha_inicio DESC, dp.id DESC
LIMIT ? OFFSET ?";

$stmt=mysqli_prepare($conn,$sql) ?: jexit(500,['error'=>'prep list']);
$typesList=$types.'ii'; $paramsList=$params; $paramsList[]=$limit; $paramsList[]=$offset;
$stmt->bind_param($typesList, ...$paramsList);
$stmt->execute() ?: jexit(500,['error'=>'exec list']);
$r=$stmt->get_result();

$rows=[];
while($row=$r->fetch_assoc()){
  $img = trim((string)($row['valla_imagen'] ?? ''));
  // normalización: si no es http(s), construir desde uploadsBase
  if ($img !== '' && stripos($img, 'http://') !== 0 && stripos($img, 'https://') !== 0) {
    // quitar posibles prefijos /uploads/ o uploads/
    $img = preg_replace('~^/?uploads/?~i','',$img);
    $img = ltrim($img, '/');
    $img = $uploadsBase . $img;
  }
  $rows[] = [
    'id'               => (int)$row['id'],
    'valla_id'         => (int)$row['valla_id'],
    'valla_nombre'     => (string)($row['valla_nombre'] ?? ''),
    'proveedor_nombre' => (string)($row['proveedor_nombre'] ?? ''),
    'fecha_inicio'     => $row['fecha_inicio'] ?? null,
    'fecha_fin'        => $row['fecha_fin'] ?? null,
    'monto_pagado'     => is_null($row['monto_pagado']) ? null : (float)$row['monto_pagado'],
    'orden'            => (int)$row['orden'],
    'valla_imagen'     => $img, // ahora absoluta y segura para el front
  ];
}
$stmt->close();

jexit(200, ['rows'=>$rows,'total'=>$total]);

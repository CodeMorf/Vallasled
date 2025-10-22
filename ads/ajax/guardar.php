<?php
// /console/ads/ajax/guardar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Allow: POST, OPTIONS');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

function jexit(int $code, array $payload){ http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') { jexit(405, ['error'=>'Método no permitido']); }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  jexit(401, ['error'=>'No autorizado']);
}
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
  jexit(403, ['error'=>'CSRF inválido']);
}

mysqli_set_charset($conn, 'utf8mb4');

/* ---- inputs (desde el front: id,valla_id,orden,desde,hasta,monto,obs) ---- */
$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$valla_id  = isset($_POST['valla_id']) ? (int)$_POST['valla_id'] : 0;
$orden     = isset($_POST['orden']) ? max(1, (int)$_POST['orden']) : 1;
$desde_raw = trim((string)($_POST['desde'] ?? ''));
$hasta_raw = trim((string)($_POST['hasta'] ?? ''));
$monto     = isset($_POST['monto']) && $_POST['monto'] !== '' ? (float)$_POST['monto'] : 0.0;
$obs       = trim((string)($_POST['obs'] ?? ''));

/* ---- validaciones ---- */
if ($valla_id <= 0) jexit(422, ['error'=>'Valla requerida']);
try {
  $desde = (new DateTimeImmutable($desde_raw))->format('Y-m-d');
  $hasta = (new DateTimeImmutable($hasta_raw))->format('Y-m-d');
} catch (Throwable $e) {
  jexit(422, ['error'=>'Fechas inválidas']);
}
if ($desde > $hasta) jexit(422, ['error'=>'Rango de fechas inválido']);
if ($monto < 0) jexit(422, ['error'=>'Monto inválido']);

$obs = mb_substr($obs, 0, 240);

/* verificar valla y proveedor por defecto */
$prov_id = null;
$stmt = $conn->prepare("SELECT COALESCE(proveedor_id,0) AS prov FROM vallas WHERE id=? LIMIT 1");
$stmt || jexit(500, ['error'=>'prep valla']);
$stmt->bind_param('i', $valla_id);
$stmt->execute() || jexit(500, ['error'=>'exec valla']);
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) jexit(404, ['error'=>'Valla no existe']);
if ((int)$row['prov'] > 0) $prov_id = (int)$row['prov'];

/* Insert o Update */
if ($id > 0) {
  $sql = "UPDATE vallas_destacadas_pagos
          SET valla_id=?, proveedor_id=?, fecha_inicio=?, fecha_fin=?, monto_pagado=?, observacion=?, orden=?
          WHERE id=? LIMIT 1";
  $stmt = $conn->prepare($sql) ?: jexit(500, ['error'=>'prep upd']);
  // proveedor_id puede ser null
  if ($prov_id === null) {
    $null = null;
    $stmt->bind_param('ibsssisi',
      $valla_id, $null, $desde, $hasta, $monto, $obs, $orden, $id
    );
  } else {
    $stmt->bind_param('iisssisi',
      $valla_id, $prov_id, $desde, $hasta, $monto, $obs, $orden, $id
    );
  }
  $ok = $stmt->execute();
  $stmt->close();
  if (!$ok) jexit(500, ['error'=>'No se pudo actualizar']);
  jexit(200, ['ok'=>true, 'id'=>$id]);
} else {
  $sql = "INSERT INTO vallas_destacadas_pagos
            (valla_id, proveedor_id, cliente_id, fecha_inicio, fecha_fin, monto_pagado, observacion, orden)
          VALUES (?, ?, NULL, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql) ?: jexit(500, ['error'=>'prep ins']);
  if ($prov_id === null) {
    $null = null;
    $stmt->bind_param('ibsss si', /* nota: corregimos abajo con tipos correctos */
      $valla_id, $null, $desde, $hasta, $monto, $obs, $orden
    );
  }
  // bind tipos correctos: iissdsi  (pero proveedor_id puede ser null -> usar 'i' con set_null)
  $stmt->bind_param('iissdsi',
    $valla_id, $prov_id, $desde, $hasta, $monto, $obs, $orden
  );
  // si proveedor es null, usar ->send_long_data? Más simple: set a NULL mediante ->bind_param con 'i' y valor NULL no funciona.
  // Rehacer bind de forma segura con mysqli_stmt::bind_param dinámico:
  $stmt->close();

  $sql = "INSERT INTO vallas_destacadas_pagos
            (valla_id, proveedor_id, cliente_id, fecha_inicio, fecha_fin, monto_pagado, observacion, orden)
          VALUES (?, ?, NULL, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql) ?: jexit(500, ['error'=>'prep ins2']);
  // trabajamos con tipos: iissdsi, pero cuando prov_id es null usamos NULL explícito con set a null vía 'i' y null var by ref
  if ($prov_id === null) {
    $tmpProv = null;
    $stmt->bind_param('iissdsi', $valla_id, $tmpProv, $desde, $hasta, $monto, $obs, $orden);
  } else {
    $stmt->bind_param('iissdsi', $valla_id, $prov_id, $desde, $hasta, $monto, $obs, $orden);
  }

  $ok = $stmt->execute();
  if (!$ok) { $err = $stmt->error; $stmt->close(); jexit(500, ['error'=>"No se pudo crear: $err"]); }
  $newId = $stmt->insert_id ?: 0;
  $stmt->close();
  jexit(200, ['ok'=>true, 'id'=>$newId]);
}

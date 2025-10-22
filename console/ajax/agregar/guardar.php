<?php
// /console/ajax/agregar/guardar.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/_bootstrap.php';
start_session_safe();
only_methods(['POST']);
need_csrf_for_write();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  json_exit(['ok'=>false,'error'=>'AUTH_REQUIRED'], 401);
}

$ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$raw = file_get_contents('php://input') ?: '';
$in = (strpos($ct, 'application/json') !== false) ? json_decode($raw, true) : [];
if (!is_array($in)) $in = [];

// Sanitización
$S = fn($k, $d='') => isset($in[$k]) ? (is_string($in[$k]) ? trim($in[$k]) : $in[$k]) : $d;

$titulo      = (string)$S('titulo');
$descripcion = (string)$S('descripcion');
$zona        = (string)$S('zona');
$direccion   = (string)$S('direccion');
$lat         = (float)($S('lat', 0));
$lon         = (float)($S('lon', 0)); // en BD es `lng`
$tipo        = (string)$S('tipo', 'led'); // enum valido
$precio_base = (float)($S('precio_base', 0));
$estado_ui   = (string)$S('estado', 'activa'); // 'activa'|'inactiva'|'mantenimiento'
$destacada   = !empty($in['destacada']);
$fotos       = is_array($in['fotos'] ?? null) ? $in['fotos'] : []; // [{url,principal}...]

// Validación mínima
if (mb_strlen($titulo) < 4)        json_exit(['ok'=>false,'error'=>'VALID_TITULO'], 422);
if (mb_strlen($descripcion) < 20)  json_exit(['ok'=>false,'error'=>'VALID_DESC'], 422);
if (!is_finite($lat) || !is_finite($lon)) json_exit(['ok'=>false,'error'=>'VALID_COORD'], 422);

// Normalizaciones BD
$tipoAllowed = ['led','impresa','movilled','vehiculo'];
if (!in_array($tipo, $tipoAllowed, true)) $tipo = 'led';

$estado_valla = ($estado_ui === 'inactiva') ? 'inactiva' : 'activa';
$estado_flag  = ($estado_ui === 'mantenimiento') ? 0 : 1; // `estado` tinyint(1)
$disponible   = 1;
$visible_publico = 1;

mysqli_begin_transaction($conn);
try {
  // Calcular next id manual (la tabla no está AUTO_INCREMENT en el dump)
  $nextId = 1;
  $rs = mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM vallas");
  if ($rs && ($row = mysqli_fetch_assoc($rs))) $nextId = max(1, (int)$row['nid']);

  // Insert valla
  $sql = "INSERT INTO vallas
    (id, tipo, nombre, zona, ubicacion, lat, lng, precio, estado, estado_valla, disponible, visible_publico, comentarios, imagen)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $stmt = mysqli_prepare($conn, $sql);
  $comentarios = $descripcion; // guardamos descripción en comentarios (campo existente)
  $imagenPrincipal = null;
  foreach ($fotos as $f) { if (!empty($f['url']) && !empty($f['principal'])) { $imagenPrincipal = (string)$f['url']; break; } }
  if (!$imagenPrincipal && !empty($fotos[0]['url'])) $imagenPrincipal = (string)$fotos[0]['url'];

  mysqli_stmt_bind_param(
    $stmt,
    'issssdddiisiss',
    $nextId,
    $tipo,
    $titulo,
    ($zona !== '' ? $zona : null),
    ($direccion !== '' ? $direccion : null),
    $lat,
    $lon, // `lng`
    $precio_base,
    $estado_flag,      // estado tinyint(1)
    $estado_valla,     // enum('activa','inactiva')
    $disponible,
    $visible_publico,
    $comentarios,
    $imagenPrincipal
  );
  if (!mysqli_stmt_execute($stmt)) throw new Exception('INSERT_VALLA_FAIL');

  // Insert media
  if ($fotos) {
    $sqlm = "INSERT INTO valla_media (id, valla_id, tipo, url, principal, creado)
             VALUES (?, ?, 'foto', ?, ?, NOW())";
    $stmtm = mysqli_prepare($conn, $sqlm);
    foreach ($fotos as $idx => $f) {
      $url = (string)($f['url'] ?? '');
      if ($url === '') continue;
      // id manual también
      $rs2 = mysqli_query($conn, "SELECT COALESCE(MAX(id),0)+1 AS nid FROM valla_media");
      $mid = 1; if ($rs2 && ($rw = mysqli_fetch_assoc($rs2))) $mid = max(1, (int)$rw['nid']);
      $principal = !empty($f['principal']) ? 1 : 0;
      mysqli_stmt_bind_param($stmtm, 'iisi', $mid, $nextId, $url, $principal);
      if (!mysqli_stmt_execute($stmtm)) throw new Exception('INSERT_MEDIA_FAIL');
    }
  }

  // Destacada: si quieres persistirlo en otra tabla, aquí quedaría el hook.
  // TODO: vallas_destacadas_pagos según lógica de negocio.

  mysqli_commit($conn);
  json_exit(['ok'=>true, 'id'=>$nextId]);
} catch (Throwable $e) {
  mysqli_rollback($conn);
  json_exit(['ok'=>false,'error'=>$e->getMessage()], 500);
}

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw ?: '[]', true) ?: [];

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfBody   = $in['csrf'] ?? '';
if (empty($_SESSION['csrf']) || ($csrfHeader !== $_SESSION['csrf'] && $csrfBody !== $_SESSION['csrf'])) {
  http_response_code(419); echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']); exit;
}

$provider = trim((string)($in['provider_code'] ?? ''));
$style    = trim((string)($in['style_code'] ?? ''));
$lat      = (float)($in['lat'] ?? 18.486058);
$lng      = (float)($in['lng'] ?? -69.931212);
$zoom     = (int)($in['zoom'] ?? 12);
$token    = trim((string)($in['token'] ?? ''));

if ($lat < -90 || $lat > 90)   { echo json_encode(['ok'=>false,'msg'=>'Latitud fuera de rango']); exit; }
if ($lng < -180 || $lng > 180) { echo json_encode(['ok'=>false,'msg'=>'Longitud fuera de rango']); exit; }
if ($zoom < 1 || $zoom > 19)   { echo json_encode(['ok'=>false,'msg'=>'Zoom fuera de rango']); exit; }

// validar style -> provider
$styleProv = null;
$stmt = $conn->prepare("SELECT provider_code FROM map_styles WHERE style_code=? LIMIT 1");
$stmt->bind_param('s', $style);
$stmt->execute();
$stmt->bind_result($styleProv);
$stmt->fetch();
$stmt->close();
if (!$styleProv) { echo json_encode(['ok'=>false,'msg'=>'Estilo no válido']); exit; }
$provider = $styleProv;

// validar provider
$stmt = $conn->prepare("SELECT 1 FROM map_providers WHERE code=? LIMIT 1");
$stmt->bind_param('s', $provider);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close(); echo json_encode(['ok'=>false,'msg'=>'Proveedor no válido']); exit; }
$stmt->close();

// upsert id=1
$exists = 0;
if ($rs = $conn->query("SELECT 1 FROM map_settings WHERE id=1 LIMIT 1")) { $exists = (int)$rs->num_rows; $rs->free(); }

if ($exists) {
  if ($token === '') {
    $stmt = $conn->prepare("UPDATE map_settings SET provider_code=?, style_code=?, lat=?, lng=?, zoom=? WHERE id=1 LIMIT 1");
    $stmt->bind_param('ssddi', $provider, $style, $lat, $lng, $zoom);
  } else {
    $stmt = $conn->prepare("UPDATE map_settings SET provider_code=?, style_code=?, lat=?, lng=?, zoom=?, token=? WHERE id=1 LIMIT 1");
    $stmt->bind_param('ssddis', $provider, $style, $lat, $lng, $zoom, $token);
  }
  $ok = $stmt->execute(); $stmt->close();
} else {
  $stmt = $conn->prepare("INSERT INTO map_settings (id, provider_code, style_code, lat, lng, zoom, token) VALUES (1, ?, ?, ?, ?, ?, NULLIF(?, ''))");
  $stmt->bind_param('ssddis', $provider, $style, $lat, $lng, $zoom, $token);
  $ok = $stmt->execute(); $stmt->close();
}

if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error BD']); exit; }

echo json_encode(['ok'=>true,'msg'=>'Preferencias de mapa guardadas','data'=>[
  'provider_code'=>$provider,'style_code'=>$style,'lat'=>$lat,'lng'=>$lng,'zoom'=>$zoom,'token_saved'=>($token!==''?1:0)
]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

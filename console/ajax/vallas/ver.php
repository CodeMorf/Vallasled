<?php
// /console/ajax/vallas/ver.php
declare(strict_types=1);

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Guard */
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

/* Input */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$debug = filter_input(INPUT_GET, 'debug', FILTER_VALIDATE_INT) ? 1 : 0;
if (!$id || $id < 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

/* Helpers */
function oneRow(mysqli $conn, string $sql, string $types = '', array $params = []): ?array {
  $st = $conn->prepare($sql);
  if ($types !== '') { $refs=[]; $refs[]=&$types; foreach ($params as &$v) $refs[]=&$v; call_user_func_array([$st,'bind_param'],$refs); }
  $st->execute(); $res=$st->get_result(); return $res->fetch_assoc() ?: null;
}
function full_upload_url(?string $val, string $base): ?string {
  if (!$val) return null; $val=trim($val); if($val==='') return null;
  if (preg_match('~^https?://~i',$val)) return $val;
  $val = preg_replace('~^/+uploads/+~i','',$val); $val=ltrim($val,'/');
  return rtrim($base,'/').'/'.$val;
}
$UPLOADS_BASE = getenv('UPLOADS_BASE') ?: 'https://auth.vallasled.com/uploads/';

/* Debug mode */
$debug_mode = 0;
if ($debug) {
  $dbg = oneRow($conn, "SELECT valor FROM config_global WHERE clave='debug_mode' AND activo=1 ORDER BY id DESC LIMIT 1");
  $debug_mode = isset($dbg['valor']) && (int)$dbg['valor']===1 ? 1 : 0;
}

/* Query */
$sql = "
SELECT
  v.id, v.tipo, v.nombre, v.ubicacion, v.lat, v.lng,
  v.url_stream_pantalla, v.url_stream_trafico, v.url_stream, v.en_vivo,
  v.estado_valla, v.disponible, v.visible_publico,
  v.mostrar_precio_cliente, v.precio, v.medida,
  v.imagen, v.imagen1, v.imagen2, v.imagen_previa,
  v.frecuencia_dias, v.spot_time_seg, v.audiencia_mensual,
  v.numero_licencia, v.fecha_vencimiento,
  v.proveedor_id
FROM vallas v
WHERE v.id=? LIMIT 1";
$row = oneRow($conn, $sql, 'i', [$id]);
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

/* Build payload */
$imgs = [];
foreach (['imagen_previa','imagen','imagen1','imagen2','imagen3','imagen4'] as $k) {
  if (!empty($row[$k])) { $u = full_upload_url((string)$row[$k], $UPLOADS_BASE); if ($u) $imgs[] = $u; }
}
$payload = [
  'id'   => (int)$row['id'],
  'nombre' => (string)($row['nombre'] ?? ''),
  'tipo' => (string)($row['tipo'] ?? ''),
  'ubicacion' => (string)($row['ubicacion'] ?? ''),
  'coords' => [
    'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
    'lng' => isset($row['lng']) ? (float)$row['lng'] : null
  ],
  'streams' => [
    'pantalla' => (string)($row['url_stream_pantalla'] ?? ''),
    'trafico'  => (string)($row['url_stream_trafico'] ?? ''),
    'fallback' => (string)($row['url_stream'] ?? ''),
  ],
  'en_vivo'        => (int)($row['en_vivo'] ?? 0),
  'estado_valla'   => (string)($row['estado_valla'] ?? ''),
  'disponible'     => (int)($row['disponible'] ?? 0),
  'visible_publico'=> (int)($row['visible_publico'] ?? 0),
  'mostrar_precio_cliente' => (int)($row['mostrar_precio_cliente'] ?? 0),
  'precio'         => is_null($row['precio']) ? null : (float)$row['precio'],
  'medida'         => (string)($row['medida'] ?? ''),
  'media'          => [
    'cover' => $imgs[0] ?? null,
    'extra' => array_slice($imgs,1),
  ],
  'spot_time_seg'   => isset($row['spot_time_seg']) ? (int)$row['spot_time_seg'] : null,
  'frecuencia_dias' => isset($row['frecuencia_dias']) ? (int)$row['frecuencia_dias'] : null,
  'audiencia_mensual'=> isset($row['audiencia_mensual']) ? (int)$row['audiencia_mensual'] : null,
  'licencia' => [
    'numero' => (string)($row['numero_licencia'] ?? ''),
    'vencimiento' => $row['fecha_vencimiento'] ?? null,
  ],
  'proveedor_id' => isset($row['proveedor_id']) ? (int)$row['proveedor_id'] : null
];

echo json_encode([
  'ok'=>true,
  'valla'=>$payload,
  'debug' => $debug_mode ? ['sql'=>$sql, 'row'=>$row] : null
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

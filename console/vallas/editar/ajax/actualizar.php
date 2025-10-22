<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED']); exit;
}

if (!csrf_ok_from_header_or_post()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'CSRF_INVALID']); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>['id'=>'required']]); exit; }

/* normalización */
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$tipo     = in_array(($_POST['tipo'] ?? 'led'), ['led','impresa','movilled','vehiculo'], true) ? $_POST['tipo'] : 'led';
$provId   = (int)($_POST['provincia'] ?? 0);
$provId   = $provId > 0 ? $provId : null;
$provName = null; // si envías nombre, puedes mapearlo en tu guardar.php
$provincia_id = $provId;

$provSel = $provincia_id;
$proveedor_id = (int)($_POST['proveedor'] ?? 0) ?: null;

$zona     = trim((string)($_POST['zona'] ?? ''));
$ubicacion= trim((string)($_POST['ubicacion'] ?? ''));
$lat      = is_numeric($_POST['lat'] ?? null) ? (float)$_POST['lat'] : null;
$lng      = is_numeric($_POST['lng'] ?? null) ? (float)$_POST['lng'] : null;

$url_stream_pantalla = trim((string)($_POST['url_stream_pantalla'] ?? ''));
$url_stream_trafico  = trim((string)($_POST['url_stream_trafico'] ?? ''));
$precio   = is_numeric($_POST['precio'] ?? null) ? (float)$_POST['precio'] : 0.0;

$audiencia_mensual = (int)($_POST['audiencia_mensual'] ?? 0) ?: null;
$spot_time_seg     = (int)($_POST['spot_time_seg'] ?? 0) ?: null;
$capacidad_reservas= (int)($_POST['capacidad_reservas'] ?? 10);
$medida   = trim((string)($_POST['medida'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$keywords_seo = trim((string)($_POST['keywords_seo'] ?? ''));
$numero_licencia = trim((string)($_POST['numero_licencia'] ?? ''));

$fecha_vencimiento = trim((string)($_POST['fecha_vencimiento'] ?? ''));
if ($fecha_vencimiento !== '') {
  // acepta YYYY-mm-dd
  $fecha_vencimiento .= (strlen($fecha_vencimiento) === 10) ? ' 00:00:00' : '';
} else {
  $fecha_vencimiento = null;
}

$estado_valla = isset($_POST['estado_valla']) ? 'activa' : 'inactiva';
$visible_publico = isset($_POST['visible_publico']) ? 1 : 0;
$mostrar_precio_cliente = isset($_POST['mostrar_precio']) ? 1 : 0;

$imagen = trim((string)($_POST['imagen_url'] ?? ''));

/* validación mínima */
$fieldsErr = [];
if ($nombre === '') $fieldsErr['nombre'] = 'required';
if (!$provincia_id) $fieldsErr['provincia'] = 'required';
if ($zona === '') $fieldsErr['zona'] = 'required';
if ($ubicacion === '') $fieldsErr['ubicacion'] = 'required';
if ($lat === null || $lng === null) $fieldsErr['latlng'] = 'required';
if ($medida === '') $fieldsErr['medida'] = 'required';

if ($fieldsErr) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>$fieldsErr]); exit; }

/* conexión PDO preferida */
$pdo = null;
try {
  if (defined('DB_HOST')) {
    $pdo = new PDO(
      'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
      DB_USER,
      DB_PASS,
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB_CONNECT']); exit;
}

if (!$pdo) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB_NO_PDO']); exit;
}

$sql = "UPDATE vallas SET
  tipo=:tipo,
  nombre=:nombre,
  provincia_id=:provincia_id,
  proveedor_id=:proveedor_id,
  zona=:zona,
  ubicacion=:ubicacion,
  lat=:lat,
  lng=:lng,
  url_stream_pantalla=:url_stream_pantalla,
  url_stream_trafico=:url_stream_trafico,
  precio=:precio,
  imagen=:imagen,
  audiencia_mensual=:audiencia_mensual,
  spot_time_seg=:spot_time_seg,
  capacidad_reservas=:capacidad_reservas,
  medida=:medida,
  descripcion=:descripcion,
  keywords_seo=:keywords_seo,
  numero_licencia=:numero_licencia,
  fecha_vencimiento=:fecha_vencimiento,
  estado_valla=:estado_valla,
  visible_publico=:visible_publico,
  mostrar_precio_cliente=:mostrar_precio_cliente
WHERE id=:id LIMIT 1";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':tipo'=>$tipo,
    ':nombre'=>$nombre,
    ':provincia_id'=>$provincia_id,
    ':proveedor_id'=>$proveedor_id,
    ':zona'=>$zona,
    ':ubicacion'=>$ubicacion,
    ':lat'=>$lat,
    ':lng'=>$lng,
    ':url_stream_pantalla'=>$url_stream_pantalla ?: null,
    ':url_stream_trafico'=>$url_stream_trafico ?: null,
    ':precio'=>$precio,
    ':imagen'=>$imagen ?: null,
    ':audiencia_mensual'=>$audiencia_mensual,
    ':spot_time_seg'=>$spot_time_seg,
    ':capacidad_reservas'=>$capacidad_reservas,
    ':medida'=>$medida,
    ':descripcion'=>$descripcion ?: null,
    ':keywords_seo'=>$keywords_seo ?: null,
    ':numero_licencia'=>$numero_licencia ?: null,
    ':fecha_vencimiento'=>$fecha_vencimiento,
    ':estado_valla'=>$estado_valla,
    ':visible_publico'=>$visible_publico,
    ':mostrar_precio_cliente'=>$mostrar_precio_cliente,
    ':id'=>$id,
  ]);
  echo json_encode(['ok'=>true,'id'=>$id]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB_ERROR','detail'=>$e->getMessage()]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
}
if (!csrf_ok_from_header_or_post()) {
  json_exit(['ok'=>false,'error'=>'CSRF_INVALID'], 403);
}

/* Zona horaria RD para timestamps por defecto (fecha_creacion) */
@$conn->query("SET time_zone = '-04:00'"); // UTC-4 fijo

/* helpers */
function s(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function i(string $k): ?int   { $v = trim((string)($_POST[$k] ?? '')); return $v===''?null:(int)$v; }
function d(string $k): ?string{
  $v = str_replace([',',' '], ['.',''], (string)($_POST[$k] ?? ''));
  return $v===''?null:(string)(float)$v; // string; MySQL castea
}
function b(string $k): int    { return (isset($_POST[$k]) && ($_POST[$k]==='1' || $_POST[$k]==='on')) ? 1 : 0; }

/* usuario actual y rol */
$__auth   = $_SESSION['auth'] ?? [];
$__id     = (int)($__auth['id'] ?? $_SESSION['uid'] ?? $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 0);
$__tipo   = strtolower((string)($__auth['tipo'] ?? $_SESSION['tipo'] ?? ''));
$__guardar_responsable = ($__id > 0) && in_array($__tipo, ['admin','staff'], true);

/* entrada */
$nombre       = s('nombre');
$tipo         = s('tipo') ?: 'led';
$proveedor_id = (int)($_POST['proveedor'] ?? $_POST['proveedor_id'] ?? 0);
$provincia_id = (int)($_POST['provincia'] ?? $_POST['provincia_id'] ?? 0);
$zona         = s('zona');
$ubicacion    = s('ubicacion');
$lat          = d('lat');
$lng          = d('lng');

$medida       = s('medida');
$precio       = d('precio') ?? '0';
$audiencia    = (int)($_POST['audiencia_mensual'] ?? 0);
$spot_seg     = (int)($_POST['spot_time_seg'] ?? 0);
$url_pantalla = s('url_stream_pantalla');
$url_trafico  = s('url_stream_trafico');

$descripcion  = s('descripcion');
$keywords_seo = s('keywords_seo');
$imagen       = s('imagen_url');

$numero_lic   = s('numero_licencia');
$fv_raw       = s('fecha_vencimiento');
$fecha_venc   = $fv_raw ? date('Y-m-d 00:00:00', strtotime($fv_raw)) : null;

$estado_valla = b('estado_valla') ? 'activa' : 'inactiva';
$visible_pub  = b('visible_publico');
$mostrar_prec = b('mostrar_precio');
$capacidad    = (int)($_POST['capacidad_reservas'] ?? 10);

/* validación mínima */
$err=[];
if ($nombre==='')          $err['nombre']='requerido';
if ($proveedor_id<=0)      $err['proveedor']='requerido';
if ($provincia_id<=0)      $err['provincia']='requerido';
if ($zona==='')            $err['zona']='requerido';
if ($ubicacion==='')       $err['ubicacion']='requerido';
if ($lat===null)           $err['lat']='inválido';
if ($lng===null)           $err['lng']='inválido';
if (!in_array($tipo,['led','impresa','movilled','vehiculo'],true)) $err['tipo']='inválido';
if ($err) json_exit(['ok'=>false,'error'=>'VALIDATION','fields'=>$err], 422);

/* columnas y valores */
$cols = [
 'tipo','nombre','provincia_id','proveedor_id','zona','ubicacion','lat','lng',
 'url_stream_pantalla','url_stream_trafico','precio','estado','disponible',
 'imagen','audiencia_mensual','spot_time_seg','capacidad_reservas','medida',
 'descripcion','keywords_seo','numero_licencia','fecha_vencimiento',
 'estado_valla','visible_publico','mostrar_precio_cliente'
];
$vals = [
  $tipo, $nombre, (string)$provincia_id, (string)$proveedor_id, $zona, $ubicacion, $lat, $lng,
  $url_pantalla, $url_trafico, $precio, '1', '1',
  $imagen, (string)$audiencia, (string)$spot_seg, (string)$capacidad, $medida,
  $descripcion, $keywords_seo, $numero_lic, $fecha_venc,
  $estado_valla, (string)$visible_pub, (string)$mostrar_prec
];

/* responsable_id para admin/staff */
if ($__guardar_responsable) {
  $cols[] = 'responsable_id';
  $vals[] = (string)$__id;
}

try {
  $place = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO vallas (".implode(',',$cols).") VALUES ($place)";
  $stmt = $conn->prepare($sql);

  $types = str_repeat('s', count($vals));
  $tmp   = $vals;
  $bind  = [&$types];
  foreach ($tmp as $k=>&$v) { $bind[] = &$v; }
  call_user_func_array([$stmt,'bind_param'], $bind);

  $stmt->execute();
  $id = (int)$conn->insert_id;
  json_exit(['ok'=>true,'id'=>$id], 200);
} catch (Throwable $e) {
  json_exit(['ok'=>false,'error'=>'DB_ERROR','detail'=>$e->getMessage()], 500);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

function pstr(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function pnum(string $k): ?float { $v=trim((string)($_POST[$k] ?? '')); return ($v===''?null:(float)$v); }
function pbool(string $k): int { return (isset($_POST[$k]) && $_POST[$k]==='1') ? 1 : 0; }

$tipo = pstr('tipo') ?: 'impresa';
$nombre = pstr('nombre');
$provincia_id = (int)($_POST['provincia_id'] ?? 0);
$ubicacion = pstr('ubicacion');
$lat = pnum('lat');
$lng = pnum('lng');
$medida = pstr('medida');
$precio = pnum('precio');
$zona = pstr('zona');
$descripcion = pstr('descripcion');
$audiencia_mensual = (int)($_POST['audiencia_mensual'] ?? 0);
$spot_time_seg = (int)($_POST['spot_time_seg'] ?? 0);
$url_stream_pantalla = pstr('url_stream_pantalla');
$url_stream_trafico = pstr('url_stream_trafico');
$mostrar_precio_cliente = pbool('mostrar_precio_cliente');
$visible_publico = pbool('visible_publico');
$disponible = pbool('disponible');
$estado_valla_enum = (($_POST['estado_valla'] ?? '')==='1') ? 'activa' : 'inactiva';
$numero_licencia = pstr('numero_licencia');
$fecha_vencimiento = pstr('fecha_vencimiento');
if ($fecha_vencimiento!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_vencimiento)) $fecha_vencimiento='';

if ($nombre==='' || $provincia_id<=0 || $lat===null || $lng===null) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'faltan_campos']); exit;
}

try{
  $sql = "INSERT INTO vallas
    (tipo,nombre,provincia_id,ubicacion,lat,lng,medida,precio,zona,descripcion,
     audiencia_mensual,spot_time_seg,url_stream_pantalla,url_stream_trafico,
     mostrar_precio_cliente,visible_publico,disponible,estado_valla,numero_licencia,fecha_vencimiento)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $stmt = $conn->prepare($sql);
  $types = 'ssisddsdssii ssiiisss'; // placeholder visual
} catch(Throwable $e){ /* no-op */ }
try{
  $stmt = $conn->prepare("INSERT INTO vallas
    (tipo,nombre,provincia_id,ubicacion,lat,lng,medida,precio,zona,descripcion,
     audiencia_mensual,spot_time_seg,url_stream_pantalla,url_stream_trafico,
     mostrar_precio_cliente,visible_publico,disponible,estado_valla,numero_licencia,fecha_vencimiento)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  // tipos exactos: s s i s d d s d s s i i s s i i i s s s
  $stmt->bind_param(
    'ssisddsdssiissiiisss',
    $tipo,$nombre,$provincia_id,$ubicacion,$lat,$lng,$medida,$precio,$zona,$descripcion,
    $audiencia_mensual,$spot_time_seg,$url_stream_pantalla,$url_stream_trafico,
    $mostrar_precio_cliente,$visible_publico,$disponible,$estado_valla_enum,$numero_licencia,$fecha_vencimiento
  );
  $stmt->execute();
  echo json_encode(['ok'=>true,'valla_id'=>$stmt->insert_id]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']);
}

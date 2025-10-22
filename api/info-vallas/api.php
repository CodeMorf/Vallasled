<?php declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { json_response(['ok'=>false,'error'=>'MISSING_ID'], 400); }

function abs_url(string $u, string $base): string {
  $u = trim($u);
  if ($u === '') return '';
  // arregla "uploads/uploads"
  $u = preg_replace('~(uploads/)(?:uploads/)~i', '$1', $u);
  if (preg_match('~^https?://~i', $u)) return $u;
  if ($u[0] === '/') $u = ltrim($u, '/');
  return rtrim($base, '/') . '/' . $u;
}

try {
  $pdo = db();
  $base = db_setting('uploads_base', 'https://auth.vallasled.com/uploads/');
  $q = $pdo->prepare("
    SELECT v.*, p.nombre AS provincia
      FROM vallas v
      LEFT JOIN provincias p ON p.id = v.provincia_id
     WHERE v.id = ?
     LIMIT 1
  ");
  $q->execute([$id]);
  $v = $q->fetch();
  if (!$v) json_response(['ok'=>false,'error'=>'NOT_FOUND'], 404);

  // Media (columnas + tabla valla_media si existe)
  $imgs = [];
  foreach (['imagen','imagen1','imagen2','imagen_previa','imagen_tercera','imagen_cuarta'] as $k) {
    if (!empty($v[$k])) $imgs[] = ['url'=>abs_url((string)$v[$k], $base), 'kind'=>'col'];
  }
  try {
    $m = $pdo->prepare("SELECT url, tipo FROM valla_media WHERE valla_id=? ORDER BY orden ASC, id ASC");
    $m->execute([$id]);
    foreach ($m as $r) $imgs[] = ['url'=>abs_url((string)$r['url'], $base), 'kind'=>$r['tipo'] ?: 'media'];
  } catch (Throwable $e) {
    // tabla opcional
  }

  $out = [
    'ok'   => true,
    'data' => [
      'id'        => (int)$v['id'],
      'tipo'      => $v['tipo'],
      'nombre'    => $v['nombre'],
      'provincia' => $v['provincia'],
      'provincia_id' => $v['provincia_id'],
      'zona'      => $v['zona'],
      'ubicacion' => $v['ubicacion'],
      'lat'       => is_null($v['lat']) ? null : (float)$v['lat'],
      'lng'       => is_null($v['lng']) ? null : (float)$v['lng'],
      'medida'    => $v['medida'],
      'descripcion'=> $v['descripcion'],
      'precio'    => is_null($v['precio']) ? null : (float)$v['precio'],
      'disponible'=> (int)$v['disponible'],
      'en_vivo'   => (int)$v['en_vivo'],
      'url_stream_pantalla' => $v['url_stream_pantalla'],
      'url_stream_trafico'  => $v['url_stream_trafico'],
      'url_stream'          => $v['url_stream'],
      'audiencia_mensual'   => is_null($v['audiencia_mensual']) ? null : (int)$v['audiencia_mensual'],
      'spot_time_seg'       => is_null($v['spot_time_seg']) ? null : (int)$v['spot_time_seg'],
      'media'     => $imgs
    ]
  ];
  json_response($out);
} catch (Throwable $e) {
  json_response(['ok'=>false,'error'=>'INTERNAL'], 500);
}

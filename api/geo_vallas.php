<?php declare(strict_types=1);
/**
 * /api/geo_vallas.php — GeoJSON para mapa
 * Filtros: q, tipo, zona, provincia, disponible, limit
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/debug.php';

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: public, max-age=300');

$q         = trim((string)($_GET['q'] ?? ''));
$tipo      = trim((string)($_GET['tipo'] ?? ''));
$zona      = trim((string)($_GET['zona'] ?? ''));
$provincia = trim((string)($_GET['provincia'] ?? ''));
$disp      = trim((string)($_GET['disponible'] ?? ''));
$limit     = max(1, min(1000, (int)($_GET['limit'] ?? 500)));

try {
  $pdo = db();

  $where = ["v.visible_publico = 1", "v.estado_valla = 'activa'", "v.lat IS NOT NULL", "v.lng IS NOT NULL"];
  $params = [];

  if ($q !== '')         { $where[]="(v.nombre LIKE ? OR v.ubicacion LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; }
  if ($tipo !== '')      { $where[]="v.tipo = ?";        $params[]=$tipo; }
  if ($zona !== '')      { $where[]="v.zona = ?";        $params[]=$zona; }
  if ($provincia !== '') { $where[]="v.provincia_id = ?";$params[]=(int)$provincia; }
  if ($disp !== '')      { $where[]="v.disponible = ?";  $params[]=(int)$disp; }

  $sql = "
    SELECT v.id, v.nombre, v.tipo, v.ubicacion, v.lat, v.lng, v.precio, v.disponible,
           (SELECT m.url FROM valla_media m WHERE m.valla_id=v.id ORDER BY m.principal DESC, m.id ASC LIMIT 1) AS media_url
      FROM vallas v
     WHERE ".implode(' AND ', $where)."
     ORDER BY v.id DESC
     LIMIT $limit
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $features = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $lat = isset($r['lat']) ? (float)$r['lat'] : null;
    $lng = isset($r['lng']) ? (float)$r['lng'] : null;
    if ($lat === null || $lng === null) continue;

    $props = [
      'id'         => (int)$r['id'],
      'nombre'     => (string)$r['nombre'],
      'tipo'       => (string)$r['tipo'],
      'ubicacion'  => (string)($r['ubicacion'] ?? ''),
      'precio'     => isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'disponible' => (int)($r['disponible'] ?? 0),
      'media_url'  => (string)($r['media_url'] ?? ''),
    ];

    $features[] = [
      'type'       => 'Feature',
      'geometry'   => ['type'=>'Point','coordinates'=>[(float)$lng,(float)$lat]],
      'properties' => $props,
    ];
  }

  echo json_encode(['type'=>'FeatureCollection','features'=>$features], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL']);
}

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/media.php';

function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
function s($v): string { return trim((string)$v); }

try {
  $pdo = db();

  // Filtros
  $id          = i($_GET['id'] ?? 0);
  $q           = s($_GET['q'] ?? '');
  $zona        = s($_GET['zona'] ?? '');
  $provId      = i($_GET['provincia'] ?? 0);             // por id
  $provNom     = s($_GET['provincia_nombre'] ?? '');     // por nombre
  $disponible  = $_GET['disponible'] ?? null;

  $where  = ["v.visible_publico=1", "v.estado_valla='activa'", "v.tipo='led'"];
  $params = [];

  if ($id > 0)           { $where[] = "v.id=:id"; $params[':id'] = $id; }
  if ($q !== '')         { $where[] = "(v.nombre LIKE :q OR v.ubicacion LIKE :q)"; $params[':q'] = "%$q%"; }
  if ($zona !== '')      { $where[] = "v.zona=:zona"; $params[':zona'] = $zona; }
  if ($provId > 0)       { $where[] = "v.provincia_id=:pid"; $params[':pid'] = $provId; }
  if ($provNom !== '')   { $where[] = "p.nombre LIKE :pnom"; $params[':pnom'] = "%$provNom%"; }
  if ($disponible !== null && $disponible !== '') {
    $where[] = "v.disponible=" . (intval($disponible) ? "1" : "0");
  }

  $sql = "
    SELECT
      v.id, v.nombre, v.tipo, v.ubicacion, v.zona, v.provincia_id,
      p.nombre AS provincia,
      v.precio, v.disponible, v.medida, v.lat, v.lng,
      v.spot_time_seg, v.descripcion,
      v.url_stream_pantalla, v.url_stream_trafico, v.url_stream,
      v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta
    FROM vallas v
    LEFT JOIN provincias p ON p.id = v.provincia_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY v.id DESC
    LIMIT " . ($id>0 ? 1 : 100) . "
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    // Media: prioriza primera foto; si no, placeholder
    $media = [];
    foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c) {
      $u = media_norm((string)($r[$c] ?? ''));
      if ($u === '') continue;
      $media[] = ['tipo' => (is_vid($u) ? 'video' : 'foto'), 'url' => $u];
      if ($media[0]['tipo'] === 'foto') break;
    }
    if (!$media) $media[] = ['tipo'=>'foto','url'=>'https://placehold.co/800x450/e2e8f0/475569?text=Sin+imagen'];

    $items[] = [
      'id'         => (int)$r['id'],
      'nombre'     => (string)$r['nombre'],
      'tipo'       => (string)$r['tipo'],                 // siempre "led"
      'ubicacion'  => (string)($r['ubicacion'] ?? ''),
      'zona'       => (string)($r['zona'] ?? ''),
      'provincia_id' => isset($r['provincia_id']) ? (int)$r['provincia_id'] : null,
      'provincia'  => (string)($r['provincia'] ?? ''),
      'precio'     => isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'disponible' => (int)($r['disponible'] ?? 0),
      'medida'     => (string)($r['medida'] ?? ''),
      'lat'        => isset($r['lat']) ? (float)$r['lat'] : null,
      'lng'        => isset($r['lng']) ? (float)$r['lng'] : null,
      'spot_time_seg' => isset($r['spot_time_seg']) ? (int)$r['spot_time_seg'] : null,
      'descripcion'=> (string)($r['descripcion'] ?? ''),
      'url_stream_pantalla' => (string)($r['url_stream_pantalla'] ?? ''),
      'url_stream_trafico'  => (string)($r['url_stream_trafico'] ?? ''),
      'url_stream'          => (string)($r['url_stream'] ?? ''), // compat
      'media'      => $media,
    ];
  }

  // Respuesta: si viene id devolvemos objeto, si no, lista
  if ($id>0) {
    echo json_encode(['ok'=>true,'data'=>$items[0] ?? null], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  } else {
    echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL']);
}

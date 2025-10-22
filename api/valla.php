<?php declare(strict_types=1);
require dirname(__DIR__) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id  = (int)($_GET['id'] ?? 0);
$all = (int)($_GET['all'] ?? 0);

$MEDIA_BASE = defined('MEDIA_BASE_URL') && MEDIA_BASE_URL !== ''
  ? rtrim((string)MEDIA_BASE_URL,'/')
  : 'https://auth.vallasled.com/uploads';

$join_media = function(string $base, string $p): string {
  $base = rtrim($base,'/'); $p = ltrim($p,'/');
  if (preg_match('~^uploads/~i',$p) && preg_match('~/uploads$~i',$base)) $p = preg_replace('~^uploads/~i','',$p);
  $parts = explode('?', $p, 2);
  $pth = implode('/', array_map('rawurlencode', array_filter(explode('/', $parts[0]), 'strlen')));
  return $base.'/'.$pth.(isset($parts[1]) && $parts[1]!=='' ? ('?'.$parts[1]) : '');
};
$norm_media = function(string $raw) use ($MEDIA_BASE, $join_media): ?string {
  $raw = trim($raw); if ($raw==='') return null;
  if (preg_match('~^https?://~i',$raw)) return $raw;
  if (strpos($raw,'//')===0) return 'https:'.$raw;
  if (preg_match('~/uploads/~',$raw)) return $join_media($MEDIA_BASE,$raw);
  return $join_media($MEDIA_BASE,'uploads/'.$raw);
};
$is_img = fn(string $u): bool => in_array(strtolower(pathinfo(parse_url($u, PHP_URL_PATH)??'', PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif','avif','bmp'], true);
$build_media = function(array $r) use ($norm_media, $is_img): array {
  $out=[];
  foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c){
    $u = $norm_media((string)($r[$c]??'')); if (!$u) continue;
    if ($is_img($u)) { $out[] = ['tipo'=>'foto','url'=>$u]; if (count($out)>=1) break; }
  }
  if (!$out) $out[] = ['tipo'=>'foto','url'=>'https://placehold.co/800x450/e2e8f0/4b5563?text=Sin+Imagen'];
  return $out;
};

try {
  $pdo = db();

  if ($all === 1 && $id === 0) {
    $sql = "SELECT id, tipo, nombre, provincia_id, zona, ubicacion, lat, lng,
                   url_stream_pantalla, url_stream_trafico,
                   precio, disponible, medida, descripcion,
                   mostrar_precios_market,
                   imagen, imagen1, imagen2, imagen_previa, imagen_tercera, imagen_cuarta
              FROM vallas
             WHERE visible_publico = 1 AND estado_valla = 'activa'
          ORDER BY COALESCE(destacado_orden, 999999), nombre ASC";
    $rows = $pdo->query($sql)->fetchAll();

    $items = [];
    foreach ($rows as $r){
      $items[] = [
        'id' => (int)$r['id'],
        'nombre' => (string)$r['nombre'],
        'provincia_id' => isset($r['provincia_id']) ? (int)$r['provincia_id'] : null,
        'zona' => (string)($r['zona'] ?? ''),
        'ubicacion' => (string)($r['ubicacion'] ?? ''),
        'precio' => isset($r['precio']) ? (float)$r['precio'] : 0.0,
        'tipo' => (string)$r['tipo'],
        'lat' => isset($r['lat']) ? (float)$r['lat'] : null,
        'lng' => isset($r['lng']) ? (float)$r['lng'] : null,
        'disponible' => (int)($r['disponible'] ?? 0),
        'mostrar_precios_market' => (int)($r['mostrar_precios_market'] ?? 0),
        'medida' => (string)($r['medida'] ?? ''),
        'descripcion' => (string)($r['descripcion'] ?? ''),
        'media' => $build_media($r),
        'url_stream_pantalla' => (string)($r['url_stream_pantalla'] ?? ''),
        'url_stream_trafico'  => (string)($r['url_stream_trafico'] ?? ''),
      ];
    }
    echo json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($id > 0) {
    $stmt = $pdo->prepare("
      SELECT v.id, v.tipo, v.nombre, v.provincia_id, p.nombre AS provincia_nombre,
             v.zona, v.ubicacion, v.lat, v.lng,
             v.url_stream_pantalla, v.url_stream_trafico,
             v.precio, v.disponible, v.medida, v.descripcion,
             v.mostrar_precios_market,
             v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta
        FROM vallas v
   LEFT JOIN provincias p ON p.id = v.provincia_id
       WHERE v.visible_publico = 1 AND v.estado_valla = 'activa' AND v.id = ?
       LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit; }

    $out = [
      'id' => (int)$row['id'],
      'nombre' => (string)$row['nombre'],
      'provincia_id' => isset($row['provincia_id']) ? (int)$row['provincia_id'] : null,
      'provincia' => (string)($row['provincia_nombre'] ?? ''),
      'zona' => (string)($row['zona'] ?? ''),
      'ubicacion' => (string)($row['ubicacion'] ?? ''),
      'precio' => isset($row['precio']) ? (float)$row['precio'] : 0.0,
      'tipo' => (string)$row['tipo'],
      'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
      'lng' => isset($row['lng']) ? (float)$row['lng'] : null,
      'disponible' => (int)($row['disponible'] ?? 0),
      'mostrar_precios_market' => (int)($row['mostrar_precios_market'] ?? 0),
      'medida' => (string)($row['medida'] ?? ''),
      'descripcion' => (string)($row['descripcion'] ?? ''),
      'media' => $build_media($row),
      'url_stream_pantalla' => (string)($row['url_stream_pantalla'] ?? ''),
      'url_stream_trafico'  => (string)($row['url_stream_trafico'] ?? ''),
    ];
    echo json_encode(['ok'=>true,'valla'=>$out], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }

  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST','hint'=>'use ?all=1 o ?id={int}']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL','msg'=>$e->getMessage()]);
}

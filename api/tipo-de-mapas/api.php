<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/media.php';

function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
function s($v): string { return trim((string)$v); }

try {
  $pdo = db();

  /* ----------------------- Filtros catálogo ----------------------- */
  $id          = i($_GET['id'] ?? 0);
  $q           = s($_GET['q'] ?? '');
  $tipo        = s($_GET['tipo'] ?? '');
  $zona        = s($_GET['zona'] ?? '');
  $provinciaId = i($_GET['provincia'] ?? 0);         // id numérico (compat)
  $provNom     = s($_GET['provincia_nombre'] ?? ''); // opcional por nombre
  $disponible  = $_GET['disponible'] ?? null;

  $where  = ["v.visible_publico=1", "v.estado_valla='activa'"];
  $params = [];

  if ($id > 0) { $where[] = "v.id=:id"; $params[':id'] = $id; }
  if ($q !== '') { $where[] = "(v.nombre LIKE :q OR v.ubicacion LIKE :q)"; $params[':q'] = "%$q%"; }
  if ($tipo !== '') { $where[] = "v.tipo=:tipo"; $params[':tipo'] = $tipo; }
  if ($zona !== '') { $where[] = "v.zona=:zona"; $params[':zona'] = $zona; }
  if ($provinciaId > 0) { $where[] = "v.provincia_id=:provincia"; $params[':provincia'] = $provinciaId; }
  if ($provNom !== '') { $where[] = "p.nombre LIKE :provnom"; $params[':provnom'] = "%$provNom%"; }
  if ($disponible !== null && $disponible !== '') {
    $where[] = "v.disponible=" . (intval($disponible) ? "1" : "0");
  }

  $sql = "
    SELECT
      v.id, v.nombre, v.tipo, v.ubicacion, v.zona, v.provincia_id, v.precio, v.disponible,
      v.medida, v.lat, v.lng,
      v.url_stream_pantalla, v.url_stream_trafico,
      v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta,
      p.nombre AS provincia
    FROM vallas v
    LEFT JOIN provincias p ON p.id = v.provincia_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY v.id DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  /* ----------------------- Normalización catálogo ----------------------- */
  $items = [];
  foreach ($rows as $r) {
    // Galería: prioriza primera foto disponible, corta cuando encuentra una foto
    $media = [];
    foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c) {
      $u = media_norm((string)($r[$c] ?? ''));
      if ($u === '') continue;
      $media[] = ['tipo' => (is_vid($u) ? 'video' : 'foto'), 'url' => $u];
      if ($media[0]['tipo'] === 'foto') break;
    }
    if (!$media) $media[] = ['tipo'=>'foto','url'=>'https://placehold.co/600x340/e2e8f0/475569?text=Sin+imagen'];

    $items[] = [
      'id'         => (int)$r['id'],
      'nombre'     => (string)$r['nombre'],
      'tipo'       => (string)$r['tipo'],
      'ubicacion'  => (string)($r['ubicacion'] ?? ''),
      'zona'       => (string)($r['zona'] ?? ''),
      'provincia_id' => isset($r['provincia_id']) ? (int)$r['provincia_id'] : null,
      'provincia'  => (string)($r['provincia'] ?? ''),
      'precio'     => isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'disponible' => (int)($r['disponible'] ?? 0),
      'medida'     => (string)($r['medida'] ?? ''),
      'lat'        => isset($r['lat']) ? (float)$r['lat'] : null,
      'lng'        => isset($r['lng']) ? (float)$r['lng'] : null,
      'url_stream_pantalla' => (string)($r['url_stream_pantalla'] ?? ''),
      'url_stream_trafico'  => (string)($r['url_stream_trafico'] ?? ''),
      'media'      => $media,
    ];
  }

  /* ----------------------- Configuración de mapas ----------------------- */
  // Selección actual
  $mapSql = "
    SELECT
      ms.provider_code, ms.style_code, ms.lat, ms.lng, ms.zoom, ms.map_id, ms.style_url, ms.token,
      mp.name AS provider_name, mp.is_free AS provider_free, mp.requires_key, mp.site_url,
      st.style_name, st.tile_url, st.subdomains, st.attribution_html, st.preview_image, st.is_free AS style_free
    FROM map_settings ms
    JOIN map_providers mp ON mp.code = ms.provider_code
    JOIN map_styles    st ON st.provider_code = ms.provider_code AND st.style_code = ms.style_code
    WHERE ms.id = 1
    LIMIT 1";
  $mapRow = $pdo->query($mapSql)->fetch(PDO::FETCH_ASSOC);

  // Lista de estilos gratuitos disponibles (mínimo 5 según tu semilla)
  $availSql = "
    SELECT provider_code, style_code, style_name, preview_image, is_free
    FROM map_styles
    WHERE is_free = 1
    ORDER BY provider_code, style_name";
  $avail = $pdo->query($availSql)->fetchAll(PDO::FETCH_ASSOC);

  // Subdominios: admite formato 'abc' o 'a,b,c,d'
  $subdomains = [];
  if ($mapRow && !empty($mapRow['subdomains'])) {
    $sd = trim((string)$mapRow['subdomains']);
    if (strpos($sd, ',') !== false) {
      $subdomains = array_values(array_filter(array_map('trim', explode(',', $sd)), fn($x)=>$x!==''));
    } else {
      $subdomains = str_split($sd);
    }
  }

  $map = $mapRow ? [
    'provider' => [
      'code'         => (string)$mapRow['provider_code'],
      'name'         => (string)$mapRow['provider_name'],
      'is_free'      => (bool)$mapRow['provider_free'],
      'requires_key' => (bool)$mapRow['requires_key'],
      'site_url'     => (string)($mapRow['site_url'] ?? '')
    ],
    'style' => [
      'code'             => (string)$mapRow['style_code'],
      'name'             => (string)$mapRow['style_name'],
      'tile_url'         => (string)$mapRow['tile_url'],
      'subdomains'       => $subdomains,
      'attribution_html' => (string)$mapRow['attribution_html'],
      'preview_image'    => (string)($mapRow['preview_image'] ?? ''),
      'is_free'          => (bool)$mapRow['style_free']
    ],
    'center' => [
      'lat'  => (float)$mapRow['lat'],
      'lng'  => (float)$mapRow['lng'],
      'zoom' => (int)$mapRow['zoom'],
    ],
    // Campos opcionales para proveedores que los usen
    'overrides' => [
      'style_url' => (string)($mapRow['style_url'] ?? ''), // Mapbox/MapLibre GL
      'map_id'    => (string)($mapRow['map_id'] ?? ''),    // Google Maps MapID
      'token'     => (string)($mapRow['token'] ?? '')      // Mapbox/MapTiler
    ],
    'available_free_styles' => array_map(static function(array $r): array {
      return [
        'provider_code' => (string)$r['provider_code'],
        'style_code'    => (string)$r['style_code'],
        'style_name'    => (string)$r['style_name'],
        'preview_image' => (string)($r['preview_image'] ?? ''),
        'is_free'       => (bool)$r['is_free'],
      ];
    }, $avail)
  ] : null;

  /* ----------------------- Respuesta ----------------------- */
  echo json_encode([
    'ok'    => true,
    'items' => $items,
    'map'   => $map
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'INTERNAL']);
}

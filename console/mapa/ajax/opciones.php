<?php
// /console/mapa/ajax/opciones.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

try {
  // Settings por defecto RD
  $settings = [
    'provider_code' => 'osm',
    'style_code'    => 'osm.standard',
    'lat'           => 18.4860580,
    'lng'           => -69.9312120,
    'zoom'          => 12,
    'map_id'        => null,
    'style_url'     => null,
    'token'         => null,
  ];

  if ($rs = $conn->query("SELECT provider_code, style_code, lat, lng, zoom, map_id, style_url, token FROM map_settings WHERE id=1 LIMIT 1")) {
    if ($row = $rs->fetch_assoc()) {
      $settings = [
        'provider_code' => (string)$row['provider_code'],
        'style_code'    => (string)$row['style_code'],
        'lat'           => (float)$row['lat'],
        'lng'           => (float)$row['lng'],
        'zoom'          => (int)$row['zoom'],
        'map_id'        => $row['map_id'] ?? null,
        'style_url'     => $row['style_url'] ?? null,
        'token'         => $row['token'] ?? null,
      ];
    }
    $rs->free();
  }

  // Providers con al menos un estilo disponible
  $providers = [];
  $qProv = "
    SELECT DISTINCT p.code, p.name
    FROM map_providers p
    JOIN map_styles s ON s.provider_code = p.code
    ORDER BY p.name ASC";
  if ($rs = $conn->query($qProv)) {
    while ($row = $rs->fetch_assoc()) {
      $providers[] = ['code' => (string)$row['code'], 'name' => (string)$row['name']];
    }
    $rs->free();
  }

  // Estilos
  $styles = [];
  $qStyles = "
    SELECT provider_code, style_code, style_name, tile_url, subdomains, attribution_html, preview_image, is_default
    FROM map_styles
    ORDER BY is_default DESC, id ASC";
  if ($rs = $conn->query($qStyles)) {
    while ($row = $rs->fetch_assoc()) {
      $styles[] = [
        'provider_code' => (string)$row['provider_code'],
        'style_code'    => (string)$row['style_code'],
        'style_name'    => (string)$row['style_name'],
        'tile_url'      => (string)$row['tile_url'],
        'subdomains'    => $row['subdomains'] !== null ? (string)$row['subdomains'] : null,
        'attribution'   => (string)$row['attribution_html'],
        'preview_image' => $row['preview_image'] ?: null,
        'is_default'    => (int)$row['is_default'],
      ];
    }
    $rs->free();
  }

  echo json_encode(['ok'=>true, 'settings'=>$settings, 'providers'=>$providers, 'styles'=>$styles], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error interno']); // no exponer errores
}

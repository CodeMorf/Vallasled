<?php declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

/* Config */
$cfg = __DIR__ . '/../config/db.php';
$med = __DIR__ . '/../config/media.php';
if (file_exists($cfg)) require $cfg;
if (file_exists($med)) require $med;

/* Helpers seguros */
function fnum($v, float $def=0.0): float {
  if ($v===null) return $def;
  $v = str_replace(',', '.', (string)$v);
  return filter_var($v, FILTER_VALIDATE_FLOAT) !== false ? (float)$v : $def;
}
function clampf(float $v, float $min, float $max): float { return max($min, min($max, $v)); }
function haversine_km(float $lat1,float $lon1,float $lat2,float $lon2): float {
  $R=6371.0088; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
function initial_bearing(float $lat1,float $lon1,float $lat2,float $lon2): float {
  $y = sin(deg2rad($lon2-$lon1))*cos(deg2rad($lat2));
  $x = cos(deg2rad($lat1))*sin(deg2rad($lat2)) - sin(deg2rad($lat1))*cos(deg2rad($lat2))*cos(deg2rad($lon2-$lon1));
  $brng = rad2deg(atan2($y,$x));
  return fmod(($brng+360.0), 360.0);
}
function bearing_cardinal(float $b): string {
  $dirs=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  return $dirs[(int)round($b/22.5) % 16];
}

/* Inputs */
$ulat = fnum($_GET['lat'] ?? $_GET['user_lat'] ?? null, NAN);
$ulng = fnum($_GET['lng'] ?? $_GET['user_lng'] ?? null, NAN);
$vid  = (int)($_GET['valla_id'] ?? 0);
$vlat = fnum($_GET['vlat'] ?? null, NAN);
$vlng = fnum($_GET['vlng'] ?? null, NAN);
$mode = strtolower((string)($_GET['mode'] ?? 'driving')); // driving|walking|cycling
$speed = fnum($_GET['speed_kmh'] ?? null, 0.0); // opcional override

/* Validación básica */
if (!is_finite($ulat) || !is_finite($ulng)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'lat/lng de usuario requeridos']); exit;
}

/* Origen valla: por id o por coords directas */
$screen_url=''; $traffic_url='';
if ($vid > 0) {
  try {
    $pdo = db();
    $st = $pdo->prepare("SELECT lat, lng, url_stream_pantalla, url_stream_trafico
                         FROM vallas
                         WHERE id=:id AND visible_publico=1 AND estado_valla='activa' LIMIT 1");
    $st->execute([':id'=>$vid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $vlat = fnum($row['lat'], NAN);
      $vlng = fnum($row['lng'], NAN);
      $screen_url  = (string)($row['url_stream_pantalla'] ?? '');
      $traffic_url = (string)($row['url_stream_trafico'] ?? '');
    }
  } catch(Throwable $e) {}
}

if (!is_finite($vlat) || !is_finite($vlng)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Coordenadas de valla inválidas o no encontradas']); exit;
}

/* Distancia y rumbo */
$km = haversine_km($ulat, $ulng, $vlat, $vlng);
$bearing = initial_bearing($ulat, $ulng, $vlat, $vlng);

/* Velocidades por modo (aprox, sin tráfico; mapas gratis sin API) */
$SPEEDS = [
  'driving' => 28.0,   // ~100 km/h autopista se reduce a promedio urbano/interurbano
  'cycling' => 15.0,   // km/h
  'walking' => 4.5
];
$mode = in_array($mode, ['driving','cycling','walking'], true) ? $mode : 'driving';
$vk = $speed > 0 ? clampf($speed, 1.0, 160.0) : $SPEEDS[$mode];
$minutes = ($vk > 0) ? (60.0 * $km / $vk) : NAN;

/* Enlaces de mapa gratuitos (OpenStreetMap) */
$zoom = 14;
$osm_link = sprintf(
  'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=%.6f,%.6f;%.6f,%.6f#map=%d/%.6f/%.6f',
  $ulat,$ulng,$vlat,$vlng,$zoom, ($ulat+$vlat)/2, ($ulng+$vlng)/2
);
/* Marcadores simples: centro en valla con marcador */
$osm_marker = sprintf('https://www.openstreetmap.org/?mlat=%.6f&mlon=%.6f#map=%d/%.6f/%.6f',
  $vlat,$vlng,$zoom,$vlat,$vlng
);

/* Respuesta */
echo json_encode([
  'ok' => true,
  'inputs' => [
    'user' => ['lat'=>$ulat, 'lng'=>$ulng],
    'valla'=> ['id'=>$vid ?: null, 'lat'=>$vlat, 'lng'=>$vlng],
    'mode' => $mode,
    'speed_kmh_used' => $vk
  ],
  'distance' => [
    'km' => round($km, 3),
    'm'  => (int)round($km*1000),
    'bearing_deg' => round($bearing, 1),
    'bearing_cardinal' => bearing_cardinal($bearing)
  ],
  'eta' => [
    'minutes' => is_finite($minutes) ? round($minutes, 1) : null
  ],
  'streams' => [
    'pantalla' => $screen_url ?: null,
    'trafico'  => $traffic_url ?: null
  ],
  'maps' => [
    'osm_route'  => $osm_link,
    'osm_marker' => $osm_marker
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

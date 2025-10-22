<?php
// Sirve el JS de Google Maps sin exponer la key en el HTML.
// Restringe la key por HTTP referrer en Google Cloud.
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
start_session_safe();

// Lee key desde DB o ENV
$gmaps_key = '';
try {
  $stmt = $conn->prepare("SELECT valor FROM config_global WHERE clave='google_maps_api_key' AND activo=1 ORDER BY id DESC LIMIT 1");
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) $gmaps_key = trim((string)$row['valor']);
} catch (Throwable $e) {}
if (!$gmaps_key && !empty($_ENV['GOOGLE_MAPS_API_KEY'])) $gmaps_key = (string)$_ENV['GOOGLE_MAPS_API_KEY'];

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: public, max-age=600');

if (!$gmaps_key) {
  http_response_code(500);
  echo 'console.error("Google Maps API key missing");';
  exit;
}

// Libs que usas hoy. Agrega "marker" si migras a AdvancedMarkerElement.
$q = http_build_query([
  'key'       => $gmaps_key,
  'v'         => 'weekly',
  'libraries' => 'places',
  'loading'   => 'async',
  'callback'  => 'initGMap',
]);

$upstream = "https://maps.googleapis.com/maps/api/js?$q";
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$js = @file_get_contents($upstream, false, $ctx);

if ($js === false) {
  http_response_code(502);
  echo 'console.error("Failed to fetch Google Maps JS");';
  exit;
}

echo $js;

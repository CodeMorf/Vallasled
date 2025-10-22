<?php declare(strict_types=1);
/**
 * /api/zipcodes.php — Búsqueda de códigos postales (gratis)
 * Fuente: Nominatim (OSM), con filtro por provincia opcional.
 * Params:
 *   q=texto|postalcode   provincia_id?=int   limit?=50   debug?=1
 * Respuesta: { ok, items:[{code,nombre,provincia,lat,lng,source}], count }
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/debug.php';
@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: max-age=900, public');

$q            = trim((string)($_GET['q'] ?? ''));
$provincia_id = isset($_GET['provincia_id']) ? (int)$_GET['provincia_id'] : null;
$limit        = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$DEBUG        = (int)($_GET['debug'] ?? 0) === 1;

// Helpers HTTP gratis (usa maps.php si existe)
$maps = __DIR__ . '/../config/maps.php';
if (is_file($maps)) require_once $maps;
if (!function_exists('nominatim_user_agent')) {
  function nominatim_user_agent(): string {
    return (db_setting('site_name','Vallasled.com') . ' (' . db_setting('nominatim_email','admin@vallasled.com') . ')');
  }
}
if (!function_exists('http_get_json')) {
  function http_get_json(string $url, array $headers = [], int $timeout = 15): array {
    $ua = 'User-Agent: ' . nominatim_user_agent();
    $headers = $headers ?: [$ua, 'Accept: application/json'];
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
      ]);
      $out = curl_exec($ch);
      $err = curl_error($ch);
      curl_close($ch);
      if ($out === false) return ['error' => $err];
      $json = json_decode((string)$out, true);
      return is_array($json) ? $json : ['raw' => $out];
    }
    $ctx = stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$headers),'timeout'=>$timeout]]);
    $out = @file_get_contents($url, false, $ctx);
    if ($out === false) return ['error'=>'request_failed'];
    $json = json_decode((string)$out, true);
    return is_array($json) ? $json : ['raw'=>$out];
  }
}

// Nombre de provincia si se pasa id
$provName = null;
if ($provincia_id) {
  try {
    $st = db()->prepare('SELECT nombre FROM provincias WHERE id = ? LIMIT 1');
    $st->execute([$provincia_id]);
    $r = $st->fetch();
    $provName = $r['nombre'] ?? null;
  } catch (Throwable $e) { /* opcional */ }
}

// Construir consulta Nominatim
$base = 'https://nominatim.openstreetmap.org/search';
$params = [
  'format'          => 'jsonv2',
  'limit'           => $limit,
  'addressdetails'  => 1,
  'countrycodes'    => 'do',           // República Dominicana
  'accept-language' => 'es',
];

// Si q parece postalcode, usar parámetro postalcode, si no usar q libre
if ($q !== '' && preg_match('/^\d{3,8}$/', $q)) {
  $params['postalcode'] = $q;
} else {
  // búsqueda textual, ejemplo: "Zona Colonial" o "10001"
  if ($q !== '') $params['q'] = $q;
}
// Filtrar por provincia si hay nombre
if ($provName) $params['state'] = $provName;

$url = $base . '?' . http_build_query($params, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
$headers = ['User-Agent: ' . nominatim_user_agent(), 'Accept: application/json'];

try {
  $res = http_get_json($url, $headers, 15);
  if (!is_array($res)) $res = [];
  $items = [];

  foreach (($res ?? []) as $row) {
    if (!is_array($row)) continue;
    $addr = $row['address'] ?? [];
    $code = $addr['postcode'] ?? null;
    if (!$code) continue; // solo entradas con postcode
    $items[] = [
      'code'      => (string)$code,
      'nombre'    => (string)($row['display_name'] ?? $code),
      'provincia' => (string)($addr['state'] ?? ($addr['county'] ?? '')),
      'lat'       => isset($row['lat']) ? (float)$row['lat'] : null,
      'lng'       => isset($row['lon']) ? (float)$row['lon'] : null,
      'source'    => 'nominatim',
    ];
  }

  // Dedup por code
  $uniq = [];
  $out  = [];
  foreach ($items as $it) {
    if (isset($uniq[$it['code']])) continue;
    $uniq[$it['code']] = true;
    $out[] = $it;
  }

  $payload = ['ok'=>true, 'items'=>$out, 'count'=>count($out)];
  if ($DEBUG) $payload['_debug'] = ['url'=>$url, 'params'=>$params];
  json_response($payload);
} catch (Throwable $e) {
  json_response(['ok'=>false, 'error'=>'LOOKUP_FAILED'], 500);
}

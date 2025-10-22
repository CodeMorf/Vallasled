<?php declare(strict_types=1);
/**
 * /config/maps.php  — Stack 100% gratis
 * - Mapa: OpenStreetMap tiles (Leaflet/MapLibre en el front)
 * - Geocodificación: Nominatim (OSM)
 * - Lugares cercanos: Overpass API (OSM)
 * Requiere: /config/db.php si quieres leer emails o base_url desde DB (opcional).
 */

require_once __DIR__ . '/db.php'; // opcional, para db_setting()

// ==================== CONFIG BÁSICA MAPA ====================
const MAP_TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const MAP_ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contrib.';
const DEFAULT_MAP_CENTER_LAT = 18.4861;   // Santo Domingo
const DEFAULT_MAP_CENTER_LNG = -69.9312;
const DEFAULT_MAP_ZOOM      = 11;

// ==================== ENDPOINTS OSM GRATIS ==================
const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org';
const OVERPASS_BASE  = 'https://overpass-api.de/api/interpreter';

// Identificación para Nominatim (muy recomendado por sus TOS)
function nominatim_user_agent(): string {
  $project = db_setting('site_name', 'Vallasled.com');
  $email   = db_setting('nominatim_email', 'admin@vallasled.com');
  return $project . ' (' . $email . ')';
}

// ==================== TIPOS DE VALLAS (como tu ejemplo) =====
$tipos_vallas = [
  'billboard' => [
    'nombre' => 'Billboard',
    'descripcion' => 'Valla publicitaria estándar',
    'precio_base' => 500,
    'icono' => 'billboard-icon.png'
  ],
  'digital' => [
    'nombre' => 'Digital',
    'descripcion' => 'Pantalla digital LED',
    'precio_base' => 1200,
    'icono' => 'digital-icon.png'
  ],
  'mobiliario' => [
    'nombre' => 'Mobiliario Urbano',
    'descripcion' => 'Publicidad en mobiliario urbano',
    'precio_base' => 300,
    'icono' => 'furniture-icon.png'
  ],
  'transporte' => [
    'nombre' => 'Transporte',
    'descripcion' => 'Publicidad en transporte público',
    'precio_base' => 800,
    'icono' => 'transport-icon.png'
  ],
];

// ==================== HELPERS HTTP ===========================
function http_get_json(string $url, array $headers = [], int $timeout = 15): array {
  // cURL si existe
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    $headers = array_values($headers);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT        => $timeout,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_USERAGENT      => $headers['User-Agent'] ?? (nominatim_user_agent()),
    ]);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($out === false) return ['error' => $err];
    $json = json_decode((string)$out, true);
    return is_array($json) ? $json : ['raw' => $out];
  }
  // Fallback file_get_contents
  $ctx = stream_context_create([
    'http' => [
      'method'  => 'GET',
      'header'  => implode("\r\n", $headers ?: ['User-Agent: ' . nominatim_user_agent()]),
      'timeout' => $timeout,
    ]
  ]);
  $out = @file_get_contents($url, false, $ctx);
  if ($out === false) return ['error' => 'request_failed'];
  $json = json_decode((string)$out, true);
  return is_array($json) ? $json : ['raw' => $out];
}

// ==================== API PÚBLICAS ===========================

/**
 * Devuelve configuración para el front (Leaflet/MapLibre).
 * Front: usa MAP_TILE_URL y MAP_ATTRIBUTION como capa base.
 */
function get_maps_config(): array {
  global $tipos_vallas;
  return [
    'tiles' => [
      'url'         => MAP_TILE_URL,
      'attribution' => MAP_ATTRIBUTION,
      'subdomains'  => ['a','b','c'],
      'maxZoom'     => 19,
    ],
    'center' => ['lat' => DEFAULT_MAP_CENTER_LAT, 'lng' => DEFAULT_MAP_CENTER_LNG],
    'zoom'   => DEFAULT_MAP_ZOOM,
    'tipos_vallas' => $tipos_vallas,
  ];
}

/**
 * Geocodifica una dirección con Nominatim.
 * Campos típicos: display_name, lat, lon, address{...}
 */
function geocode_address(string $address, int $limit = 5): array {
  $params = http_build_query([
    'q'               => $address,
    'format'          => 'jsonv2',
    'limit'           => max(1, min(10, $limit)),
    'addressdetails'  => 1,
    'accept-language' => 'es',
  ]);
  $url = NOMINATIM_BASE . '/search?' . $params;
  $headers = [
    'User-Agent: ' . nominatim_user_agent(),
    'Accept: application/json',
  ];
  return http_get_json($url, $headers, 15);
}

/**
 * Lugares cercanos vía Overpass.
 * $type acepta categorías comunes y se mapean a tags OSM.
 */
function get_nearby_places(float $lat, float $lng, int $radius = 1000, string $type = ''): array {
  $radius = max(50, min(5000, $radius));

  // Mapeo simple de tipos → filtros OSM (amenity/shop/tourism...)
  $map = [
    'restaurant'   => ['amenity' => ['restaurant','fast_food','cafe']],
    'gas_station'  => ['amenity' => ['fuel']],
    'bank'         => ['amenity' => ['bank','atm']],
    'hospital'     => ['amenity' => ['hospital','clinic','doctors','pharmacy']],
    'school'       => ['amenity' => ['school','university','college']],
    'supermarket'  => ['shop'    => ['supermarket','convenience']],
    'hotel'        => ['tourism' => ['hotel','motel','guest_house','hostel']],
    'park'         => ['leisure' => ['park','pitch','playground']],
  ];

  $filters = [];
  if ($type && isset($map[$type])) {
    foreach ($map[$type] as $k => $vals) {
      foreach ($vals as $v) $filters[] = sprintf('[%s=%s]', $k, $v);
    }
  } else {
    // Si no hay tipo, algunos amenities genéricos
    foreach (['restaurant','cafe','bank','atm','fuel','hospital','pharmacy','school','university','supermarket'] as $v) {
      $filters[] = sprintf('[amenity=%s]', $v);
    }
  }

  // Construir consulta Overpass (nodos/ways/relaciones) alrededor
  $filterStr = implode('', $filters);
  $ql = <<<OVERPASS
[out:json][timeout:30];
(
  node$filterStr(around:$radius,$lat,$lng);
  way$filterStr(around:$radius,$lat,$lng);
  relation$filterStr(around:$radius,$lat,$lng);
);
out center 60;
OVERPASS;

  $url = OVERPASS_BASE . '?' . http_build_query(['data' => $ql]);
  $headers = ['User-Agent: ' . nominatim_user_agent(), 'Accept: application/json'];
  $res = http_get_json($url, $headers, 30);

  // Normaliza a una lista simple
  if (!isset($res['elements']) || !is_array($res['elements'])) return $res;
  $items = [];
  foreach ($res['elements'] as $el) {
    $tags = $el['tags'] ?? [];
    $latc = $el['lat'] ?? ($el['center']['lat'] ?? null);
    $lngc = $el['lon'] ?? ($el['center']['lon'] ?? null);
    if ($latc === null || $lngc === null) continue;
    $items[] = [
      'id'    => $el['id'] ?? null,
      'type'  => $el['type'] ?? null,
      'name'  => $tags['name'] ?? null,
      'lat'   => (float)$latc,
      'lng'   => (float)$lngc,
      'tags'  => $tags,
    ];
  }
  return ['count' => count($items), 'items' => $items];
}

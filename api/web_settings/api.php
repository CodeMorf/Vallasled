<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
// CORS básico (ajusta dominio si necesitas)
header('Access-Control-Allow-Origin: *');
header('Vary: Accept-Encoding');

// Cache suave 5 min
$ttl = 300;
header('Cache-Control: public, max-age='.$ttl.', s-maxage='.$ttl);

require __DIR__ . '/../../config/db.php';

function kv(): array {
  $out = [];
  try {
    $pdo = db();
    $st = $pdo->query("SELECT clave, valor FROM web_setting");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $out[$r['clave']] = (string)$r['valor'];
    }
  } catch (Throwable $e) {
    // sin DB => vacío
  }
  return $out;
}

function boolish(string $v): bool {
  return in_array(strtolower(trim($v)), ['1','true','yes','on'], true);
}

// Filtrado opcional ?keys=logo_url,favicon_url
$only = null;
if (!empty($_GET['keys'])) {
  $only = array_filter(array_map('trim', explode(',', (string)$_GET['keys'])));
}

$cfg = kv();

// Defaults
$name     = $cfg['company_name']          ?? 'Vallasled';
$pri      = $cfg['theme_primary_color']   ?? '#2563eb';
$sec      = $cfg['theme_secondary_color'] ?? '#0b1220';
$heroTxt  = $cfg['hero_text_color']       ?? '#374151';
$logoUrl  = $cfg['logo_url']              ?? '';
$faviPng  = $cfg['favicon_url']           ?? '';
$faviIco  = ($cfg['favicon_ico'] ?? '') ?: $faviPng;
$w        = (int)($cfg['logo_width_px']   ?? '120');
$h        = (int)($cfg['logo_height_px']  ?? '40');
$radius   = (int)($cfg['border_radius_px']?? '8');
$av       = $cfg['asset_version']         ?? '';
$align    = strtolower(trim($cfg['logo_align'] ?? 'left')); // left|center|right
if (!in_array($align, ['left','center','right'], true)) $align='left';

// Opcionales
$footerBg   = $cfg['footer_bg_color']   ?? '#0b1220';
$footerText = $cfg['footer_text_color'] ?? '#e5e7eb';
$footerLink = $cfg['footer_link_color'] ?? '#a23434';

$payload = [
  'ok' => true,
  'data' => [
    'name'   => $name,
    'asset_version' => $av,
    'colors' => [
      'primary'   => $pri,
      'secondary' => $sec,
      'hero_text' => $heroTxt,
      'footer_bg'   => $footerBg,
      'footer_text' => $footerText,
      'footer_link' => $footerLink,
    ],
    'logo' => [
      'url'   => $logoUrl,
      'width' => $w,
      'height'=> $h,
      'align' => $align, // left|center|right
    ],
    'favicon' => [
      'png' => $faviPng,
      'ico' => $faviIco,
    ],
    // por si luego usas auth u otros enlaces
    'links' => [
      'auth_base' => $cfg['auth_base_url'] ?? '',
      'vendor_login'    => $cfg['vendor_login_url']    ?? '',
      'vendor_register' => $cfg['vendor_register_url'] ?? '',
      'terms'    => $cfg['legal_terms_url']    ?? '',
      'privacy'  => $cfg['legal_privacy_url']  ?? '',
    ],
    // banderas varios
    'logging' => [
      'enabled' => boolish($cfg['log_enabled'] ?? '0'),
      'level'   => $cfg['log_level'] ?? 'WARNING',
      'retention_days' => (int)($cfg['log_retention_days'] ?? '30'),
    ],
  ],
];

// Filtrado por keys si lo piden
if (is_array($only) && $only) {
  $flat = [];
  // aplanar para filtrar por clave simple
  $flat['name'] = $payload['data']['name'];
  $flat['asset_version'] = $payload['data']['asset_version'];
  foreach ($payload['data']['colors'] as $k=>$v)   $flat["colors.$k"] = $v;
  foreach ($payload['data']['logo'] as $k=>$v)     $flat["logo.$k"]   = $v;
  foreach ($payload['data']['favicon'] as $k=>$v)  $flat["favicon.$k"]= $v;
  foreach ($payload['data']['links'] as $k=>$v)    $flat["links.$k"]  = $v;

  $sel = [];
  foreach ($only as $k) {
    if (array_key_exists($k, $flat)) $sel[$k] = $flat[$k];
  }
  echo json_encode(['ok'=>true,'data'=>$sel], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

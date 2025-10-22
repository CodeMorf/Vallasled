<?php
// /console/gestion/web/ajax/settings_set.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
}
if (!csrf_ok_from_header_or_post()) {
  json_exit(['ok'=>false,'error'=>'BAD_CSRF'], 403);
}

/* ------------ validaciones ------------ */
function is_http_url(?string $u): bool {
  if ($u === null || $u === '') return true;                 // vacío ok
  $u = trim($u);
  return (bool)filter_var($u, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $u);
}
function is_url_or_path(?string $u): bool {
  if ($u === null || $u === '') return true;                 // vacío ok
  $u = trim($u);
  if (preg_match('~^https?://~i', $u)) {
    return (bool)filter_var($u, FILTER_VALIDATE_URL);
  }
  if (preg_match('~^data:image/[a-z0-9.+-]+;base64,~i', $u)) {
    return true;                                             // favicon/logo embebidos
  }
  // /path, ./path, ../path
  return (bool)preg_match('~^(?:/|\.{1,2}/)[^\s]+$~', $u);
}
function is_hex_color(?string $v): bool {
  if ($v === null || $v === '') return true;
  return (bool)preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v);
}
function norm_int($v, int $min, int $max): ?int {
  if ($v === null || $v === '') return null;
  if (!is_numeric($v)) return null;
  $n = (int)$v;
  if ($n < $min) $n = $min;
  if ($n > $max) $n = $max;
  return $n;
}
function is_email_ok(?string $v): bool {
  if ($v === null || $v === '') return true;
  return (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
}
function is_locale_ok(?string $v): bool {
  if ($v === null || $v === '') return true;
  return (bool)preg_match('/^[a-z]{2}(?:[_-][A-Z]{2})?$/', $v);
}

/* ------------ allowed ------------ */
$ALLOWED = [
  'site_name','site_description','site_url','site_locale',
  'hero_title_top','hero_title_bottom','hero_subtitle','hero_cta_text','hero_cta_url',
  'site_twitter','site_facebook','site_instagram',
  'wa_tpl_valla','wa_tpl_cart','wa_tpl_personal',
  'logo_url','favicon_url','logo_width_px','logo_height_px',
  'theme_primary_color','theme_secondary_color','footer_bg_color','footer_text_color','border_radius_px',
  'home_banner_enabled','home_banner_mode','home_banner_video_urls','home_banner_image_url','home_banner_height',
  'company_phone','support_whatsapp','support_email',
  'legal_terms_url','legal_privacy_url','vendor_register_url','vendor_login_url'
];

/* ------------ longitud columna (evitar corte) ------------ */
$maxLen = 1024;
$check = $conn->prepare(
  "SELECT CHARACTER_MAXIMUM_LENGTH
     FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'web_setting'
      AND column_name = 'valor'"
);
$check->execute();
if ($row = $check->get_result()->fetch_row()) { $maxLen = (int)$row[0]; }
$check->close();

/* ------------ payload ------------ */
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) json_exit(['ok'=>false,'error'=>'BAD_JSON'], 400);

/* ------------ normalización ------------ */
$norm = [];

$abs_url_only = ['site_url']; // deben ser http(s)
$url_or_path  = [
  'hero_cta_url','logo_url','favicon_url','home_banner_image_url',
  'legal_terms_url','legal_privacy_url','vendor_register_url','vendor_login_url'
];
$color_fields = ['theme_primary_color','theme_secondary_color','footer_bg_color','footer_text_color'];

foreach ($ALLOWED as $k) {
  $v = $in[$k] ?? '';
  if (is_string($v)) $v = trim($v);

  switch ($k) {
    case 'home_banner_enabled':
      $norm[$k] = ((string)$v === '1' || $v === 1 || $v === true) ? '1' : '0';
      break;

    case 'home_banner_mode':
      $norm[$k] = in_array($v, ['video','image'], true) ? $v : 'video';
      break;

    case 'site_locale':
      if (!is_locale_ok($v)) json_exit(['ok'=>false,'error'=>'INVALID_LOCALE','field'=>$k], 422);
      $norm[$k] = (string)$v;
      break;

    case 'logo_width_px':
    case 'logo_height_px':
    case 'border_radius_px':
    case 'home_banner_height':
      $n = norm_int($v, 0, 10000);
      if ($v !== '' && $n === null) json_exit(['ok'=>false,'error'=>'INVALID_INT','field'=>$k], 422);
      $norm[$k] = $n === null ? '' : (string)$n;
      break;

    case 'support_email':
      if (!is_email_ok($v)) json_exit(['ok'=>false,'error'=>'INVALID_EMAIL','field'=>$k], 422);
      $norm[$k] = (string)$v;
      break;

    case 'home_banner_video_urls':
      // acepta JSON array o texto por líneas; valida http(s)
      $text = '';
      if (is_array($v)) {
        $keep = [];
        foreach ($v as $u) {
          $u = is_string($u) ? trim($u) : '';
          if ($u !== '' && preg_match('~^https?://~i', $u) && filter_var($u, FILTER_VALIDATE_URL)) $keep[] = $u;
        }
        $text = implode("\n", $keep);
      } else {
        $keep = [];
        foreach (preg_split('/\r?\n/', (string)$v ?? '') as $u) {
          $u = trim($u);
          if ($u === '') continue;
          if (!(preg_match('~^https?://~i', $u) && filter_var($u, FILTER_VALIDATE_URL))) {
            json_exit(['ok'=>false,'error'=>'INVALID_URL','field'=>$k], 422);
          }
          $keep[] = $u;
        }
        $text = implode("\n", $keep);
      }
      if (strlen($text) > $maxLen) json_exit(['ok'=>false,'error'=>'VALUE_TOO_LONG','field'=>$k], 422);
      $norm[$k] = $text;
      break;

    default:
      if (in_array($k, $abs_url_only, true)) {
        if (!is_http_url($v)) json_exit(['ok'=>false,'error'=>'INVALID_URL','field'=>$k], 422);
        $norm[$k] = (string)$v;
      } elseif (in_array($k, $url_or_path, true)) {
        if (!is_url_or_path($v)) json_exit(['ok'=>false,'error'=>'INVALID_URL','field'=>$k], 422);
        $norm[$k] = (string)$v;
      } elseif (in_array($k, $color_fields, true)) {
        if (!is_hex_color($v)) json_exit(['ok'=>false,'error'=>'INVALID_COLOR','field'=>$k], 422);
        $norm[$k] = (string)$v;
      } else {
        $s = (string)($v ?? '');
        if (strlen($s) > $maxLen) json_exit(['ok'=>false,'error'=>'VALUE_TOO_LONG','field'=>$k], 422);
        $norm[$k] = $s;
      }
      break;
  }
}

/* ------------ persistencia ------------ */
try {
  $conn->begin_transaction();

  $up = $conn->prepare(
    "INSERT INTO web_setting (clave, valor)
          VALUES (?, ?)
      ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
  );

  $count = 0;
  foreach ($norm as $k => $v) {
    $up->bind_param('ss', $k, $v);
    $up->execute();
    $count += (int)($up->affected_rows >= 1);
  }
  $up->close();

  $conn->commit();
  json_exit(['ok'=>true,'count'=>$count,'applied'=>$norm]);
} catch (Throwable $e) {
  @mysqli_rollback($conn);
  $err = ['ok'=>false,'error'=>'APPLY_ERROR','detail'=>$e->getMessage()];
  if ($e instanceof mysqli_sql_exception) $err['errno'] = $e->getCode();
  json_exit($err, 500);
}

<?php
// /console/gestion/web/ajax/settings_get.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
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

  // mapa default
  $out = [];
  foreach ($ALLOWED as $k) $out[$k] = '';

  // consulta actual
  $place = implode(',', array_fill(0, count($ALLOWED), '?'));
  $sql = "SELECT clave, valor
            FROM web_setting
           WHERE clave IN ($place)
        ORDER BY clave ASC, updated_at DESC, id DESC";
  $stmt = $conn->prepare($sql);

  // bind por referencia para mysqli
  $types = str_repeat('s', count($ALLOWED));
  $refs  = [];
  foreach ($ALLOWED as $i => $v) $refs[$i] = &$ALLOWED[$i];
  array_unshift($refs, $types);
  call_user_func_array([$stmt,'bind_param'], $refs);

  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $out[$row['clave']] = (string)$row['valor'];
  }
  $stmt->close();

  // compat: si home_banner_video_urls vino como JSON, devolverlo en líneas
  if ($out['home_banner_video_urls'] !== '') {
    $text = $out['home_banner_video_urls'];
    $maybe = json_decode($text, true);
    if (is_array($maybe)) {
      $lines = [];
      foreach ($maybe as $u) if (is_string($u) && $u !== '') $lines[] = $u;
      $text = implode("\n", $lines);
    }
    $out['home_banner_video_urls'] = $text;
  }

  json_exit(['ok'=>true,'data'=>$out]);
} catch (Throwable $e) {
  json_exit(['ok'=>false,'error'=>'FETCH_ERROR','detail'=>$e->getMessage()], 500);
}

<?php declare(strict_types=1);

/**
 * config/home_banner_video_urls.php
 * Fuente única para el banner de portada: modo, poster, alto y playlist de videos.
 * Lee primero desde web_setting; si no existe, usa defaults.
 */

$__db_bootstrap = __DIR__ . '/db.php';
if (file_exists($__db_bootstrap)) { require_once $__db_bootstrap; }

/** Lee una clave desde web_setting con tus mismas validaciones */
if (!function_exists('ws_get')) {
  function ws_get(string $key, ?string $default=null): ?string {
    // 1) Tu helper preferido
    if (function_exists('db_setting')) {
      try { return (string)db_setting($key, $default); } catch (Throwable $e) { return $default; }
    }
    // 2) PDO via db()
    try {
      if (function_exists('db')) {
        $dbh = db();
        if ($dbh instanceof PDO) {
          $st = $dbh->prepare('SELECT valor FROM web_setting WHERE clave=? LIMIT 1');
          $st->execute([$key]);
          $v = $st->fetchColumn();
          return $v!==false ? (string)$v : $default;
        }
      }
    } catch (Throwable $e) {}
    // 3) mysqli global $conn (si aplica)
    try {
      if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $keyEsc = $GLOBALS['conn']->real_escape_string($key);
        $sql = "SELECT valor FROM web_setting WHERE clave='{$keyEsc}' LIMIT 1";
        if ($res = $GLOBALS['conn']->query($sql)) {
          $row = $res->fetch_assoc(); $res->free();
          return $row && isset($row['valor']) ? (string)$row['valor'] : $default;
        }
      }
    } catch (Throwable $e) {}
    return $default;
  }
}

/** Normaliza lista de URLs: acepta JSON array o CSV; filtra vacíos; limita a 20. */
if (!function_exists('ws_parse_url_list')) {
  function ws_parse_url_list(?string $raw, array $fallback=[]): array {
    $out = [];
    if ($raw!==null && $raw!=='') {
      $raw = trim($raw);
      // JSON array
      if ($raw[0]==='[') {
        try {
          $arr = json_decode($raw, true, flags: JSON_INVALID_UTF8_SUBSTITUTE);
          if (is_array($arr)) $out = array_map('strval', $arr);
        } catch (Throwable $e) {}
      } else {
        // CSV
        $out = array_map('trim', explode(',', $raw));
      }
    }
    $out = array_values(array_filter($out, static function($u){
      if (!is_string($u) || $u==='') return false;
      // permite http/https o ruta absoluta relativa
      return preg_match('~^(https?://|/|[a-zA-Z0-9_\-./]+\.(mp4|webm|m3u8))~', $u) === 1;
    }));
    if (!$out) $out = $fallback;
    if (count($out) > 20) $out = array_slice($out, 0, 20);
    return $out;
  }
}

/** Config completa del banner */
if (!function_exists('home_banner_config')) {
  function home_banner_config(): array {
    $defaults = [
      'enabled'    => true,
      'mode'       => 'video',  // 'video'|'image'
      'image_url'  => 'https://auth.vallasled.com/uploads/hero.jpg',
      'poster'     => '',
      'height'     => 360,
      'video_urls' => [
        'https://demo.vallasled.com/video/step1.mp4',
        'https://demo.vallasled.com/video/step2.mp4',
        'https://demo.vallasled.com/video/step3.mp4',
      ],
    ];
    $enabled = ws_get('home_banner_enabled','1') === '1';
    $mode    = ws_get('home_banner_mode','video') === 'image' ? 'image' : 'video';
    $image   = ws_get('home_banner_image_url', $defaults['image_url']) ?: $defaults['image_url'];
    $poster  = ws_get('home_banner_video_poster', '');
    $height  = (int) (ws_get('home_banner_height', (string)$defaults['height']) ?? $defaults['height']);
    $urlsRaw = ws_get('home_banner_video_urls', null);
    $urls    = ws_parse_url_list($urlsRaw, $defaults['video_urls']);

    // Sanitiza alto
    if ($height < 220) $height = 220;
    if ($height > 640) $height = 640;

    return [
      'enabled'    => $enabled,
      'mode'       => $mode,
      'image_url'  => $image,
      'poster'     => $poster,
      'height'     => $height,
      'video_urls' => $urls,
    ];
  }
}

/** Saca solo el playlist, por si lo necesitas aislado */
if (!function_exists('home_banner_playlist')) {
  function home_banner_playlist(): array {
    $cfg = home_banner_config();
    return $cfg['video_urls'] ?? [];
  }
}

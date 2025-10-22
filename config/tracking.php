<?php declare(strict_types=1);

/**
 * /config/tracking.php
 * Inyección de píxeles y GA/Ads/FB/TikTok + contador de visitas.
 * Depende de tu DB y tablas config_global / configuracion.
 */

$__db_boot = __DIR__ . '/db.php';
if (file_exists($__db_boot)) { require_once $__db_boot; }

/* === helpers seguros (no redeclarar) === */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('s')) {
  function s($v): string { return trim((string)$v); }
}

/**
 * cfg() lee primero config_global, luego configuracion como fallback.
 * Cache en request para minimizar hits a DB.
 */
if (!function_exists('cfg')) {
  function cfg(string $key, ?string $default=null): ?string {
    static $mem = [];
    if (array_key_exists($key, $mem)) return $mem[$key];

    try {
      if (function_exists('db')) {
        // 1) config_global
        $p = db()->prepare('SELECT valor FROM config_global WHERE clave=? AND activo=1 ORDER BY id DESC LIMIT 1');
        $p->execute([$key]);
        $row = $p->fetch(\PDO::FETCH_ASSOC);
        if ($row && isset($row['valor'])) return $mem[$key] = (string)$row['valor'];

        // 2) configuracion
        $p = db()->prepare('SELECT valor FROM configuracion WHERE clave=? ORDER BY id DESC LIMIT 1');
        $p->execute([$key]);
        $row = $p->fetch(\PDO::FETCH_ASSOC);
        if ($row && isset($row['valor'])) return $mem[$key] = (string)$row['valor'];
      }
    } catch (\Throwable $e) { /* silencioso */ }

    return $mem[$key] = $default;
  }
}

/* === validadores de ID === */
if (!function_exists('valid_ga4')) {
  function valid_ga4(?string $id): ?string {
    $id = s((string)$id);
    return preg_match('/^G-[A-Z0-9]{6,16}$/', $id) ? $id : null;
  }
}
if (!function_exists('valid_ads')) {
  function valid_ads(?string $id): ?string {
    $id = s((string)$id);
    return preg_match('/^AW-\d{6,16}$/', $id) ? $id : null;
  }
}
if (!function_exists('valid_fb')) {
  function valid_fb(?string $id): ?string {
    $id = s((string)$id);
    return preg_match('/^\d{5,20}$/', $id) ? $id : null; // pixel numérico
  }
}
if (!function_exists('valid_tt')) {
  function valid_tt(?string $id): ?string {
    $id = s((string)$id);
    return preg_match('/^[A-Za-z0-9_-]{5,32}$/', $id) ? $id : null; // TikTok flexible
  }
}

/* === render head/body === */
if (!function_exists('tracking_head')) {
  function tracking_head(): void {
    $ga4 = valid_ga4(cfg('ga4_measurement_id',''));
    $ads = valid_ads(cfg('google_ads_id',''));
    $fb  = valid_fb(cfg('facebook_pixel_id',''));
    $tt  = valid_tt(cfg('tiktok_pixel_id',''));

    // 1) GA4
    if ($ga4) {
      echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=".h($ga4)."\"></script>\n";
      echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config','".h($ga4)."');</script>\n";
    }

    // 2) Google Ads (gtag usa misma librería)
    if ($ads) {
      if (!$ga4) {
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=".h($ads)."\"></script>\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)};gtag('js',new Date());</script>\n";
      }
      echo "<script>gtag('config','".h($ads)."');</script>\n";
    }

    // 3) Facebook Pixel
    if ($fb) {
      echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments)}; if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[]; t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js'); fbq('init','".h($fb)."'); fbq('track','PageView');</script>\n";
      echo "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id=".h($fb)."&ev=PageView&noscript=1\"/></noscript>\n";
    }

    // 4) TikTok Pixel
    if ($tt) {
      echo "<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var tt=w[t]=w[t]||[]; tt.methods=['page','track','identify','instances','debug','on','off','once','ready','setUserProperties','setSuperProperties','trackForm','trackLink']; tt.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}; for(var i=0;i<tt.methods.length;i++)tt.setAndDefer(tt,tt.methods[i]); tt.instance=function(t){for(var e=tt._i[t]||[],n=0;n<tt.methods.length;n++)tt.setAndDefer(e,tt.methods[n]); return e}; tt.load=function(e){var n='https://analytics.tiktok.com/i18n/pixel/events.js'; tt._i=tt._i||{}; tt._i[e]=[]; tt._i[e]._u=n; tt._t=tt._t||{}; tt._t[e]=+new Date; var o=d.createElement('script'); o.type='text/javascript'; o.async=!0; o.src=n+'?sdkid='+e+'&lib='+t; var a=d.getElementsByTagName('script')[0]; a.parentNode.insertBefore(o,a)}; tt.load('".h($tt)."'); tt.page(); }(window,document,'ttq');</script>\n";
    }

    // 5) Campo libre desde admin (head)
    $customHead = cfg('pixel_head_html','');
    if ($customHead !== null && $customHead !== '') {
      echo $customHead, "\n";
    }
  }
}

if (!function_exists('tracking_body')) {
  function tracking_body(): void {
    // Campo libre desde admin (body)
    $customBody = cfg('pixel_body_html','');
    if ($customBody !== null && $customBody !== '') {
      echo $customBody, "\n";
    }
  }
}

/* === contador de visitas === */
if (!function_exists('track_pageview')) {
  function track_pageview(?string $path=null): void {
    try {
      if (!function_exists('db')) return;
      $pdo = db();

      $path = $path ? s($path) : '/';
      if ($path === '') $path = '/';

      $ip = $_SERVER['REMOTE_ADDR'] ?? '';
      // guardar IPv4/IPv6 binario si posible
      $ipBin = @inet_pton($ip);
      $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
      $ref = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);

      // UTM
      $utm_source  = isset($_GET['utm_source'])  ? substr(s($_GET['utm_source']), 0, 64)  : null;
      $utm_medium  = isset($_GET['utm_medium'])  ? substr(s($_GET['utm_medium']), 0, 64)  : null;
      $utm_campaign= isset($_GET['utm_campaign'])? substr(s($_GET['utm_campaign']),0, 64)  : null;
      $utm_term    = isset($_GET['utm_term'])    ? substr(s($_GET['utm_term']),    0, 64)  : null;
      $utm_content = isset($_GET['utm_content']) ? substr(s($_GET['utm_content']), 0, 64)  : null;

      $ymd = (int)date('Ymd');

      $sql = "INSERT INTO web_analytics
              (ymd, path, ip, ua, ref, u_campaign, u_source, u_medium, u_term, u_content)
              VALUES (:ymd, :path, :ip, :ua, :ref, :uc, :us, :um, :ut, :uco)";
      $st = $pdo->prepare($sql);
      $st->bindValue(':ymd', $ymd, \PDO::PARAM_INT);
      $st->bindValue(':path', $path, \PDO::PARAM_STR);
      $st->bindValue(':ip', $ipBin, $ipBin===false? \PDO::PARAM_NULL : \PDO::PARAM_LOB);
      $st->bindValue(':ua', $ua, \PDO::PARAM_STR);
      $st->bindValue(':ref', $ref, \PDO::PARAM_STR);
      $st->bindValue(':uc', $utm_campaign, \PDO::PARAM_STR);
      $st->bindValue(':us', $utm_source,   \PDO::PARAM_STR);
      $st->bindValue(':um', $utm_medium,   \PDO::PARAM_STR);
      $st->bindValue(':ut', $utm_term,     \PDO::PARAM_STR);
      $st->bindValue(':uco', $utm_content, \PDO::PARAM_STR);
      $st->execute();
    } catch (\Throwable $e) {
      // silencioso para no romper front
    }
  }
}

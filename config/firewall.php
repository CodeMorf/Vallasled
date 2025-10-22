<?php declare(strict_types=1);

// /config/firewall.php
// Bloqueo de scrapers, rate limit por IP, anti-hotlink imágenes,
// cabeceras anti-embed. Incluye script anti-copy + anti-devtools (opcional).

define('FW_TMP', dirname(__DIR__) . '/storage/tmp/fw');
define('FW_LOG', dirname(__DIR__) . '/storage/logs/firewall.log');
@mkdir(FW_TMP, 0775, true);
@mkdir(dirname(FW_LOG), 0775, true);

// --- Config ---
const FW_RATE_WINDOW = 60;          // segundos
const FW_RATE_MAX    = 120;         // req/IP por ventana para HTML
const FW_RATE_MAX_AS = 600;         // req/IP para assets
const FW_HOTLINK_EXT = 'png|jpe?g|webp|avif|gif|svg|mp4|webm';
const FW_ALLOW_BOTS  = '~(Googlebot|AdsBot-Google|Bingbot|DuckDuckBot|YandexBot|LinkedInBot|Twitterbot|facebookexternalhit|Slackbot|Applebot)~i';
const FW_BLOCK_UA    = '~(curl|wget|python-requests|aiohttp|httpclient|okhttp|libwww-perl|perl|ruby|java|scrapy|crawler|spider|HTTrack|sqlmap|nikto|wpscan|PostmanRuntime|axios/\d|Go-http-client)~i';

function fw_client_ip(): string {
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
    if (!empty($_SERVER[$h])) {
      $val = explode(',', (string)$_SERVER[$h])[0];
      return trim($val);
    }
  }
  return '0.0.0.0';
}
function fw_is_asset(): bool {
  $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
  return (bool)preg_match('~\.(?:css|js|mjs|map|'.FW_HOTLINK_EXT.'|woff2|ttf|otf)$~i', $uri ?? '');
}
function fw_is_image_hotlink(): bool {
  $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
  if (!preg_match('~\.('.FW_HOTLINK_EXT.')$~i', $uri ?? '')) return false;
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref === '') return false;
  $host = $_SERVER['HTTP_HOST'] ?? '';
  return stripos($ref, $host) === false;
}
function fw_rate_key(string $ip, bool $asset): string {
  $bucket = (int) floor(time() / FW_RATE_WINDOW);
  return FW_TMP . '/' . ($asset?'as_':'ht_') . $bucket . '_' . preg_replace('~[^0-9a-f:.]~i','_', $ip);
}
function fw_rate_check(bool $asset): bool {
  $ip = fw_client_ip();
  $f  = fw_rate_key($ip, $asset);
  $max = $asset ? FW_RATE_MAX_AS : FW_RATE_MAX;
  $n = 0;
  if (is_file($f)) { $n = (int)@file_get_contents($f); }
  $n++;
  @file_put_contents($f, (string)$n, LOCK_EX);
  return $n <= $max;
}
function fw_respond(int $code, string $msg, bool $noindex=true): void {
  http_response_code($code);
  header('Content-Type: text/html; charset=utf-8');
  if ($noindex) header('X-Robots-Tag: noindex, nofollow', true);
  echo "<!doctype html><meta charset='utf-8'><title>$code</title><style>body{font-family:system-ui;margin:40px}code{background:#f3f4f6;padding:2px 6px;border-radius:6px}</style><h1>$code</h1><p>$msg</p>";
  exit;
}
function fw_set_headers(): void {
  header('X-Frame-Options: SAMEORIGIN', false);
  header('Referrer-Policy: strict-origin-when-cross-origin', false);
  header("Content-Security-Policy: frame-ancestors 'self'; upgrade-insecure-requests", false);
  header("Permissions-Policy: clipboard-read=(), clipboard-write=()", false);
}

// Logging
$GLOBALS['__fw_log'] = [];
function fw_log(string $line): void { $GLOBALS['__fw_log'][] = '['.date('c').'] '.$line; }
function fw_log_flush(): void {
  if (!$GLOBALS['__fw_log']) return;
  @file_put_contents(FW_LOG, implode("\n", $GLOBALS['__fw_log'])."\n", FILE_APPEND);
}

function fw_boot(): void {
  fw_set_headers();

  // Bloqueo por UA
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  if ($ua === '') { fw_log('BLOCK UA:empty '.fw_client_ip()); fw_respond(403, 'Acceso denegado.'); }
  if (preg_match(FW_BLOCK_UA, $ua) && !preg_match(FW_ALLOW_BOTS, $ua)) {
    fw_log('BLOCK UA:'.$ua.' IP:'.fw_client_ip());
    fw_respond(403, 'Acceso denegado.');
  }

  // Hotlink imágenes
  if (fw_is_image_hotlink()) {
    fw_log('HOTLINK '.$_SERVER['REQUEST_URI'].' REF:'.($_SERVER['HTTP_REFERER'] ?? ''));
    header('Cache-Control: no-store');
    fw_respond(403, 'Hotlink no permitido.');
  }

  // Rate limit
  $asset = fw_is_asset();
  if (!fw_rate_check($asset)) {
    fw_log('RATE '.($asset?'ASSET':'HTML').' IP:'.fw_client_ip().' URI:'.($_SERVER['REQUEST_URI'] ?? ''));
    fw_respond(429, 'Demasiadas solicitudes. Intente de nuevo en breve.');
  }
}

/**
 * Script anti-copy + anti-devtools.
 * En tu layout imprime: <?php if(function_exists('fw_anticopy_script')) echo fw_anticopy_script(); ?>
 * Bypass: cookie vls_admin
 */
function fw_anticopy_script(): string {
  return <<<HTML
  <style>
    html,body{-webkit-user-select:none;-moz-user-select:none;user-select:none}
    img{-webkit-user-drag:none;user-drag:none}
    #__anti_dbg_overlay{position:fixed;inset:0;background:#0b1220;color:#e5e7eb;display:none;align-items:center;justify-content:center;z-index:2147483647}
    #__anti_dbg_overlay .box{max-width:640px;padding:24px;text-align:center}
    #__anti_dbg_overlay h2{margin:0 0 8px;font:700 20px/1.3 system-ui}
    #__anti_dbg_overlay p{margin:0;font:14px/1.5 system-ui;color:#cbd5e1}
  </style>
  <div id="__anti_dbg_overlay" role="alert" aria-live="assertive">
    <div class="box">
      <h2>Herramientas de desarrollo deshabilitadas</h2>
      <p>Para proteger el contenido esta página bloquea DevTools y el copiado.</p>
    </div>
  </div>
  <script>
  (function(){
    // Bypass para admins
    if (document.cookie.indexOf('vls_admin=') !== -1) return;

    // Helpers: permitir inputs y contenteditable
    function isEditable(n){
      return n && (n.tagName==='INPUT' || n.tagName==='TEXTAREA' || n.isContentEditable);
    }

    // Bloqueo de menú contextual
    document.addEventListener('contextmenu', function(e){
      if (!isEditable(e.target)) e.preventDefault();
    }, {capture:true});

    // Bloqueo de selección fuera de campos editables
    ['selectstart','dragstart'].forEach(function(type){
      document.addEventListener(type, function(e){
        if (!isEditable(e.target)) e.preventDefault();
      }, {capture:true});
    });

    // Bloqueo de copiar/pegar/cortar fuera de campos editables
    ['copy','cut','paste'].forEach(function(type){
      document.addEventListener(type, function(e){
        if (!isEditable(e.target)) {
          e.preventDefault();
          if (type==='copy' && e.clipboardData) e.clipboardData.setData('text/plain','Copiado deshabilitado');
        }
      }, {capture:true});
    });

    // Bloqueo de atajos comunes y DevTools
    document.addEventListener('keydown', function(e){
      var k = e.key.toLowerCase(), ctrl = e.ctrlKey||e.metaKey, sh = e.shiftKey;
      // DevTools: F12, Ctrl+Shift+I/J/C, Ctrl+U, Ctrl+S, Ctrl+P, Ctrl+A, Ctrl+C/X
      if (
        k === 'f12' ||
        (ctrl && sh && (k==='i' || k==='j' || k==='c')) ||
        (ctrl && (k==='u' || k==='s' || k==='p' || k==='a' || k==='c' || k==='x')) ||
        (k === 'printscreen')
      ){
        if (!isEditable(e.target)) { e.preventDefault(); e.stopPropagation(); showOverlay(true); }
      }
    }, {capture:true});

    // Detección heurística de DevTools abierto
    var devOpen = false, lastCheck = 0;
    function checkDev(){
      var t = Date.now();
      if (t - lastCheck < 500) return;
      lastCheck = t;
      // Heurísticas: dimensiones y tiempo de ejecución intenso
      var threshold = 160;
      var wDiff = Math.abs((window.outerWidth||0) - (window.innerWidth||0));
      var hDiff = Math.abs((window.outerHeight||0) - (window.innerHeight||0));
      var suspiciousSize = (wDiff > threshold) || (hDiff > threshold);
      var start = performance.now();
      debugger; // si DevTools está abierto, esto afecta el timing
      var dur = performance.now() - start;
      devOpen = suspiciousSize || dur > 200;
      showOverlay(devOpen);
    }
    function showOverlay(on){
      var el = document.getElementById('__anti_dbg_overlay');
      if (!el) return;
      el.style.display = on ? 'flex' : 'none';
      if (on) {
        // Oculta el contenido real para capturas
        document.documentElement.style.filter = 'blur(6px)';
      } else {
        document.documentElement.style.filter = '';
      }
    }
    setInterval(checkDev, 1000);
    window.addEventListener('resize', checkDev);

    // Intenta impedir apertura por click en Inspect
    window.addEventListener('keydown', function(e){
      if (e.key === 'F12') { e.preventDefault(); e.stopPropagation(); showOverlay(true); }
    }, true);

  })();
  </script>
HTML;
}

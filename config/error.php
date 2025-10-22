<?php
// /config/error.php
declare(strict_types=1);

/* === Detección de contexto === */
function error_is_api_like(): bool {
  if (PHP_SAPI === 'cli') return true;
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'OPTIONS') return true;
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  if (preg_match('~^/(api|ajax|console/ajax)/~i', $uri)) return true;
  $xh = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  if ($xh === 'xmlhttprequest') return true;
  $acc = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
  if (strpos($acc, 'application/json') !== false || strpos($acc, 'text/event-stream') !== false) return true;
  return false;
}
function error_home_url(): string {
  if (function_exists('base_url')) return rtrim((string)base_url(), '/');
  $sch  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'vallasled.com';
  return $sch.'://'.$host;
}
function error_logo_url(): string {
  $fallback = 'https://auth.vallasled.com/admin/assets/logo.png';
  if (function_exists('dbi') && function_exists('load_branding')) {
    try { $b = load_branding(dbi()); return $b['logo_2x'] ?: ($b['logo_url'] ?: $fallback); } catch (Throwable $e) {}
  }
  return $fallback;
}
function error_texts(int $code): array {
  $map = [
    400=>['Solicitud inválida','La petición no pudo procesarse.'],
    401=>['No autenticado','Inicia sesión para continuar.'],
    403=>['Sin permisos','No tienes acceso a este recurso.'],
    404=>['Página no encontrada','No encontramos lo que buscabas.'],
    410=>['Contenido retirado','Este recurso ya no está disponible.'],
    429=>['Demasiadas solicitudes','Espera un momento e inténtalo de nuevo.'],
    500=>['Error interno','Ocurrió un problema inesperado.'],
    501=>['No implementado','La función no está disponible.'],
    502=>['Puerta de enlace inválida','Un servidor intermedio falló.'],
    503=>['Servicio no disponible','Estamos trabajando para restablecer el servicio.'],
    504=>['Tiempo de espera agotado','El servidor tardó demasiado en responder.'],
  ];
  return $map[$code] ?? $map[500];
}

/* === Salidas === */
function error_render_json(int $code, ?string $msg = null): never {
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache'); header('Expires: 0');
    http_response_code($code);
  }
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  echo json_encode(['ok'=>false,'code'=>$code,'error'=>$msg ?: error_texts($code)[0],'path'=>$uri], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

function error_render_html(int $code, ?string $msg = null): never {
  [$title,$desc] = error_texts($code);
  $home = error_home_url();
  $logo = error_logo_url();

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'vallasled.com';
  $req    = $_SERVER['REQUEST_URI'] ?? '/';
  $full   = $scheme.'://'.$host.$req;

  if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache'); header('Expires: 0');
    http_response_code($code);
  }

  $q       = htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
  $logo    = htmlspecialchars($logo, ENT_QUOTES, 'UTF-8');
  $homeH   = htmlspecialchars($home, ENT_QUOTES, 'UTF-8');
  $codeH   = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
  $titleH  = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $descH   = htmlspecialchars($msg ?: $desc, ENT_QUOTES, 'UTF-8');
  $fullH   = htmlspecialchars($full, ENT_QUOTES, 'UTF-8');

  echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{$codeH} · {$titleH}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<style>
:root{--bg:#0b1220;--bg2:#0f172a;--card:#0f192e;--text:#e2e8f0;--muted:#94a3b8;--accent:#60a5fa;--ok:#22c55e;--ring1:#fff;--ring2:#cbd5e1;--ring3:#64748b}
*{box-sizing:border-box}html,body{height:100%}
body{margin:0;color:var(--text);background:
  radial-gradient(1200px 800px at 20% 10%, #0e1a33 0%, transparent 60%),
  radial-gradient(1000px 700px at 80% 90%, #0a1a2e 0%, transparent 55%),
  linear-gradient(160deg,var(--bg),var(--bg2));
font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;display:grid;place-items:center}
.card{width:min(840px,94vw);background:rgba(9,15,27,.65);border:1px solid #1f2b45;border-radius:22px;
box-shadow:0 24px 60px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.04);
backdrop-filter:saturate(130%) blur(6px);padding:28px;overflow:hidden;position:relative;animation:pop .35s ease-out both}
@keyframes pop{from{transform:translateY(6px) scale(.98);opacity:0}to{transform:none;opacity:1}}
.halo{position:absolute;inset:-1px;pointer-events:none;background:
  radial-gradient(520px 220px at 50% 0%, rgba(96,165,250,.25), transparent 65%),
  radial-gradient(440px 160px at 50% 100%, rgba(34,197,94,.18), transparent 70%)}
.head{display:grid;grid-template-columns:120px 1fr;gap:18px;align-items:center}
.logo-box{position:relative;width:120px;height:120px;border-radius:20px;display:grid;place-items:center;background:rgba(13,20,38,.55);box-shadow:inset 0 0 0 1px #203458}
.ring{position:absolute;inset:0;border-radius:20px;mask:radial-gradient(circle at center, transparent 64%, #000 65%);
background:conic-gradient(from 0deg,var(--ring1) 0 25%, var(--ring2) 25% 50%, var(--ring3) 50% 75%, var(--ring1) 75% 100%);animation:spin 1.1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.logo{max-width:80%;height:auto;filter:drop-shadow(0 6px 18px rgba(0,0,0,.6))}
h1{margin:0 0 6px;font-size:clamp(22px,3.2vw,34px);letter-spacing:.2px}
.sub{margin:0;color:var(--muted)}
.badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:#0b1427;border:1px solid #1f2b45;color:#cbd5e1;font-size:14px;margin-top:10px}
.url{display:block;margin-top:6px;background:#0b1427;border:1px solid #1f2b45;border-radius:10px;padding:8px 10px;color:#e5e7eb;overflow:auto;white-space:nowrap}
.grid{display:grid;grid-template-columns:1fr;gap:14px;margin-top:18px}
.search{display:flex;gap:8px}
.search input{flex:1;padding:12px 14px;border-radius:12px;border:1px solid #264061;background:#0b1427;color:#e5e7eb}
.btn{appearance:none;border:1px solid #264061;background:#14233f;color:#e5e7eb;padding:12px 16px;border-radius:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
.btn.primary{background:var(--ok);color:#062b19;border-color:transparent}
.row{display:flex;flex-wrap:wrap;gap:10px}
.hint{font-size:14px;color:#9fb0c7}
.sugs{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.sugs a{font-size:13px;border:1px solid #264061;background:#0b1427;padding:6px 10px;border-radius:999px;color:#cbd5e1;text-decoration:none}
@media (max-width:620px){.head{grid-template-columns:80px 1fr}.logo-box{width:80px;height:80px}}
</style>
</head>
<body>
  <div class="card" role="alert" aria-live="polite">
    <div class="halo"></div>
    <div class="head">
      <div class="logo-box" aria-hidden="true">
        <div class="ring"></div>
        <img class="logo" src="{$logo}" alt="VallasLed">
      </div>
      <div>
        <h1>{$codeH} · {$titleH}</h1>
        <p class="sub">{$descH}</p>
        <div class="badge">Te llevaremos al inicio en <span class="count" id="c">30</span> s · <button id="stop" class="btn" style="padding:4px 10px">Detener</button></div>
        <code class="url" id="u" title="URL solicitada">{$fullH}</code>
      </div>
    </div>

    <div class="grid">
      <form class="search" action="{$homeH}/buscar/" method="get" onsubmit="if(!this.q.value.trim()){return false;}">
        <input id="q" type="text" name="q" placeholder="¿Qué estás buscando?" value="{$q}" autocomplete="off" spellcheck="false">
        <button class="btn" type="submit" aria-label="Buscar">Buscar</button>
        <a class="btn primary" href="{$homeH}">Ir al inicio</a>
      </form>
      <div class="sugs" id="sugs"></div>
      <div class="hint">Reconocimos la URL solicitada. Puedes corregirla, buscar, o volver al inicio.</div>
    </div>
  </div>

<script>
(function(){
  // Reconocer URL actual
  var href = location.href, host = location.hostname, path = location.pathname || "/";
  document.getElementById('u').textContent = href;

  // Prefill de búsqueda con el último segmento útil
  try{
    var seg = decodeURIComponent(path.split('/').filter(Boolean).pop() || '');
    seg = seg.replace(/[-_]+/g,' ').replace(/\.[a-z0-9]{1,5}$/i,'').trim();
    if(seg && seg.length < 80){ var qi=document.getElementById('q'); if(qi && !qi.value) qi.value = seg; }
  }catch(e){}

  // Sugerencias de URL canónicas
  var sugs = [];
  // sin www
  if(/^www\./i.test(host)){ sugs.push(location.protocol + '//' + host.replace(/^www\./i,'') + path); }
  // https
  if(location.protocol !== 'https:'){ sugs.push('https://' + host.replace(/^www\./i,'') + path); }
  // limpiar dobles barras y trailing
  var clean = ('https://' + host.replace(/^www\./i,'') + path).replace(/\/{2,}/g,'/').replace('https:/','https://');
  if(clean !== href) sugs.push(clean);
  // mostrar
  var wrap = document.getElementById('sugs');
  sugs = Array.from(new Set(sugs));
  sugs.slice(0,4).forEach(function(u){
    var a=document.createElement('a'); a.href=u; a.textContent=u; a.addEventListener('click',function(e){ e.preventDefault(); location.replace(u); });
    wrap.appendChild(a);
  });

  // Redirección con 30s y controles
  var secs = 30, el = document.getElementById('c'), stop = document.getElementById('stop'), paused=false, target = "{$homeH}";
  function tick(){ if(paused) return; secs--; if(secs<=0){ go(); return; } if(el) el.textContent=secs; }
  function go(){ location.replace(target); }
  var t = setInterval(tick,1000);
  stop.addEventListener('click', function(e){ e.preventDefault(); paused = !paused; this.textContent = paused ? 'Reanudar' : 'Detener'; });

  // Si el usuario escribe, pausa
  var q = document.getElementById('q');
  ['focus','keydown','input','paste'].forEach(function(ev){ q.addEventListener(ev, function(){ paused = true; stop.textContent='Reanudar'; }); });
})();
</script>
</body>
</html>
HTML;
  exit;
}

/* === Entrypoint y boot === */
if (!function_exists('error_entrypoint')) {
  function error_entrypoint(?int $forceCode = null, ?string $msg = null): never {
    $code = $forceCode ?? (int)($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 500));
    if ($code < 400 || $code > 599) $code = 500;
    if (error_is_api_like()) error_render_json($code, $msg);
    error_render_html($code, $msg);
  }
}
if (!function_exists('error_boot')) {
  function error_boot(): void {
    set_exception_handler(function($e){
      try { error_entrypoint(500, $e instanceof Throwable ? $e->getMessage() : ''); } catch(Throwable $x) { http_response_code(500); exit; }
    });
    register_shutdown_function(function(){
      $err = error_get_last(); if (!$err) return;
      $fatal = [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR];
      if (in_array($err['type'] ?? 0, $fatal, true)) {
        if (!error_is_api_like()) { while (ob_get_level()) { @ob_end_clean(); } }
        $msg = (string)($err['message'] ?? '');
        try { error_entrypoint(500, $msg); } catch(Throwable $x) { http_response_code(500); }
      }
    });
  }
}

/* Invocación directa (útil para ErrorDocument) */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
  $root = dirname(__DIR__, 1);
  $db   = $root.'/config/db.php';
  if (is_file($db)) require_once $db;
  error_entrypoint(null, null);
}

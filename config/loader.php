<?php declare(strict_types=1);
/**
 * Fullscreen loader con logo animado.
 * Se auto-omite en /api, AJAX, JSON, CLI y OPTIONS.
 */

if (!defined('LOADER_BOOTED')) define('LOADER_BOOTED', true);

function loader_should_run(): bool {
  if (PHP_SAPI === 'cli') return false;
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'OPTIONS') return false;

  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  if (preg_match('~^/(api|ajax|admin/ajax|console/ajax)/~i', $uri)) return false;

  $xh = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  if ($xh === 'xmlhttprequest') return false;

  $acc = $_SERVER['HTTP_ACCEPT'] ?? '';
  if (stripos($acc, 'application/json') !== false || stripos($acc,'text/event-stream') !== false) return false;

  return true;
}

function loader_boot(array $opt = []): void {
  if (!loader_should_run()) return;

  // Logo desde branding si existe, si no usa fallback
  $logo = $opt['logo'] ?? null;
  if (!$logo && function_exists('load_branding') && function_exists('dbi')) {
    try { $b = load_branding(dbi()); $logo = $b['logo_2x'] ?: $b['logo_url'] ?: null; } catch (Throwable $e) {}
  }
  if (!$logo) $logo = '/asset/img/vallasled-logo.png'; // cambia si deseas
  $logo = htmlspecialchars($logo, ENT_QUOTES, 'UTF-8');

  $css = <<<CSS
<style id="__app_loader_css">
  #app-loader{position:fixed;inset:0;z-index:2147483647;background:#0b1220;display:flex;align-items:center;justify-content:center;opacity:1;transition:opacity .22s ease}
  #app-loader.hide{opacity:0;pointer-events:none}
  #app-loader .ld-center{position:relative;width:220px;height:220px;display:flex;align-items:center;justify-content:center}
  #app-loader .ld-ring{position:absolute;inset:0;border-radius:50%;
    mask: radial-gradient(circle at center, transparent 68%, #000 69%);
    background: conic-gradient(from 0deg,#ffffff 0 25%,#cbd5e1 25% 50%,#64748b 50% 75%,#ffffff 75% 100%);
    animation: ld-spin 1.2s linear infinite}
  #app-loader .ld-logo{position:relative;max-width:85%;height:auto;filter: drop-shadow(0 6px 18px rgba(0,0,0,.6));animation: ld-pulse 1.4s ease-in-out infinite}
  @keyframes ld-spin{to{transform:rotate(360deg)}}
  @keyframes ld-pulse{0%,100%{transform:scale(1);opacity:.95}50%{transform:scale(1.04);opacity:1}}
  @media (prefers-reduced-motion:reduce){
    #app-loader .ld-ring{animation:none}
    #app-loader .ld-logo{animation:none}
  }
</style>
CSS;

  $js = <<<JS
<script id="__app_loader_js">
(function(){
  var LOGO="$logo";
  // crea overlay lo antes posible
  var d=document, root=d.createElement('div');
  root.id='app-loader';
  root.setAttribute('role','status');
  root.innerHTML = '<div class="ld-center"><div class="ld-ring"></div><img class="ld-logo" alt="Cargando" src="'+LOGO+'"></div>';
  (d.body||d.documentElement).appendChild(root);

  function hide(){ try{
    root.classList.add('hide');
    setTimeout(function(){ if(root&&root.parentNode){ root.parentNode.removeChild(root); } }, 260);
    var c=d.getElementById('__app_loader_css'); if(c&&c.parentNode) setTimeout(function(){c.parentNode.removeChild(c)}, 800);
    var s=d.getElementById('__app_loader_js');  if(s&&s.parentNode) setTimeout(function(){s.parentNode.removeChild(s)}, 800);
  }catch(e){} }

  // Al cargar la página
  window.addEventListener('load', hide, {once:true});

  // Mostrar al navegar a otra página
  window.addEventListener('beforeunload', function(){
    try{
      if(!root.parentNode){ (d.body||d.documentElement).appendChild(root); root.classList.remove('hide'); }
    }catch(e){}
  });

  // También al click en enlaces internos
  d.addEventListener('click', function(ev){
    var a = ev.target.closest ? ev.target.closest('a') : null;
    if(!a) return;
    var href = a.getAttribute('href') || '';
    if(a.target==='_blank' || href.indexOf('#')===0 || /^javascript:/i.test(href)) return;
    // mismo origen
    var same = a.origin ? (a.origin === location.origin) : (href.indexOf('http')!==0);
    if(same){ root.classList.remove('hide'); }
  }, true);
})();
</script>
JS;

  echo $css, $js;
}

<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* ===== Config base ===== */
$__db_config = __DIR__ . '/config/db.php';
if (file_exists($__db_config)) { require_once $__db_config; }

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('web_setting')) {
  function web_setting(string $key, ?string $default=null): ?string {
    static $cache=[];
    if (array_key_exists($key,$cache)) return $cache[$key];
    try {
      if (function_exists('db')) {
        $st = db()->prepare('SELECT valor FROM web_setting WHERE clave=? LIMIT 1');
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['valor'])) return $cache[$key]=(string)$row['valor'];
      }
    } catch (Throwable $e) {}
    return $cache[$key]= (function_exists('db_setting') ? db_setting($key,$default) : $default);
  }
}
if (!function_exists('base_url')) {
  function base_url(): string {
    $forced = function_exists('db_setting') ? db_setting('base_url','') : '';
    if ($forced) return rtrim($forced,'/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $sch = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/', '/');
    return $sch.'://'.$host.($base ? $base : '');
  }
}

/* ===== Vars de marca/tema ===== */
$site_name   = web_setting('company_name', (function_exists('db_setting')? db_setting('site_name','Vallasled.com') : 'Vallasled.com'));
$page_title  = isset($page_title) && $page_title!=='' ? (string)$page_title : ('Buscador de Vallas | '.$site_name);

$primary      = web_setting('primary_color', '#007bff');
$primary600   = web_setting('primary_color_600', '#0069d9');
$text_color   = web_setting('text_color', '#e2e8f0');
$muted_color  = web_setting('muted_color', '#94a3b8');
$bg_color     = web_setting('bg_color', '#131925');
$bg_soft      = web_setting('bg_soft_color', '#1e293b');
$border_color = web_setting('border_color', '#334155');

$logo_url   = (string) (web_setting('logo_url', web_setting('company_logo_url', 'https://auth.vallasled.com/admin/website/uploads/img_68cf53891cf322.66583866.png')));
$logo_h     = (int)max(0, (int)(web_setting('logo_height_px','40') ?? 40));
$logo_w     = (int)max(0, (int)(web_setting('logo_width_px','0') ?? 0));
$logo_br    = (int)max(0, (int)(web_setting('border_radius_px','4') ?? 4));
$favicon_url= web_setting('favicon_url', '');
?><!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark light">
  <title><?= h($page_title) ?></title>

  <?php if ($favicon_url): ?>
    <link rel="icon" href="<?= h($favicon_url) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= h($favicon_url) ?>">
  <?php endif; ?>

  <!-- Leaflet (si aplica) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous"/>

  <style>
    :root{
      --color-primary: <?= h($primary) ?>;
      --color-primary-600: <?= h($primary600) ?>;
      --text: <?= h($text_color) ?>;
      --muted: <?= h($muted_color) ?>;
      --bg: <?= h($bg_color) ?>;
      --bg-soft: <?= h($bg_soft) ?>;
      --border: <?= h($border_color) ?>;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Helvetica,Arial,sans-serif;color:var(--text);background:var(--bg);line-height:1.5}
    body.overflow-hidden{overflow:hidden}
    a{color:var(--color-primary);text-decoration:none}
    img{max-width:100%;height:auto;display:block}
    .mx-auto{margin-left:auto;margin-right:auto}
    .px-4{padding-left:1rem;padding-right:1rem}
    .py-12{padding-top:3rem;padding-bottom:3rem}
    .py-16{padding-top:4rem;padding-bottom:4rem}
    .text-4xl{font-size:2.25rem;line-height:1.2}
    .text-3xl{font-size:1.875rem;line-height:1.3}
    .hidden{display:none}
    .max-w-7xl{max-width:80rem}

    header{
      background-color:rgba(19,25,37,.8);
      -webkit-backdrop-filter:blur(10px);
      backdrop-filter:blur(10px);
      color:#fff; position:sticky; top:0; z-index:1200;  /* z-index ↑ */
      border-bottom:1px solid var(--border);
    }
    .header-top{display:flex;justify-content:space-between;align-items:center;padding:1rem 0}
    .header-top .logo{<?= $logo_h>0?'height:'.(int)$logo_h.'px;':'' ?><?= $logo_w>0?'width:'.(int)$logo_w.'px;':'' ?>border-radius:<?= (int)$logo_br ?>px}
    .header-top nav a{color:#fff;background:transparent;padding:.5rem 1rem;border-radius:.25rem;font-weight:500;cursor:pointer;transition:all .2s}
    .header-top nav a:hover{background-color:rgba(255,255,255,.1)}

    .mobile-menu-icon{background:none;border:0;color:#fff;padding:.5rem;cursor:pointer}
    .mobile-menu-icon svg{width:1.5rem;height:1.5rem}

    /* 👇 Navegación de escritorio sin usar .hidden (evita el !important del app.min.css) */
    .nav-desktop{display:none;gap:.5rem;align-items:center}
    @media (min-width:768px){
      .nav-desktop{display:flex}
    }

    #mobile-menu{
      position:absolute;top:100%;left:0;right:0;width:100%;z-index:1100;
      background-color:var(--bg-soft);
      overflow:hidden;max-height:500px;opacity:1;visibility:visible;
      transition:max-height .4s ease-in-out,opacity .4s ease-in-out;
      border-bottom:1px solid var(--border);
    }
    #mobile-menu.menu-closed{
      max-height:0;opacity:0;visibility:hidden;
      transition:max-height .4s ease-in-out,opacity .4s ease-in-out,visibility 0s .4s;
    }

    @media (min-width:768px){
      .md\:hidden{display:none}
      #mobile-menu{display:none}
    }
    @media (max-width:767px){
      .text-4xl{font-size:1.75rem;line-height:1.2}
      .text-3xl{font-size:1.5rem;line-height:1.3}
      .py-12{padding-top:2rem;padding-bottom:2rem}
      .py-16{padding-top:2.5rem;padding-bottom:2.5rem}
    }
  </style>
</head>
<body>

<header>
  <div class="max-w-7xl mx-auto px-4">
    <div class="header-top">
      <!-- Logo -> "/" -->
      <a href="/" aria-label="Inicio">
        <img src="<?= h($logo_url) ?>" alt="<?= h($site_name) ?>" class="logo" loading="lazy" decoding="async">
      </a>

      <!-- 👇 cambiada: antes 'hidden md:flex ...' -->
      <nav class="nav-desktop items-center gap-2" aria-label="Principal">
        <a href="#tipos">Tipos</a>
        <a href="#mapa">Mapa</a>
        <a href="#catalogo">Catálogo</a>
        <a href="/login/cliente">Acceder</a>
        <a href="/proveedores/registro" style="background-color:var(--color-primary);border-radius:.25rem;padding:.5rem 1rem">Registra tu valla</a>
      </nav>

      <div class="md:hidden">
        <button id="mobile-menu-button" class="mobile-menu-icon"
                aria-label="Abrir menú" aria-expanded="false" aria-controls="mobile-menu" type="button">
          <svg id="icon-open" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
          <svg id="icon-close" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>
  </div>

  <div id="mobile-menu" class="menu-closed md:hidden" role="menu" aria-hidden="true">
    <div class="px-4 pb-4 pt-2">
      <a href="#tipos" class="block py-2 px-2 hover:bg-slate-700 rounded" role="menuitem">Tipos</a>
      <a href="#mapa" class="block py-2 px-2 hover:bg-slate-700 rounded" role="menuitem">Mapa</a>
      <a href="#catalogo" class="block py-2 px-2 hover:bg-slate-700 rounded" role="menuitem">Catálogo</a>
      <a href="/login/cliente" class="w-full block py-2 px-2 mt-2 hover:bg-slate-700 rounded" role="menuitem">Acceder</a>
      <a href="/proveedores/registro" class="w-full block text-center py-2 px-2 mt-2 text-white rounded" style="background-color:var(--color-primary);" role="menuitem">Registra tu valla</a>
    </div>
  </div>
</header>

<main>
  <!-- tu contenido -->
</main>

<script>
(function(){
  if (window.__mobile_menu_inited) return;
  window.__mobile_menu_inited = true;

  const btn = document.getElementById('mobile-menu-button');
  const menu = document.getElementById('mobile-menu');
  const iconOpen = document.getElementById('icon-open');
  const iconClose = document.getElementById('icon-close');
  const header = document.querySelector('header');
  if (!btn || !menu || !header) return;

  const links = Array.from(menu.querySelectorAll('a'));
  const OFFSET = 8;

  function setAria(open){
    btn.setAttribute('aria-expanded', open ? 'true':'false');
    menu.setAttribute('aria-hidden', open ? 'false':'true');
  }
  function openMenu() {
    menu.classList.remove('menu-closed');
    iconOpen.classList.add('hidden');
    iconClose.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    setAria(true);
  }
  function closeMenu() {
    menu.classList.add('menu-closed');
    iconOpen.classList.remove('hidden');
    iconClose.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    setAria(false);
  }
  function toggleMenu() {
    if (menu.classList.contains('menu-closed')) openMenu(); else closeMenu();
  }
  function smoothScrollTo(id) {
    const el = document.querySelector(id);
    if (!el) return;
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const y = el.getBoundingClientRect().top + window.scrollY - header.offsetHeight - OFFSET;
    window.scrollTo({ top: y, behavior: prefersReduced ? 'auto' : 'smooth' });
  }

  btn.addEventListener('click', toggleMenu);

  window.addEventListener('resize', () => { if (window.innerWidth >= 768) closeMenu(); });
  document.addEventListener('click', (e) => {
    if (menu.classList.contains('menu-closed')) return;
    if (!menu.contains(e.target) && !btn.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  links.forEach(a => {
    const href = a.getAttribute('href') || '';
    if (href.startsWith('#')) {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        closeMenu();
        smoothScrollTo(href);
      });
    } else {
      a.addEventListener('click', closeMenu);
    }
  });
})();
</script>
</body>
</html>

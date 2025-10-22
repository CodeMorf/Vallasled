<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* ===== DB + SEO ===== */
$__db_config = __DIR__ . '/config/db.php';
if (file_exists($__db_config)) { require_once $__db_config; } // incluye seo.php

/* ===== Tracking ===== */
require_once __DIR__ . '/config/tracking.php';

/* ===== Helper h() ===== */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$page_title = 'Plataforma de alquiler de vallas publicitarias en RD | Vallasled';

/* ===== SEO overrides ===== */
$__seo = [
  'title'       => 'Vallas LED en Santo Domingo | Catálogo y Mapa',
  'description' => db_setting(
    'home_meta_description',
    db_setting('site_description', 'Reserva vallas LED y estáticas en Santo Domingo, Punta Cana y toda RD. Mapas, precios y disponibilidad.')
  ),
  'og_type'     => 'article',
];

/* ===== Inyecta <meta> y assets antes de </head> ===== */
function __inject_seo_head(string $html, array $overrides): string {
  if (!function_exists('seo_page') || !function_exists('seo_head')) return $html;
  $head  = seo_head(seo_page($overrides));
  $head .= '<link rel="sitemap" type="application/xml" href="/sitemap.xml">' . "\n";
  $head .= '<link rel="stylesheet" href="/assets/css/seo-copy.css">' . "\n";
  $head .= '<script src="/assets/js/seo-copy.js" defer></script>' . "\n";
  return preg_replace('~</head>~i', $head . '</head>', $html, 1) ?: ($head . $html);
}

/* ===== Header ===== */
$__header = __DIR__ . '/partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $__hdr = ob_get_clean();
  echo __inject_seo_head($__hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  if (function_exists('seo_head')) echo seo_head(seo_page($__seo));
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<link rel="sitemap" type="application/xml" href="/sitemap.xml">';
  echo '<link rel="stylesheet" href="/assets/css/seo-copy.css">';
  echo '<script src="/assets/js/seo-copy.js" defer></script>';
  echo "</head><body>";
}

/* Tracking en body */
if (function_exists('tracking_body')) tracking_body();

/* ===== CSS base y overrides ===== */
$asset_ver = (string) (function_exists('db_setting') ? db_setting('asset_version','20251005') : '20251005');
?>
<link rel="stylesheet" href="/css/app.min.css">
<link rel="stylesheet" href="/assets/css/home.css">
<link rel="stylesheet" href="/assets/css/app.override.css?v=<?= h($asset_ver) ?>">
<meta name="site-root" content="/" />

<?php
/* ===== Banner + hero config ===== */
require_once __DIR__ . '/config/home_banner_video_urls.php';
$hb = home_banner_config();

/* ===== Hero desde BD ===== */
$hero_top     = db_setting('hero_title_top',    'Plataforma Inteligente de');
$hero_bottom  = db_setting('hero_title_bottom', 'Publicidad Exterior');
$hero_sub     = db_setting('hero_subtitle',     'Conectamos tu marca con audiencias masivas en República Dominicana usando vallas digitales y datos.');
$hero_cta_txt = db_setting('hero_cta_text',     'Explorar Mapa de Vallas');
$hero_cta_url = db_setting('hero_cta_url',      '/#mapa/');

/* Partir “Publicidad Exterior” en dos segmentos */
$hb_parts = explode(' ', trim($hero_bottom), 2);
$hero_bottom_w1 = $hb_parts[0] ?? '';
$hero_bottom_w2 = $hb_parts[1] ?? '';
?>

<?php if (!empty($hb['enabled'])): ?>
<section class="hero-banner" aria-label="Banner">
  <?php if (($hb['mode'] ?? '') === 'image'): ?>
    <img class="hero-media" src="<?= h($hb['image_url']) ?>" alt="Banner principal">
  <?php else: ?>
    <video id="heroVideo" class="hero-media" playsinline muted autoplay loop controlslist="nodownload noplaybackrate" <?= !empty($hb['poster']) ? 'poster="'.h($hb['poster']).'"' : '' ?>></video>
    <noscript><img class="hero-media" src="<?= h($hb['poster'] ?? '') ?>" alt="Banner"></noscript>
    <script>
      (function(){
        const vids = <?= json_encode($hb['video_urls'], JSON_UNESCAPED_SLASHES) ?>;
        const v = document.getElementById('heroVideo'); if (!v || !Array.isArray(vids) || !vids.length) return;
        let i = 0;
        function play(n){
          i = (n + vids.length) % vids.length;
          const src = vids[i]; if (!src) return;
          if (!v.currentSrc || v.currentSrc.indexOf(src) === -1){ v.src = src; v.load(); }
          const p = v.play(); if (p && p.then) p.catch(()=>{});
        }
        v.addEventListener('ended', ()=> play(i+1));
        v.addEventListener('error', ()=> play(i+1));
        play(0);
        setInterval(()=> play(i+1), 60000);
        document.addEventListener('visibilitychange', ()=>{ if (document.hidden) { try{v.pause()}catch(e){} } else { try{v.play()}catch(e){} } });
      })();
    </script>
  <?php endif; ?>

  <div class="hero-text-content">
    <h1 class="h1-hero">
      <span class="h1-top"><?= h($hero_top) ?></span>
      <span class="h1-bottom">
        <span class="h1-publicidad"><?= h($hero_bottom_w1) ?></span>
        <span class="h1-exterior"><?= h($hero_bottom_w2) ?></span>
      </span>
    </h1>
    <p class="hero-sub"><?= h($hero_sub) ?></p>
    <a href="<?= h($hero_cta_url) ?>" class="btn btn-primary btn-hero" aria-label="<?= h($hero_cta_txt) ?>">
      <?= h($hero_cta_txt) ?>
    </a>
  </div>
</section>
<?php endif; ?>

<section id="destacadas" class="py-12" aria-label="Vallas destacadas">
  <div class="max-w-7xl px-4">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem">
      <h2 class="text-3xl" style="font-weight:900;margin:0">Vallas destacadas</h2>
      <a href="/destacadas" class="btn btn-outline" aria-label="Ver todas las destacadas">Ver todas</a>
    </div>
    <div id="featured-row" class="featured-row"></div>
  </div>
</section>

<section id="tipos" class="py-16" aria-label="Buscar por tipo">
  <div class="max-w-7xl px-4">
    <h2 class="text-3xl" style="font-weight:900;margin:0 0 2rem 0">Busca por tipo de estructura</h2>
    <div class="grid md:grid-cols-4 gap-8">
      <button type="button" class="category-card" data-tipo="led" aria-label="Filtrar por LED">
        <img src="/assets/imagen/led.png" alt="Valla LED" loading="lazy">
        <div class="overlay"></div><span class="title">LED</span>
      </button>
      <button type="button" class="category-card" data-tipo="impresa" aria-label="Filtrar por Impresa">
        <img src="/assets/imagen/imprentas.png" alt="Valla Impresa" loading="lazy">
        <div class="overlay"></div><span class="title">Impresas</span>
      </button>
      <button type="button" class="category-card" data-tipo="movilled" aria-label="Filtrar por Mochila led">
        <img src="/assets/imagen/mochilaled.png" alt="Mochila led" loading="lazy">
        <div class="overlay"></div><span class="title">Mochila led</span>
      </button>
      <button type="button" class="category-card" data-tipo="vehiculo" aria-label="Filtrar por Vehículo">
        <img src="/assets/imagen/Vehiculo.png" alt="Publicidad en Vehículos" loading="lazy">
        <div class="overlay"></div><span class="title">Vehículos</span>
      </button>
    </div>
  </div>
</section>

<?php if (file_exists(__DIR__.'/partials/mapas.php')) include __DIR__.'/partials/mapas.php'; ?>

<section id="catalogo" class="py-16" aria-label="Catálogo">
  <div class="max-w-7xl px-4">
    <div class="text-center mb-8">
      <h2 class="text-3xl" style="font-weight:900;margin:0">Resultados de Búsqueda</h2>
      <p class="mt-2 text-muted">Explora y filtra todas las opciones disponibles.</p>
    </div>

    <div id="filters-panel" class="mb-8">
      <div>
        <label for="q">Nombre o ubicación</label>
        <input id="q" type="text" placeholder="Ej: Av. Principal, Esquina Comercial..." autocomplete="off">
      </div>
      <div class="grid md:grid-cols-4 gap-8" style="margin-top:1rem">
        <div>
          <label for="provincia">Provincia</label>
          <select id="provincia"><option value="">Todas</option></select>
        </div>
        <div>
          <label for="zona">Zona</label>
          <select id="zona"><option value="">Todas</option></select>
        </div>
        <div>
          <label for="filter-tipo">Tipo de valla</label>
          <select id="filter-tipo">
            <option value="">Todos</option>
            <option value="led">LED</option>
            <option value="impresa">Impresa</option>
            <option value="movilled">Móvil LED</option>
            <option value="vehiculo">Vehículo</option>
          </select>
        </div>
        <div>
          <label for="filter-disponibilidad">Disponibilidad</label>
          <select id="filter-disponibilidad">
            <option value="">Todas</option>
            <option value="1">Disponible</option>
            <option value="0">Ocupado</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;margin-top:1rem">
        <button id="btnBuscar" class="btn btn-primary">Buscar Vallas</button>
        <label style="display:flex;align-items:center;gap:.4rem">
          Mostrar
          <select id="pageSize" class="page-size">
            <option>9</option><option selected>12</option><option>18</option><option>24</option>
          </select>
          por página
        </label>
      </div>
    </div>

    <div id="vallas-grid" class="grid lg:grid-cols-3 gap-8"></div>
    <div id="pager"></div>
  </div>
</section>

<section class="prose max-w-3xl mx-auto my-12" id="seo-copy">
  <h1>Vallas publicitarias en República Dominicana</h1>
  <p><strong>Vallasled.com</strong> conecta marcas con <strong>vallas LED</strong> y estáticas en
    <strong>Santo Domingo</strong>, <strong>Punta Cana</strong>, <strong>Santiago</strong> y otras
    ciudades clave. Explora un catálogo con <strong>mapa</strong>, tamaños, orientación y
    disponibilidad en tiempo real para planificar campañas con alcance medible y presupuesto claro.</p>
  <p>Compara <strong>pantallas LED</strong>, vallas 8×3, panorámicas y formatos móviles como
    <strong>camión LED</strong>. Filtra por zona y fechas, revisa licencias vigentes y periodos de
    mantenimiento antes de reservar. Cada ficha incluye fotos, medidas, visibilidad estimada y
    opciones de pauta con proveedores verificados.</p>
  <ul>
    <li>Cobertura: Distrito Nacional, Santo Domingo Este/Norte/Oeste, Bávaro, La Romana, Puerto Plata, Samaná y más.</li>
    <li>Herramientas: catálogo por categorías, <a href="/mapa">mapa</a> interactivo y reservas en línea.</li>
    <li>Servicios: paquetes por ciudad y rutas de impacto para eventos y lanzamientos.</li>
  </ul>
  <p>¿Necesitas una propuesta llave en mano? <a href="/contacto">Contáctanos</a> y te sugerimos un
    circuito optimizado por audiencia y presupuesto.</p>
</section>

<?php
/* Nube de keywords */
if (function_exists('seo_render_keywords') && function_exists('seo_keywords')) {
  echo seo_render_keywords(
    seo_keywords('vallas led república dominicana', 20),
    fn($k)=>'/buscar?q='.urlencode($k)
  );
}
?>

<!-- Catálogo V2 + cache-busting -->
<script src="/assets/js/app.js?v=<?= h($asset_ver) ?>" defer></script>

<?php
/* ===== Footer ===== */
$__footer = __DIR__ . '/partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

/* Conteo de visita */
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/');
}
?>

<?php
/* /es/alquiler-de-vallas-led/buscar.php */
declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

// DB + SEO
$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

// Tracking
require_once __DIR__ . '/../../config/tracking.php';

// Helper
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

// Params
$q    = trim((string)($_GET['q'] ?? ''));
$tipo = strtolower(preg_replace('~[^a-z0-9]~','', (string)($_GET['tipo'] ?? '')));
$tipos_validos = ['led'=>'Pantallas LED','impresa'=>'Vallas Impresas','movilled'=>'Móvil LED','vehiculo'=>'Publicidad en Vehículos'];
$tipo_label = $tipos_validos[$tipo] ?? 'Todos los Tipos';
$tipo_canon = isset($tipos_validos[$tipo]) ? $tipo : '';

// SEO
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$sch  = function_exists('is_https') && is_https() ? 'https' : 'http';
$titleBase = 'Alquiler de Vallas en RD | Catálogo';
$__seo = [
  'title'       => ($tipo_label==='Todos los Tipos' ? $titleBase : "Alquiler de {$tipo_label} en RD | Catálogo"),
  'description' => ($tipo_label==='Todos los Tipos'
                    ? db_setting('site_description','Encuentra y alquila vallas LED e impresas en Santo Domingo, Punta Cana y todo RD. Mapa, fotos, precios y disponibilidad.')
                    : "Explora {$tipo_label} en RD: mapa, fotos, precios y disponibilidad. Añade al carrito y reserva en minutos."),
  'og_type'     => 'article',
  'canonical'   => $sch.'://'.$host.'/es/alquiler-de-vallas-led/buscar.php'
                   .(($tipo_canon || $q)!=='' ? ('?'.http_build_query(array_filter(['tipo'=>$tipo_canon,'q'=>$q]))) : ''),
];

// Head injector
function __inject_head_buscar(string $html, array $overrides): string {
  if (!function_exists('seo_page') || !function_exists('seo_head')) return $html;
  $head  = seo_head(seo_page($overrides));
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n";
  $head .= '<link rel="sitemap" type="application/xml" href="/sitemap.xml">'."\n";
  $head .= '<link rel="stylesheet" href="/assets/css/home.css">'."\n";
  $head .= '<link rel="stylesheet" href="/assets/css/seo-copy.css">'."\n";
  $head .= '<script src="/assets/js/seo-copy.js" defer></script>'."\n";
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

// Header
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_buscar($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  if (function_exists('seo_head')) echo seo_head(seo_page($__seo));
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<link rel="sitemap" type="application/xml" href="/sitemap.xml">';
  echo '<link rel="stylesheet" href="/assets/css/home.css">';
  echo '<link rel="stylesheet" href="/assets/css/seo-copy.css">';
  echo '<script src="/assets/js/seo-copy.js" defer></script>';
  echo "</head><body>";
}

// Tracking
if (function_exists('tracking_body')) tracking_body();
?>
<meta name="site-root" content="/" />
<meta name="prefill-tipo" content="<?= h($tipo_canon) ?>">
<meta name="prefill-q" content="<?= h($q) ?>">

<section class="hero-banner slim" aria-label="Encabezado">
  <div class="hero-text-content">
    <h1><?= h($tipo_label==='Todos los Tipos' ? 'Alquila vallas en RD' : 'Alquila '.$tipo_label) ?></h1>
    <p><?= h($tipo_label==='Todos los Tipos'
        ? 'Filtra por tipo, provincia y disponibilidad. Catálogo y mapa en tiempo real.'
        : 'Mapa, fotos, precios y disponibilidad de '.$tipo_label.' en RD. Añade al carrito y solicita cotización.') ?></p>
  </div>
</section>

<section id="catalogo" class="py-16" aria-label="Catálogo por tipo">
  <div class="max-w-7xl px-4">
    <div class="text-center mb-8">
      <h2 class="text-3xl" style="font-weight:900;margin:0">Resultados</h2>
      <p class="mt-2 text-muted">Explora y filtra todas las opciones disponibles.</p>
    </div>

    <!-- Filtros -->
    <div id="filters-panel" class="mb-8">
      <div>
        <label for="q">Nombre o ubicación</label>
        <input id="q" type="text" placeholder="Ej: Av. Principal, Esquina Comercial...">
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
  <h2><?= h($tipo_label==='Todos los Tipos' ? 'Alquiler de vallas publicitarias en RD' : 'Alquiler de '.$tipo_label.' en RD') ?></h2>
  <p>Consulta disponibilidad, fotos y ubicación en mapa. Compara tamaños, orientación y licencias antes de reservar. Añade al carrito y completa tu solicitud en minutos.</p>
</section>

<?php
if (function_exists('seo_render_keywords') && function_exists('seo_keywords')) {
  $seed = ($tipo_canon==='') ? 'alquiler vallas led república dominicana' : ('alquiler '.$tipo_label.' república dominicana');
  echo seo_render_keywords(
    seo_keywords($seed, 20),
    fn($k)=> '/buscar?q='.urlencode($k)
  );
}
?>

<script src="/assets/js/app.js" defer></script>
<script>
(function(){
  const t = document.querySelector('meta[name="prefill-tipo"]')?.content || '';
  const q = document.querySelector('meta[name="prefill-q"]')?.content || '';
  const sel = document.getElementById('filter-tipo');
  const iq  = document.getElementById('q');
  if (sel && t) sel.value = t;
  if (iq) iq.value = q;
  window.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnBuscar');
    if (btn) btn.click();
  });
})();
</script>

<?php
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

if (function_exists('track_pageview')) {
  $uri = $_SERVER['REQUEST_URI'] ?? '/es/alquiler-de-vallas-led/buscar.php';
  track_pageview($uri.(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
}
?>

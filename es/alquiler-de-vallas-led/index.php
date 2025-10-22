<?php
/* /es/alquiler-de-vallas-led/index.php */
declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

// DB + SEO
$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

// Tracking
require_once __DIR__ . '/../../config/tracking.php';

// Helper
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

// SEO
$__seo = [
  'title'       => 'Alquiler de Vallas Publicitarias en RD | VallasLED',
  'description' => db_setting('alquiler_meta_description',
                    'Encuentra, compara y alquila vallas publicitarias LED e impresas en las mejores ubicaciones de República Dominicana. Potencia tu marca con nosotros.'),
  'og_type'     => 'article',
];

// Head injector
function __inject_head_alquiler(string $html, array $overrides): string {
  if (!function_exists('seo_page') || !function_exists('seo_head')) return $html;
  $head  = seo_head(seo_page($overrides));
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n";
  $head .= '<link rel="sitemap" type="application/xml" href="/sitemap.xml">'."\n";
  $head .= '<script src="https://cdn.tailwindcss.com"></script>'."\n";
  $head .= '<link rel="preconnect" href="https://fonts.googleapis.com">'."\n";
  $head .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'."\n";
  $head .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">'."\n";
  $head .= <<<CSS
<style>
  body{font-family:'Inter',system-ui,sans-serif;background-color:#0B1220;}
  .hero-section{background-image:linear-gradient(to top, rgba(11,18,32,1) 0%, rgba(11,18,32,.6) 60%), url('https://images.unsplash.com/photo-1605648916312-0c14b43666a2?q=80&w=2070&auto=format&fit=crop');background-size:cover;background-position:center;}
  .gradient-text{background:linear-gradient(90deg,#38bdf8,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
  .feature-card:hover .icon-container{transform:scale(1.1); box-shadow:0 0 25px rgba(56,189,248,.3);}
</style>
CSS;
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

// Header
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_alquiler($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  if (function_exists('seo_head')) echo seo_head(seo_page($__seo));
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<link rel="sitemap" type="application/xml" href="/sitemap.xml">';
  echo '<script src="https://cdn.tailwindcss.com"></script>';
  echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
  echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
  echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">';
  echo <<<CSS
<style>
  body{font-family:'Inter',system-ui,sans-serif;background-color:#0B1220;}
  .hero-section{background-image:linear-gradient(to top, rgba(11,18,32,1) 0%, rgba(11,18,32,.6) 60%), url('https://images.unsplash.com/photo-1605648916312-0c14b43666a2?q=80&w=2070&auto=format&fit=crop');background-size:cover;background-position:center;}
  .gradient-text{background:linear-gradient(90deg,#38bdf8,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
  .feature-card:hover .icon-container{transform:scale(1.1); box-shadow:0 0 25px rgba(56,189,248,.3);}
</style>
CSS;
  echo "</head><body class=\"text-slate-300 antialiased\">";
}

// Tracking
if (function_exists('tracking_body')) tracking_body();
?>

<!-- HERO con “Buscar ahora” -->
<section class="hero-section relative h-[60vh] md:h-[70vh] min-h-[500px] flex items-center justify-center text-center text-white overflow-hidden">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 z-10">
    <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold tracking-tight">Tu Marca en las Mejores Ubicaciones</h1>
    <p class="mt-4 max-w-3xl mx-auto text-lg md:text-xl text-slate-300">La plataforma líder para alquilar vallas publicitarias en República Dominicana. Fácil, rápido y efectivo.</p>

    <form class="mt-8 grid grid-cols-1 md:grid-cols-12 gap-3 items-center" action="/es/alquiler-de-vallas-led/buscar.php" method="get" id="form-buscar-ahora">
      <input name="q" type="search"
             class="md:col-span-6 px-5 py-4 rounded-xl bg-slate-900/70 border border-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500"
             placeholder="Busca por calle, zona o ciudad: Lincoln, Bávaro, Piantini…" aria-label="Buscar vallas" />
      <select name="tipo" id="tipo"
              class="md:col-span-3 px-5 py-4 rounded-xl bg-slate-900/70 border border-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
        <option value="">Todos los tipos</option>
        <option value="led">Pantallas LED</option>
        <option value="impresa">Vallas Impresas</option>
        <option value="movilled">Móvil LED</option>
        <option value="vehiculo">Publicidad en Vehículos</option>
      </select>
      <button type="submit"
              class="md:col-span-3 bg-sky-600 hover:bg-sky-500 text-white font-bold py-4 px-10 rounded-xl transition-transform duration-300 hover:scale-105 text-lg shadow-2xl shadow-sky-500/20">
        Buscar Ahora
      </button>
    </form>

    <div class="mt-4">
      <a href="/catalogo" class="text-slate-300 underline decoration-sky-500/60 hover:text-white">Ver catálogo completo</a>
    </div>
  </div>
</section>

<!-- Cómo funciona -->
<section class="bg-slate-800/50 py-16 md:py-24">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-extrabold text-white">Alquila tu Valla en 3 Simples Pasos</h2>
      <p class="mt-3 text-lg text-slate-400">Proceso pensado para lanzar en tiempo récord.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">
      <div class="text-center">
        <div class="mx-auto bg-slate-700 h-20 w-20 rounded-full flex items-center justify-center border-2 border-sky-500/50 shadow-lg">
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Busca y Explora</h3>
        <p class="mt-2 text-slate-400">Mapa interactivo y filtros por ubicación, tipo y disponibilidad.</p>
      </div>
      <div class="text-center">
        <div class="mx-auto bg-slate-700 h-20 w-20 rounded-full flex items-center justify-center border-2 border-sky-500/50 shadow-lg">
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Compara y Selecciona</h3>
        <p class="mt-2 text-slate-400">Añade al carrito, compara precios, medidas y visibilidad.</p>
      </div>
      <div class="text-center">
        <div class="mx-auto bg-slate-700 h-20 w-20 rounded-full flex items-center justify-center border-2 border-sky-500/50 shadow-lg">
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="m22 11.08-2-1.83-2.09.42-1.33-1.89-1.92.88-.6-2.18L12 5l-1.06.3-1.92-.88-1.33 1.89L5.61 9.25l-2 1.83 2 1.83 2.09-.42 1.33 1.89 1.92-.88.6 2.18L12 19l1.06-.3 1.92.88 1.33-1.89 2.09.42Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Alquila y Lanza</h3>
        <p class="mt-2 text-slate-400">Confirma por WhatsApp, envía el arte y lanza tu campaña.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA final -->
<section class="py-16 md:py-24">
  <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl md:text-5xl font-extrabold text-white">¿Listo para que <span class="gradient-text">miles de personas</span> vean tu marca?</h2>
    <p class="mt-4 text-lg text-slate-400">El punto perfecto te espera. Encuéntralo ahora.</p>
    <div class="mt-8">
      <a href="/es/alquiler-de-vallas-led/buscar.php" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-4 px-10 rounded-full transition-transform duration-300 hover:scale-105 text-lg shadow-2xl shadow-sky-500/20">
        Explorar Catálogo de Vallas
      </a>
    </div>
  </div>
</section>

<?php
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/alquiler-de-vallas-led/');
}
?>

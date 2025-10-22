<?php
/* /tipos/index.php */
declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

// DB + SEO
$__db = __DIR__ . '/../config/db.php';
if (file_exists($__db)) { require_once $__db; }

// Tracking
require_once __DIR__ . '/../config/tracking.php';

// Helper
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

/** SEO */
$__seo = [
  'title'       => 'Tipos de Vallas Publicitarias | Catálogo y Guía Completa',
  'description' => 'Guía completa sobre los tipos de vallas publicitarias en República Dominicana: Pantallas LED, Vallas Impresas, Publicidad Móvil y más. Encuentra el formato perfecto para tu campaña.',
  'og_type'     => 'article',
];

/** Head injector */
function __inject_head_tipos(string $html, array $overrides): string {
  if (!function_exists('seo_page') || !function_exists('seo_head')) return $html;
  $head  = seo_head(seo_page($overrides));
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
  $head .= '<link rel="sitemap" type="application/xml" href="/sitemap.xml">' . "\n";
  $head .= '<script src="https://cdn.tailwindcss.com"></script>' . "\n";
  $head .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
  $head .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
  $head .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">' . "\n";
  $head .= <<<CSS
<style>
  body{font-family:'Inter',system-ui,sans-serif;background-color:#0B1220;}
  .category-card{transition:transform .3s ease-in-out, box-shadow .3s ease-in-out;}
  .category-card:hover{transform:translateY(-8px);box-shadow:0 20px 25px -5px rgb(0 0 0 / .2), 0 8px 10px -6px rgb(0 0 0 / .2);}
  .category-card .overlay{background:linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,0) 60%);transition:background .3s ease;}
  .category-card:hover .overlay{background:linear-gradient(to top, rgba(0,0,0,.95) 0%, rgba(0,0,0,0) 80%);}
  .gradient-text{background:linear-gradient(90deg,#38bdf8,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
</style>
CSS;
  return preg_replace('~</head>~i', $head . '</head>', $html, 1) ?: ($head . $html);
}

/* Header */
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_tipos($hdr, $__seo);
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
  .category-card{transition:transform .3s ease-in-out, box-shadow .3s ease-in-out;}
  .category-card:hover{transform:translateY(-8px);box-shadow:0 20px 25px -5px rgb(0 0 0 / .2), 0 8px 10px -6px rgb(0 0 0 / .2);}
  .category-card .overlay{background:linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,0) 60%);transition:background .3s ease;}
  .category-card:hover .overlay{background:linear-gradient(to top, rgba(0,0,0,.95) 0%, rgba(0,0,0,0) 80%);}
  .gradient-text{background:linear-gradient(90deg,#38bdf8,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
</style>
CSS;
  echo "</head><body class=\"text-slate-300 antialiased\">";
}

/* Tracking */
if (function_exists('tracking_body')) tracking_body();
?>

<main class="text-slate-300 antialiased">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 md:py-20">

    <!-- Hero -->
    <header class="text-center mb-12 md:mb-16">
      <h1 class="text-4xl md:text-6xl font-extrabold text-white tracking-tight">El Formato Perfecto Para Tu Mensaje</h1>
      <p class="mt-4 max-w-3xl mx-auto text-lg md:text-xl text-slate-400">
        Desde el dinamismo de las pantallas LED hasta la presencia constante de las vallas impresas, explora todas las opciones para llevar tu marca al siguiente nivel.
      </p>
    </header>

    <!-- Grid de categorías -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8 mb-12 md:mb-20" aria-label="Tipos de vallas publicitarias">

      <!-- LED -->
      <a href="/tipos/buscar.php?tipo=led" class="category-card group relative rounded-xl overflow-hidden shadow-lg">
        <img src="/assets/imagen/led.png" alt="Valla Publicitaria tipo LED de noche en una ciudad" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105">
        <div class="overlay absolute inset-0"></div>
        <div class="absolute bottom-0 left-0 p-6">
          <h2 class="text-2xl font-bold text-white">Pantallas LED</h2>
          <p class="text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">Impacto dinámico con contenido en video.</p>
        </div>
      </a>

      <!-- Impresas -->
      <a href="/tipos/buscar.php?tipo=impresa" class="category-card group relative rounded-xl overflow-hidden shadow-lg">
        <img src="/assets/imagen/imprentas.png" alt="Valla publicitaria impresa en una carretera" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105">
        <div class="overlay absolute inset-0"></div>
        <div class="absolute bottom-0 left-0 p-6">
          <h2 class="text-2xl font-bold text-white">Vallas Impresas</h2>
          <p class="text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">Presencia 24/7 en puntos estratégicos.</p>
        </div>
      </a>

      <!-- Móvil LED -->
      <a href="/tipos/buscar.php?tipo=movilled" class="category-card group relative rounded-xl overflow-hidden shadow-lg">
        <img src="/assets/imagen/mochilaled.png" alt="Camión con pantalla LED publicitaria en un evento" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105">
        <div class="overlay absolute inset-0"></div>
        <div class="absolute bottom-0 left-0 p-6">
          <h2 class="text-2xl font-bold text-white">Mochila Led</h2>
          <p class="text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">Publicidad flexible que va donde está tu público.</p>
        </div>
      </a>

      <!-- Vehículos -->
      <a href="/tipos/buscar.php?tipo=vehiculo" class="category-card group relative rounded-xl overflow-hidden shadow-lg">
        <img src="/assets/imagen/Vehiculo.png" alt="Vehículo comercial con rotulación publicitaria completa" class="w-full h-full object-cover transform transition-transform duration-300 group-hover:scale-105">
        <div class="overlay absolute inset-0"></div>
        <div class="absolute bottom-0 left-0 p-6">
          <h2 class="text-2xl font-bold text-white">Publicidad en Vehículos</h2>
          <p class="text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">Tu marca en movimiento por toda la ciudad.</p>
        </div>
      </a>

    </section>

    <!-- Guía rápida -->
    <section class="max-w-4xl mx-auto">
      <h2 class="text-3xl font-bold text-white text-center mb-10">Guía Rápida para Elegir Tu Valla</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 bg-slate-800 p-3 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="text-sky-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="12" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M12 16v-2"/></svg>
          </div>
          <div>
            <h3 class="font-semibold text-white text-lg">Alto Tráfico y Dinamismo</h3>
            <p class="text-slate-400 mt-1">Usa <strong class="text-sky-400">Pantallas LED</strong> para captar la máxima atención con videos y animaciones en las avenidas más concurridas.</p>
          </div>
        </div>

        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 bg-slate-800 p-3 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="text-indigo-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 6v6l4 2"/></svg>
          </div>
          <div>
            <h3 class="font-semibold text-white text-lg">Presencia Continua 24/7</h3>
            <p class="text-slate-400 mt-1">Las <strong class="text-indigo-400">Vallas Impresas</strong> son perfectas para campañas de larga duración que requieren una visibilidad constante a un costo eficiente.</p>
          </div>
        </div>

        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 bg-slate-800 p-3 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="text-emerald-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9a2 2 0 0 1 0-4h8a2 2 0 0 0 0-4h-4a2 2 0 0 0-2 2v10"/><path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2v-6Z"/></svg>
          </div>
          <div>
            <h3 class="font-semibold text-white text-lg">Eventos y Rutas Específicas</h3>
            <p class="text-slate-400 mt-1">Con la <strong class="text-emerald-400">Publicidad Móvil</strong> (LED o Vehículos) puedes seguir rutas personalizadas y estar presente en activaciones, conciertos o ferias.</p>
          </div>
        </div>

        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 bg-slate-800 p-3 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="text-amber-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>
          </div>
          <div>
            <h3 class="font-semibold text-white text-lg">Refuerzo de Marca y Frecuencia</h3>
            <p class="text-slate-400 mt-1">Rotular <strong class="text-amber-400">flotas de vehículos</strong> aumenta la frecuencia y el reconocimiento de marca en zonas clave.</p>
          </div>
        </div>

      </div>
    </section>

    <!-- CTA -->
    <section class="mt-20 text-center">
      <h2 class="text-3xl font-bold text-white">¿Listo para empezar tu campaña?</h2>
      <p class="mt-3 max-w-2xl mx-auto text-lg text-slate-400">Explora nuestro inventario completo, filtra por ubicación y consulta la disponibilidad en tiempo real.</p>
      <div class="mt-8 flex justify-center gap-4">
        <a href="/catalogo" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 px-8 rounded-full transition-colors text-lg">
          Ver Catálogo Completo
        </a>
      </div>
    </section>

  </div>
</main>

<?php
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/tipos');
}
?>

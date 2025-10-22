<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/marketing/index.php
 * Página de marketing con Tailwind. Header y footer opcionales.
 * Opcional: /config/db.php, /partials/{header,footer}.php, /config/tracking.php
 */

$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

$__seo = [
  'title'       => 'Marketing de Alto Impacto con Vallas Publicitarias | VallasLED',
  'description' => 'Descubre cómo las vallas publicitarias pueden aumentar la visibilidad de tu negocio, atraer más clientes y construir una marca inolvidable en República Dominicana.',
  'og_type'     => 'website',
];

function __inject_head_mkt(string $html, array $overrides): string {
  $head = '';
  if (function_exists('seo_page') && function_exists('seo_head')) {
    $head .= seo_head(seo_page($overrides));
  } else {
    $head .= '<title>'.h($overrides['title']).'</title><meta name="description" content="'.h($overrides['description']).'">';
  }
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">'."\n";
  $head .= '<link rel="icon" href="/asset/icono/vallasled-icon-v1-64.png">'."\n";
  $head .= '<link rel="sitemap" type="application/xml" href="'.h((function_exists('base_url')?base_url():'/')).'/sitemap.xml">'."\n";
  $head .= <<<HEAD
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  body{font-family:'Inter',system-ui,sans-serif;background-color:#0B1220}
  .hero-marketing{
    background-image:linear-gradient(to top,rgba(11,18,32,1) 0%,rgba(11,18,32,.7) 100%),
      url('https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=2000&auto=format&fit=crop');
    background-size:cover;background-position:center
  }
  .benefit-card:hover .icon-box{transform:scale(1.1);box-shadow:0 0 30px rgba(56,189,248,.3)}
</style>
HEAD;
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

// Header
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_mkt($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_mkt("</head>", $__seo);
  echo "<body class=\"text-slate-300 antialiased\">";
}

// Body tracking
if (function_exists('tracking_body')) tracking_body();
?>

<!-- Hero -->
<section class="hero-marketing relative min-h-[60vh] flex items-center py-20">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 z-10">
    <div class="max-w-3xl">
      <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-white">¿Tu Negocio Necesita Más Visibilidad?</h1>
      <p class="mt-4 text-lg md:text-xl text-slate-300">En un mundo digital saturado, las vallas publicitarias capturan la atención de miles de clientes potenciales cada día.</p>
      <div class="mt-8">
        <a href="/es/catalogo/" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 px-8 rounded-full transition-transform duration-300 hover:scale-105 text-lg shadow-2xl shadow-sky-500/20">Explorar Vallas</a>
      </div>
    </div>
  </div>
</section>

<!-- Benefits -->
<section class="py-16 md:py-24 bg-slate-800/50">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-extrabold text-white">El Poder de Estar Afuera</h2>
      <p class="mt-3 text-lg text-slate-400 max-w-2xl mx-auto">La publicidad exterior es una declaración. Rentable y constante.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
      <div class="benefit-card text-center p-6 bg-slate-800 rounded-xl border border-slate-700">
        <div class="icon-box mx-auto h-16 w-16 rounded-full flex items-center justify-center bg-slate-900 border border-slate-600 transition-transform duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Alcance Masivo</h3>
        <p class="mt-2 text-slate-400">Impacta a conductores y peatones en rutas de alto tráfico.</p>
      </div>

      <div class="benefit-card text-center p-6 bg-slate-800 rounded-xl border border-slate-700">
        <div class="icon-box mx-auto h-16 w-16 rounded-full flex items-center justify-center bg-slate-900 border border-slate-600 transition-transform duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Alta Frecuencia</h3>
        <p class="mt-2 text-slate-400">Exposición 24/7 y recuerdo sostenido de la marca.</p>
      </div>

      <div class="benefit-card text-center p-6 bg-slate-800 rounded-xl border border-slate-700">
        <div class="icon-box mx-auto h-16 w-16 rounded-full flex items-center justify-center bg-slate-900 border border-slate-600 transition-transform duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Construye Confianza</h3>
        <p class="mt-2 text-slate-400">Presencia física que legitima y posiciona.</p>
      </div>

      <div class="benefit-card text-center p-6 bg-slate-800 rounded-xl border border-slate-700">
        <div class="icon-box mx-auto h-16 w-16 rounded-full flex items-center justify-center bg-slate-900 border border-slate-600 transition-transform duration-300">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </div>
        <h3 class="mt-5 text-xl font-bold text-white">Impacto Local</h3>
        <p class="mt-2 text-slate-400">Segmentación geográfica precisa según tu público.</p>
      </div>
    </div>
  </div>
</section>

<!-- Strategy -->
<section class="py-16 md:py-24">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-extrabold text-white">Elige tu Estrategia</h2>
      <p class="mt-3 text-lg text-slate-400 max-w-2xl mx-auto">Combina formatos para una campaña robusta.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
      <div class="bg-slate-800 rounded-xl overflow-hidden border border-slate-700">
        <img src="/assets/imagen/led-marketing.png" alt="Valla LED de noche" class="w-full h-64 object-cover">
        <div class="p-8">
          <h3 class="text-2xl font-bold text-sky-400">Vallas LED</h3>
          <p class="mt-2 text-slate-400">Video y animación con cambios en tiempo real.</p>
          <ul class="mt-4 space-y-2 text-slate-300">
            <li class="flex items-start"><span class="text-sky-400 mr-2">✓</span> Máximo impacto visual.</li>
            <li class="flex items-start"><span class="text-sky-400 mr-2">✓</span> Mensajes dinámicos.</li>
            <li class="flex items-start"><span class="text-sky-400 mr-2">✓</span> Ideal en alto tráfico.</li>
          </ul>
        </div>
      </div>
      <div class="bg-slate-800 rounded-xl overflow-hidden border border-slate-700">
        <img src="/assets/imagen/led-imprenta.png" alt="Valla impresa" class="w-full h-64 object-cover">
        <div class="p-8">
          <h3 class="text-2xl font-bold text-indigo-400">Vallas Impresas</h3>
          <p class="mt-2 text-slate-400">Costo efectivo para branding sostenido.</p>
          <ul class="mt-4 space-y-2 text-slate-300">
            <li class="flex items-start"><span class="text-indigo-400 mr-2">✓</span> Exposición continua.</li>
            <li class="flex items-start"><span class="text-indigo-400 mr-2">✓</span> Gran costo beneficio.</li>
            <li class="flex items-start"><span class="text-indigo-400 mr-2">✓</span> Presencia en puntos clave.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Process -->
<section class="py-16 md:py-24 bg-slate-800/50">
  <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
      <h2 class="text-3xl md:text-4xl font-extrabold text-white">Lanza tu Campaña en 3 Pasos</h2>
    </div>
    <div class="relative grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
      <div class="hidden md:block absolute top-1/2 left-0 w-full h-px -translate-y-10">
        <svg width="100%" height="2"><line x1="0" y1="1" x2="100%" y2="1" stroke-width="2" stroke="#334155" stroke-dasharray="8 8"/></svg>
      </div>
      <div class="relative z-10">
        <div class="mx-auto h-20 w-20 rounded-full flex items-center justify-center bg-slate-700 border-2 border-slate-600 text-sky-400 text-2xl font-bold">1</div>
        <h3 class="mt-5 text-xl font-bold text-white">Explora</h3>
        <p class="mt-2 text-slate-400">Usa mapa y catálogo para ubicar las mejores posiciones.</p>
      </div>
      <div class="relative z-10">
        <div class="mx-auto h-20 w-20 rounded-full flex items-center justify-center bg-slate-700 border-2 border-slate-600 text-sky-400 text-2xl font-bold">2</div>
        <h3 class="mt-5 text-xl font-bold text-white">Elige</h3>
        <p class="mt-2 text-slate-400">Selecciona fechas y añade al carrito para cotizar.</p>
      </div>
      <div class="relative z-10">
        <div class="mx-auto h-20 w-20 rounded-full flex items-center justify-center bg-slate-700 border-2 border-slate-600 text-sky-400 text-2xl font-bold">3</div>
        <h3 class="mt-5 text-xl font-bold text-white">Lanza</h3>
        <p class="mt-2 text-slate-400">Envía tu arte o solicita diseño. Nosotros ejecutamos.</p>
      </div>
    </div>
  </div>
</section>

<!-- Testimonial -->
<section class="py-16 md:py-24">
  <div class="container mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center">
    <div class="border-l-4 border-sky-500 pl-8">
      <blockquote class="text-xl md:text-2xl italic text-white leading-relaxed">
        “Desde que usamos vallas LED el flujo de clientes subió 30 por ciento. Nos dicen que nos vieron en la Churchill. Fue clave para posicionarnos.”
      </blockquote>
      <p class="mt-6 font-semibold text-slate-300">Ana de la Cruz</p>
      <p class="text-sm text-slate-400">Gerente de Marketing</p>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="py-16 md:py-24 bg-slate-900/70">
  <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-extrabold text-white">Preguntas Frecuentes</h2>
      <p class="mt-3 text-lg text-slate-400 max-w-2xl mx-auto">Respuestas directas.</p>
    </div>
    <div class="space-y-6">
      <div class="p-6 bg-slate-800 rounded-lg border border-slate-700">
        <h3 class="font-bold text-lg text-white">¿Costo promedio?</h3>
        <p class="mt-2 text-slate-400">Depende de ubicación, tipo y duración. Revisa el catálogo para valores por pieza.</p>
      </div>
      <div class="p-6 bg-slate-800 rounded-lg border border-slate-700">
        <h3 class="font-bold text-lg text-white">¿Tiempo mínimo?</h3>
        <p class="mt-2 text-slate-400">Impresas suelen ser un mes. LED con paquetes flexibles desde una semana.</p>
      </div>
      <div class="p-6 bg-slate-800 rounded-lg border border-slate-700">
        <h3 class="font-bold text-lg text-white">¿Diseño e impresión?</h3>
        <p class="mt-2 text-slate-400">Sí. Podemos diseñar, imprimir e instalar.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA Final -->
<section class="bg-slate-800/50 py-16 md:py-24">
  <div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl md:text-5xl font-extrabold text-white">Tu Próximo Cliente te Está Esperando</h2>
    <p class="mt-4 text-lg text-slate-400">No cedas las mejores ubicaciones. Encuentra hoy tu valla ideal.</p>
    <div class="mt-8">
      <a href="/es/catalogo/" class="bg-sky-600 hover:bg-sky-500 text-white font-bold py-4 px-10 rounded-full transition-transform duration-300 hover:scale-105 text-lg shadow-2xl shadow-sky-500/20">Ver Catálogo y Precios</a>
    </div>
  </div>
</section>

<?php
// Footer
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) {
  include $__footer;
} else {
  echo '<footer class="bg-slate-900 border-t border-slate-800"><div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 text-center text-slate-500"><p>&copy; '.date('Y').' VallasLED. Todos los derechos reservados.</p><p class="text-sm mt-2">Publicidad exterior en República Dominicana.</p></div></footer>';
}

// Pageview
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/marketing');
}

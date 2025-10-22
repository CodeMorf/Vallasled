<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/blog/index.php
 * Listado de artículos del blog. Header/Footer opcionales.
 * Opcional: /config/db.php, /partials/{header,footer}.php, /config/tracking.php
 */

$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

$__seo = [
  'title'       => 'Blog | Consejos de Marketing en Vallas Publicitarias',
  'description' => 'Artículos, guías y consejos sobre cómo potenciar tu negocio con publicidad exterior, vallas LED y marketing de alto impacto.',
  'og_type'     => 'website',
];

function __inject_head_blog(string $html, array $overrides): string {
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
  .post-card:hover .post-image{transform:scale(1.05)}
</style>
HEAD;
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

// Header
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_blog($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_blog("</head>", $__seo);
  echo "<body class=\"text-slate-300 antialiased\">";
}

// Body tracking
if (function_exists('tracking_body')) tracking_body();
?>

<div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-16">
  <!-- Header -->
  <header class="text-center mb-16">
    <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-white">Nuestro Blog</h1>
    <p class="mt-4 text-lg md:text-xl text-slate-400 max-w-3xl mx-auto">
      Estrategias, ideas y casos de éxito para dominar la publicidad exterior.
    </p>
  </header>

  <!-- Featured Post -->
  <section class="mb-16">
    <a href="/es/blog/post_detalle.php?slug=razones-vallas-led" class="block group">
      <div class="grid md:grid-cols-2 gap-8 items-center bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden">
          <img src="https://blog.publiprinters.com/wp-content/uploads/Publiprinters-blog-vallas-publicitarias-1280x720.jpg"
               alt="Valla LED brillante en una ciudad de noche"
               class="post-image w-full h-full object-cover transition-transform duration-300 ease-in-out">
        </div>
        <div class="p-8">
          <span class="text-sm font-bold text-sky-400 uppercase">Estrategia LED</span>
          <h2 class="mt-2 text-3xl font-bold text-white leading-tight group-hover:text-sky-300 transition-colors">
            5 Razones por las que las Vallas LED Superan a la Publicidad Online
          </h2>
          <p class="mt-4 text-slate-400">
            El mundo físico sigue dominando la atención. Analizamos por qué la publicidad LED gana en visibilidad y recuerdo.
          </p>
          <div class="mt-6 flex items-center gap-4">
            <img class="w-12 h-12 rounded-full object-cover" src="https://i.pravatar.cc/150?u=author1" alt="Autor del blog">
            <div>
              <p class="font-semibold text-white">Carlos Valerio</p>
              <p class="text-sm text-slate-500">26 de Septiembre, 2025</p>
            </div>
          </div>
        </div>
      </div>
    </a>
  </section>

  <!-- Blog Grid -->
  <section>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

      <a href="/es/blog/post_detalle.php?slug=diseno-que-atrae" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://images.unsplash.com/photo-1545239351-1141bd82e8a6?q=80&w=1600&auto=format&fit=crop"
               alt="Diseño de valla publicitaria minimalista y efectivo"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-indigo-400 uppercase">Diseño y Creatividad</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-indigo-300 transition-colors">
            Cómo Diseñar un Anuncio de Valla que Nadie Pueda Ignorar
          </h3>
          <p class="mt-3 text-sm text-slate-400">Tienes 3 segundos. Principios clave para un anuncio memorable.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=roi-exterior" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="/assets/imagen/pizza.png"
               alt="Gráfico de retorno de inversión en marketing"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-emerald-400 uppercase">Análisis y ROI</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-emerald-300 transition-colors">
            El ROI Secreto de la Publicidad Exterior: Caso de Estudio
          </h3>
          <p class="mt-3 text-sm text-slate-400">Cómo una pyme triplicó ventas combinando vallas y ads digitales.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=hiperlocal-impresas" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://images.unsplash.com/photo-1465447142348-e9952c393450?q=80&w=1600&auto=format&fit=crop"
               alt="Valla publicitaria en una calle local"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-amber-400 uppercase">Marketing Local</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-amber-300 transition-colors">
            Marketing Hiperlocal: Conquista tu Barrio con Vallas Impresas
          </h3>
          <p class="mt-3 text-sm text-slate-400">Tácticas para dominar tu zona y atraer clientes cercanos.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=movil-vehiculos" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://lh4.googleusercontent.com/proxy/1eucE2sJ3ePbaM8UY1QK10vfjTOu5W7sIMvgWoodpZflypEZlzgqg2hnx4BRL95iTdsNPsNhQ_YTwltA2kpwjBjw2HpsTntKrrCAU5wDdtMosKn6FdN2a0KsVub_EcjyD9QF-x1zMCu5HEIvvl5E0piquvb1"
               alt="Vehículo con publicidad de marca"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-rose-400 uppercase">Publicidad Móvil</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-rose-300 transition-colors">
            El Futuro es Móvil: Vallas en Vehículos para Máxima Exposición
          </h3>
          <p class="mt-3 text-sm text-slate-400">Lleva tu anuncio donde está tu audiencia, todo el día.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=estrategia-360" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="/assets/imagen/360.png"
               alt="Persona interactuando con publicidad física y digital"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-sky-400 uppercase">Estrategia 360°</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-sky-300 transition-colors">
            Combina Marketing Digital y Vallas para una Estrategia 360°
          </h3>
          <p class="mt-3 text-sm text-slate-400">Conecta OOH con redes, SEO local y email marketing.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=psicologia-color" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?q=80&w=1600&auto=format&fit=crop"
               alt="Valla con colores vibrantes"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-indigo-400 uppercase">Diseño y Creatividad</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-indigo-300 transition-colors">
            Psicología del Color: Atrapa Miradas y Genera Emociones
          </h3>
          <p class="mt-3 text-sm text-slate-400">El color comunica. Úsalo a tu favor en exteriores.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=metricas-clave" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=1600&auto=format&fit=crop"
               alt="Dashboard con métricas de campaña"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-emerald-400 uppercase">Análisis y ROI</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-emerald-300 transition-colors">
            Mide el Éxito de tu Campaña de Vallas: Métricas Clave
          </h3>
          <p class="mt-3 text-sm text-slate-400">KPIs prácticos para evaluar alcance e impacto real.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=errores-comunes" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="/assets/imagen/feed.png"
               alt="Ejemplo de mal diseño en valla"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-amber-400 uppercase">Consejos Prácticos</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-amber-300 transition-colors">
            Errores al Alquilar una Valla (y Cómo Evitarlos)
          </h3>
          <p class="mt-3 text-sm text-slate-400">Evita fallas de diseño, mensaje y ubicación.</p>
        </div>
      </a>

      <a href="/es/blog/post_detalle.php?slug=zonas-turisticas" class="post-card block group bg-slate-800/50 border border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-hidden h-56">
          <img src="https://images.unsplash.com/photo-1505761671935-60b3a7427bad?q=80&w=1600&auto=format&fit=crop"
               alt="Valla en zona turística concurrida"
               class="post-image w-full h-full object-cover transition-transform duration-300">
        </div>
        <div class="p-6">
          <span class="text-sm font-bold text-rose-400 uppercase">Marketing de Nicho</span>
          <h3 class="mt-2 text-xl font-bold text-white group-hover:text-rose-300 transition-colors">
            Vallas en Zonas Turísticas: Puerta a un Mercado Global
          </h3>
          <p class="mt-3 text-sm text-slate-400">Capta turistas nacionales e internacionales en hotspots.</p>
        </div>
      </a>

    </div>
  </section>
</div>

<?php
// Footer
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) {
  include $__footer;
} else {
  echo '<footer class="bg-slate-900 border-t border-slate-800 mt-16"><div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 text-center text-slate-500"><p>&copy; '.date('Y').' VallasLED. Todos los derechos reservados.</p></div></footer>';
}

// Pageview
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/blog');
}

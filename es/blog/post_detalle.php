<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/blog/post_detalle.php
 * Detalle de artículo del blog por ?slug=...
 * Usa mismas validaciones, header/footer opcionales y tracking.
 */

$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

/* --------- Fuente de datos: clonado desde /es/blog/index.php (imágenes incluidas) --------- */
$posts = [
  'razones-vallas-led' => [
    'title'   => '5 Razones por las que las Vallas LED Superan a la Publicidad Online',
    'excerpt' => 'El mundo físico sigue dominando la atención. Analizamos por qué la publicidad LED gana en visibilidad y recuerdo.',
    'date'    => '2025-09-26',
    'author'  => 'Carlos Valerio',
    'avatar'  => 'https://i.pravatar.cc/150?u=author1',
    'category'=> 'Estrategia LED',
    'image'   => 'https://blog.publiprinters.com/wp-content/uploads/Publiprinters-blog-vallas-publicitarias-1280x720.jpg',
    'html'    => <<<HTML
      <p>Mientras la pauta digital compite por milisegundos de atención, las vallas LED dominan con presencia constante en avenidas clave.</p>
      <h2 class="text-2xl font-bold text-white mt-8">1) Visibilidad real 24/7</h2>
      <p>Sin ad blockers, sin scroll: exposición continua en puntos de alto tráfico.</p>
      <h2 class="text-2xl font-bold text-white mt-8">2) Creatividad dinámica</h2>
      <p>Mensajes por franja, animaciones y cambios en tiempo real según objetivos.</p>
      <h2 class="text-2xl font-bold text-white mt-8">3) Alcance incremental</h2>
      <p>Impacto adicional fuera de plataformas digitales, ampliando la cobertura total.</p>
      <h2 class="text-2xl font-bold text-white mt-8">4) Recuerdo de marca</h2>
      <p>Gran formato + repetición diaria = top of mind sostenido.</p>
      <h2 class="text-2xl font-bold text-white mt-8">5) CPM competitivo</h2>
      <p>En ubicaciones premium, el costo por mil impresiones compite con social/SEM.</p>
      <blockquote class="mt-8 border-l-4 border-sky-500 pl-5 italic text-slate-300">
        “Lo que se ve en la calle, se recuerda en la compra.”
      </blockquote>
    HTML
  ],
  'diseno-que-atrae' => [
    'title'   => 'Cómo Diseñar un Anuncio de Valla que Nadie Pueda Ignorar',
    'excerpt' => 'Tienes 3 segundos. Principios clave para un anuncio memorable.',
    'date'    => '2025-08-12',
    'author'  => 'María Gómez',
    'avatar'  => 'https://i.pravatar.cc/150?u=author2',
    'category'=> 'Diseño y Creatividad',
    'image'   => 'https://images.unsplash.com/photo-1545239351-1141bd82e8a6?q=80&w=1600&auto=format&fit=crop',
    'html'    => '<p>Reglas: tipografía grande, contraste alto, un solo llamado a la acción y distancia de lectura optimizada.</p>'
  ],
  'roi-exterior' => [
    'title'   => 'El ROI Secreto de la Publicidad Exterior: Caso de Estudio',
    'excerpt' => 'Cómo una pyme triplicó ventas combinando vallas y ads digitales.',
    'date'    => '2025-07-05',
    'author'  => 'Equipo VallasLED',
    'avatar'  => 'https://i.pravatar.cc/150?u=author3',
    'category'=> 'Análisis y ROI',
    'image'   => '/assets/imagen/pizza.png',
    'html'    => '<p>Atribución mixta: códigos QR, tráfico de marca y footfall. Metodología y aprendizajes prácticos.</p>'
  ],
  'hiperlocal-impresas' => [
    'title'   => 'Marketing Hiperlocal: Conquista tu Barrio con Vallas Impresas',
    'excerpt' => 'Tácticas para dominar tu zona y atraer clientes cercanos.',
    'date'    => '2025-06-10',
    'author'  => 'Lucía Pérez',
    'avatar'  => 'https://i.pravatar.cc/150?u=author4',
    'category'=> 'Marketing Local',
    'image'   => 'https://images.unsplash.com/photo-1465447142348-e9952c393450?q=80&w=1600&auto=format&fit=crop',
    'html'    => '<p>Selecciona ubicaciones con match demográfico, refuerza con promos en radio local y señalización en tienda.</p>'
  ],
  'movil-vehiculos' => [
    'title'   => 'El Futuro es Móvil: Vallas en Vehículos para Máxima Exposición',
    'excerpt' => 'Lleva tu anuncio donde está tu audiencia, todo el día.',
    'date'    => '2025-05-21',
    'author'  => 'Juan Herrera',
    'avatar'  => 'https://i.pravatar.cc/150?u=author5',
    'category'=> 'Publicidad Móvil',
    'image'   => 'https://lh4.googleusercontent.com/proxy/1eucE2sJ3ePbaM8UY1QK10vfjTOu5W7sIMvgWoodpZflypEZlzgqg2hnx4BRL95iTdsNPsNhQ_YTwltA2kpwjBjw2HpsTntKrrCAU5wDdtMosKn6FdN2a0KsVub_EcjyD9QF-x1zMCu5HEIvvl5E0piquvb1',
    'html'    => '<p>Rutas optimizadas por horas pico, activaciones y medición con geofencing para estimar impresiones.</p>'
  ],
  'estrategia-360' => [
    'title'   => 'Combina Marketing Digital y Vallas para una Estrategia 360°',
    'excerpt' => 'Conecta OOH con redes, SEO local y email marketing.',
    'date'    => '2025-04-18',
    'author'  => 'Equipo VallasLED',
    'avatar'  => 'https://i.pravatar.cc/150?u=author6',
    'category'=> 'Estrategia 360°',
    'image'   => '/assets/imagen/360.png',
    'html'    => '<p>Usa las vallas para disparar búsquedas de marca y capturar demanda con páginas de aterrizaje optimizadas.</p>'
  ],
  'psicologia-color' => [
    'title'   => 'Psicología del Color: Atrapa Miradas y Genera Emociones',
    'excerpt' => 'El color comunica. Úsalo a tu favor en exteriores.',
    'date'    => '2025-04-02',
    'author'  => 'María Gómez',
    'avatar'  => 'https://i.pravatar.cc/150?u=author2',
    'category'=> 'Diseño y Creatividad',
    'image'   => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?q=80&w=1600&auto=format&fit=crop',
    'html'    => '<p>Contrastes altos, paletas simples y jerarquía visual clara mejoran el recuerdo.</p>'
  ],
  'metricas-clave' => [
    'title'   => 'Mide el Éxito de tu Campaña de Vallas: Métricas Clave',
    'excerpt' => 'KPIs prácticos para evaluar alcance e impacto real.',
    'date'    => '2025-03-11',
    'author'  => 'Analítica VallasLED',
    'avatar'  => 'https://i.pravatar.cc/150?u=author7',
    'category'=> 'Análisis y ROI',
    'image'   => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=1600&auto=format&fit=crop',
    'html'    => '<p>KPI sugeridos: impresiones estimadas, búsquedas de marca, visitas a tienda y ventas en zona de influencia.</p>'
  ],
  'errores-comunes' => [
    'title'   => 'Errores al Alquilar una Valla (y Cómo Evitarlos)',
    'excerpt' => 'Evita fallas de diseño, mensaje y ubicación.',
    'date'    => '2025-02-14',
    'author'  => 'Equipo Creativo',
    'avatar'  => 'https://i.pravatar.cc/150?u=author8',
    'category'=> 'Consejos Prácticos',
    'image'   => '/assets/imagen/feed.png',
    'html'    => '<p>Mensaje confuso, demasiadas palabras y contraste pobre son los errores más frecuentes.</p>'
  ],
  'zonas-turisticas' => [
    'title'   => 'Vallas en Zonas Turísticas: Puerta a un Mercado Global',
    'excerpt' => 'Capta turistas nacionales e internacionales en hotspots.',
    'date'    => '2025-01-20',
    'author'  => 'Equipo VallasLED',
    'avatar'  => 'https://i.pravatar.cc/150?u=author9',
    'category'=> 'Marketing de Nicho',
    'image'   => 'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?q=80&w=1600&auto=format&fit=crop',
    'html'    => '<p>Elige puntos de alto flujo peatonal, adapta idiomas y usa QR con ofertas para conversión inmediata.</p>'
  ],
];

/* --------- Resolver slug --------- */
$slug = isset($_GET['slug']) ? preg_replace('~[^a-z0-9\-]~i','', (string)$_GET['slug']) : '';
$post = $posts[$slug] ?? null;

/* --------- SEO --------- */
$__seo = [
  'title'       => $post ? ($post['title'].' | Blog VallasLED') : 'Artículo no encontrado | Blog VallasLED',
  'description' => $post['excerpt'] ?? 'Artículos, guías y consejos de publicidad exterior.',
  'og_type'     => 'article',
];

function __inject_head_blog_detail(string $html, array $overrides): string {
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
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  body{font-family:'Inter',system-ui,sans-serif;background-color:#0B1220}
  .prose h2{margin-top:1.25rem}
</style>
HEAD;
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

/* --------- Header opcional --------- */
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_blog_detail($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_blog_detail("</head>", $__seo);
  echo "<body class=\"text-slate-300 antialiased\">";
}

/* --------- Tracking body --------- */
if (function_exists('tracking_body')) tracking_body();

?>
<div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-10">
  <?php if (!$post): ?>
    <header class="text-center">
      <h1 class="text-3xl md:text-5xl font-extrabold text-white">Artículo no encontrado</h1>
      <p class="mt-4 text-slate-400">El contenido que buscas no existe o fue movido.</p>
      <div class="mt-8">
        <a href="/es/blog/" class="inline-block bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white px-6 py-3 rounded-lg">Volver al blog</a>
      </div>
    </header>

    <section class="mt-12">
      <h2 class="text-xl font-bold text-white mb-4">Quizá te interese</h2>
      <div class="grid sm:grid-cols-2 gap-6">
        <?php foreach ($posts as $s => $p): ?>
          <a class="block bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden" href="/es/blog/post_detalle.php?slug=<?=h($s)?>">
            <img class="w-full h-40 object-cover" src="<?=h($p['image'])?>" alt="<?=h($p['title'])?>">
            <div class="p-4">
              <span class="text-xs font-bold text-sky-400 uppercase"><?=h($p['category'])?></span>
              <h3 class="mt-1 text-white font-semibold"><?=h($p['title'])?></h3>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <nav class="text-sm text-slate-400 mb-6">
      <a href="/" class="hover:text-slate-200">Inicio</a>
      <span class="mx-2 opacity-50">/</span>
      <a href="/es/blog/" class="hover:text-slate-200">Blog</a>
      <span class="mx-2 opacity-50">/</span>
      <span class="text-slate-300"><?=h($post['title'])?></span>
    </nav>

    <header class="mb-6">
      <span class="text-xs font-bold text-sky-400 uppercase"><?=h($post['category'])?></span>
      <h1 class="mt-2 text-3xl md:text-5xl font-extrabold text-white"><?=h($post['title'])?></h1>
      <p class="mt-3 text-slate-400"><?=h($post['excerpt'])?></p>
      <div class="mt-4 flex items-center gap-3">
        <img class="w-10 h-10 rounded-full object-cover" src="<?=h($post['avatar'])?>" alt="<?=h($post['author'])?>">
        <div class="text-sm">
          <p class="font-semibold text-white"><?=h($post['author'])?></p>
          <p class="text-slate-500"><?=date('j \d\e F, Y', strtotime($post['date']))?></p>
        </div>
      </div>
    </header>

    <figure class="rounded-2xl overflow-hidden border border-slate-700">
      <img class="w-full h-80 md:h-[28rem] object-cover" src="<?=h($post['image'])?>" alt="<?=h($post['title'])?>">
    </figure>

    <article class="prose prose-invert max-w-none mt-8">
      <?=$post['html']?>
    </article>

    <section class="mt-10">
      <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-400">Compartir:</span>
        <?php $url = (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/es/blog/post_detalle.php?slug=' . $slug; $t = $post['title']; ?>
        <a class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 hover:bg-slate-700" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?=urlencode($t)?>&url=<?=urlencode($url)?>">X/Twitter</a>
        <a class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 hover:bg-slate-700" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?=urlencode($url)?>">Facebook</a>
        <a class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 hover:bg-slate-700" target="_blank" rel="noopener" href="https://www.linkedin.com/shareArticle?mini=true&url=<?=urlencode($url)?>&title=<?=urlencode($t)?>">LinkedIn</a>
      </div>
    </section>

    <section class="mt-12">
      <h2 class="text-xl font-bold text-white mb-4">Artículos relacionados</h2>
      <div class="grid sm:grid-cols-2 gap-6">
        <?php
          $rel = array_filter($posts, fn($k) => $k !== $slug, ARRAY_FILTER_USE_KEY);
          $rel = array_slice($rel, 0, 2, true);
          foreach ($rel as $s => $p):
        ?>
          <a class="block bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden" href="/es/blog/post_detalle.php?slug=<?=h($s)?>">
            <img class="w-full h-36 object-cover" src="<?=h($p['image'])?>" alt="<?=h($p['title'])?>">
            <div class="p-4">
              <span class="text-xs font-bold text-sky-400 uppercase"><?=h($p['category'])?></span>
              <h3 class="mt-1 text-white font-semibold"><?=h($p['title'])?></h3>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }
else {
  echo '<footer class="bg-slate-900 border-t border-slate-800 mt-16"><div class="container mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 text-center text-slate-500"><p>&copy; '.date('Y').' VallasLED. Todos los derechos reservados.</p></div></footer>';
}

if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/blog/post_detalle');
}

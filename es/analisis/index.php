<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/analisis/index.php
 * Página de análisis (estática) con Tailwind. Header/Footer y tracking opcionales.
 */

$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

$__seo = [
  'title'       => 'Análisis de Alcance: Publicidad Exterior en Santo Domingo',
  'description' => 'Análisis detallado del alcance e impactos diarios de vallas LED, impresas, vehículos móviles y mochilas publicitarias en Santo Domingo.',
  'og_type'     => 'article',
];

function __inject_head_analisis(string $html, array $overrides): string {
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
  .prose{color:#cbd5e1}
  .prose h1,.prose h2,.prose h3,.prose strong{color:#f8fafc}
  .prose a{color:#38bdf8;text-decoration:none;font-weight:600}
  .prose a:hover{text-decoration:underline}
  .prose ul>li::before{background-color:#38bdf8}
  .stat-card{background-color:rgba(30,41,59,.5);border:1px solid #334155;border-radius:.75rem;padding:1.5rem;display:flex;align-items:flex-start;gap:1rem}
  .table-custom{width:100%;margin-top:2rem;border-collapse:collapse}
  .table-custom th,.table-custom td{padding:.75rem 1rem;border:1px solid #334155;text-align:left}
  .table-custom th{background-color:#1e293b;font-weight:700;color:#f8fafc}
  .table-custom tr:nth-child(even){background-color:#111827}
</style>
HEAD;
  return preg_replace('~</head>~i', $head.'</head>', $html, 1) ?: ($head.$html);
}

// Header opcional
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_analisis($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_analisis("</head>", $__seo);
  echo "<body class=\"text-slate-300 antialiased\">";
}

// Body tracking
if (function_exists('tracking_body')) tracking_body();
?>
<div class="container mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-16">
  <article class="prose prose-lg max-w-none">
    <div class="text-center">
      <h1>Análisis de Alcance Publicitario en Santo Domingo</h1>
      <p class="lead text-slate-400">Una evaluación del potencial de impacto de los medios de publicidad exterior en el principal centro urbano de República Dominicana.</p>
    </div>

    <hr class="my-12 border-slate-700">

    <!-- 1. Contexto -->
    <section>
      <h2>1. Contexto Demográfico y de Movilidad</h2>
      <p>Santo Domingo es el epicentro económico y poblacional del país, lo que lo convierte en el mercado más valioso para la publicidad OOH. El alcance potencial está determinado por su alta densidad y su constante flujo de personas.</p>

      <div class="not-prose grid md:grid-cols-2 gap-6 my-8">
        <div class="stat-card">
          <div>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          </div>
          <div>
            <h4 class="font-bold text-white">Población (GSD)</h4>
            <p class="text-slate-400 text-base">Aproximadamente <strong>3.5 millones de habitantes</strong>, generando un volumen masivo de desplazamientos diarios.</p>
          </div>
        </div>
        <div class="stat-card">
          <div>
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"></path><circle cx="7" cy="17" r="2"></circle><circle cx="17" cy="17" r="2"></circle></svg>
          </div>
          <div>
            <h4 class="font-bold text-white">Flujo Vehicular</h4>
            <p class="text-slate-400 text-base">Más de <strong>850,000 vehículos</strong> registrados solo entre el DN y la provincia Santo Domingo, sin contar el flujo interurbano.</p>
          </div>
        </div>
      </div>

      <p>Corredores como la <strong>Av. 27 de Febrero y la Av. John F. Kennedy</strong> registran un flujo superior a los <strong>200,000 vehículos diarios</strong> cada una, convirtiéndolas en las vitrinas publicitarias más importantes del país.</p>
    </section>

    <!-- 2. Alcance por formato -->
    <section class="mt-12">
      <h2>2. Análisis de Alcance por Formato</h2>
      <p>Cada formato publicitario ofrece un tipo de alcance y frecuencia diferente, adaptado a distintos objetivos de marketing.</p>

      <h3>Vallas LED y Vallas Impresas (Fijas)</h3>
      <p>Estos formatos son la base de la notoriedad de marca. Su ubicación estratégica en puntos de alta congestión o visibilidad garantiza un número masivo de impactos visuales diarios.</p>
      <ul>
        <li><strong>Alcance Estimado:</strong> Una valla en una avenida principal puede generar entre <strong>150,000 y 250,000 impactos visuales diarios</strong>.</li>
        <li><strong>Perfil de Impacto:</strong> Alcance pasivo pero de alta frecuencia, reforzando el recuerdo.</li>
        <li><strong>Ideal para:</strong> Branding, lanzamientos masivos y campañas de largo plazo.</li>
      </ul>

      <h3>Vehículos Móviles (Rutas Publicitarias)</h3>
      <p>La publicidad móvil rompe con el entorno estático y penetra sectores específicos, centros comerciales y eventos.</p>
      <ul>
        <li><strong>Alcance Estimado:</strong> En 8 horas puede generar <strong>50,000 a 90,000 impactos</strong>.</li>
        <li><strong>Perfil de Impacto:</strong> Dinámico y novedoso.</li>
        <li><strong>Ideal para:</strong> Activaciones, promociones geolocalizadas y lanzamientos.</li>
      </ul>

      <h3>Mochilas LED (Publicidad Peatonal)</h3>
      <p>Formato de marketing de guerrilla ideal para interacción directa en zonas de alta densidad peatonal.</p>
      <ul>
        <li><strong>Alcance Estimado:</strong> <strong>5,000 a 15,000 impactos diarios</strong> por promotor.</li>
        <li><strong>Perfil de Impacto:</strong> Personal e interactivo.</li>
        <li><strong>Ideal para:</strong> Inauguraciones, eventos y sampling con códigos de descuento.</li>
      </ul>
    </section>

    <!-- 3. Tabla comparativa -->
    <section class="mt-12">
      <h2>3. Tabla Comparativa de Formatos</h2>
      <div class="not-prose overflow-x-auto">
        <table class="table-custom">
          <thead>
            <tr>
              <th>Formato</th>
              <th>Alcance Diario Estimado (Santo Domingo)</th>
              <th>Tipo de Impacto</th>
              <th>Objetivo Principal</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Vallas Fijas (LED/Impresas)</strong></td>
              <td>150,000 - 250,000 (por ubicación prime)</td>
              <td>Masivo, Alta Frecuencia</td>
              <td>Notoriedad de Marca (Branding)</td>
            </tr>
            <tr>
              <td><strong>Vehículos Móviles</strong></td>
              <td>50,000 - 90,000 (por ruta)</td>
              <td>Amplio, Dinámico, Novedoso</td>
              <td>Activaciones y Cobertura Amplia</td>
            </tr>
            <tr>
              <td><strong>Mochilas LED</strong></td>
              <td>5,000 - 15,000 (por promotor)</td>
              <td>Hiperlocal, Interactivo, Personal</td>
              <td>Eventos y Marketing de Guerrilla</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <hr class="my-12 border-slate-700">

    <!-- 4. Conclusión -->
    <section class="text-center bg-slate-800/50 p-8 rounded-2xl border border-slate-700">
      <h2>4. Conclusión Estratégica</h2>
      <p>La elección debe alinearse al objetivo de campaña. Para <strong>branding masivo</strong>, las vallas fijas son insuperables; para <strong>flexibilidad y cobertura múltiple</strong>, los vehículos móviles; y para <strong>interacción directa</strong>, las mochilas LED. La combinación estratégica maximiza visibilidad y ROI.</p>
    </section>
  </article>
</div>

<?php
// Footer opcional
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) {
  include $__footer;
} else {
  echo '<footer class="bg-slate-900 border-t border-slate-800 mt-16"><div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 text-center text-slate-500"><p>&copy; '.date('Y').' VallasLED. Todos los derechos reservados.</p></div></footer>';
}

// Pageview
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/analisis');
}

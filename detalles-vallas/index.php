<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* Config y helpers */
$cfg = __DIR__ . '/../config/db.php';
$med = __DIR__ . '/../config/media.php';
$trk = __DIR__ . '/../config/tracking.php';
if (file_exists($cfg)) require $cfg;
if (file_exists($med)) require $med;
if (file_exists($trk)) require $trk;

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }

/* Resolver ID “destacada” desde /api/featured.php con varias estrategias */
function resolve_featured_id(): int {
  $api = __DIR__ . '/../api/featured.php';
  if (!file_exists($api)) return 0;

  // 1) Si expone una función conocida
  require_once $api;
  foreach (['featured_id','get_featured_id','featured_get_id','valla_destacada_id'] as $fn) {
    if (function_exists($fn)) {
      $id = (int)call_user_func($fn);
      return $id > 0 ? $id : 0;
    }
  }
  // 2) Si define una constante/variable
  if (defined('FEATURED_ID')) return max(0, (int)constant('FEATURED_ID'));
  if (isset($GLOBALS['FEATURED_ID'])) return max(0, (int)$GLOBALS['FEATURED_ID']);

  // 3) Si imprime JSON al incluirse
  try {
    ob_start();
    include $api;               // si el script hace echo JSON
    $out = trim((string)ob_get_clean());
    if ($out !== '') {
      $j = json_decode($out, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $cand = $j['id'] ?? $j['featured_id'] ?? null;
        $id = is_numeric($cand) ? (int)$cand : 0;
        return $id > 0 ? $id : 0;
      }
      // también aceptamos un número plano
      if (ctype_digit($out)) {
        $id = (int)$out;
        return $id > 0 ? $id : 0;
      }
    }
  } catch (Throwable $e) {
    @ob_end_clean();
  }
  return 0;
}

/* Resolver ID desde query, URL “bonita” o alias destacada */
$rawId = $_GET['id'] ?? null;
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$alias = null;

if (is_string($rawId) && !ctype_digit($rawId)) {
  $alias = strtolower($rawId);
}
$id = i($rawId ?? 0);

// /detalles-vallas/7  o  /detalles-vallas/id/7
if ($id === 0 && preg_match('~^/detalles-vallas/(?:id/)?(\d+)(?:/)?$~i', $path, $m)) {
  $id = (int)$m[1];
}

// /detalles-vallas/destacada  | /detalles-vallas/featured
if ($id === 0 && preg_match('~^/detalles-vallas/(destacada|featured)(?:/)?$~i', $path, $m)) {
  $alias = strtolower($m[1]);
}
// ?id=destacada | ?id=featured desde cualquier parte, incluso home
if ($id === 0 && $alias && in_array($alias, ['destacada','featured'], true)) {
  $id = resolve_featured_id();
  if ($id > 0) {
    header('Location: /detalles-vallas/?id='.$id, true, 302);
    exit;
  }
}

// Si vienen desde la home como ?id=7, redirige a canónica
if ($path === '/' && $id > 0) {
  header('Location: /detalles-vallas/?id='.$id, true, 302);
  exit;
}

/* Canonicalizar a query param para SEO consistente */
if ($id > 0 && !isset($_GET['id'])) {
  header('Location: /detalles-vallas/?id='.$id, true, 301);
  exit;
}

/* Datos */
$vd = null;
try {
  $pdo = db();
  $sql = "SELECT v.id, v.nombre, v.tipo, v.ubicacion, v.zona, v.precio, v.medida, v.disponible,
                 v.lat, v.lng, v.url_stream_trafico,
                 v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta,
                 v.mostrar_precio_cliente,
                 p.nombre AS provincia
          FROM vallas v
          LEFT JOIN provincias p ON p.id=v.provincia_id
          WHERE v.id=:id AND v.visible_publico=1 AND v.estado_valla='activa'
          LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([':id'=>$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  if ($r) {
    $gal=[];
    foreach (['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'] as $c){
      $u = media_norm((string)($r[$c]??'')); if ($u) $gal[]=$u;
    }
    $vd = [
      'id'=>(int)$r['id'],
      'nombre'=>$r['nombre'],
      'tipo'=>strtoupper((string)$r['tipo']),
      'provincia'=>$r['provincia'],
      'ubicacion'=>$r['ubicacion'],
      'zona'=>$r['zona'],
      'medida'=>$r['medida'],
      'precio'=> isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'mostrar_precio'=>(int)($r['mostrar_precio_cliente'] ?? 1),
      'disponible'=>(int)$r['disponible'],
      'trafico'=>$r['url_stream_trafico'] ?? '',
      'gal'=>$gal,
      'lat'=>$r['lat'], 'lng'=>$r['lng'],
    ];
  }
}catch(Throwable $e){}

if (!$vd){
  // fallback: si pidieron “destacada” y aún no hay $id válido, intenta resolver y redirigir
  if (in_array($alias ?? '', ['destacada','featured'], true)) {
    $fid = resolve_featured_id();
    if ($fid > 0) {
      header('Location: /detalles-vallas/?id='.$fid, true, 302);
      exit;
    }
  }
  http_response_code(404);
  echo '<h1 style="color:#fff;background:#111;padding:2rem;font-family:system-ui">No encontrado</h1>';
  exit;
}

/* SEO */
$page_title = h($vd['nombre']).' | Detalles';

/* Header global */
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) { include $__header; }
if (function_exists('tracking_body')) tracking_body();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($vd['nombre'])?> | Detalles</title>
<link rel="canonical" href="/detalles-vallas/?id=<?= (int)$vd['id'] ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  html,body{overflow-x:hidden}
  body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial}
  .thumbnail-active{outline:2px solid #38bdf8; outline-offset:2px; opacity:1}
</style>
</head>
<body class="bg-slate-900 text-slate-300 antialiased">
<div class="container mx-auto p-4 md:p-8">
  <!-- Header -->
  <header class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
    <div>
      <h1 class="text-3xl md:text-4xl font-bold text-white"><?=h($vd['nombre'])?></h1>
      <p class="text-slate-400">ID del Activo: <?= (int)$vd['id'] ?></p>
    </div>
    <div class="flex items-center gap-3">
      <a href="/calendario/?id=<?=$vd['id']?>" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
        Calendario
      </a>
      <a href="/carritos/?a=add&id=<?=$vd['id']?>" class="flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-transform hover:scale-105 shadow-lg shadow-sky-600/30">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.16"/></svg>
        Agregar al Carrito
      </a>
    </div>
  </header>

  <!-- Grid -->
  <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Izquierda: media -->
    <div class="lg:col-span-2 space-y-8">
      <div class="bg-slate-800 p-4 md:p-6 rounded-xl border border-slate-700">
        <!-- Chips -->
        <div class="flex flex-wrap gap-3 mb-4">
          <span class="inline-flex items-center font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/30 px-3 py-1 rounded-full text-sm">
            <?= $vd['tipo']==='LED' ? 'PANTALLA LED' : 'VALLA '.h($vd['tipo']) ?>
          </span>
          <?php if(!empty($vd['provincia'])): ?>
            <span class="inline-flex items-center font-semibold bg-slate-700 text-slate-300 border border-slate-600 px-3 py-1 rounded-full text-sm"><?=h($vd['provincia'])?></span>
          <?php endif; ?>
          <?php if(!empty($vd['medida'])): ?>
            <span class="inline-flex items-center font-semibold bg-slate-700 text-slate-300 border border-slate-600 px-3 py-1 rounded-full text-sm"><?=h($vd['medida'])?></span>
          <?php endif; ?>
          <span class="inline-flex items-center font-semibold <?= $vd['disponible']?'bg-emerald-500/10 text-emerald-400 border-emerald-500/30':'bg-rose-500/10 text-rose-300 border-rose-500/30' ?> px-3 py-1 rounded-full text-sm border">
            <?= $vd['disponible']?'Disponible':'Ocupado' ?>
          </span>
        </div>

        <?php
          $hasGal = !empty($vd['gal']);
          $main = $hasGal ? $vd['gal'][0] : null;
        ?>
        <?php if ($main): ?>
          <div class="mb-4 rounded-lg overflow-hidden border border-slate-700 aspect-video bg-black">
            <img id="main-image" src="<?=h($main)?>" alt="Vista principal" class="w-full h-full object-cover transition-opacity duration-300">
          </div>
        <?php endif; ?>

        <?php if ($vd['trafico']): ?>
          <div class="rounded-lg overflow-hidden border border-slate-700 aspect-video bg-black <?= $main ? 'mt-4':'' ?>">
            <iframe src="<?=h($vd['trafico'])?>" class="w-full h-full" frameborder="0" allowfullscreen loading="lazy"></iframe>
          </div>
        <?php endif; ?>

        <?php if ($hasGal && count($vd['gal'])>1): ?>
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3 mt-4">
            <?php foreach ($vd['gal'] as $idx=>$g): ?>
              <img src="<?=h($g)?>" alt="Foto <?= $idx+1 ?>" class="gallery-thumbnail cursor-pointer rounded-md opacity-70 hover:opacity-100 transition-opacity <?= ($idx===0 && $main)?'thumbnail-active':'' ?>">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!$main && !$vd['trafico']): ?>
          <p class="text-sm text-slate-400">Sin imágenes ni vista de tráfico disponibles para este activo.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Derecha: detalle y CTA -->
    <aside class="lg:col-span-1 space-y-8">
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 sticky top-8">
        <h2 class="text-2xl font-bold text-white mb-4">Detalles de Ubicación</h2>
        <ul class="space-y-4 text-slate-300">
          <li class="flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400 mt-1 flex-shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <div>
              <span class="font-semibold text-white"><?=h($vd['ubicacion'])?></span>
              <p class="text-sm text-slate-400"><?=h($vd['zona'])?><?= $vd['provincia']?' · '.h($vd['provincia']):'' ?></p>
            </div>
          </li>
        </ul>

        <?php if ($vd['mostrar_precio'] && $vd['precio'] > 0): ?>
          <div class="my-6">
            <p class="text-sm text-slate-400">Precio</p>
            <p class="text-4xl font-bold text-white">RD$ <?= number_format($vd['precio'],0,',','.') ?></p>
          </div>
        <?php else: ?>
          <div class="my-6">
            <p class="text-sm text-slate-400">Precio</p>
            <p class="text-lg text-slate-300 font-semibold">Contáctanos para cotización</p>
          </div>
        <?php endif; ?>

        <div class="mb-6">
          <a href="/carritos/?a=add&id=<?=$vd['id']?>" class="flex w-full items-center justify-center gap-3 bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 px-5 rounded-lg transition-transform hover:scale-105 shadow-lg shadow-sky-600/30">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.16"/></svg>
            Añadir al Carrito
          </a>
        </div>

        <?php if ($vd['tipo']==='LED'): ?>
          <div class="text-center">
            <a href="/detalles-led/?id=<?=$vd['id']?>" class="text-sky-400 hover:text-sky-300 font-semibold text-sm transition-colors">Ver versión LED en vivo →</a>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const mainImage = document.getElementById('main-image');
  const thumbs = document.querySelectorAll('.gallery-thumbnail');
  if (mainImage && thumbs.length){
    thumbs.forEach(t=>{
      t.addEventListener('click', function(){
        const src = this.getAttribute('src');
        mainImage.style.opacity = 0;
        setTimeout(()=>{ mainImage.src = src; mainImage.style.opacity = 1; }, 250);
        thumbs.forEach(x=>x.classList.remove('thumbnail-active'));
        this.classList.add('thumbnail-active');
      });
    });
  }
});
</script>

<?php
$show_footer_shape = false;
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/detalles-vallas');
}

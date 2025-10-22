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

/* Datos */
$id = i($_GET['id'] ?? 0);
$vd = null;

try{
  $pdo = db();
  $sql = "SELECT v.id, v.nombre, v.tipo, v.ubicacion, v.zona, v.precio, v.medida, v.disponible,
                 v.lat, v.lng, v.url_stream_pantalla, v.url_stream_trafico,
                 v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta,
                 v.mostrar_precio_cliente, p.nombre AS provincia
          FROM vallas v
          LEFT JOIN provincias p ON p.id=v.provincia_id
          WHERE v.id=:id AND v.visible_publico=1 AND v.estado_valla='activa' LIMIT 1";
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
      'provincia'=>$r['provincia'],
      'ubicacion'=>$r['ubicacion'],
      'zona'=>$r['zona'],
      'medida'=>$r['medida'],
      'precio'=> isset($r['precio']) ? (float)$r['precio'] : 0.0,
      'mostrar_precio'=>(int)($r['mostrar_precio_cliente'] ?? 1),
      'disponible'=>(int)$r['disponible'],
      'stream'=>$r['url_stream_pantalla'] ?? '',
      'trafico'=>$r['url_stream_trafico'] ?? '',
      'gal'=>$gal,
      'lat'=>$r['lat'], 'lng'=>$r['lng'],
      'tipo'=>strtoupper($r['tipo'] ?? 'LED'),
    ];
  }
}catch(Throwable $e){}

if (!$vd){ http_response_code(404); echo '<h1 style="color:#fff;background:#111;padding:2rem;font-family:system-ui">No encontrado</h1>'; exit; }

/* Alcance últimos 14 días para esta valla (web_analytics.path LIKE /detalles-led?...id=) */
$labels = []; $series = [];
try{
  $pdo = db();
  $end = (int)date('Ymd');
  $start = (int)date('Ymd', strtotime('-13 days'));
  $p = "%/detalles-led%id={$vd['id']}%";
  $q = $pdo->prepare("SELECT ymd, COUNT(*) c FROM web_analytics
                      WHERE ymd BETWEEN :s AND :e AND path LIKE :p
                      GROUP BY ymd ORDER BY ymd");
  $q->execute([':s'=>$start, ':e'=>$end, ':p'=>$p]);
  $rows = $q->fetchAll(PDO::FETCH_KEY_PAIR);

  for ($d = strtotime('-13 days'); $d <= time(); $d = strtotime('+1 day',$d)) {
    $labels[] = date('d/m',$d);
    $series[] = (int)($rows[(int)date('Ymd',$d)] ?? 0);
  }
} catch(Throwable $e){}

/* Título para header principal */
$page_title = h($vd['nombre']).' | LED en vivo';

/* Header común */
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) { include $__header; }

/* Body-level tracking */
if (function_exists('tracking_body')) tracking_body();
?>
<!-- Tailwind + Chart.js -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

<style>
  html,body{overflow-x:hidden}
  body{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif}
  .thumbnail-active{outline:2px solid #38bdf8; outline-offset:2px}
</style>

<div class="container mx-auto p-4 md:p-8 text-slate-300">
  <!-- Header local -->
  <header class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
    <div>
      <h1 class="text-3xl md:text-4xl font-bold text-white"><?=h($vd['nombre'])?></h1>
      <p class="text-slate-400">ID del Activo: <?= (int)$vd['id'] ?></p>
    </div>
    <div class="flex items-center gap-3">
      <a href="/calendario/?id=<?= (int)$vd['id'] ?>" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
        Calendario
      </a>
      <a href="/carritos/?a=add&id=<?= (int)$vd['id'] ?>" class="flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 px-5 rounded-lg transition-transform hover:scale-105 shadow-lg shadow-sky-600/30">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.16"/></svg>
        Agregar al Carrito
      </a>
    </div>
  </header>

  <!-- Grid principal -->
  <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Columna izquierda -->
    <div class="lg:col-span-2 space-y-8">
      <!-- Doble stream -->
      <div class="bg-slate-800 p-4 md:p-6 rounded-xl border border-slate-700">
        <h3 class="text-xl font-bold text-white mb-4">Vistas en Vivo</h3>
        <div id="stream-container" data-expanded="none" class="grid grid-cols-1 md:grid-cols-2 gap-4 transition-all duration-500 ease-in-out">
          <!-- Pantalla -->
          <?php if (!empty($vd['stream'])): ?>
          <div id="screen-view" class="relative aspect-video bg-black rounded-lg overflow-hidden border border-slate-600 group transition-all duration-500">
            <iframe src="<?=h($vd['stream'])?>" class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
            <div class="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-1 rounded-md font-semibold flex items-center gap-1.5">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
              </span>
              Pantalla
            </div>
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
              <button class="expand-btn p-3 bg-white/10 hover:bg-white/20 rounded-full text-white" data-target="screen-view" title="Expandir/Contraer Vista">
                <svg class="expand-icon w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                <svg class="compress-icon w-6 h-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5M15 15l5.25 5.25"/></svg>
              </button>
            </div>
          </div>
          <?php endif; ?>

          <!-- Tráfico -->
          <?php if (!empty($vd['trafico'])): ?>
          <div id="traffic-view" class="relative aspect-video bg-black rounded-lg overflow-hidden border border-slate-600 group transition-all duration-500">
            <iframe src="<?=h($vd['trafico'])?>" class="w-full h-full" frameborder="0" allowfullscreen loading="lazy"></iframe>
            <div class="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-1 rounded-md font-semibold flex items-center gap-1.5">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-sky-500"></span>
              </span>
              Tráfico
            </div>
            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
              <button class="expand-btn p-3 bg-white/10 hover:bg-white/20 rounded-full text-white" data-target="traffic-view" title="Expandir/Contraer Vista">
                <svg class="expand-icon w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                <svg class="compress-icon w-6 h-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5M15 15l5.25 5.25"/></svg>
              </button>
            </div>
          </div>
          <?php endif; ?>

          <?php if (empty($vd['stream']) && empty($vd['trafico'])): ?>
            <div class="text-slate-400">Sin streams disponibles.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Galería dinámica -->
      <?php if (!empty($vd['gal'])): ?>
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700">
        <h3 class="text-xl font-bold text-white mb-4">Galería de Ubicación</h3>
        <div class="mb-4 rounded-lg overflow-hidden">
          <img id="main-gallery-image" src="<?=h($vd['gal'][0])?>" alt="Vista principal" class="w-full h-auto object-cover transition-opacity duration-300">
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
          <?php foreach ($vd['gal'] as $idx=>$g): ?>
            <img src="<?=h($g)?>" alt="Foto <?= $idx+1 ?>" class="gallery-thumbnail cursor-pointer rounded-md hover:opacity-80 transition-opacity <?= $idx===0?'thumbnail-active':'' ?>">
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Columna derecha -->
    <aside class="lg:col-span-1 space-y-8">
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 sticky top-8">
        <div class="flex items-center gap-2 <?= $vd['disponible']? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30':'bg-rose-500/10 text-rose-300 border-rose-500/30' ?> border text-sm font-semibold px-3 py-1 rounded-full mb-4 w-fit">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
          <?= $vd['disponible'] ? 'Disponible Ahora' : 'Ocupado' ?>
        </div>

        <h2 class="text-2xl font-bold text-white mb-4">Detalles</h2>
        <ul class="space-y-4 text-slate-300">
          <li class="flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400 mt-1 flex-shrink-0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <div>
              <span class="font-semibold text-white"><?=h($vd['ubicacion'])?></span>
              <p class="text-sm text-slate-400"><?=h($vd['zona'])?> · <?=h($vd['provincia'])?></p>
            </div>
          </li>
          <li class="flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400 flex-shrink-0"><rect width="20" height="15" x="2" y="3" rx="2"/><path d="M7 21h10"/></svg>
            <span class="font-semibold text-white"><?=h($vd['tipo'])?></span>
          </li>
          <?php if(!empty($vd['medida'])): ?>
          <li class="flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-400 flex-shrink-0"><path d="M21 3H3v18h18V3zM15 9l-3 3-3-3"/></svg>
            <span class="font-semibold text-white"><?=h($vd['medida'])?></span>
          </li>
          <?php endif; ?>
        </ul>

        <!-- Precio con política -->
        <?php if ($vd['mostrar_precio'] && $vd['precio'] > 0): ?>
          <div class="my-6">
            <p class="text-sm text-slate-400">Precio por día</p>
            <p class="text-4xl font-bold text-white">RD$ <?= number_format($vd['precio'],0,',','.') ?></p>
          </div>
        <?php else: ?>
          <div class="my-6">
            <p class="text-sm text-slate-400">Precio</p>
            <p class="text-lg font-semibold text-slate-300">Contáctanos para cotización</p>
          </div>
        <?php endif; ?>

        <!-- Botón acción -->
        <div class="mb-6">
          <a href="/carritos/?a=add&id=<?= (int)$vd['id'] ?>" class="flex w-full items-center justify-center gap-3 bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 px-5 rounded-lg transition-transform hover:scale-105 shadow-lg shadow-sky-600/30">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.16"/></svg>
            Agregar al Carrito
          </a>
        </div>

        <!-- Mini chart -->
        <div class="bg-slate-900/50 p-4 rounded-lg border border-slate-700">
          <p class="font-semibold text-white mb-2">Alcance (últimos 14 días)</p>
          <div class="h-32"><canvas id="reachChart"></canvas></div>
        </div>
      </div>
    </aside>
  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Galería
  const mainImage = document.getElementById('main-gallery-image');
  const thumbs = document.querySelectorAll('.gallery-thumbnail');
  if (mainImage && thumbs.length){
    thumbs.forEach(t=>{
      t.addEventListener('click', function(){
        mainImage.style.opacity = 0;
        setTimeout(()=>{ mainImage.src = this.src; mainImage.style.opacity = 1; }, 200);
        thumbs.forEach(x=>x.classList.remove('thumbnail-active'));
        this.classList.add('thumbnail-active');
      });
    });
  }

  // Expandir/contraer streams
  const streamContainer = document.getElementById('stream-container');
  const screenView = document.getElementById('screen-view');
  const trafficView = document.getElementById('traffic-view');
  document.querySelectorAll('.expand-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const targetId = btn.dataset.target;
      const expanded = streamContainer.dataset.expanded;
      const resetIcons = () => {
        document.querySelectorAll('.expand-btn').forEach(b=>{
          b.querySelector('.expand-icon')?.classList.remove('hidden');
          b.querySelector('.compress-icon')?.classList.add('hidden');
        });
      };
      if (expanded === targetId){
        streamContainer.dataset.expanded = 'none';
        streamContainer.classList.remove('grid-cols-1');
        streamContainer.classList.add('md:grid-cols-2');
        screenView?.classList.remove('hidden');
        trafficView?.classList.remove('hidden');
        resetIcons();
      }else{
        streamContainer.dataset.expanded = targetId;
        streamContainer.classList.remove('md:grid-cols-2');
        streamContainer.classList.add('grid-cols-1');
        resetIcons();
        btn.querySelector('.expand-icon')?.classList.add('hidden');
        btn.querySelector('.compress-icon')?.classList.remove('hidden');
        if (targetId === 'screen-view'){
          trafficView?.classList.add('hidden'); screenView?.classList.remove('hidden');
        }else{
          screenView?.classList.add('hidden'); trafficView?.classList.remove('hidden');
        }
      }
    });
  });

  // Chart
  const el = document.getElementById('reachChart');
  if (el && window.Chart){
    const ctx = el.getContext('2d');
    const labels = <?= json_encode($labels, JSON_UNESCAPED_SLASHES) ?>;
    const data   = <?= json_encode($series, JSON_UNESCAPED_SLASHES) ?>;
    const grad = ctx.createLinearGradient(0,0,0,128);
    grad.addColorStop(0,'rgba(56,189,248,.4)');
    grad.addColorStop(1,'rgba(56,189,248,0)');
    new Chart(ctx,{
      type:'line',
      data:{ labels, datasets:[{ label:'Visitas', data, fill:true, backgroundColor:grad, borderColor:'rgba(56,189,248,1)', borderWidth:2, pointRadius:0, tension:.35 }]},
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:250},
        plugins:{ legend:{display:false}, tooltip:{intersect:false, mode:'index', backgroundColor:'#1e293b', titleColor:'#f8fafc', bodyColor:'#cbd5e1', callbacks:{label:c=>` ${c.parsed.y} visitas`}}},
        scales:{ x:{grid:{display:false}, ticks:{color:'#64748b'}}, y:{beginAtZero:true, ticks:{precision:0, color:'#64748b'}, grid:{color:'rgba(100,116,139,.15)'}}}
      }
    });
  }
});
</script>

<?php
/* Footer: desactiva forma decorativa */
$show_footer_shape = false;
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

/* Conteo de visita */
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/detalles-led');
}

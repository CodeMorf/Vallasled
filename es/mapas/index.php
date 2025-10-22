<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/mapas/index.php
 * Mapa interactivo con filtros (En vivo / Con imagen) y clustering.
 * Datos: /api/buscador.php
 * Opcional: /config/db.php, /config/media.php, /partials/{header,footer}.php, /config/tracking.php
 */

// DB opcional
$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; }

// Media opcional (para base de uploads)
$__media = __DIR__ . '/../../config/media.php';
if (file_exists($__media)) { require_once $__media; }

// Tracking opcional
$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

/** Descubre base de uploads igual que en catálogo */
function __uploads_base(): string {
  if (function_exists('media_base'))   { $b = (string)media_base();   if ($b) return rtrim($b,'/').'/'; }
  if (function_exists('uploads_base')) { $b = (string)uploads_base(); if ($b) return rtrim($b,'/').'/'; }
  if (defined('MEDIA_UPLOADS_BASE'))   { $b = (string)constant('MEDIA_UPLOADS_BASE'); if ($b) return rtrim($b,'/').'/'; }
  if (defined('UPLOADS_BASE'))         { $b = (string)constant('UPLOADS_BASE');       if ($b) return rtrim($b,'/').'/'; }
  return 'https://auth.vallasled.com/uploads/';
}
$__UPLOADS_BASE = __uploads_base();

// SEO
$__seo = [
  'title'       => 'Mapa de Vallas | VallasLED',
  'description' => 'Explora el mapa con todas las vallas: puntos en vivo y con imagen. Abre la ficha, revisa disponibilidad y agrega al carrito.',
  'og_type'     => 'website',
];

// Head injector
function __inject_head_map(string $html, array $overrides): string {
  $head  = '';
  if (function_exists('seo_page') && function_exists('seo_head')) {
    $head .= seo_head(seo_page($overrides));
  } else {
    $head .= '<title>'.h($overrides['title']).'</title><meta name="description" content="'.h($overrides['description']).'">';
  }
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
  $head .= '<link rel="icon" href="/asset/icono/vallasled-icon-v1-64.png">' . "\n";
  $head .= '<link rel="sitemap" type="application/xml" href="'.h((function_exists('base_url')?base_url():'/')).'/sitemap.xml">' . "\n";
  $head .= <<<CSS
<style>
  :root{
    --bg:#0b1220; --panel:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --border:#1f2937;
    --blue:#3b82f6; --green:#10b981; --amber:#f59e0b; --rose:#ef4444; --primary:#0ea5e9; --primary-600:#0284c7;
  }
  *{box-sizing:border-box}
  html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
  a{color:#93c5fd;text-decoration:none}
  .wrap{min-height:100vh;display:flex;flex-direction:column}
  .masthead{position:relative;z-index:10;background:linear-gradient(180deg,rgba(2,6,23,.9),rgba(2,6,23,.65));border-bottom:1px solid var(--border)}
  .container{max-width:96rem;margin:0 auto;padding:0 1rem}
  .masthead-inner{display:flex;align-items:center;gap:.9rem;padding:.8rem 0}
  .brand{display:flex;align-items:center;gap:.6rem}
  .brand img{width:32px;height:32px;border-radius:.4rem;display:block}
  h1{font-size:1.25rem;line-height:1.25;margin:0;font-weight:900}
  .desc{margin:.15rem 0 0;color:var(--muted);font-size:.92rem}
  .mapbox{position:relative;flex:1}
  #map{position:absolute;inset:0}
  .ui{
    position:absolute;top:.75rem;left:.75rem;z-index:1000;display:flex;gap:.5rem;flex-wrap:wrap
  }
  .card{
    background:linear-gradient(180deg,rgba(2,6,23,.9),rgba(2,6,23,.7));backdrop-filter:blur(6px);
    border:1px solid var(--border);border-radius:.75rem;padding:.6rem .7rem
  }
  .filters{display:flex;align-items:center;gap:.6rem}
  .chip{display:inline-flex;align-items:center;gap:.45rem;padding:.38rem .7rem;border-radius:9999px;background:#0b1220;border:1px solid #334155;color:#cbd5e1;font-weight:800;font-size:.82rem;cursor:pointer}
  .chip input{accent-color:var(--primary)}
  .legend{display:flex;align-items:center;gap:.7rem;font-size:.82rem;color:#cbd5e1}
  .lg{display:inline-flex;align-items:center;gap:.35rem}
  .dot{width:.7rem;height:.7rem;border-radius:9999px;display:inline-block}
  .d-live{background:var(--rose)}
  .d-img{background:var(--blue)}
  .d-oth{background:var(--green)}
  .leaflet-popup-content-wrapper{background:var(--panel);color:var(--text);border-radius:.6rem}
  .leaflet-popup-tip{background:var(--panel)}
  .popup{display:grid;grid-template-columns:120px 1fr;gap:.8rem;max-width:420px}
  .vgal{position:relative}
  .vgal img{width:120px;height:90px;object-fit:cover;border-radius:.4rem;border:1px solid rgba(255,255,255,.08)}
  .thumbs{display:flex;gap:.3rem;margin-top:.35rem;flex-wrap:wrap}
  .thumbs img{width:32px;height:24px;object-fit:cover;border-radius:.25rem;border:1px solid #334155;opacity:.9;cursor:pointer}
  .thumbs img.active{outline:2px solid var(--blue);opacity:1}
  .popup h3{margin:.1rem 0;font-size:1rem;line-height:1.25}
  .meta{font-size:.82rem;color:#cbd5e1}
  .badges{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.35rem}
  .badge{padding:.15rem .5rem;border-radius:9999px;font-weight:900;font-size:.72rem;border:1px solid rgba(255,255,255,.12)}
  .live{background:rgba(239,68,68,.15);color:#fecaca;border-color:rgba(239,68,68,.35)}
  .led{background:rgba(59,130,246,.15);color:#93c5fd}
  .imp{background:rgba(16,185,129,.15);color:#86efac}
  .mov{background:rgba(245,158,11,.15);color:#fde68a}
  .veh{background:rgba(244,63,94,.15);color:#fecdd3}
  /* marker live pulse */
  .pulse{
    background:var(--rose); width:14px; height:14px; border-radius:9999px; box-shadow:0 0 0 rgba(239,68,68,.6);
    animation:pulse 1.6s infinite; border:2px solid #fff
  }
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.6)}70%{box-shadow:0 0 0 12px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
  .footer{background:linear-gradient(0deg,rgba(2,6,23,.9),rgba(2,6,23,.65));border-top:1px solid var(--border);padding:.6rem 0;font-size:.9rem}
  .footer-inner{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
  .footer .left{display:flex;align-items:center;gap:.55rem;color:var(--muted)}
  .footer .left img{width:20px;height:20px;border-radius:.3rem}
  .footer nav{display:flex;gap:.9rem;flex-wrap:wrap}
  .pill{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .55rem;border-radius:9999px;background:#0b1220;border:1px solid #334155;color:#cbd5e1;font-weight:700;font-size:.82rem}
</style>
CSS;
  $head .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">'."\n";
  $head .= '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">'."\n";
  $head .= '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">'."\n";
  return preg_replace('~</head>~i', $head . '</head>', $html, 1) ?: ($head . $html);
}

// Header global si existe
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_map($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_map("</head>", $__seo);
  echo "<body>";
}

// Body tracking
if (function_exists('tracking_body')) tracking_body();

$logo = '/asset/icono/vallasled-icon-v1-64.png';
?>
<div class="wrap">
  <!-- Header interno -->
  <header class="masthead" role="banner" aria-label="Encabezado de mapas">
    <div class="container">
      <div class="masthead-inner">
        <div class="brand">
          <img src="<?=h($logo)?>" alt="VallasLED">
          <div>
            <h1>Mapa de Vallas</h1>
            <p class="desc">En vivo y con imagen. Pulsa un punto para ver previa, estado y accesos rápidos.</p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Panel UI encima del mapa -->
  <div class="mapbox">
    <div class="ui">
      <div class="card filters" role="group" aria-label="Filtros de mapa">
        <label class="chip"><input id="f-live" type="checkbox" checked> En vivo</label>
        <label class="chip"><input id="f-img"  type="checkbox" checked> Con imagen</label>
        <label class="chip"><input id="f-oth"  type="checkbox" checked> Otros</label>
      </div>
      <div class="card legend" aria-hidden="true">
        <span class="lg"><span class="dot d-live"></span>En vivo</span>
        <span class="lg"><span class="dot d-img"></span>Con imagen</span>
        <span class="lg"><span class="dot d-oth"></span>Otros</span>
      </div>
    </div>
    <div id="map" role="region" aria-label="Mapa de vallas"></div>
  </div>

  <!-- Footer interno -->
  <footer class="footer" role="contentinfo" aria-label="Pie de mapas">
    <div class="container">
      <div class="footer-inner">
        <div class="left">
          <img src="<?=h($logo)?>" alt="VallasLED"><span>Explora y abre detalles o agrega al carrito.</span>
        </div>
        <nav>
          <a class="pill" href="/es/catalogo/">Catálogo</a>
          <a class="pill" href="/detalles-led/">LED</a>
          <a class="pill" href="/detalles-vallas/">Impresas</a>
        </nav>
      </div>
    </div>
  </footer>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function(){
  const UPLOADS_BASE = <?=json_encode($__UPLOADS_BASE)?>;

  // Mapa base
  const tileUrl = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
  const map = L.map('map').setView([18.7357, -70.1627], 8);
  L.tileLayer(tileUrl, {
    attribution: '&copy; OSM &copy; CARTO', subdomains: 'abcd', maxZoom: 20
  }).addTo(map);

  // Clusters por tipo visual
  const clusters = {
    live: L.markerClusterGroup({ disableClusteringAtZoom: 16 }),
    img:  L.markerClusterGroup({ disableClusteringAtZoom: 16 }),
    oth:  L.markerClusterGroup({ disableClusteringAtZoom: 16 }),
  };
  clusters.live.addTo(map);
  clusters.img.addTo(map);
  clusters.oth.addTo(map);

  // Iconos
  const ImgIcon = L.divIcon({ className:'', html:'<div style="width:14px;height:14px;border-radius:9999px;background:#3b82f6;border:2px solid #fff"></div>', iconSize:[14,14], iconAnchor:[7,7] });
  const OthIcon = L.divIcon({ className:'', html:'<div style="width:14px;height:14px;border-radius:9999px;background:#10b981;border:2px solid #fff"></div>', iconSize:[14,14], iconAnchor:[7,7] });
  const LiveIcon= L.divIcon({ className:'', html:'<div class="pulse"></div>', iconSize:[14,14], iconAnchor:[7,7] });

  // Utilidades
  const esc = s => (s==null?'':String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;'));

  function resolveImg(u){
    if (!u) return '';
    if (/^https?:\/\//i.test(u)) return u;
    const name = String(u).split('/').pop();
    return UPLOADS_BASE + name.replace(/^\/+/, '');
  }

  function mediaList(v){
    // Acepta v.media[] o campos imagenX sueltos
    const list = [];
    if (Array.isArray(v.media)) {
      v.media.forEach(m => { if (m && m.url) list.push(resolveImg(m.url)); });
    }
    ['imagen','imagen_previa','imagen1','imagen2','imagen_tercera','imagen_cuarta'].forEach(k=>{
      if (v[k]) list.push(resolveImg(v[k]));
    });
    return Array.from(new Set(list.filter(Boolean)));
  }

  function popupHTML(v){
    const imgs = mediaList(v);
    const tipo = (v.tipo||'').toString().toLowerCase();
    const prov = v.provincia || v.provincia_nombre || v.zona || '';
    const live = parseInt(v.en_vivo?1:0,10)===1;
    const name = v.nombre || 'Valla';

    const bTipo = tipo==='led'?'led':(tipo==='impresa'?'imp':(tipo==='movilled'?'mov':'veh'));
    const first = imgs[0] || '';

    const thumbs = imgs.map((u,i)=>`<img ${i===0?'class="active"':''} data-url="${esc(u)}" src="${esc(u)}" alt="thumb">`).join('');

    return `
      <div class="popup">
        <div class="vgal">
          ${first ? `<img id="vgal-main-${esc(v.id||'0')}" src="${esc(first)}" alt="Previa">`
                  : `<div style="width:120px;height:90px;border:1px dashed rgba(255,255,255,.25);border-radius:.4rem;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.82rem">Sin imagen</div>`}
          ${imgs.length>1? `<div class="thumbs" data-main="vgal-main-${esc(v.id||'0')}">${thumbs}</div>` : ``}
        </div>
        <div>
          <h3>${esc(name)}</h3>
          <div class="meta">${esc(prov)} ${tipo? '· '+tipo.toUpperCase():''}</div>
          <div class="badges">
            <span class="badge ${bTipo}">${tipo? tipo.toUpperCase():'—'}</span>
            ${live? '<span class="badge live">EN VIVO</span>':''}
          </div>
          <div style="margin-top:.6rem;display:flex;gap:.45rem;flex-wrap:wrap">
            <a href="${tipo==='led'?'/detalles-led/?id='+(v.id||''): '/detalles-vallas/?id='+(v.id||'')}" target="_top" class="pill" style="background:#1f2937;border-color:#374151">Detalles</a>
            <a href="/carritos/?a=add&id=${encodeURIComponent(v.id||'')}" target="_top" class="pill">Agregar</a>
          </div>
        </div>
      </div>`;
  }

  function bindThumbs(container){
    const thumbs = container.querySelectorAll('.thumbs img');
    if (!thumbs.length) return;
    const mainId = container.querySelector('.thumbs').getAttribute('data-main');
    const mainEl = container.querySelector('#'+CSS.escape(mainId));
    thumbs.forEach(t=>{
      t.addEventListener('click', ()=>{
        thumbs.forEach(x=>x.classList.remove('active'));
        t.classList.add('active');
        if (mainEl) mainEl.src = t.getAttribute('data-url');
      });
    });
  }

  // Filtros
  const fLive = document.getElementById('f-live');
  const fImg  = document.getElementById('f-img');
  const fOth  = document.getElementById('f-oth');

  function applyVisibility(){
    if (fLive.checked) map.addLayer(clusters.live); else map.removeLayer(clusters.live);
    if (fImg.checked)  map.addLayer(clusters.img);  else map.removeLayer(clusters.img);
    if (fOth.checked)  map.addLayer(clusters.oth);  else map.removeLayer(clusters.oth);
  }
  [fLive,fImg,fOth].forEach(ch=>ch.addEventListener('change', applyVisibility));

  // Carga datos
  fetch('/api/buscador.php')
    .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(data=>{
      const items = data.items || data.data || data.results || [];
      const bounds = [];

      items.forEach(v=>{
        const lat = v.lat ?? v.latitude ?? v.Latitud ?? v.latitud;
        const lng = v.lng ?? v.longitude ?? v.Longitud ?? v.longitud;
        if (lat==null || lng==null) return;

        const live = parseInt(v.en_vivo?1:0,10)===1;
        const imgs = mediaList(v);
        const hasImg = imgs.length>0;

        const marker = L.marker([lat,lng], {
          icon: live ? LiveIcon : (hasImg ? ImgIcon : OthIcon)
        }).bindPopup(popupHTML(v));

        marker.on('popupopen', (e)=>{ bindThumbs(e.popup._contentNode); });

        if (live) clusters.live.addLayer(marker);
        else if (hasImg) clusters.img.addLayer(marker);
        else clusters.oth.addLayer(marker);

        bounds.push([lat,lng]);
      });

      applyVisibility();
      if (bounds.length) map.fitBounds(bounds, { padding:[30,30] });
    })
    .catch(()=>{/* silencioso */});

})();
</script>

<?php
// Footer global
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

// Pageview
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/mapas');
}

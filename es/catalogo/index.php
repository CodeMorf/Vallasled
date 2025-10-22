<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/**
 * /es/catalogo/index.php
 * Catálogo con filtros + grid + carrito.
 * Backend: /api/buscador.php  (lista con media[].url e imagen)
 * Integración: /config/media.php para base de uploads.
 */

// DB + SEO
$__db = __DIR__ . '/../../config/db.php';
if (file_exists($__db)) { require_once $__db; } // expone db(), db_setting(), base_url(), seo_*

// Tracking
$__trk = __DIR__ . '/../../config/tracking.php';
if (file_exists($__trk)) { require_once $__trk; }

// Media (base de uploads y utilidades)
$__media = __DIR__ . '/../../config/media.php';
if (file_exists($__media)) { require_once $__media; }

/**
 * Descubre la base de uploads desde media.php si existe.
 * Acepta cualquiera de estos contratos:
 *  - function media_base(): string
 *  - function uploads_base(): string
 *  - const MEDIA_UPLOADS_BASE
 *  - const UPLOADS_BASE
 */
function __uploads_base(): string {
  if (function_exists('media_base'))   { $b = (string)media_base(); if ($b) return rtrim($b,'/').'/'; }
  if (function_exists('uploads_base')) { $b = (string)uploads_base(); if ($b) return rtrim($b,'/').'/'; }
  if (defined('MEDIA_UPLOADS_BASE'))   { $b = (string)constant('MEDIA_UPLOADS_BASE'); if ($b) return rtrim($b,'/').'/'; }
  if (defined('UPLOADS_BASE'))         { $b = (string)constant('UPLOADS_BASE'); if ($b) return rtrim($b,'/').'/'; }
  return 'https://auth.vallasled.com/uploads/'; // fallback
}
$__UPLOADS_BASE = __uploads_base();

if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

// SEO overrides
$__seo = [
  'title'       => 'Catálogo de Vallas Publicitarias | VallasLED',
  'description' => 'Explora el catálogo completo de vallas. Filtra por tipo, ubicación y disponibilidad para encontrar el espacio ideal en República Dominicana.',
  'og_type'     => 'website',
];

// Inyección de <head>
function __inject_head_catalog(string $html, array $overrides): string {
  $head  = '';
  if (function_exists('seo_page') && function_exists('seo_head')) {
    $head .= seo_head(seo_page($overrides));
  } else {
    $head .= '<title>'.h($overrides['title']).'</title><meta name="description" content="'.h($overrides['description']).'">';
  }
  $head .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
  $head .= '<link rel="sitemap" type="application/xml" href="'.h((function_exists('base_url')?base_url():'/')).'/sitemap.xml">' . "\n";
  $head .= <<<CSS
<style>
  :root{
    --bg:#0b1220; --bg-soft:#0f172a; --border:#334155; --muted:#94a3b8;
    --text:#e2e8f0; --primary:#0ea5e9; --primary-600:#0284c7; --ring:rgba(14,165,233,.35)
  }
  *{box-sizing:border-box}
  html,body{background:var(--bg); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial}
  a{color:#93c5fd; text-decoration:none}
  .container{max-width:90rem;margin:0 auto;padding:1rem}
  .grid{display:grid}.gap-6{gap:1.5rem}.gap-8{gap:2rem}
  .layout{grid-template-columns:320px 1fr}
  .sidebar{background:linear-gradient(180deg, rgba(30,41,59,.6), rgba(2,6,23,.6));border:1px solid var(--border);border-radius:1rem;padding:1.25rem;backdrop-filter:blur(6px)}
  .sidebar h2{font-size:1.1rem;margin:0 0 .75rem 0}
  .sidebar .section{margin-bottom:1rem}
  .input, .select{width:100%;background:#0b1220;border:1px solid #475569;border-radius:.65rem;color:var(--text);padding:.6rem .7rem;outline:none}
  .input:focus, .select:focus{box-shadow:0 0 0 4px var(--ring); border-color:#60a5fa}
  .chip{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .6rem;border:1px solid #475569;border-radius:9999px;background:#0b1220}
  .chip input{accent-color:var(--primary)}
  .toolbar{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin:.5rem 0 1rem}
  .toolbar .right{margin-left:auto;display:flex;gap:.5rem;align-items:center}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.6rem .95rem;border-radius:.7rem;font-weight:700;font-size:.9rem;border:1px solid transparent;cursor:pointer;transition:transform .05s,background-color .2s,opacity .2s}
  .btn:active{transform:translateY(1px)}
  .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-600)}
  .btn-muted{background:#0b1220;border:1px solid #475569;color:var(--text)} .btn-muted:hover{background:#142033}
  .btn-cart{background:#f59e0b;color:#111827}.btn-cart:hover{background:#d97706}
  .badge{display:inline-flex;align-items:center;border-radius:9999px;font-size:.7rem;font-weight:800;letter-spacing:.04em;padding:.2rem .55rem}
  .badge-green{background:rgba(34,197,94,.12);color:#bbf7d0;border:1px solid rgba(34,197,94,.35)}
  .badge-red{background:rgba(239,68,68,.12);color:#fecaca;border:1px solid rgba(239,68,68,.35)}
  .card{border:1px solid var(--border);border-radius:1rem;overflow:hidden;background:radial-gradient(1200px 200px at 50% -20%, rgba(14,165,233,.08), transparent 40%), var(--bg-soft);transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column}
  .card:hover{transform:translateY(-4px);box-shadow:0 16px 28px rgba(0,0,0,.35)}
  .figure{position:relative;background:#0b1220}
  .figure img{width:100%;height:auto;display:block;aspect-ratio:16/10;object-fit:cover;transform:scale(1);transition:transform .35s ease}
  .card:hover .figure img{transform:scale(1.03)}
  .thumbs{position:absolute;left:.6rem;right:.6rem;bottom:.6rem;display:flex;gap:.35rem;overflow-x:auto;padding:.25rem .2rem;background:rgba(2,6,23,.35);backdrop-filter:blur(4px);border-radius:.5rem}
  .thumbs::-webkit-scrollbar{height:6px}.thumbs::-webkit-scrollbar-thumb{background:#334155;border-radius:9999px}
  .thumb{width:44px;height:32px;border:1px solid #475569;border-radius:.35rem;overflow:hidden;cursor:pointer;flex:0 0 auto;opacity:.8}
  .thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .thumb.active{outline:2px solid var(--primary);opacity:1}
  .card-body{padding:1rem;display:flex;flex-direction:column;gap:.35rem;flex:1}
  .title{margin:0;font-weight:800;font-size:1.06rem}
  .meta{opacity:.8}
  .card-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem;margin-top:.6rem}
  .page{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;justify-content:center;margin-top:1.25rem}
  .page .btn-muted.active{background:var(--primary);border-color:var(--primary)}
  #mobile-filter-btn{display:none}
  .skeleton{animation:pulse 1.2s infinite ease-in-out;background:linear-gradient(90deg,#0b1220 25%,#0e1627 37%,#0b1220 63%);background-size:400% 100%}
  @keyframes pulse{0%{background-position:100% 0}100%{background-position:-100% 0}}
  @media (max-width: 1200px){ .layout{grid-template-columns:1fr} #mobile-filter-btn{display:inline-flex} #filters{display:none} #filters.open{display:block}}
</style>
CSS;
  return preg_replace('~</head>~i', $head . '</head>', $html, 1) ?: ($head . $html);
}

// Header
$__header = __DIR__ . '/../../partials/header.php';
if (file_exists($__header)) {
  ob_start(); include $__header; $hdr = ob_get_clean();
  echo __inject_head_catalog($hdr, $__seo);
} else {
  echo "<!doctype html><html lang=\"es\"><head>";
  echo __inject_head_catalog("</head>", $__seo);
  echo "<body>";
}

// Body tracking
if (function_exists('tracking_body')) tracking_body();
?>
<main>
  <div class="container">
    <header style="margin-bottom:1rem">
      <h1 style="font-size:2.25rem;line-height:1.2;font-weight:900;margin:0">Catálogo de Vallas</h1>
      <p style="color:var(--muted);margin:.35rem 0 0">Filtra por tipo, provincia, disponibilidad o busca por texto.</p>
      <div class="toolbar">
        <button id="mobile-filter-btn" class="btn btn-muted">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#e2e8f0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
          Filtros
        </button>
        <div class="right">
          <label style="display:flex;align-items:center;gap:.45rem">
            Ordenar
            <select id="sort" class="select" style="width:auto">
              <option value="recientes">Recientes</option>
              <option value="provincia">Provincia A–Z</option>
              <option value="tipo">Tipo</option>
              <option value="disp">Disponibles primero</option>
            </select>
          </label>
          <label style="display:flex;align-items:center;gap:.45rem">
            Mostrar
            <select id="pageSize" class="select" style="width:auto">
              <option>9</option><option selected>12</option><option>18</option><option>24</option>
            </select>
          </label>
        </div>
      </div>
    </header>

    <section class="layout grid gap-8">
      <!-- Sidebar filtros -->
      <aside id="filters" class="sidebar" aria-label="Filtros">
        <h2>Filtros</h2>

        <div class="section">
          <label for="q" style="display:block;margin-bottom:.35rem;color:#cbd5e1;font-weight:600">Buscar</label>
          <input id="q" class="input" type="text" placeholder="Ej: Churchill, 8x3, LED…">
        </div>

        <div class="section">
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <label class="chip"><input type="checkbox" class="f-tipo" value="led"> LED</label>
            <label class="chip"><input type="checkbox" class="f-tipo" value="impresa"> Impresa</label>
            <label class="chip"><input type="checkbox" class="f-tipo" value="movilled"> Móvil LED</label>
            <label class="chip"><input type="checkbox" class="f-tipo" value="vehiculo"> Vehículos</label>
          </div>
        </div>

        <div class="section">
          <label for="prov" style="display:block;margin-bottom:.35rem;color:#cbd5e1;font-weight:600">Provincia</label>
          <select id="prov" class="select">
            <option value="">Todas</option>
          </select>
        </div>

        <div class="section">
          <label for="disp" style="display:block;margin-bottom:.35rem;color:#cbd5e1;font-weight:600">Disponibilidad</label>
          <select id="disp" class="select">
            <option value="">Todas</option>
            <option value="1">Disponible</option>
            <option value="0">Ocupado</option>
          </select>
        </div>

        <div class="section" style="display:flex;gap:.5rem">
          <button id="apply" class="btn btn-primary" type="button">Aplicar</button>
          <button id="clear" class="btn btn-muted" type="button">Limpiar</button>
        </div>
      </aside>

      <!-- Main grid -->
      <div>
        <div id="grid" class="grid gap-8" style="grid-template-columns:repeat(1,minmax(0,1fr))"></div>
        <nav id="pager" class="page" aria-label="Paginación"></nav>
      </div>
    </section>
  </div>
</main>

<script>
(function(){
  const grid   = document.getElementById('grid');
  const pager  = document.getElementById('pager');
  const qEl    = document.getElementById('q');
  const provEl = document.getElementById('prov');
  const dispEl = document.getElementById('disp');
  const tiposCk= Array.from(document.querySelectorAll('.f-tipo'));
  const apply  = document.getElementById('apply');
  const clear  = document.getElementById('clear');
  const pageSizeEl = document.getElementById('pageSize');
  const sortEl = document.getElementById('sort');
  const mobileBtn  = document.getElementById('mobile-filter-btn');
  const filtersBox = document.getElementById('filters');

  // Base de uploads entregada por PHP (/config/media.php)
  const UPLOADS_BASE = <?php echo json_encode($__UPLOADS_BASE, JSON_UNESCAPED_SLASHES); ?>;

  let page = 1;
  let pageSize = parseInt(pageSizeEl.value,10) || 12;
  let total = 0;
  let items = [];

  function esc(v){ if(v==null) return ''; return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  function dedup(arr){ return [...new Set(arr.filter(Boolean))]; }

  function normalizeUrl(u){
    if (!u) return '';
    if (/^https?:\/\//i.test(u)) return u;
    const name = String(u).split('/').pop();
    return (UPLOADS_BASE + name.replace(/^\/+/, ''));
  }

  function tiposSeleccionados(){
    return tiposCk.filter(x=>x.checked).map(x=>x.value).join(',');
  }

  function imagesFrom(item){
    const fromMedia = Array.isArray(item.media)
      ? item.media.filter(m => (m.tipo||'').toLowerCase()==='foto' && m.url).map(m => normalizeUrl(m.url))
      : [];
    const fromImagen = item.imagen ? [normalizeUrl(item.imagen)] : [];
    return dedup([...fromMedia, ...fromImagen]);
  }

  async function fetchList(){
    const params = new URLSearchParams({
      q: (qEl.value||'').trim(),
      provincia: provEl.value || '',
      disp: dispEl.value || '',
      tipos: tiposSeleccionados(),
      page: String(page),
      pageSize: String(pageSize),
      sort: sortEl.value || 'recientes'
    });
    const r = await fetch(`/api/buscador.php?${params.toString()}`, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
    if (!r.ok) throw new Error('HTTP '+r.status);
    const j = await r.json();
    if (!j || j.ok === false) throw new Error(j?.message || 'Error API');

    items = (Array.isArray(j.items) ? j.items : []).map(it => {
      const imgs = imagesFrom(it);
      it.images = imgs;
      it.imagen = imgs[0] || '';
      return it;
    });
    total = parseInt(j.total||0,10) || items.length;
  }

  function buildThumbsHTML(images, mainId){
    if (!Array.isArray(images) || images.length <= 1) return '';
    const thumbs = images.slice(0,8);
    return `
      <div class="thumbs" role="tablist" aria-label="Imágenes de la valla">
        ${thumbs.map((src,idx)=>`
          <button class="thumb ${idx===0?'active':''}" data-target="${esc(mainId)}" data-src="${esc(src)}" aria-label="Imagen ${idx+1}">
            <img src="${esc(src)}" loading="lazy" decoding="async" alt="">
          </button>
        `).join('')}
      </div>
    `;
  }

  function cardHTML(v, i){
    const disponible = parseInt(v.disponible ? 1 : 0);
    const tipo = (v.tipo||'').toUpperCase();
    const mainId = `img_${v.id}_${i}`;
    const imgMain = (Array.isArray(v.images) && v.images[0]) ? v.images[0] : '';
    const images = (Array.isArray(v.images) && v.images.length) ? v.images : (imgMain ? [imgMain] : []);
    return `
      <article class="card">
        <figure class="figure">
          <img id="${esc(mainId)}" src="${esc(imgMain)}" alt="Valla: ${esc(v.nombre||'')}" loading="lazy" decoding="async">
          ${buildThumbsHTML(images, mainId)}
        </figure>
        <div class="card-body">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
            <h3 class="title">${esc(v.nombre||'—')}</h3>
            <span class="badge ${disponible? 'badge-green':'badge-red'}">${disponible?'Disponible':'Ocupado'}</span>
          </div>
          <p class="meta">${esc(v.provincia||'')} · ${esc(tipo)}</p>
          <div class="card-actions">
            <a class="btn btn-muted" href="/detalles-${(v.tipo||'led').toLowerCase()==='led'?'led':'vallas'}/?id=${encodeURIComponent(v.id)}">Ver detalles</a>
            <button type="button" class="btn btn-cart js-add" data-id="${esc(v.id)}">Agregar</button>
          </div>
        </div>
      </article>
    `;
  }

  function bindThumbs(){
    grid.querySelectorAll('.thumb').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const target = btn.getAttribute('data-target');
        const src = btn.getAttribute('data-src');
        const img = document.getElementById(target);
        if (!img || !src) return;
        const parent = btn.parentElement;
        parent.querySelectorAll('.thumb').forEach(t=>t.classList.remove('active'));
        btn.classList.add('active');
        img.src = src;
      });
    });
  }

  function renderGrid(){
    const w = window.innerWidth;
    let cols = 1; if (w>=768) cols=2; if (w>=1200) cols=3;
    grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0,1fr))`;
    grid.innerHTML = items.length
      ? items.map(cardHTML).join('')
      : `<div style="color:#94a3b8">Sin resultados.</div>`;

    grid.querySelectorAll('.js-add').forEach(b=>{
      b.addEventListener('click', async ()=>{
        const id = b.getAttribute('data-id');
        try{
          const r = await fetch(`/carritos/?a=add&id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
          const j = await r.json();
          toast(j?.message || 'Agregado al carrito');
          updateCartCount();
        }catch(_){ toast('No se pudo agregar'); }
      });
    });

    bindThumbs();
  }

  function renderPager(){
    const pages = Math.max(1, Math.ceil((total||0) / pageSize));
    pager.innerHTML = '';
    if (pages <= 1) return;
    const mk = (lbl, fn, active=false, disabled=false)=>{
      const a = document.createElement('button');
      a.type='button'; a.className='btn btn-muted' + (active?' active':'');
      a.textContent = lbl; a.disabled = disabled;
      a.addEventListener('click', fn);
      return a;
    };
    pager.appendChild(mk('«', ()=>{ if(page>1){ page--; load(); } }, false, page===1));

    const win = 5;
    let s = Math.max(1, page - Math.floor(win/2));
    let e = Math.min(pages, s + win - 1);
    s = Math.max(1, e - win + 1);

    if (s>1) pager.appendChild(mk('1', ()=>{ page=1; load(); }));
    if (s>2) pager.appendChild(Object.assign(document.createElement('span'),{textContent:'…',style:'opacity:.7;padding:.35rem .5rem'}));
    for (let p=s;p<=e;p++) pager.appendChild(mk(String(p), ()=>{ page=p; load(); }, p===page));
    if (e<pages-1) pager.appendChild(Object.assign(document.createElement('span'),{textContent:'…',style:'opacity:.7;padding:.35rem .5rem'}));
    if (e<pages) pager.appendChild(mk(String(pages), ()=>{ page=pages; load(); }));
    pager.appendChild(mk('»', ()=>{ if(page<pages){ page++; load(); } }, false, page===pages));
  }

  function hydrateProvinciasFrom(itemsForFacets){
    const set = new Set();
    itemsForFacets.forEach(i => { if (i && i.provincia) set.add(i.provincia); });
    provEl.innerHTML = '<option value="">Todas</option>';
    Array.from(set).sort((a,b)=>a.localeCompare(b,'es')).forEach(o=>{
      const op = document.createElement('option');
      op.value = o; op.textContent = o;
      provEl.appendChild(op);
    });
  }

  async function loadFacetsOnce(){
    try{
      const params = new URLSearchParams({ q:'', provincia:'', disp:'', tipos:'', page:'1', pageSize:'500' });
      const r = await fetch(`/api/buscador.php?${params.toString()}`, { headers:{'X-Requested-With':'XMLHttpRequest'}});
      if (!r.ok) return;
      const j = await r.json();
      if (j && j.items) hydrateProvinciasFrom(j.items);
    }catch(_){}
  }

  function skeleton(n=9){
    grid.innerHTML = [...Array(n)].map(()=>(
      `<div class="card">
         <div class="figure"><div class="skeleton" style="width:100%;aspect-ratio:16/10"></div></div>
         <div class="card-body">
           <div class="skeleton" style="height:14px;border-radius:.3rem;width:70%"></div>
           <div class="skeleton" style="height:12px;border-radius:.3rem;width:40%;margin-top:.5rem"></div>
           <div class="card-actions" style="margin-top:.8rem">
             <div class="skeleton" style="height:38px;border-radius:.6rem"></div>
             <div class="skeleton" style="height:38px;border-radius:.6rem"></div>
           </div>
         </div>
       </div>`
    )).join('');
    pager.innerHTML = '';
  }

  async function load(){
    skeleton(pageSize);
    try{
      await fetchList();
      renderGrid();
      renderPager();
    }catch(e){
      grid.innerHTML = `<div style="color:#94a3b8">No fue posible cargar el catálogo.</div>`;
    }
  }

  function toast(txt){
    let t = document.getElementById('toast');
    if (!t) {
      t = document.createElement('div'); t.id='toast';
      t.style.position='fixed'; t.style.bottom='16px'; t.style.left='50%'; t.style.transform='translateX(-50%)';
      t.style.background='#0b1220'; t.style.border='1px solid #374151'; t.style.color='#e5e7eb';
      t.style.padding='10px 14px'; t.style.borderRadius='10px'; t.style.boxShadow='0 8px 20px rgba(0,0,0,.3)'; t.style.zIndex='9999';
      document.body.appendChild(t);
    }
    t.textContent = txt; t.style.opacity='1';
    clearTimeout(t._to); t._to = setTimeout(()=>{ t.style.opacity='0'; }, 1500);
  }

  function updateCartCount(){
    fetch('/carritos/?a=count',{headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.json()).then(j=>{
        const c=document.getElementById('cart-count'); if(c) c.textContent=String(j.count||0);
      }).catch(()=>{});
  }

  // Eventos
  apply.addEventListener('click', ()=>{ page=1; load(); });
  clear.addEventListener('click', ()=>{
    qEl.value=''; provEl.value=''; dispEl.value='';
    tiposCk.forEach(x=>x.checked=false);
    sortEl.value='recientes';
    page=1; load();
  });
  pageSizeEl.addEventListener('change', ()=>{ pageSize = parseInt(pageSizeEl.value,10)||12; page=1; load(); });
  sortEl.addEventListener('change', ()=>{ page=1; load(); });
  mobileBtn.addEventListener('click', ()=>{ filtersBox.classList.toggle('open'); });
  window.addEventListener('resize', ()=>renderGrid());
  qEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ page=1; load(); }});

  // Init
  loadFacetsOnce();
  load();
})();
</script>

<?php
// Footer
$__footer = __DIR__ . '/../../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

// Conteo de visita
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/es/catalogo');
}

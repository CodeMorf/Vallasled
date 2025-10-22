<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* === Config & tracking === */
$cfg = __DIR__ . '/../config/db.php';
$trk = __DIR__ . '/../config/tracking.php';
if (file_exists($cfg)) require $cfg;
if (file_exists($trk)) require $trk;

/* === Helpers === */
function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }

/* === Page title === */
$page_title = 'Vallas destacadas · Búsqueda por ID';

/* Header */
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) { include $__header; }

/* Tracking body-level */
if (function_exists('tracking_body')) tracking_body();
?>
<!-- Tailwind para UI moderna -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0b1220;--card:#111827;--muted:#9ca3af}
  body{font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:#e5e7eb}
  .chip{font-size:.70rem;padding:.20rem .50rem;border-radius:9999px}
  .chip-ok{background:#0ea5e91a;color:#93c5fd;border:1px solid #1d4ed8}
  .chip-err{background:#ef44441a;color:#fca5a5;border:1px solid #ef4444}
  .skeleton{background:linear-gradient(90deg,#1f2937 25%,#243244 37%,#1f2937 63%);background-size:400% 100%;animation:sheen 1.2s ease-in-out infinite}
  @keyframes sheen{0%{background-position:100% 0}100%{background-position:-100% 0}}
  .ring-focus{box-shadow:0 0 0 3px rgba(59,130,246,.4)}
</style>

<div class="container mx-auto px-4 md:px-8 py-6 max-w-7xl">
  <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl md:text-3xl font-extrabold text-white">Vallas destacadas</h1>
      <p class="text-sm text-slate-400">Explora, filtra y busca por <span class="font-semibold">ID de valla</span> o <span class="font-semibold">ID de destacado</span>.</p>
    </div>
    <a href="/" class="inline-flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2 px-4 rounded-lg">
      ← Volver al inicio
    </a>
  </header>

  <!-- Filtros -->
  <section class="bg-slate-800/70 border border-slate-700 rounded-2xl p-4 md:p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div class="md:col-span-2">
        <label class="block text-sm text-slate-400 mb-1" for="idq">Buscar por ID</label>
        <input id="idq" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 141 (valla) o 9 (destacado)"
               class="w-full bg-slate-900/60 border border-slate-700 focus:border-sky-500 rounded-lg px-3 py-2 outline-none text-slate-200">
      </div>
      <div>
        <label class="block text-sm text-slate-400 mb-1" for="estado">Estado</label>
        <select id="estado" class="w-full bg-slate-900/60 border border-slate-700 focus:border-sky-500 rounded-lg px-3 py-2 text-slate-200">
          <option value="">Todos</option>
          <option value="active" selected>Activos</option>
          <option value="expired">Expirados</option>
          <option value="pending">Pendientes</option>
        </select>
      </div>
      <div>
        <label class="block text-sm text-slate-400 mb-1" for="tipo">Tipo</label>
        <select id="tipo" class="w-full bg-slate-900/60 border border-slate-700 focus:border-sky-500 rounded-lg px-3 py-2 text-slate-200">
          <option value="">Todos</option>
          <option value="led">LED</option>
          <option value="impresa">Impresa</option>
          <option value="movilled">Móvil LED</option>
          <option value="vehiculo">Vehículo</option>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <button id="btnBuscar" class="flex-1 bg-sky-600 hover:bg-sky-500 text-white font-semibold py-2 px-4 rounded-lg">Buscar</button>
        <button id="btnLimpiar" class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold py-2 px-3 rounded-lg">Limpiar</button>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-3 mt-4 text-xs text-slate-400">
      <span>Atajos:</span>
      <button data-q="led" class="px-3 py-1 rounded-full border border-slate-600 hover:border-sky-500 hover:text-sky-300">LED</button>
      <button data-q="impresa" class="px-3 py-1 rounded-full border border-slate-600 hover:border-sky-500 hover:text-sky-300">Impresa</button>
      <button id="btnSoloActivos" class="px-3 py-1 rounded-full border border-slate-600 hover:border-sky-500 hover:text-sky-300">Solo activos</button>
      <span class="ml-auto text-slate-500">Resultados: <span id="res-total">0</span></span>
    </div>
  </section>

  <!-- Grid -->
  <section>
    <div id="grid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
    <div id="pager" class="mt-6 flex items-center gap-2 flex-wrap"></div>
  </section>
</div>

<script>
(function(){
  // ===== Utils =====
  const ph = 'https://placehold.co/640x640/0b1220/93c5fd?text=Valla';
  function esc(v){ if(v==null) return ''; return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  function normalizeImg(u){
    const base = 'https://auth.vallasled.com/uploads/';
    if (!u) return '';
    try{
      if (/^https?:\/\//i.test(u)) return u;
      u = String(u).replace(/^\/+/, '');
      if (u.startsWith('destacadas/')) u = u.replace(/^destacadas\//,'');
      if (u.startsWith('uploads/'))     u = u.replace(/^uploads\//,'');
      return base + u;
    }catch(_){ return u; }
  }
  function imgTag(url, alt){
    const src = normalizeImg(url) || ph;
    return `<img src="${esc(src)}" alt="${esc(alt||'')}" loading="lazy" decoding="async"
                class="w-full h-40 object-cover rounded-2xl"
                onerror="this.onerror=null;this.src='${ph}'">`;
  }
  function liveBadgeHTML(isLed){ return isLed ? `<span class="chip chip-ok">LIVE</span>` : ''; }
  function estadoChip(estado){
    if (!estado) return '';
    const ok = estado==='active';
    return `<span class="chip ${ok?'chip-ok':'chip-err'}">${ok?'Activo':'Expirado'}</span>`;
  }
  function disponibilidadChip(disp){
    const ok = parseInt(disp?1:0)===1;
    return `<span class="chip ${ok?'chip-ok':'chip-err'}">${ok?'Disponible':'Ocupado'}</span>`;
  }

  // ===== DOM =====
  const grid  = document.getElementById('grid');
  const pager = document.getElementById('pager');
  const totalSpan = document.getElementById('res-total');

  const idq   = document.getElementById('idq');
  const estado= document.getElementById('estado');
  const tipo  = document.getElementById('tipo');
  const btnBuscar  = document.getElementById('btnBuscar');
  const btnLimpiar = document.getElementById('btnLimpiar');
  const btnSoloActivos = document.getElementById('btnSoloActivos');

  // ===== State =====
  let all = [];     // wrappers {id, valla_id, estado, valla:{...}}
  let page = 1;
  let perPage = 24;

  // ===== Data =====
  async function loadDestacados(){
    // skeletons
    grid.innerHTML = Array.from({length:8}).map(()=>`
      <div class="rounded-2xl bg-slate-800 border border-slate-700 p-3">
        <div class="skeleton rounded-2xl h-40 w-full mb-3"></div>
        <div class="skeleton h-4 w-3/4 rounded mb-2"></div>
        <div class="skeleton h-3 w-1/2 rounded"></div>
      </div>`).join('');
    try{
      // Preferir per_page; fallback a limit
      const url = `/api/destacados/api.php?page=1&per_page=200`;
      const r = await fetch(url, {cache:'no-store'});
      if (!r.ok) throw new Error('HTTP '+r.status);
      const j = await r.json();
      const items = Array.isArray(j.items) ? j.items : [];
      all = items.map(it=>{
        const v = it?.valla || {};
        return {
          id: it.id,
          valla_id: it.valla_id ?? v.id ?? null,
          estado: it.estado || '',
          publica_desde: it.publica_desde || null,
          publica_hasta: it.publica_hasta || null,
          dias_restantes: it.dias_restantes ?? null,
          valla: Object.assign({}, v)
        };
      });
      render();
    }catch(e){
      grid.innerHTML = `<div class="text-slate-400">No fue posible cargar destacadas.</div>`;
      totalSpan.textContent = '0';
    }
  }

  // ===== Filtro + Paginación =====
  function applyFilters(){
    let arr = all.slice();

    // Estado
    const est = estado.value.trim();
    if (est) arr = arr.filter(x => String(x.estado) === est);

    // Tipo
    const t = tipo.value.trim().toLowerCase();
    if (t) arr = arr.filter(x => (x.valla?.tipo||'').toLowerCase() === t);

    // ID visual (valla_id o destacado.id)
    const raw = (idq.value||'').trim();
    if (raw){
      const num = raw.replace(/[^\d]/g,'');
      if (num) arr = arr.filter(x => String(x.valla_id)===num || String(x.id)===num);
    }

    // Orden: activos primero, luego LED primero, luego id valla desc
    arr.sort((a,b)=>{
      const sa = a.estado==='active' ? 0 : 1;
      const sb = b.estado==='active' ? 0 : 1;
      if (sa!==sb) return sa-sb;
      const la = ((a.valla?.tipo||'').toLowerCase()==='led')?0:1;
      const lb = ((b.valla?.tipo||'').toLowerCase()==='led')?0:1;
      if (la!==lb) return la-lb;
      return (parseInt(b.valla_id||0,10)) - (parseInt(a.valla_id||0,10));
    });

    return arr;
  }

  function render(){
    const filtered = applyFilters();
    totalSpan.textContent = String(filtered.length);

    const pages = Math.max(1, Math.ceil(filtered.length / perPage));
    if (page > pages) page = pages;
    const start = (page-1)*perPage;
    const slice = filtered.slice(start, start+perPage);

    if (slice.length===0){
      grid.innerHTML = `<div class="text-slate-400">Sin resultados con los filtros.</div>`;
      pager.innerHTML = '';
      return;
    }

    grid.innerHTML = slice.map(cardHTML).join('');
    bindRowActions();

    // pager
    pager.innerHTML = '';
    if (pages>1){
      const frag = document.createDocumentFragment();
      const mkBtn = (label, go, active=false, disabled=false)=>{
        const b = document.createElement('button');
        b.className = 'px-3 py-1 rounded border ' + (active?'border-sky-500 text-sky-300':'border-slate-600 text-slate-300 hover:border-sky-500 hover:text-sky-300');
        b.textContent = label; b.disabled = disabled;
        b.addEventListener('click', go);
        return b;
      };
      frag.appendChild(mkBtn('«', ()=>{ if(page>1){ page--; render(); }} , false, page===1));
      const win = 5;
      let s = Math.max(1, page - Math.floor(win/2));
      let e = Math.min(pages, s + win - 1);
      s = Math.max(1, e - win + 1);
      if (s>1) frag.appendChild(mkBtn('1', ()=>{ page=1; render(); }));
      if (s>2){ const d=document.createElement('span'); d.textContent='…'; d.className='text-slate-500 px-1'; frag.appendChild(d); }
      for (let p=s; p<=e; p++) frag.appendChild(mkBtn(String(p), ()=>{ page=p; render(); }, p===page));
      if (e<pages-1){ const d=document.createElement('span'); d.textContent='…'; d.className='text-slate-500 px-1'; frag.appendChild(d); }
      if (e<pages) frag.appendChild(mkBtn(String(pages), ()=>{ page=pages; render(); }));
      frag.appendChild(mkBtn('»', ()=>{ if(page<pages){ page++; render(); }} , false, page===pages));
      pager.appendChild(frag);
    }

    // scroll-to visual si se digitó un ID exacto
    const raw = (idq.value||'').trim();
    if (raw){
      const num = raw.replace(/[^\d]/g,'');
      if (num){
        const hit = document.querySelector(`[data-valla-id="${CSS.escape(num)}"], [data-destacado-id="${CSS.escape(num)}"]`);
        if (hit){ hit.scrollIntoView({behavior:'smooth', block:'center'}); hit.classList.add('ring-focus'); setTimeout(()=>hit.classList.remove('ring-focus'),1200); }
      }
    }
  }

  function cardHTML(it){
    const v = it.valla || {};
    const esLed = (v.tipo||'').toLowerCase()==='led';
    const mediaUrl = (v.media?.[0]?.url) || v.imagen || '';
    const nombre = v.nombre || `Valla ${it.valla_id||''}`;
    const ubic  = v.provincia ? `${v.provincia} · ${v.tipo?String(v.tipo).toUpperCase():''}` : (v.tipo?String(v.tipo).toUpperCase():'');
    const disp  = disponibilidadChip(v.disponible);
    const estado = estadoChip(it.estado);
    const desde = it.publica_desde ? new Date(it.publica_desde).toLocaleDateString('es-DO') : '';
    const hasta = it.publica_hasta ? new Date(it.publica_hasta).toLocaleDateString('es-DO') : '';
    const dias  = (it.dias_restantes!=null) ? `<span class="text-xs text-slate-400">· ${it.dias_restantes} días restantes</span>` : '';

    const urlCal = `/calendario/?id=${encodeURIComponent(it.valla_id||v.id||'')}`;
    const urlLED = `/detalles-led/?id=${encodeURIComponent(it.valla_id||v.id||'')}`;
    const urlNoL = `/detalles-vallas/?id=${encodeURIComponent(it.valla_id||v.id||'')}`;

    return `
      <article class="group rounded-2xl bg-slate-800 border border-slate-700 overflow-hidden" data-valla-id="${esc(it.valla_id||'')}" data-destacado-id="${esc(it.id)}">
        <div class="relative">
          ${imgTag(mediaUrl, `Valla: ${nombre}`)}
          <div class="absolute top-2 left-2 flex gap-2">${liveBadgeHTML(esLed)} ${estado}</div>
          <div class="absolute bottom-2 left-2 flex gap-2">${disp}</div>
        </div>
        <div class="p-4">
          <h3 class="text-white font-extrabold text-base leading-snug mb-1 line-clamp-2">${esc(nombre)}</h3>
          <p class="text-slate-400 text-sm mb-2">${esc(ubic)}</p>
          <p class="text-xs text-slate-500 mb-3">
            ID Valla: <span class="font-semibold text-slate-300">${esc(it.valla_id||'—')}</span> ·
            ID Destacado: <span class="font-semibold text-slate-300">${esc(it.id)}</span>
            ${dias}
          </p>
          <p class="text-xs text-slate-500 mb-4">${desde && hasta ? `Del ${esc(desde)} al ${esc(hasta)}` : ''}</p>
          <div class="flex flex-wrap gap-2">
            <a href="${esc(urlCal)}" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 text-sm">Calendario</a>
            ${esLed
              ? `<a href="${esc(urlLED)}" class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-emerald-950 text-sm font-bold">En vivo</a>`
              : `<a href="${esc(urlNoL)}" class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-sm">Ver ficha</a>`
            }
            <button type="button" class="btn-copy px-3 py-1.5 rounded-lg border border-slate-600 hover:border-sky-500 text-slate-200 text-sm"
                    data-copy="${esc(window.location.origin + '/detalles-vallas/?id=' + (it.valla_id||v.id||''))}">
              Copiar enlace
            </button>
          </div>
        </div>
      </article>`;
  }

  function bindRowActions(){
    grid.querySelectorAll('.btn-copy').forEach(b=>{
      b.addEventListener('click', async ()=>{
        try{ await navigator.clipboard.writeText(b.getAttribute('data-copy')||''); b.textContent='Copiado'; setTimeout(()=>b.textContent='Copiar enlace',1200);}catch(_){}
      });
    });
  }

  // ===== Events =====
  btnBuscar.addEventListener('click', ()=>{ page=1; render(); });
  btnLimpiar.addEventListener('click', ()=>{
    idq.value=''; estado.value=''; tipo.value=''; page=1; render();
  });
  idq.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ page=1; render(); } });
  document.querySelectorAll('[data-q]').forEach(el=>{
    el.addEventListener('click', ()=>{
      const v = el.getAttribute('data-q')||'';
      if (v==='led' || v==='impresa') { tipo.value=v; } else { idq.value=v; }
      estado.value='active';
      page=1; render();
    });
  });
  btnSoloActivos.addEventListener('click', ()=>{ estado.value='active'; page=1; render(); });

  // Boot
  loadDestacados();
})();
</script>

<?php
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

/* Pageview */
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/destacadas');
}

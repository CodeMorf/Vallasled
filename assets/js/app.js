/* /assets/js/app.js :: Catalogo V2.1 (provincias/zonas fallbacks + cart realtime) */
(function(){
  if (window.__vallas_inited) return;
  window.__vallas_inited = true;

  // ========= Utils =========
  const toStr = (v)=> v==null ? '' : String(v);
  const esc = (v)=> toStr(v)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  function baseURL(){
    try{
      const meta = document.querySelector('meta[name="site-root"]')?.content?.trim();
      return meta ? new URL(meta, location.origin) : new URL('/', location.origin);
    }catch(_){ return new URL('/', location.origin); }
  }
  function urlOf(path){ return new URL(path, baseURL()).href; }

  function normalizeImg(u){
    const base = 'https://auth.vallasled.com/uploads/';
    if (!u) return '';
    try{
      if (/^https?:\/\//i.test(u)) return u;
      u = toStr(u).replace(/^\/+/, '');
      if (u.startsWith('destacadas/')) u = u.replace(/^destacadas\//,'');
      if (u.startsWith('uploads/'))     u = u.replace(/^uploads\//,'');
      return base + u;
    }catch(_){ return u; }
  }
  function imgTag(url, alt){
    const ph = 'https://placehold.co/1200x800/0b1220/93c5fd?text=Valla';
    const src = normalizeImg(url) || ph;
    const srcset = `${src} 1200w, ${src} 800w, ${src} 480w`;
    const sizes = "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw";
    return `<img src="${esc(src)}" srcset="${esc(srcset)}" sizes="${esc(sizes)}" alt="${esc(alt||'')}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${ph}'">`;
  }
  const debounce = (fn,ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

  // ========= Robust fetch helpers =========
  async function fetchJSON(u, {signal, timeoutMs=9000, cache='no-store'}={}){
    const ctl = new AbortController();
    const t = setTimeout(()=>ctl.abort('timeout'), timeoutMs);
    try{
      const res = await fetch(u, {signal: (signal??ctl.signal), cache});
      if (!res.ok) throw new Error('HTTP '+res.status);
      // intenta json; si falla, re-lanza
      return await res.json();
    } finally { clearTimeout(t); }
  }
  async function fetchFirstJSON(urls, opts){
    let err;
    for (const u of urls){
      try { return await fetchJSON(u, opts); }
      catch(e){ err = e; }
    }
    throw err || new Error('all_failed');
  }

  function init(){
    // ========= Scope DOM =========
    const root = document.getElementById('catalogo') || document;
    const $  = (sel)=> root.querySelector(sel);
    const $$ = (sel)=> root.querySelectorAll(sel);

    // refs dentro del catálogo
    const vallasGrid = $('#vallas-grid');
    const pager      = $('#pager');
    const btnBuscar  = $('#btnBuscar');
    const pageSizeSel= $('#pageSize');
    const q          = $('#q');
    const provincia  = $('#provincia');
    const zona       = $('#zona');
    const tipo       = $('#filter-tipo');
    const disponibilidad = $('#filter-disponibilidad');
    // refs fuera del catálogo pero propios de esta vista
    const categoryCards = document.querySelectorAll('#tipos .category-card');
    const mapaIframe    = document.querySelector('#mapa iframe');

    if (!vallasGrid) return; // no romper otras páginas

    // ========= State =========
    let itemsAll = [];
    let page = 1;
    let pageSize = (parseInt(pageSizeSel?.value||'12',10) || parseInt(localStorage.getItem('vallasPageSize')||'12',10) || 12);
    if (pageSizeSel) pageSizeSel.value = String(pageSize);
    const selected = new Set();
    let fetchCtl = null;
    let reqId = 0;
    let lastMapQS = '';

    // ========= Query helpers =========
    function readURLToFilters(){
      const u = new URL(location.href);
      if (q) q.value = u.searchParams.get('q') || '';
      if (zona) zona.value = u.searchParams.get('zona') || '';
      if (tipo) tipo.value = u.searchParams.get('tipo') || '';
      if (disponibilidad) disponibilidad.value = u.searchParams.get('disponible') ?? '';
      if (provincia){
        const pid = u.searchParams.get('provincia') || '';
        const pnm = u.searchParams.get('provincia_nombre') || '';
        if (pid){ provincia.value = pid; }
        else if (pnm){
          const opt = Array.from(provincia.options).find(o=> (o.dataset?.nombre||'') === pnm || o.text === pnm);
          if (opt) provincia.value = opt.value;
        }
      }
    }
    function buildQS(){
      const params = new URLSearchParams();
      const qv = q?.value?.trim(); if (qv) params.set('q', qv);
      if (zona?.value) params.set('zona', zona.value);
      if (tipo?.value) params.set('tipo', tipo.value);
      if (disponibilidad && disponibilidad.value !== '') params.set('disponible', disponibilidad.value);
      const provVal = provincia?.value;
      if (provVal) {
        if (/^\d+$/.test(provVal)) params.set('provincia', provVal);
        else {
          const opt = provincia.options[provincia.selectedIndex];
          const nombre = opt?.dataset?.nombre || provVal;
          params.set('provincia_nombre', nombre);
        }
      }
      return params;
    }
    function syncURL(){
      const params = buildQS();
      const url = params.toString() ? (`?${params.toString()}`) : location.pathname;
      history.replaceState(null,'', url);
    }
    function updateMap(){
      if (!mapaIframe) return;
      const params = buildQS(); params.set('h', '520');
      const qs = params.toString();
      if (qs !== lastMapQS){
        lastMapQS = qs;
        const next = urlOf('/api/mapa/iframe.php') + '?' + qs;
        if (mapaIframe.src !== next) mapaIframe.src = next;
      }
    }

    // ========= Filtros (con fallbacks) =========
    async function loadFiltros(){
      const provURLs = [
        urlOf('/api/provincias.php'),
        urlOf('/api/provincias'),
        urlOf('/api/geo_provincias.php')
      ];
      const zonaURLs = [
        urlOf('/api/zonas.php'),
        urlOf('/api/zonas'),
        urlOf('/api/geo_zonas.php')
      ];

      try{
        const [provRes, zonaRes] = await Promise.all([
          fetchFirstJSON(provURLs, {}),
          fetchFirstJSON(zonaURLs, {})
        ]);

        // Normaliza arrays
        const provsRaw = Array.isArray(provRes?.data) ? provRes.data
                        : Array.isArray(provRes?.items) ? provRes.items
                        : Array.isArray(provRes) ? provRes
                        : Array.isArray(provRes?.results) ? provRes.results : [];
        const zonasRaw = Array.isArray(zonaRes?.data) ? zonaRes.data
                        : Array.isArray(zonaRes?.items) ? zonaRes.items
                        : Array.isArray(zonaRes) ? zonaRes
                        : Array.isArray(zonaRes?.results) ? zonaRes.results : [];

        // Provincias → {value:idStr, text:name}
        const provs = provsRaw.map(p=>{
          const id = toStr(p.id ?? p.value ?? p.codigo ?? p.code ?? p.provincia_id ?? '');
          const name = toStr(p.nombre ?? p.name ?? p.title ?? p.provincia ?? id);
          return { id: id || name, name, _raw:p };
        });

        // Zonas → {value:name}
        const zonas = zonasRaw.map(z=>{
          const name = toStr(z.nombre ?? z.name ?? z.title ?? z.zona ?? '');
          return { name };
        });

        if (provincia){
          const old = provincia.value;
          provincia.innerHTML = '<option value="">Todas</option>';
          provs.forEach(p=>{
            const opt = new Option(p.name || p.id, p.id || p.name);
            if (p.name) opt.dataset.nombre = p.name;
            provincia.add(opt);
          });
          if (old) provincia.value = old;
        }
        if (zona){
          const oldZ = zona.value;
          zona.innerHTML = '<option value="">Todas</option>';
          zonas.forEach(z=> zona.add(new Option(z.name || '', z.name || '')));
          if (oldZ) zona.value = oldZ;
        }
      }catch(_){
        // Silencio, se mantienen selects por defecto
      }
    }

    // ========= Render helpers =========
    function sortLedFirst(list){
      return [...list].sort((a,b)=>{
        const aLed = ((a.tipo||'').toLowerCase()==='led') ? 0 : 1;
        const bLed = ((b.tipo||'').toLowerCase()==='led') ? 0 : 1;
        if (aLed !== bLed) return aLed - bLed;
        const ai = parseInt(a.id||0,10); const bi = parseInt(b.id||0,10);
        return bi - ai;
      });
    }
    function liveBadgeHTML(isLed){
      return isLed ? `<span class="live-badge" title="Señal en vivo"><span class="live-dot"></span>LIVE</span>` : '';
    }
    function actionsHTML(valla, esLed){
      const id = valla.id;
      const urlDetalleLED = `/detalles-led/?id=${id}`;
      const urlDetalleNoLED = `/detalles-vallas/?id=${id}`;
      return `
        <a class="btn btn-outline" href="/calendario/?id=${esc(id)}" title="Ver en Calendario">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#e2e8f0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          <span>Ver en Calendario</span>
        </a>
        ${esLed
          ? `<a class="btn btn-danger" href="${esc(urlDetalleLED)}" title="Señal en vivo">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"></circle><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-10.48-.01a6 6 0 0 1 0-8.49m11.92 1.42a9 9 0 0 1 0 5.66m-14.8 0a9 9 0 0 1 0-5.66"></path></svg>
              <span>En Vivo</span>
            </a>`
          : `<a class="btn btn-accent" href="${esc(urlDetalleNoLED)}" title="Ver ficha">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              <span>Ver</span>
            </a>`
        }
        <button type="button" class="btn btn-outline btn-select" data-id="${esc(id)}" title="Seleccionar">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#e2e8f0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l7 17 2-7 7-2z"/></svg>
          <span>Seleccionar</span>
        </button>
        <button type="button" class="btn btn-cart btn-add" data-id="${esc(id)}" title="Agregar al carrito">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#111827" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          <span>Agregar</span>
        </button>
      `;
    }
    function createVallaCardHTML(valla, estadoDestacada){
      const esLed = (valla.tipo||'').toLowerCase()==='led';
      const mediaUrl = (valla.media?.[0]?.url) || valla.imagen || '';
      const disponibilidadTxt = parseInt(valla.disponible?1:0) ? 'Disponible' : 'Ocupado';
      const disponibilidadCls = parseInt(valla.disponible?1:0) ? 'text-green-400' : 'text-red-400';
      const estadoChip = estadoDestacada ? `<p class="text-xs mt-1 ${estadoDestacada==='active'?'text-green-400':'text-red-400'}">${estadoDestacada==='active'?'Activo':'Ocupado'}</p>` : '';
      return `
        <div class="card compact" data-id="${esc(valla.id)}" data-tipo="${esc(valla.tipo ?? '')}">
          <div class="card-media">
            ${imgTag(mediaUrl, `Valla: ${valla.nombre ?? ''}`)}
          </div>
          <div class="card-body">
            <div class="card-content">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                <h3 class="text-white" style="margin:0;font-weight:800">${esc(valla.nombre ?? '—')}</h3>
                ${liveBadgeHTML(esLed)}
              </div>
              <p class="text-gray-400">${esc(valla.provincia ?? '')} - ${(String(valla.tipo ?? '')).toUpperCase()}</p>
              <p class="text-sm ${disponibilidadCls}" style="margin-top:.25rem">${disponibilidadTxt}</p>
              ${estadoChip}
            </div>
            <div class="card-actions">${actionsHTML(valla, esLed)}</div>
          </div>
        </div>`;
    }
    function renderSkeleton(n){
      const card = `
        <div class="card compact">
          <div class="card-media"></div>
          <div class="card-body"><div class="card-content">
            <div style="height:12px;background:#1f2937;border-radius:.25rem"></div>
            <div style="height:12px;margin-top:8px;background:#1f2937;border-radius:.25rem;width:70%"></div>
          </div></div>
        </div>`;
      vallasGrid.innerHTML = Array.from({length:n}).map(()=>card).join('');
    }

    // ========= Paginación =========
    function renderPager(pages, total){
      pager.innerHTML = '';
      if (pages <= 1) return;
      const frag = document.createDocumentFragment();
      const mkBtn = (label, onClick, active=false, disabled=false)=>{
        const b = document.createElement('button');
        b.className = 'page-btn' + (active?' active':'');
        b.type='button'; b.textContent=label; b.disabled=disabled;
        b.addEventListener('click', onClick);
        return b;
      };
      const info = document.createElement('div');
      info.style.marginRight='auto'; info.style.opacity='.8'; info.style.fontSize='.9rem';
      info.textContent = `Total: ${total}`;
      pager.appendChild(info);

      frag.appendChild(mkBtn('« Anterior', ()=>{ if(page>1){ page--; renderPage(); } }, false, page===1));
      const windowSize = 5;
      let start = Math.max(1, Math.floor(page - windowSize/2));
      let end = Math.min(pages, start + windowSize - 1);
      start = Math.max(1, end - windowSize + 1);
      const dot=()=>{ const s=document.createElement('span'); s.style.opacity='.7'; s.textContent='…'; s.style.padding='.2rem .4rem'; return s; };
      if (start > 1) frag.appendChild(mkBtn('1', ()=>{page=1; renderPage();}));
      if (start > 2) frag.appendChild(dot());
      for (let p=start; p<=end; p++) frag.appendChild(mkBtn(String(p), ()=>{ page=p; renderPage(); }, p===page));
      if (end < pages-1) frag.appendChild(dot());
      if (end < pages) frag.appendChild(mkBtn(String(pages), ()=>{ page=pages; renderPage(); }));
      frag.appendChild(mkBtn('Siguiente »', ()=>{ if(page<pages){ page++; renderPage(); } }, false, page===pages));
      pager.appendChild(frag);
    }
    function renderPage(){
      const total = itemsAll.length;
      const pages = Math.max(1, Math.ceil(total / pageSize));
      if (page > pages) page = pages;
      const start = (page-1)*pageSize;
      const slice = itemsAll.slice(start, start + pageSize);
      vallasGrid.innerHTML = slice.length
        ? slice.map(v=>createVallaCardHTML(v)).join('')
        : '<div id="no-results"><h3 class="text-lg" style="font-weight:800">No se encontraron resultados</h3><p class="text-muted">Ajusta los filtros.</p></div>';
      renderPager(pages, total);
    }
    function renderData(list){
      itemsAll = sortLedFirst(Array.isArray(list)? list : []);
      page = 1;
      renderPage();
    }

    // ========= Data catálogo =========
    async function buscar(){
      const myId = ++reqId;
      syncURL();
      updateMap();
      if (fetchCtl) try{ fetchCtl.abort(); }catch(_){}
      fetchCtl = new AbortController();

      try{
        vallasGrid.setAttribute('aria-busy','true');
        renderSkeleton(pageSize);
        const params = buildQS();
        const urls = [
          urlOf('/api/buscador.php') + '?' + params.toString(),
          urlOf('/api/buscador')     + '?' + params.toString()
        ];
        const data = await fetchFirstJSON(urls, {signal: fetchCtl.signal});
        if (myId !== reqId) return; // respuesta vieja
        const items = Array.isArray(data?.items) ? data.items
                   : Array.isArray(data?.data) ? data.data
                   : Array.isArray(data?.results) ? data.results : [];
        renderData(items);
      }catch(e){
        if (e?.name === 'AbortError') return;
        try{
          const alt = await fetchJSON(urlOf('/api/geo_vallas.php'), {signal: fetchCtl.signal});
          if (myId !== reqId) return;
          const items = Array.isArray(alt?.items) ? alt.items
                     : Array.isArray(alt?.data) ? alt.data
                     : Array.isArray(alt?.results) ? alt.results : [];
          renderData(items);
        }catch(_){
          if (myId !== reqId) return;
          renderData([]);
        }
      }finally{
        vallasGrid.removeAttribute('aria-busy');
      }
    }

    // ========= Delegación de eventos en grid =========
    vallasGrid.addEventListener('click', async (ev)=>{
      if (!(ev.target instanceof Element)) return;
      const btnSel = ev.target.closest('.btn-select');
      const btnAdd = ev.target.closest('.btn-add');
      if (btnSel){
        const id = btnSel.getAttribute('data-id');
        const card = btnSel.closest('.card');
        if (!id || !card) return;
        const selTxt = btnSel.querySelector('span');
        if (selected.has(id)) { selected.delete(id); card.classList.remove('selected'); toast('Quitado de selección'); if(selTxt) selTxt.textContent='Seleccionar'; }
        else { selected.add(id); card.classList.add('selected'); toast('Seleccionado'); if(selTxt) selTxt.textContent='Seleccionado'; }
        return;
      }
      if (btnAdd){
        const id = btnAdd.getAttribute('data-id');
        try{
          const r = await fetch(urlOf(`/carritos/?a=add&id=${encodeURIComponent(id)}`), { headers: { 'X-Requested-With':'XMLHttpRequest' }});
          const j = await r.json();
          toast(j?.message || 'Agregado al carrito');
          cartCount();
        }catch(_){ toast('No se pudo agregar'); }
      }
    });

    // ========= Carrito en tiempo real (universal) =========
    const CART_COUNT_URLS = [ urlOf('/api/carritos/api.php?a=count'), urlOf('/carritos/?a=count') ];
    async function cartCount(){
      // pinta rápido si existe badge
      const badge = (document.getElementById('cart-count') || document.getElementById('cartBadge'));
      if (badge){
        const cur = parseInt(badge.textContent||'0',10)||0;
        badge.hidden = cur<=0;
      }
      try{
        const j = await fetchFirstJSON(CART_COUNT_URLS, {});
        const n = Number(j?.count ?? j?.data?.count ?? 0);
        const nodes = [document.getElementById('cart-count'), document.getElementById('cartBadge')].filter(Boolean);
        nodes.forEach(b=>{ b.textContent = String(n); b.hidden = !(n>0); });
      }catch(_){ /* keep old value */ }
    }
    window.cartCount = cartCount;

    // ========= Destacadas =========
    async function loadDestacadas(){
      const cont = document.getElementById('featured-row');
      if (!cont) return;
      cont.classList.add('grid','lg:grid-cols-4','gap-8');
      cont.innerHTML = [...Array(4)].map(()=>(`
        <div class="card compact">
          <div class="card-media"></div>
          <div class="card-body"><div class="card-content">
            <div style="height:12px;background:#1f2937;border-radius:.25rem"></div>
            <div style="height:12px;margin-top:8px;background:#1f2937;border-radius:.25rem;width:70%"></div>
          </div></div>
        </div>`)).join('');
      try{
        const r = await fetchJSON(urlOf('/api/destacados/api.php?limit=12'));
        const items = Array.isArray(r?.items) ? r.items : [];
        const vallas = items.map(it=>{
          const v = it?.valla || {};
          return Object.assign({}, v, { id: it.valla_id ?? v.id, __estado_destacado: it.estado || '' });
        });
        cont.innerHTML = vallas.length
          ? vallas.map(v=>createVallaCardHTML(v, v.__estado_destacado)).join('')
          : `<div class="text-muted">Sin destacadas activas ahora.</div>`;
        cont.querySelectorAll('.btn-select,.btn-add').forEach(b=>b.addEventListener('click', e=>e.stopPropagation()));
      }catch(_){
        cont.innerHTML = `<div class="text-muted">No fue posible cargar destacadas.</div>`;
      }
    }

    // ========= Toast =========
    function toast(txt){
      let t = document.getElementById('toast');
      if (!t) {
        t = document.createElement('div'); t.id='toast';
        t.style.cssText='position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#111827;color:#e5e7eb;padding:.6rem .9rem;border-radius:.5rem;z-index:9999;box-shadow:0 10px 20px rgba(0,0,0,.25)';
        document.body.appendChild(t);
      }
      t.textContent = txt; t.className = 'show'; setTimeout(()=>{ t.className=''; }, 1600);
    }

    // ========= Eventos filtros =========
    categoryCards.forEach(card=>{
      card.addEventListener('click', ()=>{
        const t = card.getAttribute('data-tipo') || '';
        if (tipo) tipo.value = t;
        buscar();
        document.getElementById('catalogo')?.scrollIntoView({behavior:'smooth'});
      });
    });
    btnBuscar?.addEventListener('click', ()=> buscar());
    pageSizeSel?.addEventListener('change', ()=>{
      pageSize = parseInt(pageSizeSel.value,10) || 12;
      localStorage.setItem('vallasPageSize', String(pageSize));
      page = 1; renderPage();
    });
    q?.addEventListener('input', debounce(()=> buscar(), 350));
    q?.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); buscar(); }});
    provincia?.addEventListener('change', ()=> buscar());
    zona?.addEventListener('change', ()=> buscar());
    tipo?.addEventListener('change', ()=> buscar());
    disponibilidad?.addEventListener('change', ()=> buscar());

    // ========= FAB bootstrap (si falta) =========
    (function initFAB(){
      let el = document.querySelector('.fab');
      if (!el) {
        el = document.createElement('a');
        el.className = 'fab'; el.href = '/carritos/';
        el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="fab-badge" id="cart-count">0</span>`;
        document.body.appendChild(el);
      }
      let wa = document.querySelector('.fab-wa');
      if (!wa) {
        wa = document.createElement('a');
        wa.className = 'fab-wa'; wa.href = '/api/whatsapp/api.php?a=chat'; wa.target = '_blank'; wa.rel='noopener'; wa.title='WhatsApp';
        wa.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M380.9 97.1C339 55.1 283.2 32 224.6 32 119.1 32 32 119.1 32 224.6c0 40.6 10.6 80.2 30.7 114.9L32 480l143.7-30.2c32.9 18 70 27.5 108.9 27.5h.1c105.5 0 192.6-87.1 192.6-192.6 0-58.6-23.1-114.4-65.1-156.6z"/></svg>`;
        document.body.appendChild(wa);
      }
      cartCount();
    })();

    // ========= Boot =========
    loadFiltros().then(()=>{ readURLToFilters(); buscar(); });
    loadDestacadas();
    window.addEventListener('popstate', ()=>{ readURLToFilters(); buscar(); });
    window.addEventListener('pageshow', cartCount);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

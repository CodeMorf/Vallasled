(function(){
  // ========= Utils =========
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
    const ph = 'https://placehold.co/1200x800/0b1220/93c5fd?text=Valla';
    const src = normalizeImg(url) || ph;
    const srcset = `${src} 1200w, ${src} 800w, ${src} 480w`;
    const sizes = "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw";
    return `<img src="${esc(src)}" srcset="${esc(srcset)}" sizes="${esc(sizes)}" alt="${esc(alt||'')}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${ph}'">`;
  }

  document.addEventListener('DOMContentLoaded', function () {
    // ========= DOM refs =========
    const vallasGrid = document.getElementById('vallas-grid');
    const pager = document.getElementById('pager');
    const btnBuscar = document.getElementById('btnBuscar');
    const pageSizeSel = document.getElementById('pageSize');
    const q = document.getElementById('q');
    const provincia = document.getElementById('provincia');
    const zona = document.getElementById('zona');
    const tipo = document.getElementById('filter-tipo');
    const disponibilidad = document.getElementById('filter-disponibilidad');
    const categoryCards = document.querySelectorAll('.category-card');
    const mapaIframe = document.querySelector('#mapa iframe');

    // ========= State =========
    let itemsAll = [];
    let page = 1;
    let pageSize = parseInt(pageSizeSel?.value || '12', 10) || 12;
    const selected = new Set();

    // ========= Query helpers =========
    function buildQS(){
      const params = new URLSearchParams();
      if (q && q.value) params.set('q', q.value);
      if (zona && zona.value) params.set('zona', zona.value);
      if (tipo && tipo.value) params.set('tipo', tipo.value);
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
    function updateMap(){
      if (!mapaIframe) return;
      const params = buildQS(); params.set('h', '520');
      mapaIframe.src = '/api/mapa/iframe.php?' + params.toString();
    }

    // ========= Filtros =========
    async function loadFiltros(){
      try{
        const [provRes, zonaRes] = await Promise.all([
          fetch('/api/provincias.php').then(r=>r.json()),
          fetch('/api/zonas.php').then(r=>r.json())
        ]);
        const provs = Array.isArray(provRes?.data) ? provRes.data : (Array.isArray(provRes) ? provRes : []);
        const zonas = Array.isArray(zonaRes?.data) ? zonaRes.data : (Array.isArray(zonaRes) ? zonaRes : []);
        if (provincia){
          provincia.innerHTML = '<option value="">Todas</option>';
          provs.forEach(p=>{
            const id = (p.id ?? p.value ?? '').toString();
            const name = p.nombre ?? p.name ?? '';
            const opt = new Option(name || id, id || name);
            if (name) opt.dataset.nombre = name;
            provincia.add(opt);
          });
        }
        if (zona){
          zona.innerHTML = '<option value="">Todas</option>';
          zonas.forEach(z=>{
            const name = z.nombre ?? z.name ?? '';
            zona.add(new Option(name || '', name || ''));
          });
        }
      }catch(_){}
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
    function disponibilidadBadgeHTML(disponible){
      const ok = parseInt(disponible?1:0) === 1;
      return `<span class="badge ${ok?'ok':'err'}">${ok?'Disponible':'Ocupado'}</span>`;
    }
    function estadoDestacadoBadgeHTML(estado){ if (!estado) return ''; const ok = (estado === 'active'); return `<span class="badge ${ok?'ok':'err'}" style="margin-left:.5rem">${ok?'Activo':'Ocupado'}</span>`; }
    function createVallaCardHTML(valla, estadoDestacada){
      const esLed = (valla.tipo||'').toLowerCase()==='led';
      const mediaUrl = (valla.media?.[0]?.url) || valla.imagen || '';
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
              <p class="text-gray-400" style="margin:.35rem 0 .25rem 0">
                ${esc(valla.provincia ?? '')} - ${(String(valla.tipo ?? '')).toUpperCase()}
              </p>
              <p class="text-sm" style="margin:0">
                ${disponibilidadBadgeHTML(valla.disponible)}${estadoDestacadoBadgeHTML(estadoDestacada||'')}
              </p>
            </div>
            <div class="card-actions">${actionsHTML(valla, esLed)}</div>
          </div>
        </div>`;
    }

    // ========= Paginación catálogo =========
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
      bindCardActions();
      renderPager(pages, total);
    }
    function renderData(list){
      itemsAll = sortLedFirst(Array.isArray(list)? list : []);
      page = 1;
      renderPage();
    }

    // ========= Data catálogo =========
    async function buscar(){
      const params = buildQS();
      try{
        const res = await fetch('/api/buscador.php?' + params.toString());
        if (!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();
        const items = data.items || data.data || data.results || [];
        renderData(items);
      }catch(e){
        try{
          const alt = await fetch('/api/geo_vallas.php').then(r=>r.json());
          const items = alt.items || alt.data || alt.results || [];
          renderData(items);
        }catch(_){
          renderData([]);
        }
      }
      updateMap();
    }

    // ========= Carrito: puente unificado =========
    async function fetchCountFallback(){
      // intenta API principal y luego fallback
      const heads = {'X-Requested-With':'XMLHttpRequest','Cache-Control':'no-store'};
      async function one(url){
        const r = await fetch(url,{headers:heads,cache:'no-store',credentials:'include'});
        if(!r.ok) throw new Error('http');
        const j = await r.json();
        const n = Number(j?.count||0);
        if (!Number.isFinite(n)) throw new Error('nan');
        return n;
      }
      try { return await one('/api/carritos/api.php?a=count'); }
      catch(_){ return await one('/carritos/?a=count'); }
    }
    function paintCount(n){
      const val = String(Math.max(0, parseInt(n||0,10)));
      const legacy = document.getElementById('cart-count');
      if (legacy) legacy.textContent = val;
      const badge = document.getElementById('cartBadge');
      if (badge){
        if (val !== '0'){ badge.textContent = val; badge.hidden = false; }
        else { badge.hidden = true; }
      }
    }
    function updateCartCount(){
      if (typeof window.cartCount === 'function') return window.cartCount();
      return fetchCountFallback().then(paintCount).catch(()=>{});
    }
    // Exponer por compatibilidad
    window.updateCartCount = updateCartCount;

    // ========= Interacciones =========
    function bindCardActions(){
      vallasGrid.querySelectorAll('.btn-select').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const id = btn.getAttribute('data-id');
          const card = btn.closest('.card'); if (!id||!card) return;
          const selTxt = btn.querySelector('span');
          if (selected.has(id)) { selected.delete(id); card.classList.remove('selected'); toast('Quitado de selección'); if(selTxt) selTxt.textContent='Seleccionar'; }
          else { selected.add(id); card.classList.add('selected'); toast('Seleccionado'); if(selTxt) selTxt.textContent='Seleccionado'; }
        });
      });
      vallasGrid.querySelectorAll('.btn-add').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          const id = btn.getAttribute('data-id');
          try{
            const r = await fetch(`/carritos/?a=add&id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
            const j = await r.json();
            toast(j?.message || 'Agregado al carrito');
            updateCartCount();
          }catch(_){ toast('No se pudo agregar'); }
        });
      });
    }

    // ========= Destacadas =========
    async function loadDestacadas(){
      const cont = document.getElementById('featured-row');
      if (!cont) return;
      cont.innerHTML = [...Array(4)].map(()=>(`
        <div class="card compact">
          <div class="card-media"></div>
          <div class="card-body"><div class="card-content">
            <div class="skeleton s1"></div>
            <div class="skeleton s2"></div>
          </div></div>
        </div>`)).join('');
      try{
        const r = await fetch('/api/destacados/api.php?limit=12', {cache:'no-store'});
        if (!r.ok) throw new Error('HTTP '+r.status);
        const j = await r.json();
        const items = Array.isArray(j.items) ? j.items : [];
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

    // ========= FAB (fallback si no está el parcial) =========
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
      updateCartCount();
    })();

    // Refresco por clics globales “Agregar/Quitar”
    document.addEventListener('click', (e)=>{
      if (e.target.closest('.btn-add, .add-to-cart, [data-add-to-cart], a[href*="/carritos/?a=add"]')) {
        setTimeout(updateCartCount, 120);
      }
      if (e.target.closest('.btn-remove, .remove-from-cart, [data-remove-from-cart], a[href*="/carritos/?a=del"], a[href*="/carritos/?a=clear"]')) {
        setTimeout(updateCartCount, 120);
      }
    });

    // ========= Toast =========
    function toast(txt){
      let t = document.getElementById('toast');
      if (!t) { t = document.createElement('div'); t.id='toast'; document.body.appendChild(t); }
      t.textContent = txt; t.className = 'show'; setTimeout(()=>{ t.className=''; }, 1600);
    }

    // ========= Boot =========
    loadFiltros().then(()=>{ buscar(); updateMap(); });
    loadDestacadas();
    window.addEventListener('pageshow', updateCartCount);
    updateCartCount();
    categoryCards.forEach(card=>{
      card.addEventListener('click', ()=>{
        const t = card.getAttribute('data-tipo') || '';
        if (tipo) tipo.value = t;
        buscar();
        document.getElementById('catalogo')?.scrollIntoView({behavior:'smooth'});
      });
    });
    btnBuscar?.addEventListener('click', ()=> buscar());
    pageSizeSel?.addEventListener('change', ()=>{ pageSize = parseInt(pageSizeSel.value,10) || 12; page = 1; renderPage(); });
  });
})();

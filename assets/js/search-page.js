// El JavaScript no requiere modificaciones, solo se mueve a este archivo.
function esc(v) {
  if (v == null) return '';
  return String(v)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function normalizeImg(u) {
  const base = 'https://auth.vallasled.com/uploads/';
  if (!u) return 'https://placehold.co/1200x800/0b1220/93c5fd?text=Valla';
  try {
    if (/^https?:\/\//i.test(u)) return u;
    let cleanUrl = String(u).replace(/^\/+/, '');
    if (cleanUrl.startsWith('destacadas/')) cleanUrl = cleanUrl.substring('destacadas/'.length);
    if (cleanUrl.startsWith('uploads/')) cleanUrl = cleanUrl.substring('uploads/'.length);
    return base + cleanUrl;
  } catch (_) {
    return u;
  }
}

function imgTag(url, alt) {
  const src = normalizeImg(url);
  const srcset = `${src}&w=1200 1200w, ${src}&w=800 800w, ${src}&w=480 480w`;
  const sizes = "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw";
  return `<img src="${esc(src)}" srcset="${esc(srcset)}" sizes="${esc(sizes)}" alt="${esc(alt || '')}" loading="lazy" decoding="async">`;
}

document.addEventListener('DOMContentLoaded', function () {
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

  let itemsAll = [];
  let page = 1;
  let pageSize = parseInt(pageSizeSel.value, 10) || 12;
  const selected = new Set();

  function buildQS() {
    const params = new URLSearchParams();
    if (q.value) params.set('q', q.value);
    if (zona.value) params.set('zona', zona.value);
    if (tipo.value) params.set('tipo', tipo.value);
    if (disponibilidad.value !== '') params.set('disponible', disponibilidad.value);
    const provVal = provincia.value;
    if (provVal) {
      if (/^\d+$/.test(provVal)) {
        params.set('provincia', provVal);
      } else {
        const opt = provincia.options[provincia.selectedIndex];
        const nombre = opt?.dataset?.nombre || provVal;
        params.set('provincia_nombre', nombre);
      }
    }
    return params;
  }

  function updateMap() {
    if (!mapaIframe) return;
    const params = buildQS();
    params.set('h', '520');
    mapaIframe.src = '/api/mapa/iframe.php?' + params.toString();
  }

  async function loadFiltros() {
    try {
      const [provRes, zonaRes] = await Promise.all([
        fetch('/api/provincias.php').then(r => r.json()),
        fetch('/api/zonas.php').then(r => r.json())
      ]);
      const provs = Array.isArray(provRes?.data) ? provRes.data : (Array.isArray(provRes) ? provRes : []);
      const zonas = Array.isArray(zonaRes?.data) ? zonaRes.data : (Array.isArray(zonaRes) ? zonaRes : []);

      provs.forEach(p => {
        const id = (p.id ?? p.value ?? '').toString();
        const name = p.nombre ?? p.name ?? '';
        const opt = new Option(name || id, id || name);
        if (name) opt.dataset.nombre = name;
        provincia.add(opt);
      });
      zonas.forEach(z => {
        const name = z.nombre ?? z.name ?? '';
        zona.add(new Option(name || '', name || ''));
      });
    } catch (e) {
      console.error("Error al cargar filtros:", e);
    }
  }

  function sortLedFirst(list) {
    return [...list].sort((a, b) => {
      const aLed = (a.tipo || '').toLowerCase() === 'led' ? 0 : 1;
      const bLed = (b.tipo || '').toLowerCase() === 'led' ? 0 : 1;
      if (aLed !== bLed) return aLed - bLed;
      const ai = parseInt(a.id || 0, 10);
      const bi = parseInt(b.id || 0, 10);
      return bi - ai;
    });
  }

  function liveBadgeHTML(isLed) {
    return isLed ? `<span class="live-badge" title="Señal en vivo"><span class="live-dot"></span>LIVE</span>` : '';
  }

  function actionsHTML(valla, esLed) {
    const id = valla.id;
    const urlDetalleLED = `/detalles-led/?id=${id}`;
    const urlDetalleNoLED = `/detalles-vallas/?id=${id}`;
    return `
      <a class="btn btn-outline" href="/calendario/?id=${esc(id)}" title="Ver en Calendario">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#e2e8f0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        <span>Calendario</span>
      </a>
      ${esLed
        ? `<a class="btn btn-danger" href="${esc(urlDetalleLED)}" title="Señal en vivo">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"></circle><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-10.48-.01a6 6 0 0 1 0-8.49m11.92 1.42a9 9 0 0 1 0 5.66m-14.8 0a9 9 0 0 1 0-5.66"></path></svg>
            <span>En Vivo</span>
          </a>`
        : `<a class="btn btn-accent" href="${esc(urlDetalleNoLED)}" title="Ver ficha">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <span>Ver Ficha</span>
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

  function createVallaCardHTML(valla) {
    const esLed = (valla.tipo || '').toLowerCase() === 'led';
    const mediaUrl = valla.media?.[0]?.url || valla.imagen || '';
    return `
      <div class="card" data-id="${esc(valla.id)}" data-tipo="${esc(valla.tipo ?? '')}">
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
            <p class="text-sm ${parseInt(valla.disponible ? 1 : 0) ? 'text-green-400' : 'text-red-400'}" style="margin-top:.25rem">${parseInt(valla.disponible ? 1 : 0) ? 'Disponible' : 'Ocupado'}</p>
          </div>
          <div class="card-actions">${actionsHTML(valla, esLed)}</div>
        </div>
      </div>`;
  }

  function renderPager(pages, total) {
      pager.innerHTML = '';
      if (pages <= 1) return;

      const frag = document.createDocumentFragment();
      const mkBtn = (label, onClick, active = false, disabled = false) => {
        const b = document.createElement('button');
        b.className = 'page-btn' + (active ? ' active' : '');
        b.type = 'button';
        b.textContent = label;
        b.disabled = disabled;
        b.addEventListener('click', onClick);
        return b;
      };

      const info = document.createElement('div');
      info.style.marginRight = 'auto';
      info.style.opacity = '.8';
      info.style.fontSize = '.9rem';
      info.textContent = `Total: ${total}`;
      pager.appendChild(info);

      frag.appendChild(mkBtn('« Anterior', () => { if (page > 1) { page--; renderPage(); } }, false, page === 1));

      const windowSize = 5;
      let start = Math.max(1, page - Math.floor(windowSize / 2));
      let end = Math.min(pages, start + windowSize - 1);
      start = Math.max(1, end - windowSize + 1);

      const dot = () => {
        const s = document.createElement('span');
        s.style.opacity = '.7';
        s.textContent = '…';
        s.style.padding = '.2rem .4rem';
        return s;
      };

      if (start > 1) frag.appendChild(mkBtn('1', () => { page = 1; renderPage(); }));
      if (start > 2) frag.appendChild(dot());

      for (let p = start; p <= end; p++) {
        frag.appendChild(mkBtn(String(p), () => { page = p; renderPage(); }, p === page));
      }

      if (end < pages - 1) frag.appendChild(dot());
      if (end < pages) frag.appendChild(mkBtn(String(pages), () => { page = pages; renderPage(); }));

      frag.appendChild(mkBtn('Siguiente »', () => { if (page < pages) { page++; renderPage(); } }, false, page === pages));
      pager.appendChild(frag);
  }

  function renderPage() {
    const total = itemsAll.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));
    if (page > pages) page = pages;

    const start = (page - 1) * pageSize;
    const slice = itemsAll.slice(start, start + pageSize);

    vallasGrid.innerHTML = slice.length
      ? slice.map(createVallaCardHTML).join('')
      : '<div id="no-results" style="grid-column: 1 / -1; text-align: center;"><h3 style="font-weight:800">No se encontraron resultados</h3><p class="text-muted">Intenta ajustar los filtros de búsqueda.</p></div>';

    bindCardActions();
    renderPager(pages, total);
    window.scrollTo({ top: document.getElementById('catalogo').offsetTop, behavior: 'smooth' });
  }

  function renderData(list) {
    itemsAll = sortLedFirst(Array.isArray(list) ? list : []);
    page = 1;
    renderPage();
  }

  async function buscar() {
    const params = buildQS();
    try {
      const res = await fetch('/api/buscador.php?' + params.toString());
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      renderData(data.items || data.data || data.results || []);
    } catch (e) {
      console.error("Error en la búsqueda principal, intentando fallback:", e);
      try {
        const alt = await fetch('/api/geo_vallas.php').then(r => r.json());
        renderData(alt.items || alt.data || alt.results || []);
      } catch (fallbackError) {
        console.error("Error en el fallback:", fallbackError);
        renderData([]);
      }
    }
    updateMap();
  }

  function bindCardActions() {
    vallasGrid.querySelectorAll('.btn-select').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const card = btn.closest('.card');
        if (!id || !card) return;
        const selTxt = btn.querySelector('span');
        if (selected.has(id)) {
          selected.delete(id);
          card.classList.remove('selected');
          toast('Quitado de selección');
          if (selTxt) selTxt.textContent = 'Seleccionar';
        } else {
          selected.add(id);
          card.classList.add('selected');
          toast('Seleccionado');
          if (selTxt) selTxt.textContent = 'Seleccionado';
        }
      });
    });

    vallasGrid.querySelectorAll('.btn-add').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        try {
          const r = await fetch(`/carritos/?a=add&id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const j = await r.json();
          toast(j?.message || 'Agregado al carrito');
          updateCartCount();
        } catch (_) {
          toast('No se pudo agregar');
        }
      });
    });
  }

  function updateCartCount() {
    fetch('/carritos/?a=count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(j => {
        const c = document.getElementById('cart-count');
        if (c) c.textContent = String(j.count || 0);
      })
      .catch(() => {});
  }

  async function loadDestacadas() {
    const cont = document.getElementById('featured-row');
    if (!cont) return;
    cont.innerHTML = [...Array(4)].map(() => (`
      <div class="card" aria-hidden="true">
        <div class="card-media" style="background-color: #1f2937;"></div>
        <div class="card-body">
          <div class="card-content">
            <div style="height:1.2rem;background:#334155;border-radius:.25rem;width:80%;"></div>
            <div style="height:1rem;margin-top:8px;background:#334155;border-radius:.25rem;width:50%;"></div>
          </div>
        </div>
      </div>`)).join('');

    try {
      const r = await fetch('/api/featured.php?limit=12');
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const j = await r.json();
      const items = Array.isArray(j.items) ? j.items : [];
      cont.innerHTML = items.length ? items.map(createVallaCardHTML).join('') :
        `<p class="text-muted">No hay vallas destacadas en este momento.</p>`;
      bindCardActions(); // Bind actions for featured cards too
    } catch (_) {
      cont.innerHTML = `<p class="text-muted">No fue posible cargar las vallas destacadas.</p>`;
    }
  }

  categoryCards.forEach(card => {
    card.addEventListener('click', () => {
      const t = card.dataset.tipo || '';
      tipo.value = t;
      buscar();
      document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
    });
  });

  btnBuscar.addEventListener('click', buscar);
  pageSizeSel.addEventListener('change', () => {
    pageSize = parseInt(pageSizeSel.value, 10) || 12;
    page = 1;
    renderPage();
  });

  function initFAB() {
    let el = document.querySelector('.fab');
    if (!el) {
      el = document.createElement('a');
      el.className = 'fab';
      el.href = '/carritos/';
      el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="fab-badge" id="cart-count">0</span>`;
      document.body.appendChild(el);
    }
    let wa = document.querySelector('.fab-wa');
    if (!wa) {
      wa = document.createElement('a');
      wa.className = 'fab-wa';
      wa.href = '/api/whatsapp/api.php?a=chat';
      wa.target = '_blank';
      wa.rel = 'noopener';
      wa.title = 'Contactar por WhatsApp';
      wa.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 448 512" fill="currentColor"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32 119.9 32 32 120 32 224.1c0 40.6 10.6 80.2 30.7 114.9L32 480l143.7-37.5c34.5 20.3 73.1 32.2 114.2 32.2 104.9 0 192-87.1 192-192.1 0-58.6-23.1-114.4-65.1-156.6zM223.9 439.1c-39.6 0-77.5-12.3-108.9-35.3L64 425.6l21.5-51.4c-25.3-33.8-40.5-75.3-40.5-119.9 0-88.4 72.1-160.5 160.5-160.5S384.4 135.7 384.4 224.1c0 88.4-72.1 160.5-160.5 160.5zM302.4 278.6c-4.4-2.2-26.2-12.9-30.2-14.4-4-1.5-7-2.2-10 2.2-3 4.4-11.4 14.4-14 17.3-2.6 3-5.2 3.3-9.6 1.1-4.4-2.2-18.6-6.9-35.4-21.8-13.1-11.6-21.9-26-24.5-30.5-2.6-4.4-.3-6.9 1.9-9.1 2-2 4.4-5.2 6.6-7.8 2.2-2.6 3-4.4 4.4-7.3s.7-5.2-.4-7.3c-1.1-2.2-10-24.1-13.6-33-3.6-8.8-7.3-7.6-10-7.7-2.6-.1-5.2-.1-7.8-.1-2.6 0-7 1.1-10.6 4.4-3.6 3.3-13.6 13.4-13.6 32.7 0 19.3 13.9 37.9 15.8 40.5 1.9 2.6 27.4 41.6 66.4 58.4 9.6 4.1 17.1 6.5 22.9 8.4 9.5 3 18.1 2.6 24.9 1.5 7.8-1.1 26.2-10.7 29.9-21.1 3.6-10.4 3.6-19.3 2.6-21.1-1.1-1.8-4.4-3.3-8.8-5.5z"/></svg>`;
      document.body.appendChild(wa);
    }
    updateCartCount();
  }

  function toast(txt) {
    let t = document.getElementById('toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'toast';
      document.body.appendChild(t);
    }
    t.textContent = txt;
    t.classList.add('show');
    setTimeout(() => {
      t.classList.remove('show');
    }, 1800);
  }

  // Initial Load
  loadFiltros().then(() => {
    buscar();
    updateMap();
  });
  loadDestacadas();
  initFAB();
});
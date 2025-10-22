(function () {
  'use strict';

  // ------------ helpers ------------
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const CSRF = document.querySelector('meta[name="csrf"]')?.content
            || document.querySelector('input[name="csrf"]')?.value || '';
  const fmt = n => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(+n || 0);
  const today = () => new Date().toLocaleDateString('es-DO');

  async function jGET(url, params = {}) {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k, v]) => { if (v !== '' && v != null) u.searchParams.set(k, v); });
    const r = await fetch(u, { credentials: 'same-origin', headers: { 'X-CSRF': CSRF } });
    const t = await r.text();
    let j = null; try { j = JSON.parse(t); } catch {}
    if (!r.ok || !j) throw new Error(`${r.status} ${r.statusText} :: ${t.slice(0, 200)}`);
    if (j.ok === false) throw new Error(j.msg || 'Error');
    return j;
  }
  async function jPOST(url, data) {
    const body = new URLSearchParams();
    Object.entries(data || {}).forEach(([k, v]) => body.append(k, v == null ? '' : String(v)));
    const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': CSRF }, body });
    const t = await r.text();
    let j = null; try { j = JSON.parse(t); } catch {}
    if (!r.ok || !j) throw new Error(`${r.status} ${r.statusText} :: ${t.slice(0, 200)}`);
    if (j.ok !== true) throw new Error(j.msg || 'Operación fallida');
    return j;
  }
  function toast(msg, ms = 2200) {
    const n = $('#notification'); if (!n) return;
    n.textContent = msg; n.classList.remove('hidden', 'opacity-0');
    setTimeout(() => n.classList.add('opacity-0'), ms);
    setTimeout(() => n.classList.add('hidden'), ms + 350);
  }

  // ------------ theme ------------
  (function theme() {
    const html = document.documentElement, btn = $('#theme-toggle');
    const moon = $('#theme-toggle-dark-icon'), sun = $('#theme-toggle-light-icon');
    const apply = m => { if (m === 'dark') { html.classList.add('dark'); moon?.classList.remove('hidden'); sun?.classList.add('hidden'); } else { html.classList.remove('dark'); moon?.classList.add('hidden'); sun?.classList.remove('hidden'); } };
    let mode = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    apply(mode);
    btn?.addEventListener('click', () => { mode = html.classList.contains('dark') ? 'light' : 'dark'; localStorage.setItem('theme', mode); apply(mode); });
  })();

  // ------------ sidebar ------------
  (function sidebar() {
    const body = document.body;
    const aside = $('aside.sidebar');
    const main = $('main.main-content');

    let ov = $('#sidebar-overlay');
    if (!ov) {
      ov = document.createElement('div');
      ov.id = 'sidebar-overlay';
      ov.className = 'fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden';
      document.body.appendChild(ov);
    }

    const openMobile = () => { body.classList.add('sidebar-open'); ov.classList.remove('hidden'); };
    const closeMobile = () => { body.classList.remove('sidebar-open'); ov.classList.add('hidden'); };

    const LS_KEY = 'sidebarHidden';
    function applyDesktopState(fromStorage = true) {
      const hidden = fromStorage ? localStorage.getItem(LS_KEY) === '1' : aside?.dataset.state === 'hidden';
      if (!aside || !main) return;
      if (hidden) {
        aside.style.transform = 'translateX(-100%)';
        aside.style.transition = 'transform .25s ease';
        main.style.marginLeft = '0';
        aside.dataset.state = 'hidden';
      } else {
        aside.style.transform = '';
        main.style.marginLeft = '';
        aside.dataset.state = '';
      }
    }
    function toggleDesktop() {
      if (!aside || !main) return;
      const hide = aside.dataset.state !== 'hidden';
      localStorage.setItem(LS_KEY, hide ? '1' : '0');
      applyDesktopState(false);
      setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
    }

    $('#mobile-menu-button')?.addEventListener('click', (e) => {
      e.preventDefault();
      if (body.classList.contains('sidebar-open')) closeMobile(); else openMobile();
    });
    $('#sidebar-toggle-desktop')?.addEventListener('click', (e) => { e.preventDefault(); toggleDesktop(); });
    ov?.addEventListener('click', closeMobile);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeMobile(); } });

    $$('.submenu-trigger').forEach(btn => btn.addEventListener('click', () => {
      if (aside?.dataset.state === 'hidden') return;
      btn.classList.toggle('submenu-open');
      btn.nextElementSibling?.classList.toggle('hidden');
    }));

    const mq = matchMedia('(min-width:768px)');
    const onMQ = () => { if (mq.matches) { closeMobile(); applyDesktopState(true); } };
    mq.addEventListener('change', onMQ);
    onMQ();
  })();

  // ------------ refs ------------
  const form = $('#create-invoice-form');
  const selBase = $('#cliente-base-select');
  const selCRM = $('#cliente-crm-select');
  const selValla = $('#valla-item-select');
  const selProv  = $('#proveedor-select');
  const inMonto = $('#monto');
  const inDesc = $('#descuento');
  const inNotas = $('#notas');

  const prevNom = $('#preview-cliente-nombre');
  const prevRNC = $('#preview-cliente-rnc');
  const prevFecha = $('#preview-fecha');
  const prevItemDesc = $('#preview-item-desc');
  const prevItemMonto = $('#preview-item-monto');
  const prevSub = $('#preview-subtotal');
  const prevDes = $('#preview-descuento');
  const prevTotal = $('#preview-total');

  let currentCliente = null; // {id,tipo,nombre,email,rnc}

  // ------------ tabs ------------
  (function tabs() {
    const tabs = $$('.tab-button');
    const views = { existente: $('#tab-content-existente'), nuevo: $('#tab-content-nuevo') };
    tabs.forEach(t => t.addEventListener('click', () => {
      tabs.forEach(x => x.classList.remove('active', 'text-indigo-600', 'border-indigo-500'));
      tabs.forEach(x => x.classList.add('text-[var(--text-secondary)]', 'border-transparent'));
      t.classList.add('active', 'text-indigo-600', 'border-indigo-500');
      t.classList.remove('text-[var(--text-secondary)]', 'border-transparent');
      Object.values(views).forEach(v => v?.classList.add('hidden'));
      views[t.dataset.tab]?.classList.remove('hidden');
    }));
  })();

  // ------------ Select2 + AJAX ------------
  function initSelect2(el, url, mapFn, onSel) {
    if (!el || !window.jQuery || !jQuery.fn.select2) return;
    jQuery(el).select2({
      placeholder: 'Buscar...',
      allowClear: true,
      width: '100%',
      minimumInputLength: 0,
      ajax: {
        transport: (params, success, failure) => {
          const u = new URL(url, location.origin);
          const q = params.data?.q || '';
          const limit = params.data?.limit || 50;
          u.searchParams.set('q', q);
          u.searchParams.set('limit', limit);
          u.searchParams.set('_', Date.now());

          // pista de proveedor/valla para endpoints que lo acepten
          const vSel = (window.jQuery ? jQuery(selValla).val() : '') || '';
          const pSel = (window.jQuery ? jQuery(selProv).val()  : '') || '';
          if (el === selCRM) {
            if (vSel) u.searchParams.set('valla_id', vSel);
            else if (pSel) u.searchParams.set('proveedor_id', pSel);
          }

          fetch(u, { credentials: 'same-origin', headers: { 'X-CSRF': CSRF } })
            .then(r => r.text())
            .then(t => { try { success(JSON.parse(t)); } catch (e) { failure(e); toast('Error de listado'); console.error('Listado fallo', url, t); } })
            .catch(failure);
        },
        delay: 300,
        processResults: (data) => {
          const arr = Array.isArray(data) ? data : (data?.items || []);
          return { results: arr.map(mapFn) };
        }
      }
    }).on('select2:select', e => onSel?.(e.params.data));
  }

  // Proveedores
  initSelect2(
    selProv,
    '/console/facturacion/facturas/ajax/buscar_proveedores.php',
    it => ({ id: it.id, text: it.nombre }),
    () => { /* nada; solo mantener selección */ }
  );

  // clientes base
  initSelect2(
    selBase,
    '/console/facturacion/facturas/ajax/buscar_clientes_base.php',
    it => ({ id: it.id, text: (it.nombre || it.email || `Cliente #${it.id}`), email: it.email || '', rnc: it.rnc || '', tipo: 'base' }),
    d => {
      currentCliente = { id: d.id, tipo: 'base', nombre: d.text, email: d.email || '', rnc: d.rnc || '' };
      prevNom.textContent = currentCliente.nombre || 'Cliente';
      prevRNC.textContent = `RNC: ${currentCliente.rnc || 'N/A'}`;
      if (window.jQuery) jQuery(selCRM).val(null).trigger('change');
    }
  );

  // clientes CRM
  initSelect2(
    selCRM,
    '/console/facturacion/facturas/ajax/buscar_clientes_crm.php',
    it => ({ id: it.id, text: (it.empresa ? `${it.empresa} (${it.nombre || '-'})` : (it.nombre || `CRM #${it.id}`)), email: it.email || '', rnc: it.rnc || '', tipo: 'crm' }),
    d => {
      currentCliente = { id: d.id, tipo: 'crm', nombre: d.text, email: d.email || '', rnc: d.rnc || '' };
      prevNom.textContent = currentCliente.nombre || 'Cliente CRM';
      prevRNC.textContent = `RNC: ${currentCliente.rnc || 'N/A'}`;
      if (window.jQuery) jQuery(selBase).val(null).trigger('change');
    }
  );

  // vallas
  initSelect2(
    selValla,
    '/console/facturacion/facturas/ajax/listar_vallas.php',
    it => ({ id: it.id, text: it.nombre, precio: +(it.precio_est || 0) }),
    d => {
      if (+d.precio > 0 && inMonto) inMonto.value = d.precio;
      prevItemDesc.textContent = `Servicio: ${d.text}`;
      updatePreview();
      previewComision().catch(() => {});
    }
  );

  // ------------ preview ------------
  function updatePreview() {
    const monto = parseFloat(inMonto?.value || '0') || 0;
    const desc = parseFloat(inDesc?.value || '0') || 0;
    const subtotal = monto;
    const total = Math.max(monto - desc, 0);
    prevFecha && (prevFecha.textContent = today());
    prevItemMonto && (prevItemMonto.textContent = fmt(monto));
    prevSub && (prevSub.textContent = fmt(subtotal));
    prevDes && (prevDes.textContent = fmt(desc));
    prevTotal && (prevTotal.textContent = fmt(total));
  }
  inMonto?.addEventListener('input', updatePreview);
  inDesc?.addEventListener('input', updatePreview);
  updatePreview();

  async function previewComision() {
    const vId = window.jQuery ? jQuery(selValla).val() : null;
    if (!vId) return;
    try {
      const r = await jGET('/console/facturacion/facturas/ajax/preview_comision.php', { valla_id: vId });
      const pct = (typeof r.pct === 'number') ? r.pct : 0.10;
      const monto = parseFloat(inMonto?.value || '0') || 0;
      const el = $('#comision-preview'); if (el) el.textContent = fmt(monto * pct);
    } catch { /* silencio */ }
  }

  // ------------ crear cliente rápido ------------
  $('#btn-crear-cliente')?.addEventListener('click', async () => {
    const vallaSel = window.jQuery ? jQuery(selValla).val() : null;
    const provSel  = window.jQuery ? jQuery(selProv).val()  : null;

    // si no hay valla, exigir proveedor
    if (!vallaSel && !provSel) return toast('Seleccione proveedor o valla');

    const payload = {
      empresa: $('#nuevo-empresa')?.value.trim() || '',
      responsable: $('#nuevo-responsable')?.value.trim() || '',
      email: $('#nuevo-email')?.value.trim() || '',
      telefono: $('#nuevo-telefono')?.value.trim() || '',
      rnc: $('#nuevo-rnc')?.value.trim() || '',
      direccion: $('#nuevo-direccion')?.value.trim() || '',
      valla_id: vallaSel ? parseInt(vallaSel, 10) : '',
      proveedor_id: (!vallaSel && provSel) ? parseInt(provSel, 10) : ''
    };
    const nombre = payload.responsable || payload.empresa;
    if (!nombre) return toast('Nombre requerido');

    try {
      const r = await jPOST('/console/facturacion/facturas/ajax/crear_cliente_rapido.php', payload);
      toast('Cliente creado');
      if (selCRM && window.jQuery) {
        const label = (r.nombre || r.email || `Cliente #${r.id}`) + (r.email ? ` (${r.email})` : '');
        const op = new Option(label, r.id, true, true);
        jQuery(selCRM).append(op).trigger('change');
        jQuery(selBase).val(null).trigger('change');
      }
      currentCliente = { id: r.id, tipo: 'crm', nombre: r.nombre || payload.empresa || payload.responsable, email: r.email || payload.email || '', rnc: '' };
      prevNom.textContent = currentCliente.nombre;
      prevRNC.textContent = 'RNC: N/A';
    } catch (e) { toast(e.message || 'Error'); }
  });

  // ------------ copiar banco ------------
  $('#copy-bank-details')?.addEventListener('click', () => {
    const box = $('#bank-details'); if (!box) return;
    const txt = [...box.querySelectorAll('p')].map(p => p.innerText.trim()).join('\n');
    const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    toast('Datos bancarios copiados');
  });

  // ------------ share modal ------------
  (function shareModalInit() {
    const modal = $('#share-modal'), box = $('#share-modal-container');
    const txt = $('#share-modal-text'), urlInput = $('#share-url');
    $('#copy-url-btn')?.addEventListener('click', () => { urlInput?.select(); document.execCommand('copy'); toast('Enlace copiado'); });
    $('#close-share-modal-btn')?.addEventListener('click', closeShare);
    modal?.addEventListener('click', e => { if (e.target === modal) closeShare(); });
    function openShare(id, url) {
      if (txt) txt.textContent = `La factura #${id} ha sido creada con éxito.`;
      if (urlInput) { try { urlInput.value = new URL(url, location.origin).href; } catch { urlInput.value = url; } }
      modal?.classList.remove('hidden');
      requestAnimationFrame(() => { modal?.classList.remove('opacity-0'); box?.classList.remove('scale-95'); });
    }
    function closeShare() { box?.classList.add('scale-95'); modal?.classList.add('opacity-0'); setTimeout(() => modal?.classList.add('hidden'), 200); }
    window.showShareModal = openShare;
  })();

  // ------------ crear factura ------------
  form?.addEventListener('submit', async e => {
    e.preventDefault();
    const vallaId = window.jQuery ? jQuery(selValla).val() : null;
    const provId  = window.jQuery ? jQuery(selProv).val()  : null;

    const monto = parseFloat(inMonto?.value || '0') || 0;
    const desc = parseFloat(inDesc?.value || '0') || 0;
    if (!(monto > 0)) return toast('Monto inválido');
    if (!currentCliente) return toast('Seleccione o cree un cliente');

    // si no hay valla, exigir proveedor
    if (!vallaId && !provId) return toast('Seleccione proveedor o valla');

    const payload = {
      cliente_id: currentCliente.tipo === 'base' ? currentCliente.id : '',
      crm_cliente_id: currentCliente.tipo === 'crm' ? currentCliente.id : '',
      cliente_nombre: currentCliente.nombre || '',
      cliente_email: currentCliente.email || '',
      valla_id: vallaId ? parseInt(vallaId, 10) : '',
      proveedor_id: (!vallaId && provId) ? parseInt(provId, 10) : '',
      monto, descuento: desc,
      metodo_pago: 'transferencia',
      notas: inNotas?.value || ''
    };

    try {
      const r = await jPOST('/console/facturacion/facturas/ajax/create.php', payload);
      toast('Factura creada');
      const url = r.share_url || (`/console/facturacion/facturas/ver.php?id=${r.id}`);
      if (window.showShareModal) window.showShareModal(r.id, url); else location.href = url;
    } catch (e) { toast(e.message || 'Error al crear'); }
  });

  prevFecha && (prevFecha.textContent = today());
})();

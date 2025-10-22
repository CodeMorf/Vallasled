/* /console/asset/js/ads/ads.js */
/* eslint-disable */
(function () {
  "use strict";

  // ------ helpers
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const CSRF = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const cfg = Object.freeze(window.ADS_CFG || { endpoints: {}, page: { limit: 24 } });

  const grid   = $('#ads-grid');
  if (!grid) return; // no iniciar fuera del módulo

  const noRes  = $('#no-results');
  const pager  = $('#ads-pager');
  const pgPrev = $('#pg-prev');
  const pgNext = $('#pg-next');
  const pgInfo = $('#pg-info');

  const fQ     = $('#f-q');
  const fProv  = $('#f-prov');
  const fClear = $('#f-clear');

  // modal
  const dlgBackdrop = $('#dlg-ad');
  const dlgTitle = $('#dlg-title');
  const inpId   = $('#ad-id');
  const selValla= $('#ad-valla');
  const inpOrden= $('#ad-orden');
  const inpDesde= $('#ad-desde');
  const inpHasta= $('#ad-hasta');
  const inpMonto= $('#ad-monto');
  const inpObs  = $('#ad-obs');
  const btnSave = $('#ad-save');
  const btnCancel = $('#ad-cancel');
  const btnClose  = $('#dlg-close');

  // toast
  const toast = $('#toast');
  const toastMsg = $('#toast-msg');

  const money = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: 2 });
  const dmy = v => v ? new Date(v).toLocaleDateString('es-DO') : '—';

  let state = {
    q: '',
    prov_id: '',
    limit: (cfg.page && cfg.page.limit) || 24,
    offset: 0,
    total: 0,
    rows: [],
    options: { proveedores: [], vallas: [] }
  };

  function showToast(msg) {
    if (!toast || !toastMsg) return;
    toastMsg.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 2400);
  }

  function openModal() {
    if (!dlgBackdrop) return;
    dlgBackdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    if (!dlgBackdrop) return;
    dlgBackdrop.classList.remove('show');
    document.body.style.overflow = '';
  }

  function resetForm() {
    inpId.value = '';
    selValla.value = '';
    inpOrden.value = '1';
    inpDesde.value = '';
    inpHasta.value = '';
    inpMonto.value = '';
    inpObs.value = '';
  }

  function setForm(row) {
    inpId.value = row?.id || '';
    selValla.value = row?.valla_id || '';
    inpOrden.value = row?.orden ?? 1;
    inpDesde.value = row?.fecha_inicio || '';
    inpHasta.value = row?.fecha_fin || '';
    inpMonto.value = row?.monto_pagado ?? '';
    inpObs.value = row?.observacion ?? '';
  }

  // --- Sanitize URL for images to prevent javascript: etc.
  function sanitizeUrl(u) {
    try {
      if (!u) return '';
      const url = new URL(u, location.origin);
      const proto = url.protocol.replace(':','');
      if (proto === 'http' || proto === 'https') return url.href;
      return '';
    } catch { return ''; }
  }

  // secure fetch wrapper with timeout, same-origin cookies, CSRF header
  async function postJSON(url, data) {
    const body = new URLSearchParams({ csrf: CSRF, ...data });
    const ac = new AbortController();
    const t = setTimeout(() => ac.abort(), 15000); // 15s
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'error',
        referrerPolicy: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF
        },
        body,
        signal: ac.signal
      });
    } finally {
      clearTimeout(t);
    }
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error('Tipo de respuesta inválido');
    return res.json();
  }

  // ------- OPTIONS
  async function cargarOpciones() {
    const data = await postJSON(cfg.endpoints.opciones, {});
    state.options.proveedores = Array.isArray(data.proveedores) ? data.proveedores : [];
    state.options.vallas = Array.isArray(data.vallas) ? data.vallas : [];

    // filtros proveedor
    if (fProv) {
      fProv.innerHTML = `<option value="">Todos los proveedores</option>` +
        state.options.proveedores.map(p => `<option value="${String(p.id)}">${escapeHtml(p.nombre)}</option>`).join('');
    }

    // select de vallas
    if (selValla) {
      selValla.innerHTML = `<option value="">Seleccione una valla...</option>` +
        state.options.vallas.map(v => `<option value="${String(v.id)}">#${String(v.id)} - ${escapeHtml(v.nombre || '')}</option>`).join('');
    }
  }

  // ------- LIST
  function renderCard(r) {
    const fallback = `https://placehold.co/600x400/374151/FFFFFF?text=Valla+${encodeURIComponent(r.valla_id)}`;
    const safeImg = sanitizeUrl(r.valla_imagen) || fallback;
    const periodo = `${dmy(r.fecha_inicio)} - ${dmy(r.fecha_fin)}`;
    return `
    <div class="ad-card bg-[var(--card-bg)] rounded-xl shadow-md overflow-hidden flex flex-col transition-all duration-300" data-id="${Number(r.id)}">
      <img src="${safeImg}" alt="Valla ${Number(r.valla_id)}" class="w-full h-48 object-cover" loading="lazy" referrerpolicy="no-referrer">
      <div class="p-5 flex-grow flex flex-col">
        <h3 class="font-bold text-lg mb-2">#${Number(r.valla_id)} - ${escapeHtml(r.valla_nombre || '')}</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-4 flex items-center gap-2"><i class="fas fa-truck-field"></i> ${escapeHtml(r.proveedor_nombre || '—')}</p>
        <div class="mt-auto space-y-3 text-sm">
          <div class="flex justify-between items-center"><span class="font-semibold">Período:</span><span>${periodo}</span></div>
          <div class="flex justify-between items-center"><span class="font-semibold">Monto:</span><span class="font-bold text-green-600">${money.format(Number(r.monto_pagado || 0))}</span></div>
          <div class="flex justify-between items-center"><span class="font-semibold">Orden:</span><span class="font-bold text-indigo-500">${Number(r.orden || 1)}</span></div>
        </div>
      </div>
      <div class="bg-[var(--main-bg)] p-3 flex justify-end gap-2 border-t border-[var(--border-color)]">
        <button class="btn-edit py-2 px-3 rounded-lg text-sm font-semibold text-yellow-600 dark:text-yellow-400 hover:bg-yellow-100 dark:hover:bg-yellow-500/20"><i class="fas fa-pencil-alt mr-1"></i> Editar</button>
        <button class="btn-del py-2 px-3 rounded-lg text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20"><i class="fas fa-trash-alt mr-1"></i> Eliminar</button>
      </div>
    </div>`;
  }

  function renderGrid(rows) {
    grid.innerHTML = rows.map(renderCard).join('');
    const has = rows.length > 0;
    grid.classList.toggle('hidden', !has);
    noRes?.classList.toggle('hidden', has);
  }

  function renderPager() {
    if (!pager) return;
    if (state.total <= state.limit) { pager.classList.add('hidden'); return; }
    pager.classList.remove('hidden');
    const from = state.total ? state.offset + 1 : 0;
    const to = Math.min(state.offset + state.limit, state.total);
    if (pgInfo) pgInfo.textContent = `${from}–${to} de ${state.total}`;
    pgPrev?.toggleAttribute('disabled', state.offset <= 0);
    pgNext?.toggleAttribute('disabled', state.offset + state.limit >= state.total);
  }

  async function listar(offset = 0) {
    state.offset = Math.max(0, offset);
    const data = await postJSON(cfg.endpoints.listar, {
      q: state.q,
      prov_id: state.prov_id,
      limit: String(state.limit),
      offset: String(state.offset)
    });
    state.rows = Array.isArray(data.rows) ? data.rows : [];
    state.total = Number.isFinite(+data.total) ? +data.total : state.rows.length;
    renderGrid(state.rows);
    renderPager();
  }

  // ------- CRUD
  let saving = false;
  async function guardar() {
    if (saving) return;
    // validaciones
    const valla_id = Number(selValla?.value || 0);
    const orden = Math.max(1, parseInt(inpOrden?.value || '1', 10));
    const desde = inpDesde?.value || '';
    const hasta = inpHasta?.value || '';
    const monto = inpMonto?.value !== '' ? Number(inpMonto.value) : 0;
    const obs   = (inpObs?.value || '').trim();

    if (!valla_id) { showToast('Seleccione una valla.'); return; }
    if (!desde || !hasta) { showToast('Complete fechas.'); return; }
    if (new Date(desde) > new Date(hasta)) { showToast('Rango de fechas inválido.'); return; }
    if (!Number.isFinite(monto) || monto < 0) { showToast('Monto inválido.'); return; }

    const payload = {
      id: inpId?.value || '',
      valla_id: String(valla_id),
      orden: String(orden),
      desde, hasta,
      monto: String(monto),
      obs
    };

    try {
      saving = true;
      btnSave?.setAttribute('disabled','true');
      const res = await postJSON(cfg.endpoints.guardar, payload);
      if (res.error) { showToast(res.error); return; }
      closeModal();
      showToast('Guardado.');
      await listar(state.offset);
    } catch (err) {
      console.error(err);
      showToast('Error al guardar.');
    } finally {
      saving = false;
      btnSave?.removeAttribute('disabled');
    }
  }

  async function eliminar(id) {
    if (!confirm('¿Eliminar anuncio?')) return;
    try {
      const res = await postJSON(cfg.endpoints.eliminar, { id: String(id) });
      if (res.error) { showToast(res.error); return; }
      showToast('Eliminado.');
      const after = Math.max(0, state.offset - state.limit);
      await listar(state.offset);
      if (state.rows.length === 0 && state.offset > 0) await listar(after);
    } catch (err) {
      console.error(err);
      showToast('Error al eliminar.');
    }
  }

  // ------- events
  grid.addEventListener('click', (e) => {
    const card = e.target.closest('.ad-card');
    if (!card) return;
    const id = Number(card.dataset.id);
    if (e.target.closest('.btn-edit')) {
      const row = state.rows.find(r => Number(r.id) === id);
      if (!row) return;
      if (dlgTitle) dlgTitle.textContent = 'Editar Anuncio';
      setForm(row);
      openModal();
    } else if (e.target.closest('.btn-del')) {
      eliminar(id);
    }
  });

  if (fQ) {
    fQ.addEventListener('input', (e) => {
      let value = e.target.value.trim();
      if (value.length > 200) value = value.slice(0, 200); // límite
      state.q = value;
      clearTimeout(fQ._t);
      fQ._t = setTimeout(() => listar(0).catch(err => { console.error(err); showToast('Error listando.'); }), 250);
    });
  }

  fProv?.addEventListener('change', (e) => {
    state.prov_id = e.target.value || '';
    listar(0).catch(err => { console.error(err); showToast('Error listando.'); });
  });

  fClear?.addEventListener('click', (e) => {
    e.preventDefault();
    if (fQ) fQ.value = '';
    if (fProv) fProv.value = '';
    state.q = '';
    state.prov_id = '';
    listar(0).catch(err => { console.error(err); showToast('Error listando.'); });
  });

  pgPrev?.addEventListener('click', (e) => { e.preventDefault(); listar(Math.max(0, state.offset - state.limit)).catch(err => { console.error(err); showToast('Error listando.'); }); });
  pgNext?.addEventListener('click', (e) => { e.preventDefault(); listar(state.offset + state.limit).catch(err => { console.error(err); showToast('Error listando.'); }); });

  btnSave?.addEventListener('click', (e) => { e.preventDefault(); guardar(); });
  btnCancel?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); });
  btnClose?.addEventListener('click', (e) => { e.preventDefault(); closeModal(); });

  // esc para cerrar modal
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') closeModal();
  });
  // click fuera para cerrar
  dlgBackdrop?.addEventListener('click', (ev) => {
    if (ev.target === dlgBackdrop) closeModal();
  });

  // api pública simple
  window.ADS = {
    openCreate() {
      if (dlgTitle) dlgTitle.textContent = 'Crear Anuncio';
      resetForm();
      openModal();
    }
  };

  function escapeHtml(s) {
    return String(s ?? '')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  // init
  (async function init(){
    try {
      await cargarOpciones();
      await listar(0);
    } catch (e) {
      console.error(e);
      showToast('Error inicializando anuncios.');
    }
  })();

})();

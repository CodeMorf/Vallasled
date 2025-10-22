// /console/asset/js/vendors/index.js
(function () {
  'use strict';

  /* ===== Helpers ===== */
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const on = (el, ev, fn) => el && el.addEventListener(ev, fn);
  const escapeHtml = s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const num = v => Number.isFinite(+v) ? +v : 0;
  const fmtMoney = v => new Intl.NumberFormat('es-DO',{style:'currency',currency:'DOP',maximumFractionDigits:2}).format(num(v));
  const debounce = (fn, ms=250) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };
  const cfg = window.VENDORS_CFG || {};
  const CSRF = cfg.csrf || $('meta[name="csrf"]')?.content || '';
  const EP = cfg.endpoints || {};
  const PER_PAGE = cfg.page?.limit || 20;

  /* ===== Tema claro/oscuro (solo módulo) ===== */
  const btnTheme = $('#theme-toggle');
  const iconMoon = $('#theme-toggle-dark-icon');
  const iconSun  = $('#theme-toggle-light-icon');
  const applyTheme = t => {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    iconMoon && iconMoon.classList.toggle('hidden', !dark);
    iconSun  && iconSun.classList.toggle('hidden',  dark);
  };
  const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(saved);
  on(btnTheme, 'click', () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
  });

  /* ===== Toast ===== */
  const toast = (msg, ok=true) => {
    const n = document.createElement('div');
    n.className = `toast ${ok?'ok':'err'}`;
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(()=>{ n.remove(); }, 2600);
  };

  /* ===== Fetch JSON (CSRF en header y JSON) ===== */
  const fetchJSON = (url, opts = {}) => {
    const method = (opts.method || 'GET').toUpperCase();
    const headers = { ...(opts.headers || {}) };
    const final = {
      credentials: 'same-origin',
      ...opts,
      method,
      headers: { 'X-CSRF-Token': CSRF, ...headers }
    };

    // Para no-GET y no-FormData, enviar JSON con {csrf,...}
    if (method !== 'GET' && !(final.body instanceof FormData)) {
      const payload = (final.body && typeof final.body === 'object') ? final.body : {};
      final.body = JSON.stringify({ csrf: CSRF, ...payload });
      final.headers['Content-Type'] = 'application/json; charset=utf-8';
    }

    return fetch(url, final).then(r => r.json());
  };

  /* ===== State ===== */
  let state = {
    q: '', estado: '', plan_id: '', feature: '', sort: 'recientes',
    page: 1, per_page: PER_PAGE, total: 0, rows: []
  };

  /* ===== UI refs ===== */
  const $tbody   = $('#vendors-tbody');
  const $empty   = $('#vendors-empty');
  const $cards   = $('#vendors-cards');
  const $emptyCards = $('#vendors-empty-cards');
  const $pager   = $('#vendors-pager');

  const $kpiTotal = $('#kpi-total');
  const $kpiPlanes= $('#kpi-planes');
  const $kpiVallas= $('#kpi-vallas');
  const $kpiPend  = $('#kpi-pendiente');

  const $fQ      = $('#f-q');
  const $fEstado = $('#f-estado');
  const $fPlan   = $('#f-plan');
  const $fFeature= $('#f-feature');
  const $fSort   = $('#f-sort');

  const $modal      = $('#vendorModal');
  const $modalTitle = $('#vendor-modal-title');
  const $btnCreate  = $('#create-vendor-btn');
  const $form       = $('#vendor-form');

  /* ===== Render: desktop ===== */
  const planBadge = (name) => {
    const n = (name || '').toString().toLowerCase();
    const map = n.includes('prem') ? 'purple' : n.includes('pro') ? 'blue' : n.includes('gratis') ? 'gray' : 'green';
    return `<span class="badge ${map}">${escapeHtml(name||'—')}</span>`;
  };
  const estadoDot = v => `<span class="dot ${v ? 'ok':'off'}"><i class="fas fa-${v?'check':'times'} text-xs"></i></span>`;

  const renderRows = (rows) => {
    if (!$tbody) return;
    if (!rows || rows.length === 0) {
      $tbody.innerHTML = '';
      $empty && $empty.classList.remove('hidden');
      return;
    }
    $empty && $empty.classList.add('hidden');
    const html = rows.map(r => {
      const vallasTxt = `<span class="text-emerald-600 font-semibold">${r.vallas_activas ?? 0}</span> / <span class="text-gray-400">${r.vallas_total ?? 0}</span>`;
      const factTxt = `<div class="text-emerald-600">${fmtMoney(r.total_pagado||0)} pagado</div><div class="${(r.total_pendiente||0)>0?'text-amber-600':'text-gray-400'}">${fmtMoney(r.total_pendiente||0)} pendiente</div>`;
      return `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/20">
          <td class="px-6 py-4">
            <div class="font-bold">${escapeHtml(r.nombre)}</div>
            <div class="text-xs text-[var(--text-secondary)]">${escapeHtml(r.email||'—')}</div>
          </td>
          <td class="px-6 py-4">${planBadge(r.plan_nombre)}</td>
          <td class="px-6 py-4 text-center">
            ${vallasTxt}
          </td>
          <td class="px-6 py-4">
            ${factTxt}
          </td>
          <td class="px-6 py-4 text-center">${estadoDot(+r.estado===1)}</td>
          <td class="px-6 py-4">
            <button class="edit-vendor p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" data-id="${r.id}">
              <i class="fas fa-pencil-alt"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
    $tbody.innerHTML = html;
    $$('.edit-vendor', $tbody).forEach(btn => on(btn, 'click', () => openModal('Editar Vendor', btn.dataset.id)));
  };

  /* ===== Render: móvil (cards) ===== */
  const renderCards = (rows) => {
    if (!$cards) return;
    if (!rows || rows.length === 0) {
      $cards.innerHTML = '';
      $emptyCards && $emptyCards.classList.remove('hidden');
      return;
    }
    $emptyCards && $emptyCards.classList.add('hidden');

    const html = rows.map(r => {
      const vallas = `${r.vallas_activas ?? 0} / ${r.vallas_total ?? 0}`;
      const pagado = fmtMoney(r.total_pagado||0);
      const pendiente = fmtMoney(r.total_pendiente||0);
      const estado = +r.estado===1;

      return `
        <div class="vendor-card" data-id="${r.id}">
          <div class="left">
            <div class="vendor-title">${escapeHtml(r.nombre)}</div>
            <div class="vendor-sub">${escapeHtml(r.email||'—')}</div>
            <div class="stat-row">
              ${planBadge(r.plan_nombre)}
              <span class="stat-pill"><i class="fas fa-ad"></i>${vallas}</span>
              <span class="stat-pill"><i class="fas fa-check-circle"></i>${pagado}</span>
              <span class="stat-pill"><i class="fas fa-clock"></i>${pendiente}</span>
            </div>
          </div>
          <div class="right">
            ${estadoDot(estado)}
            <button class="btn-icon edit-vendor" aria-label="Editar" data-id="${r.id}">
              <i class="fas fa-pencil-alt"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');
    $cards.innerHTML = html;
    $$('.edit-vendor', $cards).forEach(btn => on(btn, 'click', () => openModal('Editar Vendor', btn.dataset.id)));
  };

  /* ===== Paginador ===== */
  const renderPager = (page, perPage, total) => {
    if (!$pager) return;
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (pages <= 1) { $pager.innerHTML=''; return; }
    const clamp = (x,min,max)=>Math.max(min,Math.min(max,x));
    const range = (a,b)=>Array.from({length:b-a+1},(_,i)=>i+a);
    const start = clamp(page-2, 1, Math.max(1,pages-4));
    const end   = clamp(start+4, 1, pages);
    const items = range(start,end);

    const btn = (p, label = p, disabled=false, active=false) =>
      `<button ${disabled?'disabled':''} data-page="${p}" class="${active?'active':''}">${label}</button>`;

    let html = '';
    html += btn(page-1, '«', page<=1);
    items.forEach(p => html += btn(p, p, false, p===page));
    html += btn(page+1, '»', page>=pages);
    $pager.innerHTML = `<div class="pager">${html}</div>`;
    $$('.pager button', $pager).forEach(b => on(b, 'click', () => {
      const p = parseInt(b.dataset.page,10);
      if (!Number.isFinite(p)) return;
      state.page = p;
      loadVendors();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
  };

  /* ===== Data loaders ===== */
  const loadPlans = async () => {
    try {
      const res = await fetchJSON(`${EP.planes}?per_page=200`);
      const rows = res?.rows || res?.items || res?.data || [];
      $('#f-plan').innerHTML = `<option value="">Todos los planes</option>` +
        rows.map(p => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.nombre||'Plan')}</option>`).join('');
      const sel = $('#v-plan');
      if (sel) {
        sel.innerHTML = `<option value="">— Sin plan —</option>` +
          rows.map(p => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.nombre||'Plan')}</option>`).join('');
      }
    } catch {}
  };

  const loadKPIs = async () => {
    try {
      const res = await fetchJSON(EP.global_stats);
      if (res?.ok) {
        $kpiTotal && ($kpiTotal.textContent  = res.total_vendors ?? '—');
        $kpiPlanes&& ($kpiPlanes.textContent = res.planes_activos ?? '—');
        $kpiVallas&& ($kpiVallas.textContent = res.vallas_activas ?? '—');
        $kpiPend  && ($kpiPend.textContent   = fmtMoney(res.monto_pendiente ?? 0));
        return;
      }
    } catch {}
    const rows = state.rows || [];
    $kpiTotal && ($kpiTotal.textContent  = state.total || rows.length || '—');
    $kpiPlanes&& ($kpiPlanes.textContent = rows.filter(r => r.plan_id != null).length || '0');
    $kpiVallas&& ($kpiVallas.textContent = rows.reduce((a,r)=>a+num(r.vallas_activas),0));
    $kpiPend  && ($kpiPend.textContent   = fmtMoney(rows.reduce((a,r)=>a+num(r.total_pendiente),0)));
  };

  const loadVendors = async () => {
    const params = new URLSearchParams({
      q: state.q, estado: state.estado, plan_id: state.plan_id, feature: state.feature, sort: state.sort,
      page: String(state.page), per_page: String(state.per_page)
    });
    try {
      const res = await fetchJSON(`${EP.listar}?${params.toString()}`);
      if (!res?.ok) throw new Error(res?.msg || 'Error');
      state.total = res.total || 0;
      state.rows  = res.rows || [];
      renderRows(state.rows);   // desktop
      renderCards(state.rows);  // móvil
      renderPager(state.page, state.per_page, state.total);
      loadKPIs();
    } catch {
      $tbody && ($tbody.innerHTML = '');
      $empty && $empty.classList.remove('hidden');
      $cards && ($cards.innerHTML = '');
      $emptyCards && $emptyCards.classList.remove('hidden');
      $pager && ($pager.innerHTML = '');
      toast('No se pudo cargar el listado', false);
    }
  };

  /* ===== Modal ===== */
  const openModal = (title='Nuevo Vendor', id=null) => {
    $modalTitle.textContent = title;
    $form.reset();
    $('#vendor-id').value = id || '';
    if (id) {
      const r = (state.rows||[]).find(x => String(x.id) === String(id));
      if (r) {
        $('#v-nombre').value = r.nombre || '';
        $('#v-contacto').value = r.contacto || '';
        $('#v-email').value = r.email || '';
        $('#v-telefono').value = r.telefono || '';
        $('#v-plan').value = r.plan_id ?? '';
        $('#v-inicio').value = (r.fecha_inicio||'').slice(0,10);
        $('#v-fin').value = (r.fecha_fin||'').slice(0,10);
        $('#v-estado').checked = !!(+r.estado===1);
      }
    }
    $modal.classList.add('show');
    $modal.classList.remove('pointer-events-none');
  };
  const closeModal = () => {
    $modal.classList.remove('show');
    setTimeout(()=>{ $modal.classList.add('pointer-events-none'); }, 200);
  };
  $$('[data-close-modal="vendorModal"]').forEach(b => on(b, 'click', closeModal));
  on($btnCreate, 'click', () => openModal('Crear Vendor'));
  on($modal, 'click', (e) => { if (e.target === $modal) closeModal(); });

  /* ===== Filters ===== */
  on($fQ, 'input', debounce(() => { state.q = $fQ.value.trim(); state.page=1; loadVendors(); }, 300));
  on($fEstado, 'change', () => { state.estado = $fEstado.value; state.page=1; loadVendors(); });
  on($fPlan,   'change', () => { state.plan_id= $fPlan.value;   state.page=1; loadVendors(); });
  on($fFeature,'change', () => { state.feature= $fFeature.value; state.page=1; loadVendors(); });
  on($fSort,   'change', () => { state.sort   = $fSort.value;   state.page=1; loadVendors(); });

  /* ===== Init ===== */
  (async function init(){
    await loadPlans();
    await loadVendors();
  })();

})();

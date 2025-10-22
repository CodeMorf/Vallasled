// /console/asset/js/contabilidad/index.js
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

  const cfg = window.CONTAB_CFG || {};
  const CSRF = cfg.csrf || $('meta[name="csrf"]')?.content || '';
  const EP = cfg.endpoints || {};
  const PER_PAGE = cfg.page?.limit || 20;

  /* ===== Toast ===== */
  const toast = (msg, ok=true) => {
    const n = document.createElement('div');
    n.className = `toast ${ok?'ok':'err'}`;
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(()=>{ n.remove(); }, 2600);
  };

  /* ===== Fetch JSON con CSRF ===== */
  const fetchJSON = (url, opts = {}) => {
    const method = (opts.method || 'GET').toUpperCase();
    const headers = { 'X-CSRF-Token': CSRF, ...(opts.headers || {}) };
    let body = opts.body;

    if (method !== 'GET' && !(body instanceof FormData)) {
      body = { csrf: CSRF, ...(body || {}) };
      headers['Content-Type'] = 'application/json; charset=utf-8';
      body = JSON.stringify(body);
    }

    return fetch(url, {
      credentials: 'same-origin',
      ...opts,
      method,
      headers,
      body
    }).then(r => r.json());
  };

  /* ===== State ===== */
  let state = {
    q: '', desde: '', hasta: '', tipo: '', cat: '',
    page: 1, per_page: PER_PAGE, total: 0, rows: []
  };

  /* ===== UI refs ===== */
  const $tbody   = $('#tx-tbody');
  const $empty   = $('#tx-empty');
  const $pager   = $('#tx-pager');

  const $kIn  = $('#kpi-ingresos');
  const $kEg  = $('#kpi-egresos');
  const $kCom = $('#kpi-comisiones');
  const $kBal = $('#kpi-balance');

  const $fDesde = $('#f-desde');
  const $fHasta = $('#f-hasta');
  const $fTipo  = $('#f-tipo');
  const $fCat   = $('#f-cat');
  const $fQ     = $('#f-q');
  const $btnExp = $('#btn-export');

  /* ===== Render ===== */
  const tipoBadge = t => {
    const isIn = (t||'').toLowerCase()==='ingreso';
    const cls = isIn
      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300'
      : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    return `<span class="${cls} text-xs font-medium px-2.5 py-0.5 rounded">${isIn?'Ingreso':'Egreso'}</span>`;
  };

  const renderRows = (rows=[]) => {
    if (!rows.length) {
      $tbody.innerHTML = '';
      $empty.classList.remove('hidden');
      return;
    }
    $empty.classList.add('hidden');
    $tbody.innerHTML = rows.map(r => {
      const id   = r.id ?? r.tx_id ?? '';
      const f    = (r.fecha || r.created_at || '').toString().slice(0,10);
      const desc = r.descripcion || r.detalle || '—';
      const catN = r.categoria_nombre || r.categoria || '—';
      const tipo = (r.tipo || '').toLowerCase()==='egreso' ? 'egreso' : 'ingreso';
      const sign = tipo==='egreso' ? '-' : '';
      const mTxt = `${sign}${fmtMoney(r.monto || r.total || 0)}`;
      const mCls = tipo==='egreso' ? 'text-red-500' : 'text-emerald-500';

      return `
        <tr data-id="${escapeHtml(id)}" class="border-b border-[var(--border-color)] last:border-b-0 md:hover:bg-gray-50 md:dark:hover:bg-gray-800/20">
          <td class="px-6 py-4 md:whitespace-nowrap">${escapeHtml(f||'—')}</td>
          <td class="px-6 py-4">
            <div class="font-medium">${escapeHtml(desc)}</div>
            ${r.meta ? `<div class="text-sm text-[var(--text-secondary)]">${escapeHtml(r.meta)}</div>`:''}
          </td>
          <td class="px-6 py-4">${escapeHtml(catN)}</td>
          <td class="px-6 py-4 text-right ${mCls} font-semibold">${mTxt}</td>
          <td class="px-6 py-4 text-center">${tipoBadge(tipo)}</td>
          <td class="px-6 py-4 text-right">
            <button class="tx-edit p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" title="Editar" aria-label="Editar" data-id="${escapeHtml(id)}">
              <i class="fas fa-pencil-alt"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  };

  const renderPager = (page, perPage, total) => {
    const pages = Math.max(1, Math.ceil(total / perPage));
    if (pages <= 1) { $pager.innerHTML = ''; return; }
    const clamp = (x,min,max)=>Math.max(min,Math.min(max,x));
    const range = (a,b)=>Array.from({length:b-a+1},(_,i)=>i+a);
    const start = clamp(page-2, 1, Math.max(1,pages-4));
    const end   = clamp(start+4, 1, pages);
    const btn = (p, label = p, disabled=false, active=false) =>
      `<button ${disabled?'disabled':''} data-page="${p}" class="${active?'active':''}">${label}</button>`;
    let html = '';
    html += btn(page-1, '«', page<=1);
    range(start,end).forEach(p => html += btn(p, p, false, p===page));
    html += btn(page+1, '»', page>=pages);
    $pager.innerHTML = `<div class="pager">${html}</div>`;
    $$('.pager button', $pager).forEach(b => on(b, 'click', () => {
      const p = parseInt(b.dataset.page,10);
      if (!Number.isFinite(p)) return;
      state.page = p; loadTX(); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
  };

  /* ===== Data ===== */
  const loadKPIs = async () => {
    try {
      const q = new URLSearchParams({
        desde: state.desde || '', hasta: state.hasta || '', tipo: state.tipo || '', cat: state.cat || '', q: state.q || ''
      });
      const res = await fetchJSON(`${EP.kpis}?${q.toString()}`);
      if (res?.ok) {
        $kIn.textContent  = fmtMoney(res.ingresos || 0);
        $kEg.textContent  = fmtMoney(res.egresos || 0);
        $kCom.textContent = fmtMoney(res.comisiones || 0);
        $kBal.textContent = fmtMoney((res.ingresos||0) - (res.egresos||0));
        return;
      }
    } catch {}
    const rows = state.rows || [];
    const sum = rows.reduce((a,r)=>{
      const eg = ((r.tipo||'').toLowerCase()==='egreso');
      const m  = num(r.monto||r.total||0);
      eg ? (a.eg+=m) : (a.in+=m);
      return a;
    },{in:0,eg:0});
    $kIn.textContent  = fmtMoney(sum.in);
    $kEg.textContent  = fmtMoney(sum.eg);
    $kCom.textContent = '—';
    $kBal.textContent = fmtMoney(sum.in - sum.eg);
  };

  const loadTX = async () => {
    const q = new URLSearchParams({
      page: String(state.page), per_page: String(state.per_page),
      q: state.q || '', desde: state.desde || '', hasta: state.hasta || '',
      tipo: state.tipo || '', cat: state.cat || ''
    });
    try {
      const res = await fetchJSON(`${EP.listar}?${q.toString()}`);
      if (!res?.ok) throw 0;
      state.total = res.total || 0;
      state.rows  = res.rows  || [];
      renderRows(state.rows);
      renderPager(state.page, state.per_page, state.total);
      loadKPIs();
    } catch {
      state.total = 0; state.rows = [];
      renderRows([]); renderPager(1, state.per_page, 0);
      toast('No se pudo cargar el listado', false);
    }
  };

  /* ===== Delegación Acciones ===== */
  on($tbody, 'click', (e) => {
    const btn = e.target.closest('.tx-edit');
    if (!btn) return;
    const id = btn.dataset.id || btn.closest('tr')?.dataset.id || '';
    if (!id) return;
    toast(`Editar transacción #${id}`);
    // Para editor real:
    // window.location.href = `/console/gestion/contabilidad/editar.php?id=${encodeURIComponent(id)}`;
  });

  /* ===== Export ===== */
  on($btnExp, 'click', async () => {
    const q = new URLSearchParams({
      q: state.q || '', desde: state.desde || '', hasta: state.hasta || '',
      tipo: state.tipo || '', cat: state.cat || ''
    });
    window.location.href = `${EP.exportar}?${q.toString()}`;
  });

  /* ===== Filtros ===== */
  on($fDesde, 'change', () => { state.desde = $fDesde.value; state.page=1; loadTX(); });
  on($fHasta, 'change', () => { state.hasta = $fHasta.value; state.page=1; loadTX(); });
  on($fTipo,  'change', () => { state.tipo  = $fTipo.value;  state.page=1; loadTX(); });
  on($fCat,   'change', () => { state.cat   = $fCat.value;   state.page=1; loadTX(); });
  on($fQ, 'input', debounce(() => { state.q = $fQ.value.trim(); state.page=1; loadTX(); }, 300));

  /* ===== Init ===== */
  loadTX();

})();

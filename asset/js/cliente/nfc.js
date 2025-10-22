/* /console/asset/js/cliente/nfc.js */
(function () {
  'use strict';

  // ---------- helpers ----------
  const $  = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const cfg = window.NCF_CFG || {};
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const esc = v => (v == null ? '' : String(v).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])));

  const apiGet = async (url, params={}) => {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k,v]) => (v!=='' && v!=null) && u.searchParams.set(k, v));
    const r = await fetch(u, {credentials:'same-origin'});
    return r.json().catch(() => ({}));
  };
  const apiPost = async (url, data={}) => {
    const r = await fetch(url, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body: JSON.stringify(data)
    });
    return r.json().catch(()=>({ok:false,msg:'ERROR'}));
  };
  const debounce = (fn, ms=200) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  // ---------- layout: sidebar + theme ----------
  const body = document.body;
  const overlay = document.getElementById('sidebar-overlay');
  const lsKey = 'sidebarCollapsed';

  try { if (localStorage.getItem(lsKey) === '1') body.classList.add('sidebar-collapsed'); } catch {}
  $('#mobile-menu-button')?.addEventListener('click', e => {
    e.stopPropagation(); body.classList.toggle('sidebar-open'); overlay?.classList.toggle('hidden');
  });
  overlay?.addEventListener('click', () => { body.classList.remove('sidebar-open'); overlay.classList.add('hidden'); });
  $('#sidebar-toggle-desktop')?.addEventListener('click', () => {
    body.classList.toggle('sidebar-collapsed');
    try { localStorage.setItem(lsKey, body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch {}
    setTimeout(()=>window.dispatchEvent(new Event('resize')),120);
  });
  $$('.submenu-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      if (body.classList.contains('sidebar-collapsed')) return;
      btn.nextElementSibling?.classList.toggle('hidden');
      btn.classList.toggle('submenu-open');
    });
  });

  const themeBtn = $('#theme-toggle');
  const darkI = $('#theme-toggle-dark-icon');
  const lightI = $('#theme-toggle-light-icon');
  const applyTheme = t => {
    document.documentElement.classList.toggle('dark', t === 'dark');
    darkI?.classList.toggle('hidden', t !== 'dark');
    lightI?.classList.toggle('hidden', t === 'dark');
    try { localStorage.setItem('theme', t); } catch {}
  };
  applyTheme(localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  themeBtn?.addEventListener('click', () => {
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
  });

  // ---------- state ----------
  const state = {
    tab: 'emitidos',
    em: { page: 1, q: '', tipo: '', estado: '' },
    seq: { page: 1, tipo: '' },
    perPage: (cfg.page && cfg.page.limit) || 20
  };

  // ---------- KPIs ----------
  const kpiEmit = $('#kpi-emitidos');
  const kpiSeq  = $('#kpi-secuencias');
  const kpiAnu  = $('#kpi-anulados');

  async function loadKPIs() {
    const a = await apiGet(cfg.listar, { tab:'emitidos', page:1, per_page:1 });
    const b = await apiGet(cfg.listar, { tab:'emitidos', estado:'anulado', page:1, per_page:1 });
    const c = await apiGet(cfg.listar, { tab:'secuencias', page:1, per_page:1 });
    if (typeof a?.total === 'number') kpiEmit.textContent = a.total;
    if (typeof b?.total === 'number') kpiAnu.textContent  = b.total;
    if (typeof c?.total === 'number') kpiSeq.textContent  = c.total;
  }

  // ---------- tabs ----------
  const tabs = $$('.tab-button');
  const tabViews = {
    emitidos: $('#tab-content-emitidos'),
    secuencias: $('#tab-content-secuencias')
  };
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(b => { b.classList.remove('active','text-indigo-600','border-indigo-500'); b.classList.add('text-[var(--text-secondary)]','border-transparent'); });
      btn.classList.add('active','text-indigo-600','border-indigo-500'); btn.classList.remove('text-[var(--text-secondary)]','border-transparent');
      const t = btn.dataset.tab;
      state.tab = t;
      Object.values(tabViews).forEach(v => v.classList.add('hidden'));
      tabViews[t]?.classList.remove('hidden');
      if (t === 'emitidos') loadEmitidos(); else loadSecuencias();
    });
  });

  // ---------- emitidos ----------
  const emRows  = $('#em-rows');
  const emPager = $('#em-pager');

  function renderEm(items) {
    emRows.innerHTML = '';
    if (!items.length) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="7" class="py-6 px-4 text-center text-sm text-[var(--text-secondary)]">Sin resultados</td>`;
      emRows.appendChild(tr);
      return;
    }
    const frag = document.createDocumentFragment();
    items.forEach(x => {
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-[var(--main-bg)]';
      tr.innerHTML = `
        <td class="py-3 px-4 font-mono text-sm">${esc(x.ncf || '')}</td>
        <td class="py-3 px-4"><span class="text-indigo-500 font-semibold">${esc(x.factura || '')}</span></td>
        <td class="py-3 px-4"><p class="font-semibold">${esc(x.cliente||'')}</p><p class="text-xs text-[var(--text-secondary)]">RNC: ${esc(x.rnc||'—')}</p></td>
        <td class="py-3 px-4 hidden lg:table-cell">${x.monto!=null ? new Intl.NumberFormat().format(x.monto) : '—'}</td>
        <td class="py-3 px-4 hidden md:table-cell">${esc(x.fecha || '')}</td>
        <td class="py-3 px-4">
          <span class="px-2 py-1 text-xs font-semibold rounded-full ${x.estado==='anulado'?'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300':'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300'}">${esc(x.estado||'')}</span>
        </td>
        <td class="py-3 px-4 text-center">
          ${x.estado==='generado'
            ? `<button class="text-red-600 hover:underline" data-act="anular" data-ncf="${esc(x.ncf)}">Anular</button>`
            : `<span class="text-[var(--text-secondary)] text-sm italic">—</span>`}
        </td>`;
      frag.appendChild(tr);
    });
    emRows.appendChild(frag);
  }

  function buildPager(el, total, page, perPage, onPage) {
    el.innerHTML = '';
    if (!total || total <= perPage) return;
    const pages = Math.max(1, Math.ceil(total / perPage));
    const mk = (p, label = String(p), dis=false, act=false) => {
      const b = document.createElement('button');
      b.className = `mx-1 px-3 h-9 rounded-md border border-[var(--border-color)] ${act?'bg-indigo-600 text-white border-indigo-600':''}`;
      b.textContent = label; b.disabled = dis; if(!dis) b.addEventListener('click', ()=>onPage(p));
      el.appendChild(b);
    };
    mk(Math.max(1,page-1),'‹', page===1);
    for (let i=Math.max(1,page-2); i<=Math.min(pages,page+2); i++) mk(i,String(i),false,i===page);
    mk(Math.min(pages,page+1),'›', page===pages);
  }

  async function loadEmitidos() {
    const r = await apiGet(cfg.listar, {
      tab:'emitidos',
      q: state.em.q,
      tipo: state.em.tipo,
      estado: state.em.estado,
      page: state.em.page,
      per_page: state.perPage
    });
    renderEm(r.data || []);
    const total = typeof r.total === 'number' ? r.total : (r.data||[]).length;
    buildPager(emPager, total, state.em.page, state.perPage, p => { state.em.page = p; loadEmitidos(); });
    if (typeof r.total === 'number') kpiEmit.textContent = r.total;
  }

  emRows?.addEventListener('click', async e => {
    const btn = e.target.closest('[data-act="anular"]');
    if (!btn) return;
    const ncf = btn.getAttribute('data-ncf');
    if (!ncf) return;
    if (!confirm(`¿Anular NCF ${ncf}?`)) return;
    const resp = await apiPost(cfg.crud, { action:'anular', ncf, motivo:'UI' });
    if (!resp.ok) return alert(resp.msg || 'Error');
    loadEmitidos(); loadKPIs();
  });

  // filters emitidos
  $('#em-q')?.addEventListener('input', debounce(e => { state.em.q = e.target.value.trim(); state.em.page = 1; loadEmitidos(); }, 200));
  $('#em-tipo')?.addEventListener('change', e => { state.em.tipo = e.target.value; state.em.page = 1; loadEmitidos(); });
  $('#em-estado')?.addEventListener('change', e => { state.em.estado = e.target.value; state.em.page = 1; loadEmitidos(); });

  // ---------- emitir NCF ----------
  const ncfModal = $('#ncf-modal');
  const ncfBox   = $('#ncf-modal-container');
  const openNcf  = () => { ncfModal.classList.remove('hidden'); setTimeout(()=>{ ncfModal.classList.remove('opacity-0'); ncfBox.classList.remove('scale-95'); },10); };
  const closeNcf = () => { ncfBox.classList.add('scale-95'); ncfModal.classList.add('opacity-0'); setTimeout(()=>ncfModal.classList.add('hidden'),280); };

  $('#emitir-ncf-btn')?.addEventListener('click', openNcf);
  $('#close-ncf-modal-btn')?.addEventListener('click', closeNcf);
  $('#cancel-ncf-btn')?.addEventListener('click', closeNcf);
  ncfModal?.addEventListener('click', e => { if (e.target === ncfModal) closeNcf(); });

  const montoInput = $('#ncf-monto');
  const itbisChk   = $('#ncf-itbis');
  let totalHintEl;
  function updateTotalHint() {
    const base = parseFloat(montoInput.value || '0') || 0;
    const itbisPct = itbisChk.checked ? 0.18 : 0;
    const itbis    = +(base * itbisPct).toFixed(2);
    const total    = +(base + itbis).toFixed(2);
    if (!totalHintEl) {
      totalHintEl = document.createElement('div');
      totalHintEl.className = 'text-xs text-[var(--text-secondary)] mt-1';
      montoInput.parentElement.appendChild(totalHintEl);
    }
    totalHintEl.textContent = itbisChk.checked ? `Total con ITBIS (18%): ${new Intl.NumberFormat().format(total)}` : '';
  }
  montoInput?.addEventListener('input', updateTotalHint);
  itbisChk?.addEventListener('change', updateTotalHint);

  $('#ncf-form')?.addEventListener('submit', async e => {
    e.preventDefault();
    const factura_id = $('#ncf-factura-id')?.value.trim() || null;
    const tipo_ncf   = $('#ncf-tipo')?.value || 'B01';
    const cliente    = $('#ncf-cliente')?.value.trim() || '';
    const rnc        = $('#ncf-rnc')?.value.trim() || '';
    const base       = parseFloat($('#ncf-monto')?.value || '0') || 0;
    if (base <= 0) return alert('Monto inválido');
    const aplica_itbis = !!$('#ncf-itbis')?.checked;
    const itbis_pct = aplica_itbis ? 18 : 0;
    const itbis_monto = +(base * (itbis_pct/100)).toFixed(2);
    const monto_total = +(base + itbis_monto).toFixed(2);

    const payload = {
      action:'emitir',
      factura_id,
      tipo_ncf,
      cliente_nombre: cliente,
      rnc,
      aplica_itbis: aplica_itbis ? 1 : 0,
      monto: monto_total,
      monto_base: base,
      itbis_porcentaje: itbis_pct,
      itbis_monto
    };
    const resp = await apiPost(cfg.crud, payload);
    if (!resp.ok) return alert(resp.msg || 'Error');
    closeNcf();
    loadEmitidos(); loadKPIs();
  });

  // ---------- secuencias ----------
  const seqRows  = $('#seq-rows');
  const seqPager = $('#seq-pager');

  function renderSeq(items) {
    seqRows.innerHTML = '';
    if (!items.length) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="7" class="py-6 px-4 text-center text-sm text-[var(--text-secondary)]">Sin resultados</td>`;
      seqRows.appendChild(tr);
      return;
    }
    const frag = document.createDocumentFragment();
    items.forEach(s => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="py-3 px-4 font-mono font-semibold">${esc(s.tipo || '')}</td>
        <td class="py-3 px-4">${esc(s.serie || '')}</td>
        <td class="py-3 px-4 font-mono">${esc(s.desde)}</td>
        <td class="py-3 px-4 font-mono">${esc(s.hasta)}</td>
        <td class="py-3 px-4">${esc(s.vence || '')}</td>
        <td class="py-3 px-4">
          <span class="px-2 py-1 text-xs font-semibold rounded-full ${s.estado==='vigente'?'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300':'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300'}">
            ${esc(s.estado || (s.activo? 'vigente':'inactivo'))}
          </span>
        </td>
        <td class="py-3 px-4 text-center">
          <button class="mr-2 text-indigo-600 hover:underline" data-act="edit" data-id="${esc(s.id)}">Editar</button>
          <button class="text-red-600 hover:underline" data-act="del" data-id="${esc(s.id)}">Eliminar</button>
        </td>`;
      tr.dataset.row = JSON.stringify(s);
      frag.appendChild(tr);
    });
    seqRows.appendChild(frag);
  }

  async function loadSecuencias() {
    const r = await apiGet(cfg.listar, {
      tab:'secuencias',
      tipo: state.seq.tipo,
      page: state.seq.page,
      per_page: state.perPage
    });
    renderSeq(r.data || []);
    const total = typeof r.total === 'number' ? r.total : (r.data||[]).length;
    buildPager(seqPager, total, state.seq.page, state.perPage, p => { state.seq.page = p; loadSecuencias(); });
    if (typeof r.total === 'number') kpiSeq.textContent = r.total;
  }

  $('#seq-filter-tipo')?.addEventListener('change', e => { state.seq.tipo = e.target.value; state.seq.page = 1; loadSecuencias(); });

  // modal secuencia
  const seqModal = $('#seq-modal');
  const seqBox   = $('#seq-modal-container');
  const openSeq  = () => { seqModal.classList.remove('hidden'); setTimeout(()=>{ seqModal.classList.remove('opacity-0'); seqBox.classList.remove('scale-95'); },10); };
  const closeSeq = () => { seqBox.classList.add('scale-95'); seqModal.classList.add('opacity-0'); setTimeout(()=>seqModal.classList.add('hidden'),280); };

  $('#seq-add-btn')?.addEventListener('click', () => {
    $('#seq-modal-title').textContent = 'Nueva secuencia';
    $('#seq-id').value = '';
    $('#seq-tipo').value = 'B01';
    $('#seq-serie').value = 'B';
    $('#seq-desde').value = '';
    $('#seq-hasta').value = '';
    $('#seq-vence').value = '';
    $('#seq-activo').checked = true;
    openSeq();
  });
  $('#seq-close')?.addEventListener('click', closeSeq);
  $('#seq-cancel')?.addEventListener('click', closeSeq);
  seqModal?.addEventListener('click', e => { if (e.target === seqModal) closeSeq(); });

  seqRows?.addEventListener('click', async e => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const tr = btn.closest('tr');
    const data = tr?.dataset.row ? JSON.parse(tr.dataset.row) : null;
    if (btn.dataset.act === 'edit' && data) {
      $('#seq-modal-title').textContent = 'Editar secuencia';
      $('#seq-id').value = data.id || '';
      $('#seq-tipo').value = data.tipo || 'B01';
      $('#seq-serie').value = data.serie || 'B';
      $('#seq-desde').value = data.desde || '';
      $('#seq-hasta').value = data.hasta || '';
      $('#seq-vence').value = data.vence || '';
      $('#seq-activo').checked = !!(data.activo ?? (data.estado!=='inactivo'));
      openSeq();
    }
    if (btn.dataset.act === 'del' && data?.id) {
      if (!confirm('¿Eliminar secuencia?')) return;
      const resp = await apiPost(cfg.crud, { action:'seq_delete', id: data.id });
      if (!resp.ok) return alert(resp.msg || 'Error');
      loadSecuencias(); loadKPIs();
    }
  });

  $('#seq-form')?.addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      action: 'seq_save',
      id: $('#seq-id').value || null,
      tipo: $('#seq-tipo').value || 'B01',
      serie: ($('#seq-serie').value || 'B').toUpperCase().slice(0,1),
      desde: parseInt($('#seq-desde').value || '0', 10),
      hasta: parseInt($('#seq-hasta').value || '0', 10),
      vence: $('#seq-vence').value || null,
      activo: $('#seq-activo').checked ? 1 : 0
    };
    if (!payload.desde || !payload.hasta || payload.hasta < payload.desde) return alert('Rango inválido');
    const resp = await apiPost(cfg.crud, payload);
    if (!resp.ok) return alert(resp.msg || 'Error');
    closeSeq(); loadSecuencias(); loadKPIs();
  });

  // ---------- init ----------
  loadKPIs();
  loadEmitidos(); // default tab

})();

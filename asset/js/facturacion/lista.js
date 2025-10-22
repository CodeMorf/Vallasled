// /console/asset/js/facturacion/lista.js
(function () {
  'use strict';

  // --- Config y helpers ---
  const CFG = window.FACTURAS_CFG || {};
  const qs  = (s) => document.querySelector(s);
  const qsa = (s) => Array.from(document.querySelectorAll(s));
  const esc = (s) => s == null ? '' : String(s).replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const money = (n) => new Intl.NumberFormat('es-DO',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n||0));

  const badge = (estado) => (
    estado === 'pagado'
      ? `<span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-100 dark:bg-green-500/20 dark:text-green-300 rounded-full">Pagado</span>`
      : `<span class="px-3 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 dark:bg-yellow-500/20 dark:text-yellow-300 rounded-full">Pendiente</span>`
  );

  function parseRange(val){
    if(!val) return {desde:'',hasta:''};
    let p = val.split(' to ');
    if(p.length!==2) p = val.split(' a ');
    if(p.length!==2) return {desde:'',hasta:''};
    return {desde:p[0].trim(),hasta:p[1].trim()};
  }

  function buildQuery(params){
    const usp=new URLSearchParams();
    Object.entries(params).forEach(([k,v])=>{ if(v!=='' && v!=null) usp.set(k,v); });
    return usp.toString();
  }

  async function postJSON(url, body){
    const res = await fetch(url,{
      method:'POST',
      credentials:'same-origin',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF': CFG.csrf || ''
      },
      body: JSON.stringify(body||{})
    });
    if(!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // --- Elementos UI ---
  const el = {
    rango: qs('#flt-rango'),
    proveedor: qs('#flt-proveedor'),
    estado: qs('#flt-estado'),
    q: qs('#flt-q'),
    kpiPend: qs('#kpi-pendiente-sum'),
    kpiCob: qs('#kpi-cobrado-sum'),
    chkAll: qs('#chk-all'),
    tbody: qs('#tabla-rows'),
    stats: qs('#list-stats'),
    pager: qs('#paginador'),
    thead: qs('#thead'),
    themeBtn: qs('#theme-toggle'),
    themeDarkIcon: qs('#theme-toggle-dark-icon'),
    themeLightIcon: qs('#theme-toggle-light-icon'),
  };

  const state = {
    page: 1,
    limit: (CFG.paging && CFG.paging.limitDefault) || 20,
    sort:  (CFG.sort && CFG.sort.field) || 'fecha_generada',
    order: (CFG.sort && CFG.sort.order) || 'desc',
    total: 0,
    pages: 0
  };

  function getFilters(){
    const r=parseRange(el.rango?.value.trim()||'');
    return {
      q: el.q?.value.trim() || '',
      proveedor_id: el.proveedor?.value || '',
      estado: el.estado?.value || '',
      desde: r.desde, hasta: r.hasta,
      page: String(state.page), limit: String(state.limit),
      sort: state.sort, order: state.order
    };
  }

  // --- Render ---
  function renderRow(r){
    const num   = esc(r.numero || `#${r.id}`);
    const cli   = esc(r.cliente || '');
    const prv   = esc(r.proveedor || '');
    const monto = money(r.monto);
    const comi  = money(r.comision_monto);
    const fecha = esc(r.fecha || '');
    const est   = esc(r.estado || 'pendiente');

    return `
<tr class="row-card hover:bg-[var(--sidebar-active-bg)] transition-colors">
  <td class="py-3 px-4" data-label="Sel"><input type="checkbox" class="rounded invoice-checkbox" data-id="${r.id}"></td>
  <td class="py-3 px-4 font-medium text-indigo-500" data-label="Nº">${num}</td>
  <td class="py-3 px-4" data-label="Cliente"><p class="font-semibold text-[var(--text-primary)]">${cli}</p></td>
  <td class="py-3 px-4 text-[var(--text-secondary)] hidden lg:table-cell" data-label="Proveedor">${prv}</td>
  <td class="py-3 px-4 font-medium text-[var(--text-primary)] hidden md:table-cell" data-label="Monto">$${monto}</td>
  <td class="py-3 px-4 text-[var(--text-secondary)] hidden xl:table-cell" data-label="Comisión">$${comi}</td>
  <td class="py-3 px-4" data-label="Estado">${badge(est)}</td>
  <td class="py-3 px-4 text-[var(--text-secondary)] hidden md:table-cell" data-label="Fecha">${fecha}</td>
  <td class="py-3 px-4 text-center" data-label="Acciones">
    <div class="flex items-center justify-center gap-2">
      <a href="/console/facturacion/facturas/ver.php?id=${r.id}" class="p-2 text-[var(--text-secondary)] hover:text-blue-500" title="Ver"><i class="fas fa-eye"></i></a>
      <a href="/console/facturacion/facturas/editar.php?id=${r.id}" class="p-2 text-[var(--text-secondary)] hover:text-yellow-500" title="Editar"><i class="fas fa-pencil-alt"></i></a>
      <button class="p-2 text-[var(--text-secondary)] hover:text-red-500 act-eliminar" data-id="${r.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
      ${
        est === 'pagado'
          ? `<button class="p-2 text-[var(--text-secondary)] hover:text-yellow-600 act-pendiente" data-id="${r.id}" title="Marcar Pendiente"><i class="fas fa-rotate-left"></i></button>`
          : `<button class="p-2 text-[var(--text-secondary)] hover:text-green-600 act-pagar" data-id="${r.id}" title="Marcar Pagada"><i class="fas fa-check-circle"></i></button>`
      }
    </div>
  </td>
</tr>`;
  }

  function renderPager(page, pages){
    const btn = (label,p,disabled=false,active=false)=>
      `<button class="px-3 py-1.5 border rounded-md ${active?'border-indigo-500 bg-indigo-50 text-indigo-600 font-semibold dark:bg-indigo-500/20 dark:text-indigo-300':'border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]'}" data-page="${p}" ${disabled?'disabled':''}>${label}</button>`;

    const parts=[];
    parts.push(btn('<i class="fas fa-chevron-left"></i>', Math.max(1,page-1), page<=1));
    const W=3; let start=Math.max(1,page-W), end=Math.min(pages,page+W);
    if(start>1){ parts.push(btn('1',1,false,page===1)); if(start>2) parts.push(`<span class="px-3 py-1.5 text-[var(--text-secondary)]">...</span>`); }
    for(let p=start;p<=end;p++) parts.push(btn(String(p),p,false,p===page));
    if(end<pages){ if(end<pages-1) parts.push(`<span class="px-3 py-1.5 text-[var(--text-secondary)]">...</span>`); parts.push(btn(String(pages),pages,false,page===pages)); }
    parts.push(btn('<i class="fas fa-chevron-right"></i>', Math.min(pages,page+1), page>=pages));
    el.pager.innerHTML = parts.join('');
  }

  function render(data){
    const rows = Array.isArray(data.rows)?data.rows:[];
    state.total = Number(data.total||0);
    state.pages = Math.max(1, Number(data.pages||1));

    const from = state.total===0 ? 0 : ((state.page-1)*state.limit+1);
    const to   = Math.min(state.page*state.limit, state.total);

    el.tbody.innerHTML = rows.map(renderRow).join('') || `<tr><td colspan="9" class="py-4 px-4 text-sm text-[var(--text-secondary)]">Sin resultados.</td></tr>`;
    el.stats.textContent = `Mostrando ${from} a ${to} de ${state.total} facturas`;
    el.kpiPend.textContent = `$${money(data.sum_pendiente||0)}`;
    el.kpiCob.textContent  = `$${money(data.sum_pagado||0)}`;
    if(el.chkAll) el.chkAll.checked=false;
    updateBulkButtons();
    renderPager(state.page, state.pages);
  }

  // --- Data ---
  async function fetchList(){
    el.tbody.innerHTML = `<tr><td colspan="9" class="py-4 px-4 text-sm text-[var(--text-secondary)]">Cargando…</td></tr>`;
    const url = `${CFG.endpoints.listar}?${buildQuery(getFilters())}`;
    const res = await fetch(url,{credentials:'same-origin',headers:{'Accept':'application/json'}});
    if(!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    render(data);
  }

  // --- Selección y acciones ---
  function selectedIds(){ return qsa('.invoice-checkbox:checked').map(x=>Number(x.dataset.id)); }
  function updateBulkButtons(){
    const any = selectedIds().length>0;
    const bulkPagar   = qs('#btn-bulk-pagar');
    const bulkEliminar= qs('#btn-bulk-eliminar');
    if(bulkPagar)    bulkPagar.disabled    = !any;
    if(bulkEliminar) bulkEliminar.disabled = !any;
  }

  // --- Eventos ---
  function bindFilterEvents(){
    [el.proveedor, el.estado].forEach(c=> c?.addEventListener('change', ()=>{ state.page=1; fetchList().catch(console.error); }));
    el.q?.addEventListener('keyup', (e)=>{ if(e.key==='Enter'){ state.page=1; fetchList().catch(console.error); } });
    el.rango?.addEventListener('change', ()=>{ state.page=1; fetchList().catch(console.error); });
  }

  function bindPagerEvents(){
    el.pager.addEventListener('click',(e)=>{
      const b=e.target.closest('button[data-page]'); if(!b) return;
      const p=Number(b.dataset.page);
      if(p && p!==state.page){ state.page=p; fetchList().catch(console.error); }
    });
  }

  function bindSelectionEvents(){
    el.chkAll?.addEventListener('change',(e)=>{
      const v=!!e.target.checked;
      qsa('.invoice-checkbox').forEach(cb=>{ cb.checked=v; });
      updateBulkButtons();
    });
    el.tbody.addEventListener('change',(e)=>{
      if(e.target.classList.contains('invoice-checkbox')){
        if(!e.target.checked && el.chkAll) el.chkAll.checked=false;
        updateBulkButtons();
      }
    });
  }

  function bindRowActionEvents(){
    el.tbody.addEventListener('click', async (e)=>{
      const payBtn  = e.target.closest('.act-pagar');
      const pendBtn = e.target.closest('.act-pendiente');
      const delBtn  = e.target.closest('.act-eliminar');

      try{
        if(payBtn){
          const id = Number(payBtn.dataset.id);
          await postJSON(CFG.endpoints.cambiar_estado,{id,estado:'pagado'});
          fetchList().catch(console.error);
        } else if(pendBtn){
          const id = Number(pendBtn.dataset.id);
          await postJSON(CFG.endpoints.cambiar_estado,{id,estado:'pendiente'});
          fetchList().catch(console.error);
        } else if(delBtn){
          const id = Number(delBtn.dataset.id);
          if(!confirm('¿Eliminar factura seleccionada?')) return;
          await postJSON(CFG.endpoints.eliminar,{id});
          fetchList().catch(console.error);
        }
      }catch(err){ alert('Error: ' + err.message); }
    });
  }

  function bindBulkActions(){
    qs('#btn-bulk-pagar')?.addEventListener('click', async ()=>{
      const ids=selectedIds(); if(!ids.length) return;
      try{ await postJSON(CFG.endpoints.bulk_estado,{ids,estado:'pagado'}); fetchList().catch(console.error); }
      catch(err){ alert('Error: ' + err.message); }
    });

    qs('#btn-bulk-eliminar')?.addEventListener('click', async ()=>{
      const ids=selectedIds(); if(!ids.length) return;
      if(!confirm('¿Eliminar facturas seleccionadas?')) return;
      try{
        for(const id of ids){ await postJSON(CFG.endpoints.eliminar,{id}); }
        fetchList().catch(console.error);
      }catch(err){ alert('Error: ' + err.message); }
    });
  }

  function bindSortEvents(){
    el.thead?.addEventListener('click',(e)=>{
      const th = e.target.closest('[data-sort]'); if(!th) return;
      const field = th.getAttribute('data-sort'); if(!field) return;
      if(state.sort===field){ state.order = state.order==='asc' ? 'desc' : 'asc'; }
      else { state.sort = field; state.order='asc'; }
      state.page=1; fetchList().catch(console.error);
    });
  }

  // Tema oscuro/claro estándar
  function initTheme(){
    const d = el.themeDarkIcon, l = el.themeLightIcon;
    const apply = (t)=>{
      if(t==='dark'){ document.documentElement.classList.add('dark'); d?.classList.remove('hidden'); l?.classList.add('hidden'); }
      else { document.documentElement.classList.remove('dark'); d?.classList.add('hidden'); l?.classList.remove('hidden'); }
    };
    const saved = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark':'light');
    apply(saved);
    el.themeBtn?.addEventListener('click', ()=>{
      const nt = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('theme', nt); apply(nt);
    });
  }

  // Compat: si el sidebar universal no está cargado, soporta el botón desktop
  function initSidebarCompat(){
    if (window.__sidebar_fix__) return; // ya lo maneja el universal
    document.addEventListener('click',(e)=>{
      const btn = e.target.closest('#sidebar-toggle-desktop');
      if(!btn) return;
      e.preventDefault();
      document.body.classList.toggle('sidebar-collapsed');
      setTimeout(()=>window.dispatchEvent(new Event('resize')),120);
    });
  }

  // --- Init ---
  document.addEventListener('DOMContentLoaded', ()=>{
    // Flatpickr rango
    if (window.flatpickr) { window.flatpickr('#flt-rango',{mode:'range',dateFormat:'Y-m-d',locale:'es'}); }

    initTheme();
    initSidebarCompat();

    bindFilterEvents();
    bindPagerEvents();
    bindSelectionEvents();
    bindRowActionEvents();
    bindBulkActions();
    bindSortEvents();

    fetchList().catch((err)=>{
      console.error(err);
      el.tbody.innerHTML = `<tr><td colspan="9" class="py-4 px-4 text-sm text-red-600">No se pudo cargar el listado.</td></tr>`;
    });
  });
})();

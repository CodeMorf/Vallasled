/* licencias.js: listado + acciones en modales */
(() => {
  const CFG = window.LIC_CFG || {};
  const CSRF = CFG.csrf;
  const EP = CFG.endpoints || {};
  const LIMIT = (CFG.page && CFG.page.limit) ? CFG.page.limit : 50;

  // asegura rutas absolutas
  const ABS = p => /^https?:\/\//.test(p) ? p : (p.startsWith('/') ? p : '/'+p);

  const $ = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  const elTbody = $('#lic-tbody');
  const elEmpty = $('#lic-empty');
  const elPager = $('#lic-pager');

  const qEl = $('#f-q'), estEl = $('#f-estado'), dEl = $('#f-desde'), hEl = $('#f-hasta');

  let state = { offset: 0, total: 0, limit: LIMIT, lastQuery: '' };

  /* -------- utilities -------- */
  const fmt = d => d ? String(d).substring(0,10) : '';
  const esc = s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
  const badge = est => {
    const map = {
      aprobada: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
      enviada: 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
      borrador: 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-300',
      rechazada: 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
      vencida: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
      por_vencer: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'
    };
    return `<span class="chip ${map[est] || ''}">${esc(est || '')}</span>`;
  };
  const toast = (msg, ms=2200) => {
    const t = $('#toast'), m = $('#toast-msg');
    m.textContent = msg; t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, ms);
  };

  /* -------- KPIs -------- */
  async function loadKPIs() {
    try {
      const r = await fetch(ABS(EP.kpis), {headers: {'X-CSRF': CSRF}});
      const j = await r.json();
      if (!j.ok) return;
      $('#kpi-aprob').textContent = j.data.aprobadas ?? 0;
      $('#kpi-porv').textContent  = j.data.por_vencer ?? 0;
      $('#kpi-venc').textContent  = j.data.vencidas ?? 0;
      $('#kpi-borr').textContent  = j.data.borrador ?? 0;
    } catch {}
  }

  /* -------- Listado -------- */
  const buildQuery = () => {
    const params = new URLSearchParams();
    const q = qEl.value.trim(); if (q) params.set('q', q);
    const e = estEl.value; if (e) params.set('estado', e);
    const d = dEl.value; if (d) params.set('desde', d);
    const h = hEl.value; if (h) params.set('hasta', h);
    params.set('limit', state.limit);
    params.set('offset', state.offset);
    return params.toString();
  };

  function rowHtml(it){
    const id = it.id;
    const col1 = `<div class="font-semibold">#${id}</div><div class="text-[var(--text-secondary)]">${esc(it.valla || it.codigo || '')}</div>`;
    const col2 = `<div>${esc(it.proveedor || '')}</div><div class="text-[var(--text-secondary)]">${esc(it.cliente || '')}</div>`;
    const col3 = `<div>${esc(it.ciudad || '')}</div><div class="text-[var(--text-secondary)]">${esc(it.entidad || '')}</div>`;
    const col4 = `<div>Emisión: ${fmt(it.emision)}</div><div>Venc.: ${fmt(it.vencimiento)}</div>`;
    const act = `
      <div class="flex items-center justify-end gap-2">
        <button class="act-view px-2 py-1 rounded hover:bg-[var(--sidebar-active-bg)]" data-id="${id}" title="Ver"><i class="fa fa-eye"></i></button>
        <button class="act-edit px-2 py-1 rounded hover:bg-[var(--sidebar-active-bg)]" data-id="${id}" title="Editar"><i class="fa fa-pen"></i></button>
        <button class="act-del px-2 py-1 rounded hover:bg-[var(--sidebar-active-bg)] text-red-600" data-id="${id}" title="Eliminar"><i class="fa fa-trash"></i></button>
      </div>`;
    return `<tr data-id="${id}">
      <td class="px-6 py-3">${col1}</td>
      <td class="px-6 py-3">${col2}</td>
      <td class="px-6 py-3">${col3}</td>
      <td class="px-6 py-3">${col4}</td>
      <td class="px-6 py-3 text-center">${badge(it.estado)}</td>
      <td class="px-6 py-3 text-right">${act}</td>
    </tr>`;
  }

  async function loadList(reset=false){
    if (reset) state.offset = 0;
    const q = buildQuery();
    state.lastQuery = q;
    const url = ABS(EP.listar) + '?' + q;
    try{
      const r = await fetch(url, {headers:{'X-CSRF': CSRF}});
      const j = await r.json();
      if (!j.ok) throw new Error('bad');
      state.total = j.total || 0;

      if (!j.items || j.items.length === 0) {
        elTbody.innerHTML = '';
        elEmpty.classList.remove('hidden');
        elPager.innerHTML = '';
        return;
      }
      elEmpty.classList.add('hidden');
      elTbody.innerHTML = j.items.map(rowHtml).join('');

      // bind actions
      elTbody.querySelectorAll('.act-view').forEach(b => b.addEventListener('click', onView));
      elTbody.querySelectorAll('.act-edit').forEach(b => b.addEventListener('click', onEdit));
      elTbody.querySelectorAll('.act-del').forEach(b => b.addEventListener('click', onDelete));

      renderPager();
    }catch(e){
      elTbody.innerHTML = '';
      elEmpty.classList.remove('hidden');
      elPager.innerHTML = '';
    }
  }

  function renderPager(){
    const total = state.total, limit = state.limit, offset = state.offset;
    const page = Math.floor(offset/limit)+1;
    const pages = Math.max(1, Math.ceil(total/limit));
    const prevDis = offset<=0 ? 'opacity-50 pointer-events-none' : '';
    const nextDis = (offset+limit)>=total ? 'opacity-50 pointer-events-none' : '';
    elPager.innerHTML = `
      <div class="inline-flex items-center gap-2">
        <button id="pg-prev" class="px-3 py-1.5 rounded border ${prevDis}">Anterior</button>
        <span class="text-sm">Página ${page} de ${pages} · ${total} registros</span>
        <button id="pg-next" class="px-3 py-1.5 rounded border ${nextDis}">Siguiente</button>
      </div>`;
    $('#pg-prev')?.addEventListener('click', ()=>{ state.offset=Math.max(0, state.offset - limit); loadList(false); });
    $('#pg-next')?.addEventListener('click', ()=>{ state.offset = Math.min(total-1, state.offset + limit); loadList(false); });
  }

  /* -------- Modales -------- */
  const dlg = $('#dlg-detalle');
  const dlgBody = $('#dlg-body');
  const dlgTitle = $('#dlg-title');
  const dlgEdit = $('#dlg-edit');

  function openDlg(){ dlg.classList.add('show'); document.body.style.overflow='hidden'; }
  function closeDlg(){ dlg.classList.remove('show'); document.body.style.overflow=''; }

  $('#dlg-close')?.addEventListener('click', closeDlg);
  dlg?.addEventListener('click', (e)=>{ if(e.target===dlg) closeDlg(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') { closeDlg(); closeConfirm(); } });

  async function onView(e){
    e.preventDefault();
    const id = Number(e.currentTarget.dataset.id);
    try{
      const uDet = new URL(ABS(EP.detalle), location.origin);
      uDet.searchParams.set('id', String(id));
      const r = await fetch(uDet, {headers:{'X-CSRF': CSRF}});
      const j = await r.json();
      if (!j.ok) throw new Error('NO_OK');

      const d = j.data || {};
      dlgTitle.textContent = `Licencia #${d.id}${d.titulo ? ' · '+d.titulo : ''}`;
      dlgBody.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Proveedor</p><p>${esc(d.proveedor?.nombre || d.proveedor?.id || '')}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Cliente</p><p>${esc(d.cliente?.nombre || d.cliente?.id || '')}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Valla</p><p>${esc(d.valla?.nombre || d.valla?.codigo || d.valla?.id || '')}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Estado</p><p>${badge(d.estado)}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Ciudad / Entidad</p><p>${esc(d.ciudad || '')} · ${esc(d.entidad || '')}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Tipo</p><p>${esc(d.tipo_licencia || '')}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Fechas</p><p>Emisión: ${fmt(d.fecha_emision)} · Venc.: ${fmt(d.fecha_vencimiento)}</p></div>
          <div><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Recordatorio</p><p>${d.reminder_days ?? ''} días</p></div>
          <div class="md:col-span-2"><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Dirección</p><p>${esc(d.direccion || '')}</p></div>
          <div class="md:col-span-2"><p class="text-[var(--text-secondary)] text-xs uppercase mb-1">Notas</p><p>${esc(d.notas || '')}</p></div>
        </div>
        ${Array.isArray(d.files) && d.files.length ? `
          <div class="mt-4">
            <p class="text-[var(--text-secondary)] text-xs uppercase mb-2">Archivos</p>
            <ul class="list-disc pl-6">
              ${d.files.map(f=>`<li><a class="text-indigo-600 hover:underline" href="${esc(f.ruta)}" target="_blank" rel="noopener">${esc(f.nombre || f.ruta)}</a></li>`).join('')}
            </ul>
          </div>` : '' }
      `;
      dlgEdit.onclick = () => { location.href = `/console/licencias/crear/index.php?id=${id}`; };
      openDlg();
    }catch(_e){ toast('No se pudo cargar el detalle'); }
  }

  /* confirm modal */
  const cDlg = $('#dlg-confirm');
  const cText = $('#c-text');
  let cResolve = null;

  function openConfirm(msg){
    cText.textContent = msg || '¿Confirmar?';
    cDlg.classList.add('show'); document.body.style.overflow='hidden';
    return new Promise(res => { cResolve = res; });
  }
  function closeConfirm(){ cDlg.classList.remove('show'); document.body.style.overflow=''; cResolve=null; }

  $('#c-close')?.addEventListener('click', closeConfirm);
  $('#c-cancel')?.addEventListener('click', ()=>{ if(cResolve) cResolve(false); closeConfirm(); });
  $('#c-ok')?.addEventListener('click', ()=>{ if(cResolve) cResolve(true); closeConfirm(); });

  async function onDelete(e){
    e.preventDefault();
    const id = Number(e.currentTarget.dataset.id);
    const ok = await openConfirm(`¿Eliminar la licencia #${id}? Esta acción no se puede deshacer.`);
    if (!ok) return;
    try{
      const r = await fetch(ABS(EP.eliminar), {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF': CSRF},
        body: JSON.stringify({id})
      });
      const j = await r.json();
      if (!j.ok) throw new Error('bad');
      toast('Eliminado');
      loadList(false);
      loadKPIs();
    }catch(_e){ toast('Error al eliminar'); }
  }

  function onEdit(e){
    e.preventDefault();
    const id = Number(e.currentTarget.dataset.id);
    location.href = `/console/licencias/crear/index.php?id=${id}`;
  }

  /* -------- filtros y export -------- */
  $('#btn-filtrar')?.addEventListener('click', (e)=>{ e.preventDefault(); loadList(true); });

  let tDeb = null;
  qEl?.addEventListener('input', ()=> {
    clearTimeout(tDeb);
    tDeb = setTimeout(()=> loadList(true), 300);
  });

  $('#btn-export')?.addEventListener('click', (e)=>{
    e.preventDefault();
    const qs = buildQuery();
    const url = ABS(EP.exportar) + `?${qs}&csrf=${encodeURIComponent(CSRF)}`;
    window.open(url, '_blank');
  });

  /* -------- init -------- */
  loadKPIs();
  loadList(true);
})();

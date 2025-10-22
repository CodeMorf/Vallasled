// /console/asset/js/gestion/clientes.js
(function(){
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const cfg = window.CLIENTES_CFG || {};
  const CSRF = cfg.csrf || $('meta[name="csrf"]')?.content || '';

  // ===== Tema (no toca sidebar) =====
  (function theme(){
    const btn=$('#theme-toggle'), d=$('#theme-toggle-dark-icon'), l=$('#theme-toggle-light-icon');
    const apply = t => { const dark=t==='dark'; document.documentElement.classList.toggle('dark',dark); d?.classList.toggle('hidden',!dark); l?.classList.toggle('hidden',dark); };
    const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
    apply(saved);
    btn?.addEventListener('click', ()=>{ const nt=document.documentElement.classList.contains('dark')?'light':'dark'; localStorage.setItem('theme',nt); apply(nt); });
  })();

  // ===== Helpers =====
  const escapeHtml = s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const debounce = (fn, ms=220)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };
  const jGET = (url, params={})=>{
    const u=new URL(url, location.origin);
    Object.entries(params).forEach(([k,v])=>{ if(v!=='' && v!=null) u.searchParams.set(k, v); });
    return fetch(u.toString(), {credentials:'same-origin'}).then(r=>r.json());
  };
  const jPOST = (url, body={})=>fetch(url,{
    method:'POST', credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
    body:JSON.stringify(body)
  }).then(r=>r.json());
  const toast = msg => { const el=document.createElement('div'); el.className='toast'; el.textContent=msg; document.body.appendChild(el); setTimeout(()=>el.remove(), 2500); };

  // ===== DOM =====
  const tbody = $('#clients-tbody'), empty = $('#empty-state'), totalLbl = $('#total-clientes');
  const q = $('#search-filter'), selProv = $('#proveedor-filter');
  const addBtn = $('#add-client-btn');

  // Modal
  const modal = $('#client-modal'), box = $('#client-modal-container');
  const title = $('#modal-title'), closeX = $('#close-modal-btn'), cancelBtn = $('#cancel-btn'), form = $('#client-form');
  const fid = $('#client-id'), ftype = $('#client-type-input');
  const fnombre = $('#responsable'), fempresa = $('#nombre_empresa'), femail = $('#email'), ftel = $('#telefono'), frnc = $('#rnc'), fprov = $('#modal-proveedor-select');
  const tabs = $$('#client-modal .tab-button');

  // ===== Estado =====
  let page=1, limit=20, pages=1;

  // ===== Proveedores =====
  async function loadProveedores(){
    try{
      const r = await jGET(cfg.proveedores);
      if(!r?.ok) return;
      const list = r.data || [];
      selProv.innerHTML = list.map(p=>`<option value="${p.id}">${escapeHtml(p.nombre)}</option>`).join('');
      // asegúrate que "Todos" exista como id 0 al inicio
      if (!list.some(p=>String(p.id)==='0')){
        selProv.insertAdjacentHTML('afterbegin','<option value="0">Todos los Proveedores</option>');
      }
      fprov.innerHTML = list.filter(p=>String(p.id)!=='0').map(p=>`<option value="${p.id}">${escapeHtml(p.nombre)}</option>`).join('');
    }catch{}
  }

  // ===== Render =====
  function render(items=[], total=0, pageNow=1, pageCount=1){
    tbody.innerHTML = '';
    if (!items.length){ empty.classList.remove('hidden'); totalLbl.textContent='0'; pages=1; return; }
    empty.classList.add('hidden');

    const frag=document.createDocumentFragment();
    for (const c of items){
      // tolerante a nombres de campos
      const tipo = c.type || c.tipo || (c.proveedor_id ? 'crm' : 'base');
      const responsable = c.responsable || c.nombre || '';
      const empresa = c.nombre_empresa || c.empresa || '';
      const email = c.email || '';
      const tel = c.telefono || c.telefono1 || '';
      const rnc = c.rnc || c.cedula || '';
      const provName = c.proveedor_nombre || c.proveedor?.nombre || '';

      const tr=document.createElement('tr');
      tr.className='hover:bg-[var(--main-bg)]';
      tr.dataset.id = c.id;
      tr.dataset.type = tipo;

      tr.innerHTML = `
        <td class="py-4 px-4">
          <p class="font-semibold text-[var(--text-primary)]">${escapeHtml(responsable || empresa || '—')}</p>
          <p class="text-xs text-[var(--text-secondary)]">${escapeHtml(empresa)}</p>
        </td>
        <td class="py-4 px-4 hidden md:table-cell">
          <p>${escapeHtml(email || 'N/A')}</p>
          <p class="text-xs text-[var(--text-secondary)]">${escapeHtml(tel || 'N/A')}</p>
        </td>
        <td class="py-4 px-4 hidden lg:table-cell font-mono">${escapeHtml(rnc || 'N/A')}</td>
        <td class="py-4 px-4">
          <span class="pill ${tipo==='crm'?'pill-crm':'pill-base'}">${tipo==='crm'?'CRM':'Base'}</span>
        </td>
        <td class="py-4 px-4 hidden lg:table-cell">${escapeHtml(provName || 'N/A')}</td>
        <td class="py-4 px-4 text-center">
          <button class="p-2 text-[var(--text-secondary)] hover:text-yellow-500 edit-btn" title="Editar"><i class="fas fa-pencil-alt"></i></button>
          <button class="p-2 text-[var(--text-secondary)] hover:text-red-500 delete-btn" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      frag.appendChild(tr);
    }
    tbody.appendChild(frag);
    totalLbl.textContent = String(total||items.length);
    pages = pageCount;
  }

  // ===== Data =====
  async function load(){
    const params = { q: q?.value?.trim() || '', proveedor_id: Number(selProv?.value || 0) || 0, page, limit };
    let r;
    try{ r = await jGET(cfg.listar, params); }catch{ toast('Error de red'); return; }
    if(!r?.ok){ toast(r?.msg||'Error'); return; }
    const d = r.data || {};
    render(d.items||[], d.total||0, d.page||1, d.pages||1);
  }

  // ===== Modal =====
  function setTab(tipo){
    ftype.value = tipo;
    tabs.forEach(t=>t.classList.toggle('active', t.dataset.tab===tipo));
    // proveedor visible solo en CRM
    $('#proveedor-container')?.classList.toggle('hidden', tipo!=='crm');
    fprov.required = (tipo==='crm');
  }
  function openModal(data){
    form?.reset(); $$('.input-error').forEach(x=>x.classList.remove('input-error'));
    if (data){
      title.textContent = 'Editar Cliente';
      fid.value = data.id;
      setTab(data.type || data.tipo || 'base');
      fnombre.value = data.responsable || data.nombre || '';
      fempresa.value = data.nombre_empresa || data.empresa || '';
      femail.value = data.email || '';
      ftel.value = data.telefono || data.telefono1 || '';
      frnc.value = data.rnc || data.cedula || '';
      if (ftype.value==='crm' && (data.proveedor_id || data.proveedor?.id)) fprov.value = String(data.proveedor_id || data.proveedor?.id || '');
    } else {
      title.textContent = 'Agregar Nuevo Cliente';
      fid.value = ''; setTab('base');
    }
    modal.classList.remove('hidden');
    setTimeout(()=>{ modal.classList.remove('opacity-0'); box?.classList.remove('scale-95'); }, 10);
  }
  function closeModal(){ box?.classList.add('scale-95'); modal?.classList.add('opacity-0'); setTimeout(()=>modal?.classList.add('hidden'), 220); }

  function validar(p){
    const errs={};
    if (!p.responsable || p.responsable.trim().length<2) errs.responsable='Requerido';
    if (p.type==='crm' && !p.proveedor_id) errs.proveedor_id='Proveedor requerido';
    if (p.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.email)) errs.email='Email inválido';
    if (p.telefono && !/^\+?[0-9 ()\-]{6,20}$/.test(p.telefono)) errs.telefono='Teléfono inválido';
    return errs;
  }

  // ===== Eventos =====
  q?.addEventListener('input', debounce(()=>{ page=1; load(); }, 250));
  selProv?.addEventListener('change', ()=>{ page=1; load(); });

  addBtn?.addEventListener('click', ()=>openModal());
  closeX?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });

  tabs.forEach(tb=>tb.addEventListener('click', ()=>setTab(tb.dataset.tab)));

  // guardar
  form?.addEventListener('submit', async e=>{
    e.preventDefault();
    const payload = {
      id: fid.value ? Number(fid.value) : null,
      type: ftype.value || 'base',
      responsable: fnombre.value.trim(),
      nombre_empresa: fempresa.value.trim(),
      email: femail.value.trim(),
      telefono: ftel.value.trim(),
      rnc: frnc.value.trim(),
    };
    if (payload.type==='crm') payload.proveedor_id = Number(fprov.value||0);
    const errs = validar(payload);
    $$('.input-error').forEach(x=>x.classList.remove('input-error'));
    if (Object.keys(errs).length){
      if (errs.responsable) fnombre.classList.add('input-error');
      if (errs.proveedor_id) fprov.classList.add('input-error');
      if (errs.email) femail.classList.add('input-error');
      if (errs.telefono) ftel.classList.add('input-error');
      toast('Corrige los campos marcados'); return;
    }
    let r; try{
      r = await jPOST(payload.id ? cfg.editar : cfg.crear, payload);
    }catch{ toast('Error de red'); return; }
    if(!r?.ok){ toast(r?.msg||'No se pudo guardar'); return; }
    toast('Guardado');
    closeModal(); load();
  });

  // acciones fila
  tbody?.addEventListener('click', async e=>{
    const tr = e.target.closest('tr'); if(!tr) return;
    const id = Number(tr.dataset.id), type = tr.dataset.type||'base';

    if (e.target.closest('.edit-btn')){
      // opcional: GET por id si tu listar.php acepta ?id
      let it;
      try{
        const r = await jGET(cfg.listar, { id, page:1, limit:1 });
        it = r?.data?.items?.[0];
      }catch{}
      openModal(it || { id, type,
        responsable: tr.querySelector('td:nth-child(1) .font-semibold')?.textContent.trim() || '',
        nombre_empresa: tr.querySelector('td:nth-child(1) .text-xs')?.textContent.trim() || '',
        email: tr.querySelector('td:nth-child(2) p')?.textContent.trim() || '',
        telefono: tr.querySelector('td:nth-child(2) .text-xs')?.textContent.trim() || ''
      });
      return;
    }

    if (e.target.closest('.delete-btn')){
      if(!confirm('¿Eliminar cliente?')) return;
      let r; try{ r = await jPOST(cfg.eliminar, { id }); }catch{ toast('Error de red'); return; }
      if(!r?.ok){ toast(r?.msg||'No se pudo eliminar'); return; }
      toast('Eliminado'); load();
    }
  });

  // ===== Init =====
  (async function init(){
    await loadProveedores();
    await load();
  })();

})();

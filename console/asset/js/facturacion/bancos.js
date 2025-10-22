// /console/asset/js/facturacion/bancos.js
(function(){
  'use strict';

  const $ = s => document.querySelector(s);
  const cfg = window.BANCOS_CFG || {};
  const CSRF = cfg.csrf || $('meta[name="csrf"]')?.content || '';
  const escapeHtml = s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const debounce = (fn, ms=150) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  const jGET = (url, params = {}) => {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k,v]) => { if(v!=='' && v!=null) u.searchParams.set(k, v); });
    return fetch(u.toString(), { credentials:'same-origin' }).then(r => r.json());
  };
  const jPOST = (url, body) => fetch(url, {
    method:'POST', credentials:'same-origin',
    headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify(body)
  }).then(r => r.json());

  // Tema claro/oscuro (igual a facturas)
  (function themeUnit(){
    const btn = $('#theme-toggle');
    const d = $('#theme-toggle-dark-icon');
    const l = $('#theme-toggle-light-icon');
    const apply = (t) => {
      if (t === 'dark') { document.documentElement.classList.add('dark'); d?.classList.remove('hidden'); l?.classList.add('hidden'); }
      else { document.documentElement.classList.remove('dark'); d?.classList.add('hidden'); l?.classList.remove('hidden'); }
    };
    const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark':'light');
    apply(saved);
    btn?.addEventListener('click', () => {
      const nt = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('theme', nt); apply(nt);
    });
  })();

  // DOM
  const tbody = $('#accounts-tbody');
  const empty = $('#empty-state');
  const statTotal = $('#stat-total');
  const pager = $('#pager');
  const q = $('#flt-q');
  const est = $('#flt-estado');

  // Modal
  const modal = $('#account-modal');
  const modalBox = $('#account-modal-container');
  const openBtn = $('#add-account-btn');
  const closeBtn = $('#close-modal-btn');
  const cancelBtn = $('#cancel-btn');
  const form = $('#account-form');
  const fid = $('#account-id');
  const fbanco = $('#banco');
  const ftit = $('#titular');
  const ftipo = $('#tipo-cuenta');
  const fnum = $('#numero-cuenta');

  // Estado
  let page = 1;
  const limit = (cfg.page && cfg.page.limit) ? cfg.page.limit : 20;

  function badge(b){ return b ? '<span class="badge-active">Activo</span>' : '<span class="badge-inactive">Inactivo</span>'; }

  function renderRows(rows){
    tbody.innerHTML = '';
    if (!rows.length){ empty?.classList.remove('hidden'); return; }
    empty?.classList.add('hidden');

    const frag = document.createDocumentFragment();
    rows.forEach(r=>{
      const id = Number(r.id);
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-[var(--main-bg)]';
      tr.dataset.id = id;
      const toggleId = `toggle-${id}`;
      tr.innerHTML = `
        <td class="py-4 px-4 font-semibold text-[var(--text-primary)]">${escapeHtml(r.banco||'')}</td>
        <td class="py-4 px-4">${escapeHtml(r.titular||'')}</td>
        <td class="py-4 px-4 hidden md:table-cell">${escapeHtml(r.tipo_cuenta||'')}</td>
        <td class="py-4 px-4 hidden lg:table-cell font-mono">${escapeHtml(r.numero_cuenta||'')}</td>
        <td class="py-4 px-4">
          ${badge(!!r.activo)}
          <label class="relative inline-block w-10 h-6 align-middle select-none ml-2">
            <input type="checkbox" id="${toggleId}" class="toggle-checkbox" ${r.activo?'checked':''}>
            <span class="toggle-track block"></span>
            <span class="toggle-thumb"></span>
          </label>
        </td>
        <td class="py-4 px-4 text-center">
          <button class="edit-btn p-2 text-[var(--text-secondary)] hover:text-yellow-600" title="Editar"><i class="fas fa-pencil-alt"></i></button>
          <button class="delete-btn p-2 text-[var(--text-secondary)] hover:text-red-600" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      frag.appendChild(tr);
    });
    tbody.appendChild(frag);
  }

  function renderPager(pages){
    if (!pager) return;
    pager.innerHTML = '';
    if (pages <= 1) return;
    function mk(label, disabled, go){
      const b=document.createElement('button');
      b.className='pager-btn'; b.textContent=label; b.disabled=disabled;
      b.addEventListener('click', ()=>{ page=go; load(); });
      return b;
    }
    pager.appendChild(mk('«', page===1, 1));
    pager.appendChild(mk('‹', page===1, page-1));
    const s=document.createElement('span'); s.className='px-3 text-sm text-[var(--text-secondary)]'; s.textContent=`${page} / ${pages}`; pager.appendChild(s);
    pager.appendChild(mk('›', page>=pages, page+1));
    pager.appendChild(mk('»', page>=pages, pages));
  }

  async function load(){
    const params = { page, limit };
    const qq = q?.value.trim(); if (qq) params.q = qq;
    if (est && est.value!=='') params.estado = est.value;

    let r;
    try{ r = await jGET(cfg.listar, params); } catch(e){ console.error(e); return; }

    const rows = r?.rows || [];
    const total = r?.total || 0;
    statTotal && (statTotal.textContent = String(total));
    renderRows(rows);
    renderPager(r?.pages || 1);
  }

  function openModal(data){
    form?.reset();
    if (data){
      $('#modal-title').textContent = 'Editar Cuenta Bancaria';
      fid.value = data.id;
      fbanco.value = data.banco || '';
      ftit.value = data.titular || '';
      ftipo.value = data.tipo_cuenta || 'Corriente';
      fnum.value = data.numero_cuenta || '';
    } else {
      $('#modal-title').textContent = 'Agregar Cuenta Bancaria';
      fid.value = '';
    }
    modal?.classList.remove('hidden');
    setTimeout(()=>{ modal?.classList.remove('opacity-0'); modalBox?.classList.remove('scale-95'); },10);
  }
  function closeModal(){ modalBox?.classList.add('scale-95'); modal?.classList.add('opacity-0'); setTimeout(()=>modal?.classList.add('hidden'), 300); }

  function validar(p){
    if (!p.banco || !p.titular || !p.numero_cuenta) return 'Campos requeridos: banco, titular, número de cuenta.';
    if (!/^[A-Za-zÁÉÍÓÚÑáéíóúñ0-9 \-]{6,34}$/.test(p.numero_cuenta)) return 'Número de cuenta inválido.';
    if (!['Corriente','Ahorros'].includes(p.tipo_cuenta)) return 'Tipo de cuenta inválido.';
    return null;
  }

  q?.addEventListener('input', debounce(()=>{ page=1; load(); }, 200));
  est?.addEventListener('change', ()=>{ page=1; load(); });

  openBtn?.addEventListener('click', ()=>openModal());
  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !modal?.classList.contains('hidden')) closeModal(); });

  form?.addEventListener('submit', async e=>{
    e.preventDefault();
    const payload = {
      id: fid.value ? Number(fid.value) : null,
      banco: fbanco.value.trim(),
      titular: ftit.value.trim(),
      tipo_cuenta: ftipo.value,
      numero_cuenta: fnum.value.trim()
    };
    const err = validar(payload); if (err){ alert(err); return; }
    let resp;
    try{ resp = await jPOST(cfg.guardar, payload); }
    catch{ alert('Error de red'); return; }
    if (!resp?.ok){ alert(resp?.msg || 'Error al guardar'); return; }
    closeModal(); load();
  });

  tbody?.addEventListener('click', async e=>{
    const tr = e.target.closest('tr'); if (!tr) return;
    const id = Number(tr.dataset.id);

    if (e.target.closest('.edit-btn')){
      const tds = tr.querySelectorAll('td');
      openModal({
        id,
        banco: tds[0]?.textContent.trim() || '',
        titular: tds[1]?.textContent.trim() || '',
        tipo_cuenta: tds[2]?.textContent.trim() || 'Corriente',
        numero_cuenta: tds[3]?.textContent.trim() || ''
      });
      return;
    }
    if (e.target.closest('.delete-btn')){
      if (!confirm('¿Eliminar cuenta?')) return;
      let r; try{ r = await jPOST(cfg.estados, { action:'eliminar', id }); }
      catch{ alert('Error de red'); return; }
      if (!r?.ok){ alert(r?.msg || 'No se pudo eliminar'); return; }
      load(); return;
    }
    if (e.target.classList.contains('toggle-checkbox')){
      const checked = e.target.checked;
      let r;
      try{ r = await jPOST(cfg.estados, { action: checked ? 'activar' : 'inactivar', id }); }
      catch{ e.target.checked = !checked; alert('Error de red'); return; }
      if (!r?.ok){
        e.target.checked = !checked;
        alert(r?.msg || 'No se pudo actualizar el estado');
      } else {
        const stateCell = tr.children[4];
        stateCell.querySelectorAll('.badge-active,.badge-inactive').forEach(s=>s.remove());
        stateCell.insertAdjacentHTML('afterbegin', checked ? '<span class="badge-active">Activo</span>' : '<span class="badge-inactive">Inactivo</span>');
      }
    }
  });

  load();
})();

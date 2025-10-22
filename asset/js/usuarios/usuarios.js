// /console/asset/js/usuarios/usuarios.js
/* eslint-disable */
(function () {
  "use strict";

  // --- helpers
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => [...r.querySelectorAll(s)];
  const CSRF = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
  const cfg = window.USR_CFG || window.USU_CFG || { endpoints:{}, page:{ limit: 50 } };

  const tbody   = $('#usr-tbody');
  const emptyBx = $('#usr-empty');
  const pager   = $('#usr-pager');
  const pgPrev  = $('#pg-prev');
  const pgNext  = $('#pg-next');
  const pgInfo  = $('#pg-info');

  const fQ      = $('#f-q');
  const fTipo   = $('#f-tipo');
  const fRol    = $('#f-rol');
  const fEstado = $('#f-estado');
  const fClear  = $('#f-clear');

  // modal
  const modal      = $('#dlg-user');
  const dlgTitle   = $('#dlg-title');
  const uId        = $('#u-id');
  const uEmail     = $('#u-email');
  const uResp      = $('#u-resp');
  const uPass      = $('#u-pass');
  const uPass2     = $('#u-pass2');
  const uTipo      = $('#u-tipo');
  const uRol       = $('#u-rol');
  const uEmp       = $('#u-empresa');
  const uActivo    = $('#u-activo');
  const uSave      = $('#u-save');
  const uCancel    = $('#u-cancel');
  const dlgClose   = $('#dlg-close');

  // toast
  const toast    = document.getElementById('toast') || (()=>{ const t=document.createElement('div'); t.id='toast'; t.className='toast'; t.style.display='none'; t.innerHTML='<div id="toast-msg" class="px-4 py-2 rounded-lg bg-[var(--card-bg)] border border-[var(--border-color)] shadow"></div>'; document.body.appendChild(t); return t; })();
  const toastMsg = document.getElementById('toast-msg') || toast.querySelector('#toast-msg');
  function showToast(msg){ toastMsg.textContent=msg; toast.style.display='block'; setTimeout(()=>toast.style.display='none', 2400); }

  const state = {
    limit: (cfg.page && cfg.page.limit) || 50,
    offset: 0,
    total: 0,
    rows: [],
    loading: false,
    filters: { q:'', tipo:'', rol:'', estado:'' }
  };

  function escapeHtml(s){
    return String(s ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  async function postForm(url, data){
    const body = new URLSearchParams({ csrf: CSRF, ...data });
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  function renderRow(u){
    const statusCls = u.activo ? 'text-green-800 bg-green-200 dark:bg-green-500/20 dark:text-green-300'
                               : 'text-red-800 bg-red-200 dark:bg-red-500/20 dark:text-red-300';
    const statusTx  = u.activo ? 'Activo' : 'Inactivo';
    return `
      <tr class="hover:bg-[var(--main-bg)]" data-id="${u.id}">
        <td class="py-4 px-4">
          <div class="font-medium text-[var(--text-primary)]">${escapeHtml(u.responsable || u.usuario)}</div>
          <div class="text-xs text-[var(--text-secondary)]">${escapeHtml(u.usuario)}</div>
        </td>
        <td class="py-4 px-4 hidden lg:table-cell">${escapeHtml(u.nombre_empresa || '-')}</td>
        <td class="py-4 px-4 hidden md:table-cell capitalize">${escapeHtml(u.tipo)}</td>
        <td class="py-4 px-4 hidden sm:table-cell">${escapeHtml(u.rol)}</td>
        <td class="py-4 px-4">
          <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusCls}">${statusTx}</span>
        </td>
        <td class="py-4 px-4 text-center">
          <button class="edit-btn p-2 text-[var(--text-secondary)] hover:text-yellow-500" title="Editar"><i class="fas fa-pencil-alt"></i></button>
          <button class="del-btn  p-2 text-[var(--text-secondary)] hover:text-red-500"    title="Eliminar"><i class="fas fa-trash-alt"></i></button>
        </td>
      </tr>`;
  }

  function renderTable(rows){
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '';
      emptyBx?.classList.remove('hidden');
      pager?.classList.add('hidden');
      return;
    }
    emptyBx?.classList.add('hidden');
    tbody.innerHTML = rows.map(renderRow).join('');
    const from = state.total ? (state.offset + 1) : 0;
    const to = Math.min(state.offset + rows.length, state.total);
    if (pgInfo) pgInfo.textContent = `${from}–${to} de ${state.total}`;
    const hasPrev = state.offset > 0;
    const hasNext = state.offset + state.limit < state.total;
    pager?.classList.toggle('hidden', state.total <= state.limit);
    pgPrev?.toggleAttribute?.('disabled', !hasPrev);
    pgNext?.toggleAttribute?.('disabled', !hasNext);
  }

  async function listar(offset=0){
    if (state.loading) return;
    state.loading = true;
    state.offset = Math.max(0, offset);
    const payload = {
      limit: String(state.limit),
      offset: String(state.offset),
      q: state.filters.q,
      tipo: state.filters.tipo,
      rol: state.filters.rol,
      estado: state.filters.estado
    };
    try{
      const data = await postForm(cfg.endpoints.listar, payload);
      if (data.error) { showToast(data.error); return; }
      state.rows  = Array.isArray(data.rows) ? data.rows : [];
      state.total = Number(data.total || state.rows.length || 0);
      renderTable(state.rows);
    }catch(e){
      console.error(e); showToast('Error listando');
    }finally{
      state.loading = false;
    }
  }

  // filtros
  const debounce = (fn, ms=280) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };
  const applyFilters = debounce(async ()=>{
    state.filters.q      = fQ?.value.trim() || '';
    state.filters.tipo   = fTipo?.value || '';
    state.filters.rol    = fRol?.value || '';
    state.filters.estado = fEstado?.value || '';
    await listar(0);
  }, 250);

  fQ?.addEventListener('input', applyFilters);
  fTipo?.addEventListener('change', applyFilters);
  fRol?.addEventListener('change', applyFilters);
  fEstado?.addEventListener('change', applyFilters);
  fClear?.addEventListener('click', (e)=>{ e.preventDefault();
    if (fQ) fQ.value=''; if (fTipo) fTipo.value=''; if (fRol) fRol.value=''; if (fEstado) fEstado.value='';
    applyFilters();
  });

  // modal
  function openCreate(){
    if (!modal) return;
    dlgTitle.textContent = 'Crear Usuario';
    uId && (uId.value = '');
    if (uEmail){ uEmail.value=''; uEmail.disabled = false; }
    uResp && (uResp.value='');
    uPass && (uPass.value='');
    uPass2 && (uPass2.value='');
    uTipo && (uTipo.value='cliente');
    uRol && (uRol.value='operador');
    uEmp && (uEmp.value='');
    uActivo && (uActivo.checked = true);
    modal.classList.add('show');
    document.body.style.overflow='hidden';
    setTimeout(()=>uEmail?.focus(), 10);
  }
  function openEdit(u){
    if (!modal) return;
    dlgTitle.textContent = 'Editar Usuario';
    uId && (uId.value = String(u.id));
    if (uEmail){ uEmail.value = u.usuario || ''; uEmail.disabled = true; }
    uResp && (uResp.value = u.responsable || '');
    uPass && (uPass.value = '');
    uPass2 && (uPass2.value= '');
    uTipo && (uTipo.value = u.tipo || 'cliente');
    uRol && (uRol.value  = u.rol  || 'operador');
    uEmp && (uEmp.value  = u.nombre_empresa || '');
    uActivo && (uActivo.checked = !!u.activo);
    modal.classList.add('show');
    document.body.style.overflow='hidden';
    setTimeout(()=>uResp?.focus(), 10);
  }
  function closeModal(){
    modal?.classList.remove('show');
    document.body.style.overflow='auto';
  }

  // guardar (crear o editar)
  let saving = false;
  async function guardar(){
    if (saving) return;
    const payload = {
      id: uId?.value || '',
      usuario: uEmail?.value.trim() || '',
      responsable: uResp?.value.trim() || '',
      tipo: uTipo?.value || 'cliente',
      rol:  uRol?.value  || 'operador',
      nombre_empresa: uEmp?.value.trim() || '',
      activo: uActivo?.checked ? '1' : '0',
      pass: uPass?.value || '',
      pass2: uPass2?.value || ''
    };

    const creating = !payload.id;
    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.usuario);
    if (creating && !emailOk) { showToast('Email inválido'); return; }
    if ((payload.pass || payload.pass2) && payload.pass !== payload.pass2) { showToast('Las contraseñas no coinciden'); return; }
    if (payload.pass && payload.pass.length < 6) { showToast('Contraseña mínima 6'); return; }

    try{
      saving = true;
      uSave?.setAttribute('disabled','disabled');
      const res = await postForm(cfg.endpoints.guardar, payload);
      if (res.error) { showToast(res.error); return; }
      showToast('Guardado');
      closeModal();
      await listar(state.offset);
    }catch(e){
      console.error(e); showToast('Error guardando');
    }finally{
      saving = false;
      uSave?.removeAttribute('disabled');
    }
  }

  // eliminar (Alt+click = borrado físico si backend lo permite)
  async function eliminar(id, hard){
    if (!confirm(hard ? '¿Eliminar PERMANENTEMENTE este usuario?' : '¿Eliminar usuario?')) return;
    try{
      const res = await postForm(cfg.endpoints.eliminar, { id:String(id), hard: hard ? '1':'0' });
      if (res.error) { showToast(res.error); return; }
      showToast(hard ? 'Eliminado permanentemente' : 'Eliminado');
      await listar(state.offset);
      if (!state.rows.length && state.offset>0) await listar(Math.max(0, state.offset - state.limit));
    }catch(e){
      console.error(e); showToast('Error eliminando');
    }
  }

  // eventos modal
  uSave?.addEventListener('click', (e)=>{ e.preventDefault(); guardar().catch(()=>{}); });
  uCancel?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });
  dlgClose?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });
  modal?.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if (e.key==='Escape') closeModal(); });

  // acciones tabla
  tbody?.addEventListener('click', (e)=>{
    const tr = e.target.closest('tr'); if (!tr) return;
    const id = Number(tr.dataset.id);
    if (e.target.closest('.edit-btn')) {
      const u = state.rows.find(r => Number(r.id) === id);
      if (u) openEdit(u);
    } else if (e.target.closest('.del-btn')) {
      eliminar(id, e.altKey === true).catch(()=>{});
    }
  });

  // API pública
  window.USR = {
    openCreate,
    refresh: ()=>listar(state.offset),
    openEditById: (id)=>{ const u = state.rows.find(r => Number(r.id)===Number(id)); if (u) openEdit(u); }
  };

  // paginación
  pgPrev?.addEventListener('click', async (e)=>{ e.preventDefault(); await listar(Math.max(0, state.offset - state.limit)); });
  pgNext?.addEventListener('click', async (e)=>{ e.preventDefault(); const next = state.offset + state.limit; if (next < state.total) await listar(next); });

  // init
  (async function init(){
    try { await listar(0); } catch(e){ console.error(e); showToast('Error cargando'); }
  })();
})();

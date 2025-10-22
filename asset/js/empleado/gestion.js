/* /console/asset/js/empleado/gestion.js */
(function () {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // CSRF desde <meta name="csrf"> o <input hidden> fallback
  const CSRF_META = $('meta[name="csrf"]')?.content?.trim() || '';
  const csrf = CSRF_META || document.querySelector('input[name="csrf"]')?.value || '';

  // Endpoints desde <meta>, con fallbacks
  const EP_LIST = $('meta[name="ajax-list"]')?.content || '/console/gestion/empleados/ajax/listar.php';
  const EP_GET  = $('meta[name="ajax-acl-get"]')?.content || '/console/gestion/empleados/ajax/acl_get.php';
  const EP_SAVE = $('meta[name="ajax-acl-save"]')?.content || '/console/gestion/empleados/ajax/acl_save.php';

  const qInput   = $('#emp-q');
  const listBox  = $('#emp-list');
  const emptyBox = $('#emp-empty');
  const form     = $('#acl-form');
  const empIdInp = $('#empleado_id');
  const empLbl   = $('#emp-selected-label');
  const msgBox   = $('#acl-msg');
  const urlsBox  = $('#acl-urls');
  const btnNew   = $('#emp-add');
  const btnPresetBasic = $('#acl-preset-basic');
  const btnPresetClear = $('#acl-preset-clear');
  const btnReload = $('#acl-reload');

  // Carga presets desde el JSON embebido
  const PRESETS = (() => {
    try { return JSON.parse($('#ACL_PRESETS_JSON')?.textContent || '{"urls":[]}'); }
    catch { return { urls: [] }; }
  })();

  // Utils
  const debounce = (fn, ms) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };
  const icon = (cls) => `<i class="fas ${cls} w-4 text-[var(--text-secondary)]"></i>`;
  const toast = (txt, ok = true) => {
    if (!msgBox) return;
    msgBox.textContent = txt;
    msgBox.style.color = ok ? 'var(--text-secondary)' : 'var(--tone-err)';
  };
  const escapeHtml = (s) => String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  async function api(url, opt = {}) {
    const r = await fetch(url, {
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      ...opt
    });
    if (r.status === 401) { location.href = '/console/auth/login/'; return { ok:false, error:'unauth' }; }
    if (r.status === 405)  return { ok:false, error:'method_not_allowed' };
    let j;
    try { j = await r.json(); } catch { j = { ok:false, error:'invalid_json' }; }
    return j;
  }

  // Render checkboxes de URLs
  function renderUrlCheckboxes() {
    urlsBox.innerHTML = '';
    PRESETS.urls.forEach(u => {
      const row = document.createElement('label');
      row.className = 'flex items-center justify-between gap-3 px-3 py-2 rounded-lg border border-[var(--border-color)]';
      row.innerHTML = `
        <div class="flex items-center gap-3">
          ${icon(u.icon || 'fa-link')}
          <span class="font-medium">${escapeHtml(u.label || u.path)}</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs text-[var(--text-secondary)]">${escapeHtml(u.path)}</span>
          <input type="checkbox" class="w-4 h-4" data-path="${escapeHtml(u.path)}">
        </div>`;
      urlsBox.appendChild(row);
    });
  }

  function setChecks(paths) {
    const set = new Set(paths || []);
    $$('#acl-urls input[type="checkbox"]').forEach(ch => { ch.checked = set.has(ch.dataset.path); });
  }
  function getChecks() {
    return $$('#acl-urls input[type="checkbox"]:checked').map(ch => ch.dataset.path);
  }

  // Listado empleados
  let listCtrl = null;
  async function fetchList(q = '') {
    if (listCtrl) try { listCtrl.abort(); } catch {}
    listCtrl = new AbortController();
    listBox.innerHTML = '';
    emptyBox.classList.add('hidden');

    const u = new URL(EP_LIST, location.origin);
    if (q) u.searchParams.set('q', q);

    const j = await api(u.toString(), { signal: listCtrl.signal });
    if (!j.ok) {
      emptyBox.classList.remove('hidden');
      toast(j.error || 'Error listando', false);
      return;
    }
    renderList(j.items || []);
  }

  function renderList(items) {
    listBox.innerHTML = '';
    if (!items.length) {
      emptyBox.classList.remove('hidden');
      return;
    }
    emptyBox.classList.add('hidden');

    items.forEach(it => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'w-full text-left px-3 py-3 rounded-lg hover:bg-[var(--sidebar-active-bg)] flex flex-col gap-1';
      el.dataset.id = it.id;
      el.innerHTML = `
        <div class="flex items-center justify-between">
          <div class="font-semibold">${escapeHtml(it.usuario || '—')}</div>
          <span class="text-xs ${it.activo ? 'text-green-500' : 'text-[var(--text-secondary)]'}">
            ${it.activo ? 'activo' : 'inactivo'}
          </span>
        </div>
        <div class="text-xs text-[var(--text-secondary)]">${escapeHtml(it.email || '')}</div>`;
      el.addEventListener('click', () => loadACL(it.id, it));
      listBox.appendChild(el);
    });
  }

  // Cargar ACL de un empleado
  async function loadACL(uid, meta = null) {
    toast('');
    empIdInp.value = String(uid);
    empLbl.textContent = 'Cargando…';

    const u = new URL(EP_GET, location.origin);
    u.searchParams.set('empleado_id', String(uid));

    const j = await api(u.toString());
    if (!j.ok) {
      empLbl.textContent = 'Error cargando ACL';
      toast(j.error || 'Error cargando ACL', false);
      return;
    }
    const info = j.user || meta || {};
    empLbl.textContent = `Empleado: ${escapeHtml(info.usuario || info.email || ('#' + uid))}`;
    setChecks(j.urls || []);
    toast('ACL cargado');
  }

  // Guardar ACL
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const uid = parseInt(empIdInp.value || '0', 10);
    if (!uid) { toast('Selecciona un empleado', false); return; }

    const fd = new FormData();
    fd.set('csrf', csrf);
    fd.set('empleado_id', String(uid));
    getChecks().forEach(p => fd.append('urls[]', p));

    const j = await api(EP_SAVE, { method: 'POST', body: fd });
    if (!j.ok) { toast(j.error || 'Error guardando', false); return; }
    toast('Guardado');
  });

  // Recargar ACL actual
  btnReload?.addEventListener('click', () => {
    const uid = parseInt(empIdInp.value || '0', 10);
    if (uid) loadACL(uid); else toast('Sin empleado seleccionado');
  });

  // Presets
  const BASIC_PRESET = new Set([
    '/console/empleados/',
    '/console/vallas/',
    '/console/reservas/',
    '/console/gestion/clientes/'
  ]);
  btnPresetBasic?.addEventListener('click', () => setChecks(Array.from(BASIC_PRESET)));
  btnPresetClear?.addEventListener('click', () => setChecks([]));

  // Búsqueda
  qInput?.addEventListener('input', debounce(() => fetchList(qInput.value.trim()), 250));

  // Modal “Nuevo”
  btnNew?.addEventListener('click', (e) => { e.preventDefault(); openCreateModal(); });

  function openCreateModal() {
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50 flex items-center justify-center';
    wrap.innerHTML = `
      <div class="absolute inset-0 bg-black/50"></div>
      <div class="relative w-full max-w-md card rounded-2xl p-6 bg-[var(--card-bg)] border border-[var(--border-color)]">
        <h3 class="text-lg font-semibold mb-4">Nuevo empleado</h3>
        <form id="createForm" class="space-y-3" autocomplete="off">
          <input type="hidden" name="csrf" value="${escapeHtml(csrf)}">
          <div>
            <label class="block text-sm mb-1">Nombre</label>
            <input name="usuario" required maxlength="100" class="w-full px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]">
          </div>
          <div>
            <label class="block text-sm mb-1">Email</label>
            <input name="email" type="email" required maxlength="150" class="w-full px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]">
          </div>
          <div>
            <label class="block text-sm mb-1">Contraseña</label>
            <div class="flex gap-2">
              <input id="cf-pass" name="password" required minlength="8" maxlength="128" class="flex-1 px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]" type="text">
              <button id="cf-gen" type="button" class="px-3 py-2 rounded border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]">Generar</button>
            </div>
          </div>
          <div>
            <label class="block text-sm mb-1">Rol predefinido (opcional)</label>
            <select name="rol" class="w-full px-3 py-2 rounded border border-[var(--border-color)] bg-[var(--main-bg)]">
              <option value="">— Ninguno —</option>
              <option value="staff_basico">staff_basico</option>
              <option value="staff_operaciones">staff_operaciones</option>
              <option value="staff_full">staff_full</option>
            </select>
          </div>
          <input type="hidden" name="action" value="create">
          <div id="cf-msg" class="text-sm text-[var(--text-secondary)]"></div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="cf-cancel" class="px-4 py-2 rounded border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]">Cancelar</button>
            <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-500">Crear</button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(wrap);

    const cf = $('#createForm', wrap);
    const cfMsg = $('#cf-msg', wrap);
    const pass = $('#cf-pass', wrap);

    $('#cf-cancel', wrap).onclick = () => wrap.remove();
    $('#cf-gen', wrap).onclick = () => { pass.value = genPass(); };

    cf.onsubmit = async (e) => {
      e.preventDefault();
      cfMsg.textContent = 'Creando...';
      const fd = new FormData(cf);
      // Se usa EP_LIST como endpoint de creación (listar.php soporta action=create)
      const j = await api(EP_LIST, { method: 'POST', body: fd });
      if (!j.ok) { cfMsg.textContent = j.error || 'Error creando'; return; }
      cfMsg.textContent = 'Creado';
      wrap.remove();
      await fetchList('');
      if (j.id) loadACL(j.id);
      alert('Empleado creado. Login: https://dev.vallasled.com/console/auth/login/');
    };
  }

  function genPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@$%*-_=+';
    let s = '';
    for (let i = 0; i < 14; i++) s += chars[Math.floor(Math.random() * chars.length)];
    return s;
  }

  // Toggle tema claro/oscuro
  (function initThemeBtn(){
    const btn = $('#theme-toggle'); if(!btn) return;
    const moon = $('#theme-toggle-dark-icon');
    const sun  = $('#theme-toggle-light-icon');
    try{
      const pref = localStorage.getItem('theme');
      if (pref === 'dark')  document.documentElement.classList.add('dark');
      if (pref === 'light') document.documentElement.classList.remove('dark');
    }catch(e){}
    const sync = ()=>{
      const dark = document.documentElement.classList.contains('dark');
      if (moon) moon.classList.toggle('hidden', dark);
      if (sun)  sun.classList.toggle('hidden', !dark);
    };
    btn.addEventListener('click', ()=>{
      document.documentElement.classList.toggle('dark');
      try{
        localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
      }catch(e){}
      sync();
    });
    sync();
  })();

  // Botón menú móvil del header
  document.addEventListener('click', (e)=>{
    const el = e.target.closest('#mobile-menu-button'); if(!el) return;
    e.preventDefault(); if (window.sidebarOpen) window.sidebarOpen();
  });

  // Init
  renderUrlCheckboxes();
  fetchList('');
})();

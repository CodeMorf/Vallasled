// /console/asset/js/zonas/zonas.js
(() => {
  'use strict';

  // ---------- Helpers ----------
  const $  = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const on = (el, ev, fn) => el && el.addEventListener(ev, fn);
  const cfg  = window.ZONAS_CFG || {};
  const CSRF = cfg.csrf || $('meta[name="csrf"]')?.content || '';

  const debounce = (fn, ms = 250) => {
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  };

  const esc = (s) => (s ?? '').toString()
    .replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  const toast = (msg) => {
    const box = $('#toast'), span = $('#toast-msg');
    if (!box || !span) return;
    span.textContent = msg;
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 2200);
  };

  const safeJSON = async (r) => {
    try { return await r.json(); }
    catch {
      const t = await r.text().catch(()=> '');
      throw new Error(t?.slice(0,160) || 'Respuesta no válida');
    }
  };

  const fetchJSON = async (url, bodyObj = {}) => {
    const r = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF': CSRF},
      credentials: 'same-origin',
      body: JSON.stringify(bodyObj || {})
    });
    if (r.status === 401 || r.status === 403) {
      location.href = '/console/auth/login/';
      return { ok:false };
    }
    if (!r.ok) {
      const t = await r.text().catch(()=> '');
      throw new Error(t?.slice(0,160) || `HTTP ${r.status}`);
    }
    return safeJSON(r);
  };

  const nombreNorm = s => (s||'')
    .toLowerCase()
    .replace(/^[^a-záéíóúñ]+/i,'')
    .replace(/\s+/g,' ')
    .trim();

  // ---------- Tema + Sidebar ----------
  const applyTheme = (t) => {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    const iconMoon = document.getElementById('theme-toggle-dark-icon');
    const iconSun  = document.getElementById('theme-toggle-light-icon');
    if (iconMoon) iconMoon.classList.toggle('hidden', !dark);
    if (iconSun)  iconSun.classList.toggle('hidden',  dark);
  };
  const initTheme = () => {
    const saved = localStorage.getItem('theme') ||
      (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
  };
  const bindChrome = () => {
    on($('#theme-toggle'), 'click', (e) => {
      e.preventDefault();
      const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('theme', next);
      applyTheme(next);
    });
    const overlay = $('#sidebar-overlay');
    on($('#mobile-menu-button'), 'click', () => overlay && overlay.classList.toggle('hidden'));
    on(overlay, 'click', () => overlay && overlay.classList.add('hidden'));
    on(document, 'keydown', (e) => {
      if (e.key === 'Escape') {
        $('#dlg-zona')?.classList.remove('show');
        $('#dlg-asignar')?.classList.remove('show');
        overlay?.classList.add('hidden');
      }
    });
  };

  // ---------- Estado ----------
  let page  = 1;
  const limit = (cfg.page && cfg.page.limit) || 100;
  let q = '';
  let dupOnly = '';

  // ---------- Render ----------
  const renderPager = (len) => {
    const pager = $('#zonas-pager');
    if (!pager) return;
    pager.innerHTML = '';
    const prev = document.createElement('button');
    prev.className = 'btn-outline mx-1';
    prev.textContent = 'Prev';
    prev.disabled = page <= 1;
    prev.onclick = () => { if (page > 1) { page--; load(); } };
    const next = document.createElement('button');
    next.className = 'btn-outline mx-1';
    next.textContent = 'Next';
    next.disabled = len < limit;
    next.onclick = () => { if (len >= limit) { page++; load(); } };
    pager.appendChild(prev); pager.appendChild(next);
  };

  const render = (data) => {
    const tbody = $('#zonas-tbody');
    if (!tbody) return;
    const items = data.items || [];

    tbody.innerHTML = '';
    if (!items.length) {
      $('#zonas-empty')?.classList.remove('hidden');
    } else {
      $('#zonas-empty')?.classList.add('hidden');
      const frag = document.createDocumentFragment();
      for (const it of items) {
        const tr = document.createElement('tr');
        tr.className = it.dupe ? 'row-dupe' : '';
        tr.innerHTML = `
          <td class="px-6 py-3" data-label="Sel">
            <input type="checkbox" class="chk" data-id="${esc(it.id)}">
          </td>
          <td class="px-6 py-3" data-label="Zona">${esc(it.nombre)}</td>
          <td class="px-6 py-3 text-[var(--text-secondary)]" data-label="Normalizada">
            ${esc(it.nombre_norm || nombreNorm(it.nombre))}
          </td>
          <td class="px-6 py-3 text-center" data-label="Vallas">${esc(it.vallas_count ?? 0)}</td>
          <td class="px-6 py-3" data-label="Acciones">
            <button class="btn-outline btn-asignar" data-id="${esc(it.id)}">
              <i class="fa fa-billboard"></i> Asignar valla
            </button>
          </td>`;
        frag.appendChild(tr);
      }
      tbody.appendChild(frag);
    }

    $('#kpi-zonas')      && ($('#kpi-zonas').textContent      = String(data.kpis?.zonas ?? '—'));
    $('#kpi-dups')       && ($('#kpi-dups').textContent       = String(data.kpis?.grupos_duplicados ?? '—'));
    $('#kpi-vallas')     && ($('#kpi-vallas').textContent     = String(data.kpis?.vallas_asignadas ?? '—'));
    $('#kpi-noasig')     && ($('#kpi-noasig').textContent     = String(data.kpis?.vallas_sin_asignar ?? '—'));

    renderPager(items.length);
  };

  const load = async () => {
    try {
      const res = await fetchJSON(cfg.endpoints.listar, { q, dup_only: dupOnly, page, limit });
      if (!res?.ok) { toast(res?.msg || 'Error'); return; }
      render(res);
    } catch (e) {
      toast(e?.message || 'Error de red');
    }
  };

  // ---------- Filtros ----------
  on($('#btn-filtrar'), 'click', () => {
    q = $('#f-q')?.value?.trim() || '';
    dupOnly = $('#f-dup')?.value || '';
    page = 1; load();
  });
  on($('#f-q'), 'input', debounce(() => {
    q = $('#f-q')?.value?.trim() || '';
    page = 1; load();
  }, 300));
  on($('#chk-all'), 'change', (e) => {
    $$('.chk').forEach(c => { c.checked = e.target.checked; });
  });

  // ---------- Crear zona ----------
  const dlgZona = $('#dlg-zona');
  on($('#btn-nueva'), 'click', () => {
    const input = $('#z-nombre'); if (input) input.value = '';
    const title = $('#dlg-title'); if (title) title.textContent = 'Nueva Zona';
    dlgZona?.classList.add('show');
  });
  on($('#dlg-close'), 'click', () => dlgZona?.classList.remove('show'));
  on($('#z-cancel'), 'click', () => dlgZona?.classList.remove('show'));
  on($('#z-save'), 'click', async () => {
    const nombre = $('#z-nombre')?.value?.trim() || '';
    if (!nombre) { toast('Nombre requerido'); return; }
    try {
      const r = await fetchJSON(cfg.endpoints.opciones, { op:'crear', nombre });
      if (!r.ok) { toast(r.msg || 'Error'); return; }
      dlgZona?.classList.remove('show'); load();
    } catch (e) { toast(e?.message || 'Error'); }
  });

  // ---------- Delegación en tabla ----------
  on($('#zonas-tbody'), 'click', (ev) => {
    const btn = ev.target.closest('.btn-asignar');
    if (!btn) return;
    const zonaId = btn.getAttribute('data-id');
    openAsignar(zonaId);
  });

  // ---------- Merge ----------
  on($('#btn-merge'), 'click', async () => {
    const checked = [...$$('.chk:checked')];
    const ids = checked.map(c => parseInt(c.getAttribute('data-id')||'0',10)).filter(Boolean);
    if (ids.length < 2) { toast('Selecciona 2+ zonas'); return; }

    const filas   = checked.map(c => c.closest('tr'));
    const nombres = filas.map(tr => tr?.children?.[1]?.textContent?.trim() || '');
    const keepIdx = nombres.reduce((m,_,i)=> nombres[i].length < nombres[m].length ? i : m, 0);
    const keep_id = ids[keepIdx];
    const dup_ids = ids.filter((_, i) => i !== keepIdx);

    if (!window.confirm(`Unificar ${ids.length-1} zonas en #${keep_id}?`)) return;

    try {
      const r = await fetchJSON(cfg.endpoints.merge, { keep_id, dup_ids });
      if (!r.ok) { toast(r.msg || 'Error'); return; }
      toast(`Unificadas en #${keep_id}`); load();
    } catch (e) { toast(e?.message || 'Error de red'); }
  });

  // ---------- Asignar valla ----------
  const dlgAsig = $('#dlg-asignar');
  const fillZonasSelect = (sel, zonas, prefer) => {
    if (!sel) return;
    sel.innerHTML = '';
    const frag = document.createDocumentFragment();
    zonas.forEach(z => {
      const opt = document.createElement('option');
      opt.value = z.id;
      opt.textContent = z.nombre;
      if (String(z.id) === String(prefer)) opt.selected = true;
      frag.appendChild(opt);
    });
    sel.appendChild(frag);
  };

  const openAsignar = async (zonaIdPreferida) => {
    dlgAsig?.classList.add('show');
    try {
      const r = await fetchJSON(cfg.endpoints.opciones, { op:'zonas' });
      fillZonasSelect($('#a-zona'), r.zonas || [], zonaIdPreferida);
      const v = $('#a-valla'); if (v) v.value = '';
    } catch (e) { toast(e?.message || 'Error'); }
  };

  on($('#a-close'),  'click', () => dlgAsig?.classList.remove('show'));
  on($('#a-cancel'), 'click', () => dlgAsig?.classList.remove('show'));
  on($('#a-ok'),     'click', async () => {
    const valla_id = parseInt($('#a-valla')?.value || '0', 10);
    const zona_id  = parseInt($('#a-zona')?.value  || '0', 10);
    if (!valla_id || !zona_id) { toast('Datos insuficientes'); return; }
    try {
      const r = await fetchJSON(cfg.endpoints.asignar, { valla_id, zona_id });
      if (!r.ok) { toast(r.msg || 'Error'); return; }
      toast('Valla asignada');
      dlgAsig?.classList.remove('show'); load();
    } catch (e) { toast(e?.message || 'Error de red'); }
  });

  // ---------- Init ----------
  initTheme();
  bindChrome();
  load();
})();

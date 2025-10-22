// /console/asset/js/planes/planes.js
(() => {
  'use strict';

  /* ==== Utils ==== */
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const html = (s) => s == null ? '' : String(s);
  const esc = (s) => html(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  // number robusto: soporta "1.234,56" y "1,234.56"
  const number = (v, d=0) => {
    if (v == null) return d;
    let s = String(v).trim().replace(/\s+/g,'');
    if (/^\d{1,3}([.,]\d{3})+([.,]\d+)?$/.test(s)) s = s.replace(/[.,](?=\d{3}\b)/g,'');
    s = s.replace(',', '.');
    const n = Number(s);
    return Number.isFinite(n) ? n : d;
  };
  const fmtMoney = (n) => Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  const fmtPct = (n) => Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' %';
  const debounce = (fn, ms=220) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const cfg = window.PLANES_CFG || { endpoints:{}, page:{limit:20} };

  const toast = (msg, ms=2200) => {
    const el = document.createElement('div');
    el.className = 'toast'; el.textContent = msg;
    document.body.appendChild(el); setTimeout(()=>{ el.remove(); }, ms);
  };

  const baseHeaders = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF': CSRF,
    'X-CSRF-TOKEN': CSRF,
    'Csrf-Token': CSRF
  };

  const jGET = async (url, params={}) => {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k,v]) => { if(v!=='' && v!=null) u.searchParams.set(k, v); });
    const r = await fetch(u.toString(), { credentials:'same-origin', headers: baseHeaders });
    let data = null; try { data = await r.json(); } catch {}
    if (!r.ok) throw new Error(data?.error || `HTTP ${r.status}`);
    return data;
  };

  const jPOST = async (url, data={}) => {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { ...baseHeaders, 'Content-Type':'application/json' },
      body: JSON.stringify(data)
    });
    let json = null; try { json = await r.json(); } catch {}
    if (!r.ok) throw new Error(json?.error || `HTTP ${r.status}`);
    return json;
  };

  /* ==== Tema oscuro/claro ==== */
  (() => {
    const btn = $('#theme-toggle');
    const moon = $('#theme-toggle-dark-icon');
    const sun  = $('#theme-toggle-light-icon');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const getTheme = () => localStorage.getItem('theme') || (prefersDark ? 'dark':'light');
    const applyTheme = (t) => {
      const dark = t==='dark';
      document.documentElement.classList.toggle('dark', dark);
      moon?.classList.toggle('hidden', !dark);
      sun?.classList.toggle('hidden', dark);
    };
    applyTheme(getTheme());
    btn?.addEventListener('click', () => {
      const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem('theme', next);
      applyTheme(next);
    });
  })();

  /* ==== Estado ==== */
  const state = {
    plans: [], plansPage: 1, plansTotal: 0,
    rules: [], rulesPage: 1, rulesTotal: 0,
    limit: cfg.page?.limit || 20,
    planSearch: localStorage.getItem('planes:search') || '',
    planTipo: localStorage.getItem('planes:tipo') || '',
    proveedores: [],
    vallasCache: new Map()
  };

  /* ==== Render ==== */
  const elPlansGrid   = $('#plans-grid');
  const elPlansEmpty  = $('#plans-empty');
  const elPlansPager  = $('#plans-pager');
  const elRulesTbody  = $('#rules-tbody');
  const elRulesEmpty  = $('#rules-empty');
  const elRulesPager  = $('#rules-pager');
  const elGlobalValue = $('#commission-global-value');

  function renderPager(container, total, page, limit, onPage) {
    container.innerHTML = '';
    const pages = Math.max(1, Math.ceil(total / limit));
    if (pages <= 1) return;
    const wrap = document.createElement('div'); wrap.className = 'pager';
    const btn = (label, p, disabled=false, active=false) => {
      const b = document.createElement('button'); b.textContent = label;
      if (active) b.classList.add('is-active');
      if (disabled) { b.disabled = true; b.style.opacity = .6; }
      b.addEventListener('click', () => onPage(p)); return b;
    };
    wrap.appendChild(btn('«', 1, page===1));
    wrap.appendChild(btn('‹', Math.max(1, page-1), page===1));
    const start = Math.max(1, page - 2), end = Math.min(pages, page + 2);
    for (let p=start; p<=end; p++) wrap.appendChild(btn(String(p), p, false, p===page));
    wrap.appendChild(btn('›', Math.min(pages, page+1), page===pages));
    wrap.appendChild(btn('»', pages, page===pages));
    container.appendChild(wrap);
  }

  const BADGE = {
    green: 'bg-green-200 dark:bg-green-500/20 text-green-800 dark:text-green-300',
    red:   'bg-red-200 dark:bg-red-500/20 text-red-800 dark:text-red-300',
    sky:   'bg-sky-200 dark:bg-sky-500/20 text-sky-800 dark:text-sky-300',
    slate: 'bg-slate-200 dark:bg-slate-500/20 text-slate-800 dark:text-slate-300'
  };
  const badge = (text, tone='slate') =>
    `<span class="badge ${BADGE[tone]||BADGE.slate}">${esc(text)}</span>`;

  const featureIcon = (ok, label) => {
    const icon = ok ? '<i class="fas fa-check text-green-500"></i>' : '<i class="fas fa-times text-red-500"></i>';
    return `<li class="flex items-center gap-2">${icon}<span>${esc(label)}</span></li>`;
  };

  function renderPlans() {
    if (!state.plans.length) { elPlansGrid.innerHTML = ''; elPlansEmpty.classList.remove('hidden'); return; }
    elPlansEmpty.classList.add('hidden');
    elPlansGrid.innerHTML = state.plans.map(p => {
      const tipo = p.tipo || p.tipo_facturacion || '';
      const activo = ('activo' in p) ? p.activo : ('estado' in p ? Number(p.estado)===1 : 0);
      const feats = p.features || {};
      const activeBadge = activo ? badge('Activo','green') : badge('Inactivo','red');
      const tipoLabel = { gratis:'Gratis', mensual:'Mensual', trimestral:'Trimestral', anual:'Anual', comision:'Comisión' }[tipo] || tipo;
      const precio = tipo==='comision' ? '—' : `$${fmtMoney(p.precio||0)}`;
      const periodo = tipo==='mensual'?'mes': tipo==='trimestral'?'tri': tipo==='anual'?'año':'';
      const comi = feats.comision_model==='pct' ? fmtPct(feats.comision_pct||0)
                  : feats.comision_model==='flat' ? `$${fmtMoney(feats.comision_flat||0)}` : '—';
      return `
        <div class="plan-card flex flex-col text-center">
          <i class="fas fa-rocket fa-2x text-indigo-500 mx-auto mb-3"></i>
          <h3 class="text-lg font-bold mb-1">${esc(p.nombre||'')}</h3>
          <p class="text-[var(--text-secondary)] text-sm mb-2">${esc(tipoLabel)}</p>
          <p class="text-2xl font-bold mb-3">${precio}${tipo!=='comision' && periodo ? `<span class="text-sm font-normal text-[var(--text-secondary)]">/${periodo}</span>`:''}</p>
          <ul class="space-y-1 text-sm text-left mb-4 flex-grow">
            ${featureIcon(!!feats.access_crm,'Acceso CRM')}
            ${featureIcon(!!feats.access_facturacion,'Facturación')}
            ${featureIcon(!!feats.access_mapa,'Mapa')}
            ${featureIcon(!!(feats.exportar_datos ?? feats.access_export),'Exportar Datos')}
            ${featureIcon(!!feats.soporte_ncf,'Soporte NCF')}
            ${featureIcon(!!feats.factura_auto,'Fact. Automática')}
            <li class="flex items-center gap-2"><i class="fas fa-percent text-emerald-500"></i><span>Comisión: ${esc(comi)}</span></li>
          </ul>
          <div class="mt-auto flex justify-between items-center">
            ${activeBadge}
            <div class="flex gap-1">
              <button class="icon-btn hover:bg-[var(--sidebar-active-bg)]" data-plan-edit="${p.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
              <button class="icon-btn hover:bg-[var(--sidebar-active-bg)]" data-plan-del="${p.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
            </div>
          </div>
        </div>`;
    }).join('');
  }

  function renderRules() {
    if (!state.rules.length) { elRulesTbody.innerHTML=''; elRulesEmpty.classList.remove('hidden'); return; }
    elRulesEmpty.classList.add('hidden');
    elRulesTbody.innerHTML = state.rules.map(r => {
      const aplica = r.valla_id ? badge('Valla','sky') : badge('Proveedor','sky');
      const nombre = r.nombre || '—';
      const rango = r.hasta ? `${r.desde} - ${r.hasta}` : `${r.desde} - <span class="text-[var(--text-secondary)]">Indefinido</span>`;
      return `
        <tr class="hover:bg-[var(--main-bg)]">
          <td class="py-3 px-4">${aplica}</td>
          <td class="py-3 px-4 font-medium text-[var(--text-primary)] hidden sm:table-cell">${esc(nombre)}</td>
          <td class="py-3 px-4 font-semibold text-indigo-500">${fmtPct(r.comision_pct)}</td>
          <td class="py-3 px-4 hidden md:table-cell">${rango}</td>
          <td class="py-3 px-4 text-center">
            <button class="p-2 text-[var(--text-secondary)] hover:text-yellow-500" data-rule-edit="${r.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
            <button class="p-2 text-[var(--text-secondary)] hover:text-red-500" data-rule-del="${r.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
          </td>
        </tr>`;
    }).join('');
  }

  /* ==== Cargas AJAX ==== */
  async function loadPlans() {
    const res = await jGET(cfg.endpoints.planes_listar, {
      q: state.planSearch || '',
      tipo: state.planTipo || '',
      page: state.plansPage,
      limit: state.limit
    });
    state.plans = (res.data || []).map(p => ({
      ...p,
      tipo: p.tipo || p.tipo_facturacion,
      activo: ('activo' in p) ? p.activo : ('estado' in p ? Number(p.estado)===1 : 0)
    }));
    state.plansTotal = Number(res.total || 0);
    renderPlans();
    renderPager(elPlansPager, state.plansTotal, state.plansPage, state.limit,
      p => { state.plansPage = p; loadPlans().catch(e=>toast(e.message||'Error listando planes')); });
  }

  async function loadRules() {
    const res = await jGET(cfg.endpoints.comisiones_listar, {
      page: state.rulesPage,
      limit: state.limit
    });
    state.rules = res.data || [];
    state.rulesTotal = Number(res.total || 0);
    renderRules();
    renderPager(elRulesPager, state.rulesTotal, state.rulesPage, state.limit,
      p => { state.rulesPage = p; loadRules().catch(e=>toast(e.message||'Error listando comisiones')); });
  }

  async function loadGlobalCommission() {
    try {
      const res = await jGET(cfg.endpoints.global_get);
      const pct = number(res?.data?.vendor_comision_pct, NaN);
      elGlobalValue.textContent = Number.isFinite(pct) ? fmtPct(pct) : '—';
    } catch { elGlobalValue.textContent = '—'; }
  }

  async function loadProveedores() {
    try {
      const res = await jGET(cfg.endpoints.proveedores);
      state.proveedores = res.data || [];
      const sel = $('#rule-proveedor');
      if (sel) {
        sel.innerHTML = `<option value="">Seleccione</option>` +
          state.proveedores.map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
      }
    } catch { state.proveedores = []; }
  }

  async function loadVallasByProveedor(pid) {
    if (!pid) return [];
    if (state.vallasCache.has(pid)) return state.vallasCache.get(pid);
    const res = await jGET(cfg.endpoints.vallas_por_proveedor, { proveedor_id: pid });
    const arr = res.data || [];
    state.vallasCache.set(pid, arr);
    return arr;
  }

  /* ==== Plan: CRUD ==== */
  const planForm = $('#plan-form');

  function resetPlanForm() {
    planForm.reset();
    $('#plan-id').value = '';
    $('#plan-nombre').value = '';
    $('#plan-tipo').value = 'gratis';
    $('#plan-precio').value = '0.00';
    $('#plan-limite').value = '0';
    $('#plan-prueba').value = '0';
    $('#plan-activo').checked = true;
    $('#plan-descripcion').value = '';
    ['f_access_crm','f_access_fact','f_access_mapa','f_export','f_ncf','f_fact_auto']
      .forEach(id => { $('#'+id).checked = false; });
    $$('input[name="comision_model"]').forEach(r=>{ r.checked = r.value==='none'; });
    $('#f_comision_pct').value = '0.00'; $('#f_comision_flat').value = '0.00';
    $('#f_comision_pct').disabled = true; $('#f_comision_flat').disabled = true;
    $('#plan-modal-title').textContent = 'Nuevo Plan';
  }

  function fillPlanForm(p) {
    $('#plan-id').value = p.id;
    $('#plan-nombre').value = p.nombre || '';
    $('#plan-tipo').value = p.tipo || p.tipo_facturacion || 'gratis';
    $('#plan-precio').value = Number(p.precio||0).toFixed(2);
    $('#plan-limite').value = String(p.limite_vallas ?? 0);
    $('#plan-prueba').value = String(p.dias_prueba ?? 0);
    $('#plan-activo').checked = !!p.activo;
    $('#plan-descripcion').value = p.descripcion || '';

    const f = p.features || {};
    $('#f_access_crm').checked = !!f.access_crm;
    $('#f_access_fact').checked = !!f.access_facturacion;
    $('#f_access_mapa').checked = !!f.access_mapa;
    $('#f_export').checked = !!(f.exportar_datos ?? f.access_export);
    $('#f_ncf').checked = !!f.soporte_ncf;
    $('#f_fact_auto').checked = !!f.factura_auto;

    const model = f.comision_model || 'none';
    $$('input[name="comision_model"]').forEach(r=>{ r.checked = r.value===model; });
    $('#f_comision_pct').disabled = model!=='pct';
    $('#f_comision_flat').disabled = model!=='flat';
    $('#f_comision_pct').value = Number(f.comision_pct||0).toFixed(2);
    $('#f_comision_flat').value = Number(f.comision_flat||0).toFixed(2);

    $('#plan-modal-title').textContent = 'Editar Plan';
  }

  function validatePlanForm() {
    const nombre = $('#plan-nombre').value.trim();
    const tipo = $('#plan-tipo').value;
    if (!nombre) return { ok:false, msg:'Nombre es requerido' };
    if (!['gratis','mensual','trimestral','anual','comision'].includes(tipo)) return { ok:false, msg:'Tipo inválido' };

    const precio = number($('#plan-precio').value, 0);
    const limite = number($('#plan-limite').value, 0);
    const prueba = number($('#plan-prueba').value, 0);
    if (precio < 0 || limite < 0 || prueba < 0) return { ok:false, msg:'Valores no pueden ser negativos' };
    if (['mensual','trimestral','anual'].includes(tipo) && precio <= 0) return { ok:false, msg:'Precio > 0 requerido' };

    const model = ($$('input[name="comision_model"]:checked')[0] || {}).value || 'none';
    const pct  = number($('#f_comision_pct').value, 0);
    const flat = number($('#f_comision_flat').value, 0);
    if (model === 'pct' && !(pct >= 0 && pct <= 100)) return { ok:false, msg:'% Comisión debe estar entre 0 y 100' };
    if (model === 'flat' && flat < 0) return { ok:false, msg:'Monto fijo no puede ser negativo' };

    return { ok:true, data:{
      id: $('#plan-id').value || null,
      nombre, tipo,
      precio: tipo==='comision' ? 0 : +precio,
      limite_vallas: +limite,
      dias_prueba: +prueba,
      activo: $('#plan-activo').checked ? 1 : 0,
      descripcion: $('#plan-descripcion').value.trim(),
      access_crm: $('#f_access_crm').checked ? 1:0,
      access_facturacion: $('#f_access_fact').checked ? 1:0,
      access_mapa: $('#f_access_mapa').checked ? 1:0,
      exportar_datos: $('#f_export').checked ? 1:0,
      soporte_ncf: $('#f_ncf').checked ? 1:0,
      factura_auto: $('#f_fact_auto').checked ? 1:0,
      comision_model: model,
      comision_pct: +pct,
      comision_flat: +flat
    }};
  }

  $('#add-plan-btn')?.addEventListener('click', () => { resetPlanForm(); openModal('planModal'); });

  $('#plans-grid')?.addEventListener('click', e => {
    const btnEdit = e.target.closest('[data-plan-edit]');
    const btnDel  = e.target.closest('[data-plan-del]');
    if (btnEdit) {
      const id = +btnEdit.getAttribute('data-plan-edit');
      const p = state.plans.find(x => +x.id === id);
      if (p) { fillPlanForm(p); openModal('planModal'); }
    } else if (btnDel) {
      const id = +btnDel.getAttribute('data-plan-del');
      if (confirm('¿Eliminar este plan?')) {
        jPOST(cfg.endpoints.plan_eliminar, { id }).then(r=>{
          if (r.ok) { toast('Plan eliminado'); loadPlans(); }
          else toast(r.msg || 'No se pudo eliminar');
        }).catch(err=>toast(err.message||'Error eliminando plan'));
      }
    }
  });

  $$('input[name="comision_model"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const val = ($$('input[name="comision_model"]:checked')[0] || {}).value;
      $('#f_comision_pct').disabled = val!=='pct';
      $('#f_comision_flat').disabled = val!=='flat';
    });
  });

  $('#plan-form')?.addEventListener('submit', e => {
    e.preventDefault();
    const v = validatePlanForm();
    if (!v.ok) { toast(v.msg); return; }
    jPOST(cfg.endpoints.plan_guardar, v.data).then(r=>{
      if (r.ok) { toast('Guardado'); closeModal('planModal'); loadPlans(); }
      else toast(r.msg || 'Error guardando');
    }).catch(err=>toast(err.message||'Error guardando'));
  });

  /* ==== Reglas: CRUD ==== */
  const ruleForm = $('#rule-form');
  const ruleTypeRadios = $$('input[name="rule_type"]');
  const ruleProveedorSel = $('#rule-proveedor');
  const ruleVallaWrap = $('#rule-valla-wrap');
  const ruleVallaSel = $('#rule-valla');

  function toggleRuleScope() {
    const t = ($$('input[name="rule_type"]:checked')[0] || {}).value || 'proveedor';
    if (t === 'valla') ruleVallaWrap.classList.remove('hidden');
    else { ruleVallaWrap.classList.add('hidden'); ruleVallaSel.innerHTML = ''; }
  }
  ruleTypeRadios.forEach(r => r.addEventListener('change', toggleRuleScope));

  $('#add-commission-btn')?.addEventListener('click', () => {
    ruleForm.reset(); $('#rule-id').value = ''; toggleRuleScope(); openModal('commissionModal');
  });

  ruleProveedorSel?.addEventListener('change', async e => {
    if ( ($$('input[name="rule_type"]:checked')[0]||{}).value !== 'valla') return;
    const pid = +e.target.value;
    const vallas = await loadVallasByProveedor(pid);
    ruleVallaSel.innerHTML = `<option value="">Seleccione</option>` + vallas.map(v => `<option value="${v.id}">${esc(v.nombre)}</option>`).join('');
  });

  $('#rules-tbody')?.addEventListener('click', e => {
    const edit = e.target.closest('[data-rule-edit]');
    const del  = e.target.closest('[data-rule-del]');
    if (edit) {
      const id = +edit.getAttribute('data-rule-edit');
      const r = state.rules.find(x => +x.id === id); if (!r) return;
      $('#rule-id').value = r.id;
      if (r.valla_id) {
        $$('input[name="rule_type"]').find(x=>x.value==='valla').checked = true;
        toggleRuleScope();
        $('#rule-proveedor').value = r.proveedor_id || '';
        loadVallasByProveedor(r.proveedor_id).then(list => {
          ruleVallaSel.innerHTML = `<option value="">Seleccione</option>` + list.map(v => `<option value="${v.id}">${esc(v.nombre)}</option>`).join('');
          $('#rule-valla').value = r.valla_id || '';
        });
      } else {
        $$('input[name="rule_type"]').find(x=>x.value==='proveedor').checked = true;
        toggleRuleScope();
        $('#rule-proveedor').value = r.proveedor_id || '';
      }
      $('#rule-pct').value = Number(r.comision_pct || 0).toFixed(2);
      $('#rule-desde').value = r.desde || '';
      $('#rule-hasta').value = r.hasta || '';
      openModal('commissionModal');
    } else if (del) {
      const id = +del.getAttribute('data-rule-del');
      if (confirm('¿Eliminar esta regla?')) {
        jPOST(cfg.endpoints.comision_eliminar, { id }).then(r=>{
          if (r.ok) { toast('Regla eliminada'); loadRules(); }
          else toast(r.msg || 'No se pudo eliminar');
        }).catch(err=>toast(err.message||'Error eliminando regla'));
      }
    }
  });

  $('#rule-form')?.addEventListener('submit', e => {
    e.preventDefault();
    const type = ($$('input[name="rule_type"]:checked')[0] || {}).value || 'proveedor';
    const proveedor_id = +($('#rule-proveedor').value || 0);
    const valla_id = +($('#rule-valla').value || 0);
    const pct = number($('#rule-pct').value, NaN);
    const desde = $('#rule-desde').value;
    const hasta = $('#rule-hasta').value;

    if (type==='proveedor' && !proveedor_id) { toast('Proveedor requerido'); return; }
    if (type==='valla' && (!proveedor_id || !valla_id)) { toast('Proveedor y valla requeridos'); return; }
    if (!Number.isFinite(pct) || pct < 0 || pct > 100) { toast('% Comisión 0–100'); return; }
    if (!desde) { toast('Fecha desde requerida'); return; }
    if (hasta && hasta < desde) { toast('Rango de fechas inválido'); return; }

    jPOST(cfg.endpoints.comision_guardar, {
      id: $('#rule-id').value || null,
      rule_type: type,
      proveedor_id,
      valla_id: type==='valla' ? valla_id : null,
      comision_pct: +pct,
      desde,
      hasta: hasta || null
    }).then(r=>{
      if (r.ok) { toast('Guardado'); closeModal('commissionModal'); loadRules(); }
      else toast(r.msg || 'Error guardando');
    }).catch(err=>toast(err.message||'Error guardando'));
  });

  /* ==== Comisión Global ==== */
  $('#edit-global-commission')?.addEventListener('click', async () => {
    const current = elGlobalValue.textContent.replace('%','').trim();
    const input = prompt('Nueva comisión global (%):', current || '10.00');
    if (input == null) return;
    const val = number(input, NaN);
    if (!Number.isFinite(val) || val < 0 || val > 100) { toast('Valor inválido'); return; }
    try {
      const r = await jPOST(cfg.endpoints.global_set, { comision_pct: val });
      if (r.ok) { toast('Actualizada'); loadGlobalCommission(); }
      else toast(r.msg || 'No se pudo actualizar');
    } catch (err) { toast(err.message||'Error actualizando'); }
  });

  /* ==== Filtros ==== */
  const planSearch = $('#plan-search');
  const planTipo = $('#plan-tipo-filter');
  if (planSearch) planSearch.value = state.planSearch;
  if (planTipo) planTipo.value = state.planTipo;

  planSearch?.addEventListener('input', debounce(() => {
    state.planSearch = planSearch.value.trim();
    localStorage.setItem('planes:search', state.planSearch);
    state.plansPage = 1; loadPlans().catch(e=>toast(e.message||'Error de búsqueda'));
  }, 250));

  planTipo?.addEventListener('change', () => {
    state.planTipo = planTipo.value;
    localStorage.setItem('planes:tipo', state.planTipo);
    state.plansPage = 1; loadPlans().catch(e=>toast(e.message||'Error filtrando'));
  });

  /* ==== Modales ==== */
  window.openModal = function(id){
    const m = document.getElementById(id); if (!m) return;
    m.classList.remove('opacity-0','pointer-events-none'); document.body.style.overflow = 'hidden';
  };
  window.closeModal = function(id){
    const m = document.getElementById(id); if (!m) return;
    m.classList.add('opacity-0');
    setTimeout(() => { m.classList.add('pointer-events-none'); document.body.style.overflow = 'auto'; }, 200);
  };
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-close-modal]');
    if (t) window.closeModal(t.getAttribute('data-close-modal'));
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') $$('.modal').forEach(m => { if (!m.classList.contains('pointer-events-none')) window.closeModal(m.id); });
  });
  $$('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) window.closeModal(m.id); }));

  /* ==== Init ==== */
  (async function init() {
    try {
      await loadProveedores();
      await Promise.all([loadPlans(), loadRules(), loadGlobalCommission()]);
    } catch (err) { toast(err.message||'Error inicializando módulo'); }
  })();

})();

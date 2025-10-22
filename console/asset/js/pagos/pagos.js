// /console/asset/js/pagos/pagos.js
(() => {
  'use strict';

  /* ==== Utils (clonadas) ==== */
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
  const html = s => s==null ? '' : String(s);
  const esc  = s => html(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const number = (v,d=0)=>{ if(v==null)return d; let s=String(v).trim().replace(/\s+/g,'');
    if(/^\d{1,3}([.,]\d{3})+([.,]\d+)?$/.test(s)) s=s.replace(/[.,](?=\d{3}\b)/g,''); s=s.replace(',', '.');
    const n=Number(s); return Number.isFinite(n)?n:d; };
  const fmtMoney = n => Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
  const debounce = (fn,ms=250)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const cfg = window.PAGOS_CFG || { endpoints:{}, page:{limit:20} };

  const toast = (msg,ms=2200)=>{ const el=document.createElement('div'); el.className='toast'; el.textContent=msg;
    document.body.appendChild(el); setTimeout(()=>el.remove(),ms); };

  const baseHeaders = {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF,'X-CSRF-TOKEN':CSRF,'Csrf-Token':CSRF};

  const jGET = async (url,params={})=>{
    const u=new URL(url, location.origin); Object.entries(params).forEach(([k,v])=>{ if(v!=='' && v!=null) u.searchParams.set(k,v); });
    const r=await fetch(u.toString(), {credentials:'same-origin', headers:baseHeaders});
    let data=null; try{ data=await r.json(); }catch{}
    if(!r.ok) throw new Error(data?.error || `HTTP ${r.status}`); return data;
  };
  const jPOST = async (url,data={})=>{
    const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{...baseHeaders,'Content-Type':'application/json'},body:JSON.stringify(data)});
    let json=null; try{ json=await r.json(); }catch{}
    if(!r.ok) throw new Error(json?.error || `HTTP ${r.status}`); return json;
  };

  /* ==== Tema (idéntico al módulo Planes) ==== */
  (() => {
    const btn=$('#theme-toggle'), moon=$('#theme-toggle-dark-icon'), sun=$('#theme-toggle-light-icon');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const getTheme = () => localStorage.getItem('theme') || (prefersDark?'dark':'light');
    const apply = t => { const dark=t==='dark'; document.documentElement.classList.toggle('dark',dark); moon?.classList.toggle('hidden',!dark); sun?.classList.toggle('hidden',dark); };
    apply(getTheme());
    btn?.addEventListener('click',()=>{ const next=document.documentElement.classList.contains('dark')?'light':'dark'; localStorage.setItem('theme',next); apply(next); });
  })();

  /* ==== Estado ==== */
  const state = {
    pagos:[], total:0, page:1, limit: cfg.page?.limit || 20,
    q:'', estado:'', desde:'',
    cuentas:[],
  };

  /* ==== Render pagos ==== */
  const tbody = $('#pay-tbody'), empty = $('#pay-empty'), pager = $('#pay-pager'), counter = $('#pay-counter');
  function badgeEstado(s){
    if(s==='pagado') return '<span class="badge bg-green-200 dark:bg-green-500/20 text-green-800 dark:text-green-300 px-3 py-1 text-xs font-semibold rounded-full">Pagado</span>';
    return '<span class="badge bg-yellow-200 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-300 px-3 py-1 text-xs font-semibold rounded-full">Pendiente</span>';
  }
  function renderPagos(){
    if(!state.pagos.length){ tbody.innerHTML=''; empty.classList.remove('hidden'); counter.textContent=''; pager.innerHTML=''; return; }
    empty.classList.add('hidden');
    tbody.innerHTML = state.pagos.map(r=>`
      <tr class="hover:bg-[var(--sidebar-active-bg)]">
        <td class="py-4 px-4 font-semibold text-indigo-500">#${esc(String(r.id).padStart(4,'0'))}</td>
        <td class="py-4 px-4 hidden md:table-cell">
          <p class="font-semibold text-[var(--text-primary)]">${esc(r.cliente_nombre || '—')}</p>
          ${r.cliente_email ? `<p class="text-sm text-[var(--text-secondary)]">${esc(r.cliente_email)}</p>`:''}
        </td>
        <td class="py-4 px-4 hidden lg:table-cell text-[var(--text-secondary)]">${esc(r.fecha_generacion||'—')}</td>
        <td class="py-4 px-4 hidden lg:table-cell text-[var(--text-secondary)]">${esc(r.fecha_pago||'—')}</td>
        <td class="py-4 px-4 font-semibold text-[var(--text-primary)]">$${fmtMoney(r.total||0)}</td>
        <td class="py-4 px-4">${badgeEstado(r.estado)}</td>
        <td class="py-4 px-4 text-center">
          <div class="flex items-center justify-center gap-2">
            <button class="p-2 text-[var(--text-secondary)] hover:text-blue-500" data-view="${r.id}" title="Ver"><i class="fas fa-eye"></i></button>
            ${r.estado==='pendiente'
              ? `<button class="p-2 text-[var(--text-secondary)] hover:text-green-500" data-paid="${r.id}" title="Marcar Pagado"><i class="fas fa-check"></i></button>
                 <button class="p-2 text-[var(--text-secondary)] hover:text-red-500" data-annul="${r.id}" title="Anular"><i class="fas fa-times-circle"></i></button>`
              : `<button class="p-2 text-[var(--text-secondary)] hover:text-gray-500" disabled title="Ya pagada"><i class="fas fa-check-double"></i></button>`}
          </div>
        </td>
      </tr>`).join('');
    // contador
    const start = (state.page-1)*state.limit + 1;
    const end = Math.min(state.total, state.page*state.limit);
    counter.textContent = `Mostrando ${start} a ${end} de ${state.total} facturas`;
    // paginador
    renderPager(pager, state.total, state.page, state.limit, p=>{ state.page=p; loadPagos().catch(e=>toast(e.message)); });
  }
  function renderPager(container,total,page,limit,onPage){
    container.innerHTML = ''; const pages=Math.max(1, Math.ceil(total/limit)); if(pages<=1) return;
    const btn=(t,p,dis=false,act=false)=>{ const b=document.createElement('button'); b.textContent=t; if(act)b.classList.add('is-active'); if(dis){b.disabled=true;b.style.opacity=.6;} b.addEventListener('click',()=>onPage(p)); return b; };
    container.appendChild(btn('«',1,page===1));
    container.appendChild(btn('‹',Math.max(1,page-1),page===1));
    const s=Math.max(1,page-2), e=Math.min(pages,page+2); for(let i=s;i<=e;i++) container.appendChild(btn(String(i),i,false,i===page));
    container.appendChild(btn('›',Math.min(pages,page+1),page===pages));
    container.appendChild(btn('»',pages,page===pages));
  }

  /* ==== Render cuentas ==== */
  const acctBody = $('#acct-tbody'), acctEmpty = $('#acct-empty');
  function renderCuentas(){
    if(!state.cuentas.length){ acctBody.innerHTML=''; acctEmpty.classList.remove('hidden'); return; }
    acctEmpty.classList.add('hidden');
    acctBody.innerHTML = state.cuentas.map(c=>`
      <tr class="hover:bg-[var(--sidebar-active-bg)]">
        <td class="py-4 px-4 font-semibold">${esc(c.banco)}</td>
        <td class="py-4 px-4 hidden sm:table-cell text-[var(--text-secondary)]">${esc(c.titular||'')}</td>
        <td class="py-4 px-4 hidden md:table-cell text-[var(--text-secondary)]">${esc(c.tipo_cuenta||'')}</td>
        <td class="py-4 px-4 hidden lg:table-cell text-[var(--text-secondary)]">**** ${esc((c.numero_cuenta||'').slice(-6))}</td>
        <td class="py-4 px-4">${c.activo?'<span class="px-3 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded-full dark:bg-green-500/20 dark:text-green-300">Activa</span>':'<span class="px-3 py-1 text-xs font-semibold text-gray-800 bg-gray-200 rounded-full dark:bg-gray-600/20 dark:text-gray-300">Inactiva</span>'}</td>
        <td class="py-4 px-4 text-center">
          <div class="flex items-center justify-center gap-2">
            <button class="p-2 text-[var(--text-secondary)] hover:text-yellow-500" data-edit="${c.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
            <button class="p-2 text-[var(--text-secondary)] hover:text-red-500" data-del="${c.id}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
          </div>
        </td>
      </tr>`).join('');
  }

  /* ==== Cargas ==== */
  async function loadResumen(){
    const r = await jGET(cfg.endpoints.resumen_get);
    $('#sum-pagado').textContent    = `$${fmtMoney(r.data.total_pagado||0)}`;
    $('#sum-pendiente').textContent = `$${fmtMoney(r.data.total_pendiente||0)}`;
    $('#sum-vencidas').textContent  = String(r.data.vencidas||0);
    $('#sum-30d').textContent       = `$${fmtMoney(r.data.facturado_30d||0)}`;
  }
  async function loadPagos(){
    const r = await jGET(cfg.endpoints.pagos_listar,{ q:state.q, estado:state.estado, desde:state.desde, page:state.page, limit:state.limit });
    state.pagos = r.data||[]; state.total = Number(r.total||0);
    renderPagos();
  }
  async function loadCuentas(){
    const r = await jGET(cfg.endpoints.cuentas_listar);
    state.cuentas = r.data||[]; renderCuentas();
  }

  /* ==== Acciones sobre tabla pagos ==== */
  tbody?.addEventListener('click', e=>{
    const paid = e.target.closest('[data-paid]'); const annul = e.target.closest('[data-annul]');
    if(paid){ const id=+paid.getAttribute('data-paid'); jPOST(cfg.endpoints.factura_pagado,{ id }).then(r=>{ if(r.ok){ toast('Marcada como pagada'); loadPagos(); loadResumen(); } else toast(r.msg||'No se pudo marcar'); }).catch(err=>toast(err.message)); }
    if(annul){ const id=+annul.getAttribute('data-annul'); if(!confirm('¿Anular esta factura?')) return;
      jPOST(cfg.endpoints.factura_anular,{ id }).then(r=>{ if(r.ok){ toast('Anulada'); loadPagos(); loadResumen(); } else toast(r.msg||'No se pudo anular'); }).catch(err=>toast(err.message));
    }
  });

  /* ==== Filtros ==== */
  const fSearch=$('#pay-search'), fEstado=$('#pay-estado'), fDesde=$('#pay-desde');
  fSearch?.addEventListener('input', debounce(()=>{ state.q=fSearch.value.trim(); state.page=1; loadPagos().catch(e=>toast(e.message)); },250));
  fEstado?.addEventListener('change', ()=>{ state.estado=fEstado.value; state.page=1; loadPagos().catch(e=>toast(e.message)); });
  fDesde?.addEventListener('change', ()=>{ state.desde=fDesde.value; state.page=1; loadPagos().catch(e=>toast(e.message)); });
  $('#pay-export')?.addEventListener('click', ()=>{ const u=new URL(cfg.endpoints.export_csv, location.origin);
    if(state.q)u.searchParams.set('q',state.q); if(state.estado)u.searchParams.set('estado',state.estado); if(state.desde)u.searchParams.set('desde',state.desde);
    window.location.href=u.toString();
  });

  /* ==== Modal cuentas ==== */
  const acctForm=$('#acct-form');
  const openModal=id=>{ const m=document.getElementById(id); if(!m)return; m.classList.remove('opacity-0','pointer-events-none'); document.body.style.overflow='hidden'; };
  const closeModal=id=>{ const m=document.getElementById(id); if(!m)return; m.classList.add('opacity-0'); setTimeout(()=>{ m.classList.add('pointer-events-none'); document.body.style.overflow='auto'; },200); };
  document.addEventListener('click',e=>{ const t=e.target.closest('[data-close-modal]'); if(t) closeModal(t.getAttribute('data-close-modal')); });
  $$('#acctModal').forEach(m=> m.addEventListener('click',e=>{ if(e.target===m) closeModal('acctModal'); }));

  $('#acct-add')?.addEventListener('click',()=>{ $('#acct-id').value=''; $('#acct-modal-title').textContent='Agregar Cuenta';
    $('#acct-banco').value=''; $('#acct-titular').value=''; $('#acct-tipo').value='Ahorros'; $('#acct-numero').value=''; $('#acct-activo').checked=true;
    openModal('acctModal');
  });
  acctBody?.addEventListener('click', e=>{
    const edit=e.target.closest('[data-edit]'); const del=e.target.closest('[data-del]');
    if(edit){ const id=+edit.getAttribute('data-edit'); const c=state.cuentas.find(x=>+x.id===id); if(!c) return;
      $('#acct-id').value=c.id; $('#acct-modal-title').textContent='Editar Cuenta';
      $('#acct-banco').value=c.banco||''; $('#acct-titular').value=c.titular||''; $('#acct-tipo').value=c.tipo_cuenta||'Ahorros'; $('#acct-numero').value=c.numero_cuenta||''; $('#acct-activo').checked=!!c.activo;
      openModal('acctModal');
    } else if(del){
      const id=+del.getAttribute('data-del'); if(!confirm('¿Eliminar esta cuenta?')) return;
      jPOST(cfg.endpoints.cuenta_eliminar,{id}).then(r=>{ if(r.ok){ toast('Eliminada'); loadCuentas(); } else toast(r.msg||'No se pudo eliminar'); }).catch(err=>toast(err.message));
    }
  });

  acctForm?.addEventListener('submit', e=>{
    e.preventDefault();
    const id=$('#acct-id').value||null, banco=$('#acct-banco').value.trim(), titular=$('#acct-titular').value.trim(),
          tipo=$('#acct-tipo').value, numero=$('#acct-numero').value.trim(), activo=$('#acct-activo').checked?1:0;
    if(!banco || !titular) { toast('Banco y Titular requeridos'); return; }
    if(!['Ahorros','Corriente'].includes(tipo)) { toast('Tipo inválido'); return; }
    if(!/^[0-9A-Za-z\- ]{3,50}$/.test(numero)) { toast('Número de cuenta inválido'); return; }
    jPOST(cfg.endpoints.cuenta_guardar,{id,banco,titular,tipo_cuenta:tipo,numero_cuenta:numero,activo}).then(r=>{
      if(r.ok){ toast('Guardado'); closeModal('acctModal'); loadCuentas(); } else toast(r.msg||'Error guardando');
    }).catch(err=>toast(err.message));
  });

  /* ==== Init ==== */
  (async function init(){
    try{ await Promise.all([loadResumen(), loadPagos(), loadCuentas()]); } catch(err){ toast(err.message||'Error inicializando'); }
  })();
})();

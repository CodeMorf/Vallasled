// /console/asset/js/cliente/clientes.lista.js
(function () {
  'use strict';
  if (window.__CLIENTES_JS__) return;
  window.__CLIENTES_JS__ = true;

  const $ = (s) => document.querySelector(s);
  const esc = (s) => s == null ? '' : String(s).replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const CFG  = window.CLIENTES_CFG || {};
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

  const els = {
    // sidebar
    mobileBtn: $('#mobile-menu-button'),
    desktopBtn: $('#sidebar-toggle-desktop'),
    overlay: $('#sidebar-overlay'),
    // theme
    themeBtn: $('#theme-toggle'),
    dk: $('#theme-toggle-dark-icon'),
    lt: $('#theme-toggle-light-icon'),
    // ui
    search: $('#cli-search'),
    prov: $('#cli-proveedor'),
    total: $('#total-clientes'),
    importBtn: $('#cli-import-btn'),
    newBtn: $('#new-btn'),
    panel: $('#cli-import-panel'),
    paste: $('#cli-paste'),
    drop: $('#cli-dropzone'),
    grid: $('#cli-grid'),
    empty: $('#cli-empty-state'),
    pager: $('#cli-pager')
  };

  // ===== Sidebar universal =====
  (function bindSidebar(){
    const body = document.body;
    const lsKey = 'sidebarCollapsed';
    const md = window.matchMedia('(min-width: 768px)');

    function openMobile(){ if (window.sidebarOpen) return window.sidebarOpen(); body.classList.add('sidebar-open','overflow-hidden'); els.overlay?.classList.remove('hidden'); }
    function closeMobile(){ if (window.sidebarClose) return window.sidebarClose(); body.classList.remove('sidebar-open','overflow-hidden'); els.overlay?.classList.add('hidden'); }
    function toggleDesktop(){
      if (window.sidebarToggle) return window.sidebarToggle();
      body.classList.toggle('sidebar-collapsed');
      try{ localStorage.setItem(lsKey, body.classList.contains('sidebar-collapsed')?'1':'0'); }catch{}
      setTimeout(()=>window.dispatchEvent(new Event('resize')),120);
    }

    try{ if(localStorage.getItem(lsKey)==='1') body.classList.add('sidebar-collapsed'); }catch{}
    els.mobileBtn?.addEventListener('click', (e)=>{ e.preventDefault(); const open = body.classList.contains('sidebar-open'); open ? closeMobile() : openMobile(); });
    els.overlay?.addEventListener('click', closeMobile);
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeMobile(); });
    els.desktopBtn?.addEventListener('click', (e)=>{ e.preventDefault(); toggleDesktop(); });
    md.addEventListener('change', ()=>{ if (md.matches) closeMobile(); });
  })();

  // ===== Tema =====
  (function theme(){
    const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    apply(saved);
    els.themeBtn?.addEventListener('click', ()=>{ const t=document.documentElement.classList.contains('dark')?'light':'dark'; apply(t); localStorage.setItem('theme',t); });
    function apply(t){
      document.documentElement.classList.toggle('dark', t==='dark');
      els.dk?.classList.toggle('hidden', t==='dark');
      els.lt?.classList.toggle('hidden', t!=='dark');
    }
  })();

  // ===== Listado =====
  const state = { limit:(CFG.page&&CFG.page.limit)||12, offset:0, q:'', proveedor_id:'', loading:false, done:false, rows:[] };

  function card(c){
    const nombre=esc(c.nombre||''), empresa=esc(c.empresa||''), email=esc(c.email||'—'), tel=esc(c.telefono||'—'), prov=esc(c.proveedor?.nombre||'');
    return `<div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-5 flex flex-col">
      <div class="flex justify-between items-start">
        <div><h3 class="font-semibold text-[var(--text-primary)]">${nombre}</h3><p class="text-sm text-[var(--text-secondary)]">${empresa||'&nbsp;'}</p></div>
        <button class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]" aria-label="acciones"><i class="fa fa-ellipsis-vertical"></i></button>
      </div>
      <div class="mt-4 space-y-2 text-sm">
        <p class="flex items-center gap-2"><i class="fa fa-envelope w-4 text-center text-[var(--text-secondary)]"></i> ${email}</p>
        <p class="flex items-center gap-2"><i class="fa fa-phone w-4 text-center text-[var(--text-secondary)]"></i> ${tel}</p>
      </div>
      <div class="mt-4 pt-4 border-t border-[var(--border-color)]">
        <span class="px-2 py-1 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-[var(--text-secondary)]">${prov}</span>
      </div>
    </div>`;
  }

  function render(append=false){
    if(!append) els.grid.innerHTML='';
    const frag=document.createDocumentFragment();
    state.rows.forEach(c=>{ const d=document.createElement('div'); d.innerHTML=card(c); frag.appendChild(d.firstChild); });
    els.grid.appendChild(frag);
    const total = Number(els.total?.dataset.total || state.rows.length);
    els.total.dataset.total = String(total); els.total.textContent = String(total);
    els.empty.classList.toggle('hidden', state.rows.length>0);
    els.grid.classList.toggle('hidden', state.rows.length===0);
    renderPager();
  }

  function renderPager(){
    els.pager.innerHTML='';
    if(state.done) return;
    const b=document.createElement('button');
    b.className='px-4 h-10 inline-flex items-center justify-center rounded-lg border border-[var(--border-color)] bg-[var(--card-bg)] text-sm font-medium';
    b.textContent= state.loading ? 'Cargando…' : 'Cargar más';
    b.disabled=state.loading;
    b.addEventListener('click', loadMore);
    els.pager.appendChild(b);
  }

  async function fetchList({reset=false, meta=false}={}){
    if(state.loading) return; state.loading=true;
    if(reset){ state.offset=0; state.done=false; state.rows=[]; render(false); }

    const u = new URL(CFG.listar, location.origin);
    u.searchParams.set('limit', String(state.limit));
    u.searchParams.set('offset', String(state.offset));
    if(state.q) u.searchParams.set('q', state.q);
    if(state.proveedor_id) u.searchParams.set('proveedor_id', state.proveedor_id);
    if(meta) u.searchParams.set('meta','1');

    const r = await fetch(u, {credentials:'same-origin', headers:{'Accept':'application/json'}});
    if(!r.ok){ state.loading=false; throw new Error('HTTP '+r.status); }
    const data = await r.json();

    if(meta && data?.meta?.proveedores && els.prov && !els.prov.dataset.filled){
      const f=document.createDocumentFragment();
      const all=document.createElement('option'); all.value=''; all.textContent='Todos los Proveedores'; f.appendChild(all);
      data.meta.proveedores.forEach(p=>{ const o=document.createElement('option'); o.value=p.id; o.textContent=p.nombre; f.appendChild(o); });
      els.prov.appendChild(f); els.prov.dataset.filled='1';
    }

    const items = Array.isArray(data?.data)?data.data:[];
    state.rows = state.rows.concat(items);
    state.offset = Number(data?.next_offset ?? state.offset + items.length);
    const total = Number(data?.total ?? state.rows.length);
    els.total.dataset.total = String(total); els.total.textContent = String(total);
    state.done = state.offset >= total || items.length < state.limit;
    state.loading=false;
    render(true);
  }

  async function loadMore(){ if(state.done||state.loading) return; fetchList({reset:false, meta:false}).catch(console.error); }

  // ===== Filtros =====
  function debounce(fn,ms=220){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
  function bindFilters(){
    els.search?.addEventListener('input', debounce(()=>{ state.q=els.search.value.trim(); fetchList({reset:true}).catch(console.error); }));
    els.prov?.addEventListener('change', ()=>{ state.proveedor_id=els.prov.value||''; fetchList({reset:true}).catch(console.error); });
  }

  // ===== Import UI (sin candado) =====
  function toggleImport(){
    if(!els.panel) return;
    const show = els.panel.classList.contains('hidden');
    els.panel.classList.toggle('hidden', !show);
    if(show) els.paste?.focus();
  }
  function bindImport(){
    els.importBtn?.addEventListener('click', (e)=>{ e.preventDefault(); toggleImport(); });
    els.newBtn?.addEventListener('click', (e)=>{ e.preventDefault(); toggleImport(); });

    if(els.drop){
      ['dragenter','dragover','dragleave','drop'].forEach(ev=>els.drop.addEventListener(ev,(e)=>{e.preventDefault();e.stopPropagation();},false));
      els.drop.addEventListener('dragenter',()=>els.drop.classList.add('is-dragover'));
      els.drop.addEventListener('dragleave',()=>els.drop.classList.remove('is-dragover'));
      els.drop.addEventListener('drop',()=>els.drop.classList.remove('is-dragover'));
    }
  }

  // ===== Init =====
  document.addEventListener('DOMContentLoaded', ()=>{
    bindFilters();
    bindImport();
    fetchList({reset:true, meta:true}).catch((e)=>{
      console.error(e);
      if(els.grid) els.grid.innerHTML='<div class="text-sm text-red-600">No se pudo cargar el listado.</div>';
    });
  });
})();

// /console/asset/js/vallas.js
(function(){
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const qs   = (s,el=document)=>el.querySelector(s);
  const qsa  = (s,el=document)=>Array.from(el.querySelectorAll(s));
  const money=(n)=>'$'+Number(n||0).toLocaleString('en-US',{maximumFractionDigits:2});

  // ---- ADS animado (badge) ----
  (function injectAdsCSS(){
    if (document.getElementById('ads-anim-css')) return;
    const css = `
    @keyframes adsGlow{0%{box-shadow:0 0 0 0 rgba(147,51,234,.6)}70%{box-shadow:0 0 0 .5rem rgba(147,51,234,0)}100%{box-shadow:0 0 0 0 rgba(147,51,234,0)}}
    @keyframes adsShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
    .ads-badge-anim{position:absolute;top:-1px;left:-1px;background-size:200% 200%;
      background-image:linear-gradient(120deg,#6d28d9,#be185d,#f59e0b);
      color:#fff;padding:4px 12px;font-size:.75rem;font-weight:700;border-top-left-radius:.75rem;border-bottom-right-radius:.75rem;
      animation:adsShift 3s ease infinite, adsGlow 2.5s ease-out infinite}
    .ads-chip-anim{display:inline-block;margin-left:.375rem;padding:.15rem .45rem;border-radius:9999px;font-size:.65rem;font-weight:700;color:#fff;
      background-size:200% 200%;background-image:linear-gradient(120deg,#6d28d9,#be185d,#f59e0b);animation:adsShift 3s ease infinite}
    .icon-btn{padding:.5rem;border-radius:9999px;color:var(--text-secondary)}
    .icon-btn:hover{background:var(--sidebar-active-bg)}
    `;
    const s=document.createElement('style'); s.id='ads-anim-css'; s.textContent=css; document.head.appendChild(s);
  })();

  // ---- Tema ----
  const tBtn=qs('#theme-toggle'), moon=qs('#theme-toggle-dark-icon'), sun=qs('#theme-toggle-light-icon');
  function applyTheme(t){ if(t==='dark'){document.documentElement.classList.add('dark');moon?.classList.remove('hidden');sun?.classList.add('hidden');}else{document.documentElement.classList.remove('dark');sun?.classList.remove('hidden');moon?.classList.add('hidden');} }
  let theme=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'); applyTheme(theme);
  tBtn?.addEventListener('click',()=>{theme=document.documentElement.classList.contains('dark')?'light':'dark';localStorage.setItem('theme',theme);applyTheme(theme);});

  // ---- Sidebar móvil ----
  const body=document.body, overlay=qs('#sidebar-overlay');
  qs('#mobile-menu-button')?.addEventListener('click',e=>{e.stopPropagation();body.classList.toggle('sidebar-open');overlay?.classList.toggle('hidden');});
  overlay?.addEventListener('click',()=>{body.classList.remove('sidebar-open');overlay?.classList.add('hidden');});
  qs('#sidebar-toggle-desktop')?.addEventListener('click',()=>body.classList.toggle('sidebar-collapsed'));

  // ---- Estado filtros/paginación ----
  const S = { q:'', prov:'', disp:'', publico:'', ads:'', page:1, size:12, view:localStorage.getItem('vallasView')||'grid' };

  // ---- Controles ----
  const fQ=qs('#f-q'), fProv=qs('#f-proveedor'), fDisp=qs('#f-disp'), fPub=qs('#f-publico'), fAds=qs('#f-ads');
  const gBtn=qs('#grid-view-btn'), lBtn=qs('#list-view-btn'), grid=qs('#grid-view'), list=qs('#list-view'), pager=qs('#pager'), lockBtn=qs('#drag-lock-btn');
  let sortable=null;

  // ---- Vista grid/list ----
  function setView(v){ S.view=v; localStorage.setItem('vallasView',v);
    if(v==='grid'){ grid.classList.remove('hidden'); list.classList.add('hidden'); gBtn?.classList.add('active'); lBtn?.classList.remove('active'); lockBtn?.style && (lockBtn.style.display='flex'); }
    else { grid.classList.add('hidden'); list.classList.remove('hidden'); lBtn?.classList.add('active'); gBtn?.classList.remove('active'); lockBtn?.style && (lockBtn.style.display='none'); }
  }
  gBtn?.addEventListener('click',()=>setView('grid')); lBtn?.addEventListener('click',()=>setView('list')); setView(S.view);

  // ---- Drag & drop en grid ----
  if (grid) {
    sortable = new Sortable(grid,{animation:150,ghostClass:'sortable-ghost',dragClass:'sortable-drag',disabled:true,
      onEnd: async()=>{
        const ids = qsa('[data-id]',grid).map(el=>el.dataset.id);
        await fetch('/console/vallas/ajax/reorder.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF},body:'order='+encodeURIComponent(ids.join(','))});
      }
    });
  }
  lockBtn?.addEventListener('click',()=>{ const dis = !sortable.option('disabled'); sortable.option('disabled', dis); lockBtn.classList.toggle('unlocked', !dis); grid.classList.toggle('drag-unlocked', !dis); });

  // ---- API Destacados (ETag cache) ----
  const Dest = {
    set: new Set(),
    etag: localStorage.getItem('destacadosETag')||'',
    cache: localStorage.getItem('destacadosCache')||''
  };
  function extractDestacadosSet(payload){
    const out = new Set();
    const arr = payload?.items || payload?.data || [];
    for (const it of arr) {
      let vid = it?.valla_id ?? it?.vallaId ?? (it?.valla?.id);
      if (typeof vid === 'string') { const n = parseInt(vid,10); if(!Number.isNaN(n)) vid=n; }
      if (typeof vid === 'number' && vid>0) out.add(vid);
    }
    return out;
  }
  async function loadDestacados(){
    try{
      const headers = {'Accept':'application/json'};
      if (Dest.etag) headers['If-None-Match']=Dest.etag;
      const r = await fetch('/api/destacados/api.php?per_page=1000', {headers});
      if (r.status === 304 && Dest.cache) {
        Dest.set = extractDestacadosSet(JSON.parse(Dest.cache));
        return;
      }
      const j = await r.json();
      const et = r.headers.get('ETag');
      if (et) { Dest.etag=et; localStorage.setItem('destacadosETag', et); }
      localStorage.setItem('destacadosCache', JSON.stringify(j));
      Dest.set = extractDestacadosSet(j);
    }catch(_){ /* tolerante */ }
  }

  // ---- Filtros ----
  let qT=null;
  fQ?.addEventListener('input',()=>{ clearTimeout(qT); qT=setTimeout(()=>{S.q=fQ.value.trim(); S.page=1; load();}, 250); });
  fProv?.addEventListener('change',()=>{S.prov=fProv.value; S.page=1; load();});
  fDisp?.addEventListener('change',()=>{S.disp=fDisp.value; S.page=1; load();});
  fPub?.addEventListener('change',()=>{S.publico=fPub.value; S.page=1; load();});
  fAds?.addEventListener('change',()=>{S.ads=fAds.value; S.page=1; load();});

  // ---- Render tarjeta ----
  function card(v){
    const disp = String(v.disponible??1)==='1';
    const publico = String(v.publico??0)==='1';
    const estado = String(v.activo??1)==='1';
    const ads = String(v.ads??0)==='1';
    const dot = disp?'bg-green-500':'bg-red-500';
    const badge = estado ? 'text-green-800 bg-green-200 dark:bg-green-500/20 dark:text-green-300' : 'text-red-800 bg-red-200 dark:bg-red-500/20 dark:text-red-300';
    const eye = publico ? '<i class="fas fa-eye text-green-500" title="Precio público"></i>' : '<i class="fas fa-eye-slash text-gray-400" title="Precio no público"></i>';
    return `
      <div class="v-card relative" data-id="${v.id}">
        ${ads?'<div class="ads-badge-anim">ADS</div>':''}
        <div class="overflow-hidden h-40"><img src="${(v.imagen||'https://placehold.co/400x300/e2e8f0/718096?text=Valla')}" class="v-img w-full h-full object-cover" alt=""></div>
        <div class="p-4">
          <div class="flex justify-between items-start">
            <div class="min-w-0">
              <p class="font-bold text-lg truncate">${v.nombre||'-'}${ads?'<span class="ads-chip-anim">ADS</span>':''}</p>
              <p class="text-sm text-[var(--text-secondary)] flex items-center gap-2"><span class="w-2 h-2 rounded-full ${dot}"></span> ${disp?'Disponible':'Ocupado'}</p>
            </div>
            <span class="px-3 py-1 text-xs font-semibold rounded-full ${badge}">${estado?'Activa':'Inactiva'}</span>
          </div>
          <div class="flex justify-between items-center mt-4 pt-4 border-t border-[var(--border-color)]">
            <div class="flex items-center gap-2"><p class="font-semibold text-indigo-500">${money(v.precio)}</p>${eye}</div>
            <div class="flex items-center gap-1">
              <button class="icon-btn" data-act="view" data-id="${v.id}"><i class="fas fa-eye"></i></button>
              <button class="icon-btn" data-act="edit" data-id="${v.id}"><i class="fas fa-pencil-alt"></i></button>
              <button class="icon-btn" data-act="del"  data-id="${v.id}"><i class="fas fa-trash-alt"></i></button>
            </div>
          </div>
        </div>
      </div>`;
  }

  // ---- Render fila tabla (sin descripción) ----
  function row(v){
    const disp = String(v.disponible??1)==='1';
    const publico = String(v.publico??0)==='1';
    const estado = String(v.activo??1)==='1';
    const ads = String(v.ads??0)==='1';
    const eye = publico ? '<i class="fas fa-eye text-green-500"></i>' : '<i class="fas fa-eye-slash text-gray-400"></i>';
    const badge = estado ? 'text-green-800 bg-green-200 dark:bg-green-500/20 dark:text-green-300' : 'text-red-800 bg-red-200 dark:bg-red-500/20 dark:text-red-300';
    return `
      <tr class="hover:bg-[var(--sidebar-active-bg)] transition-colors">
        <td class="py-4 px-4">
          <div class="flex items-center gap-4">
            <img src="${(v.imagen||'https://placehold.co/80x60/e2e8f0/718096?text=Valla')}" class="w-20 h-14 object-cover rounded-md hidden sm:block" alt="">
            <div><p class="font-semibold">${v.nombre||'-'}${ads?'<span class="ads-chip-anim">ADS</span>':''}</p></div>
          </div>
        </td>
        <td class="py-4 px-4 hidden lg:table-cell text-[var(--text-secondary)]">${v.tipo||''}</td>
        <td class="py-4 px-4 hidden md:table-cell text-[var(--text-secondary)]">${v.proveedor||''}</td>
        <td class="py-4 px-4 hidden lg:table-cell font-medium text-[var(--text-secondary)]">${money(v.precio)} ${eye}</td>
        <td class="py-4 px-4"><div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full ${disp?'bg-green-500':'bg-red-500'}"></span><span class="px-3 py-1 text-xs font-semibold rounded-full ${badge}">${estado?'Activa':'Inactiva'}</span></div></td>
        <td class="py-4 px-4 text-center">
          <div class="flex items-center justify-center gap-2">
            <button class="icon-btn" data-act="view" data-id="${v.id}"><i class="fas fa-eye"></i></button>
            <button class="icon-btn" data-act="edit" data-id="${v.id}"><i class="fas fa-pencil-alt"></i></button>
            <button class="icon-btn" data-act="del"  data-id="${v.id}"><i class="fas fa-trash-alt"></i></button>
          </div>
        </td>
      </tr>`;
  }

  // ---- Render general ----
  function render(items, meta){
    if (meta?.proveedores && fProv) {
      fProv.innerHTML = '<option value="">Proveedor</option>' + meta.proveedores.map(p=>`<option value="${p.id}">${p.nombre}</option>`).join('');
      fProv.value = S.prov;
    }
    grid.innerHTML = items.map(card).join('') || '<div class="text-sm text-[var(--text-secondary)]">Sin resultados.</div>';
    const tbody = qs('#tbody-list'); if (tbody) tbody.innerHTML = items.map(row).join('');

    qsa('.icon-btn').forEach(b=>{
      b.addEventListener('click', async ()=>{
        const id = b.dataset.id, act=b.dataset.act;
        if (act==='del' && confirm('¿Eliminar esta valla?')) {
          await fetch('/console/vallas/ajax/delete.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF},body:'id='+encodeURIComponent(id)});
          load();
        } else if (act==='edit') {
          location.href='/console/vallas/editar.php?id='+encodeURIComponent(id);
        } else if (act==='view') {
          alert('Vista rápida ID '+id);
        }
      });
    });

    const total = meta?.total||items.length, pages = Math.max(1, Math.ceil(total/ (meta?.size||S.size||total)));
    const prevDis = S.page<=1?'opacity-40 pointer-events-none':''; const nextDis = S.page>=pages?'opacity-40 pointer-events-none':'';
    pager.innerHTML = `
      <p class="text-sm text-[var(--text-secondary)]">Mostrando ${items.length} de ${total} vallas</p>
      <div class="flex items-center gap-1 ${pages===1?'hidden':''}">
        <button class="px-3 py-1.5 border border-[var(--border-color)] rounded-md hover:bg-[var(--sidebar-active-bg)] ${prevDis}" data-pg="prev"><i class="fas fa-chevron-left"></i></button>
        <button class="px-3 py-1.5 border border-indigo-500 bg-indigo-50 text-indigo-600 rounded-md font-semibold dark:bg-indigo-500/20 dark:text-indigo-300">${S.page}</button>
        <button class="px-3 py-1.5 border border-[var(--border-color)] rounded-md hover:bg-[var(--sidebar-active-bg)] ${nextDis}" data-pg="next"><i class="fas fa-chevron-right"></i></button>
      </div>`;
    qsa('[data-pg]').forEach(b=>b.addEventListener('click',()=>{S.page += (b.dataset.pg==='prev'?-1:1); if(S.page<1)S.page=1; load();}));
  }

  // ---- Carga datos (lista + destacados) ----
  async function load(){
    const params = new URLSearchParams({q:S.q, prov:S.prov, disp:S.disp, publico:S.publico, ads:S.ads, page:String(S.page), size:String(S.size)});
    // Cargar destacados en paralelo
    const [_, listResp] = await Promise.all([
      loadDestacados(),
      fetch('/console/vallas/ajax/list.php?'+params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF}})
    ]);
    const j = await listResp.json();

    // Mezcla: marca como ADS si entra en set de destacados
    let items = (j.items||[]).map(v=>{
      const isAd = (String(v.ads??0)==='1') || Dest.set.has(Number(v.id));
      return {...v, ads: isAd?1:0};
    });

    // Filtro "Destacadas" desde UI
    if (S.ads==='1') items = items.filter(v=>String(v.ads??0)==='1');
    if (S.ads==='0') items = items; // "Todas"

    render(items, j.meta||{});
  }

  load();
})();

// /console/asset/js/dashboard.js
(function(){
  // SW
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', ()=>navigator.serviceWorker.register('/console/pwa/sw.js').catch(()=>{}));
  }

  // Tema claro/oscuro
  const btnTheme = document.getElementById('theme-toggle');
  const darkI = document.getElementById('theme-toggle-dark-icon');
  const lightI = document.getElementById('theme-toggle-light-icon');
  function applyTheme(t){
    if(t==='dark'){ document.documentElement.classList.add('dark'); darkI?.classList.remove('hidden'); lightI?.classList.add('hidden'); }
    else { document.documentElement.classList.remove('dark'); lightI?.classList.remove('hidden'); darkI?.classList.add('hidden'); }
  }
  let theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
  applyTheme(theme);
  btnTheme?.addEventListener('click', async ()=>{
    theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', theme); applyTheme(theme);
    await ensureCharts();
  });

  // Helpers
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const money = (n)=>'$'+Number(n||0).toLocaleString('en-US',{maximumFractionDigits:2});
  const pill = (txt)=>{
    const k=(txt||'').toString().toLowerCase();
    const c = k==='confirmada'?'green':k==='pendiente'?'yellow':k==='cancelada'?'red':'gray';
    return `<span class="px-2 py-1 text-[10px] sm:text-xs font-medium rounded-full bg-${c}-100 text-${c}-800 dark:bg-${c}-900 dark:text-${c}-300">${txt||'-'}</span>`;
  };
  const isMobile = ()=>window.matchMedia('(max-width: 640px)').matches;

  // Estado
  let DATA=null, revReady=false, typReady=false;
  const revCanvas = document.getElementById('revenueChart');
  const typCanvas = document.getElementById('billboardTypeChart');
  const revSkel = document.getElementById('skel-rev');
  const typSkel = document.getElementById('skel-typ');
  let revChart=null, typChart=null;

  // Lazy charts
  const obs = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if (e.isIntersecting) {
        if (e.target===revCanvas) { revReady=true; ensureCharts(); }
        if (e.target===typCanvas) { typReady=true; ensureCharts(); }
      }
    });
  }, {threshold:.2});
  obs.observe(revCanvas); obs.observe(typCanvas);

  // Aumentar tamaños en móvil (KPIs y títulos)
  function bumpMobileSizes(){
    const big = isMobile();
    const kpis = ['k_vallas','k_ingresos','k_reservas','k_ads'];
    kpis.forEach(id=>{
      const el = document.getElementById(id);
      if (!el) return;
      el.style.fontSize = big ? '2rem' : ''; // ~32px en móvil
      el.style.lineHeight = big ? '2.25rem' : '';
    });
    document.querySelectorAll('.sec-title').forEach(el=>{
      el.style.fontSize = big ? '1.125rem' : ''; // 18px
    });
  }
  bumpMobileSizes();
  window.addEventListener('resize', ()=>{ bumpMobileSizes(); ensureCharts(); });

  async function ensureCharts(){
    if (!DATA) return;
    const dark = document.documentElement.classList.contains('dark');
    const grid = dark?'rgba(255,255,255,.12)':'rgba(0,0,0,.08)';
    const ticks= dark?'#9CA3AF':'#6B7280';
    const border= dark?'#1f2937':'#FFFFFF';

    // Paletas
    const palLight = ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#14B8A6'];
    const palDark  = ['#60A5FA','#34D399','#FBBF24','#A78BFA','#F87171','#2DD4BF'];
    const doughnutPal = dark ? palDark : palLight;
    const barBg  = dark ? 'rgba(129,140,248,0.75)' : 'rgba(79,70,229,0.75)';
    const barBor = dark ? 'rgba(129,140,248,1)'    : 'rgba(79,70,229,1)';

    const tickSize   = isMobile()? 13 : 12;
    const legendSize = isMobile()? 12 : 11;

    if (revReady) {
      if (revChart) revChart.destroy();
      const ctx1 = revCanvas.getContext('2d');
      revChart = new Chart(ctx1,{
        type:'bar',
        data:{
          labels: DATA.revenue?.labels || [],
          datasets:[{
            label:'Ingresos',
            data: DATA.revenue?.data || [],
            backgroundColor: barBg,
            borderColor: barBor,
            borderWidth: 1,
            borderRadius: 8,
            maxBarThickness: isMobile()? 24 : 28
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false, layout:{padding:{top:4,right:6,left:0,bottom:0}},
          scales:{
            y:{beginAtZero:true,grid:{color:grid},ticks:{color:ticks, font:{size:tickSize}}},
            x:{grid:{display:false},      ticks:{color:ticks, font:{size:tickSize}}}
          },
          plugins:{
            legend:{display:false},
            tooltip:{
              backgroundColor: dark?'#111827':'#FFFFFF',
              titleColor: dark?'#F9FAFB':'#111827',
              bodyColor:  dark?'#E5E7EB':'#1F2937',
              borderColor: border, borderWidth: 1
            }
          }
        }
      });
      revSkel?.classList.add('hidden'); revCanvas.style.opacity='1';
    }

    if (typReady) {
      if (typChart) typChart.destroy();
      const ctx2 = typCanvas.getContext('2d');
      const dataArr = DATA.types?.data || [];
      const bg = dataArr.map((_,i)=>doughnutPal[i % doughnutPal.length]);

      typChart = new Chart(ctx2,{
        type:'doughnut',
        data:{
          labels: DATA.types?.labels || [],
          datasets:[{
            data: dataArr,
            backgroundColor: bg,
            borderWidth: 4,
            borderColor: border
          }]
        },
        options:{
          responsive:true, maintainAspectRatio:false, cutout: isMobile()? '62%' : '66%',
          plugins:{
            legend:{position:'bottom', labels:{color:ticks, font:{size:legendSize}}},
            tooltip:{
              backgroundColor: dark?'#111827':'#FFFFFF',
              titleColor: dark?'#F9FAFB':'#111827',
              bodyColor:  dark?'#E5E7EB':'#1F2937',
              borderColor: border, borderWidth: 1
            }
          }
        }
      });
      typSkel?.classList.add('hidden'); typCanvas.style.opacity='1';
    }
  }

  function renderKPIs(t){
    const v=document.getElementById('k_vallas'),
          i=document.getElementById('k_ingresos'),
          r=document.getElementById('k_reservas'),
          a=document.getElementById('k_ads');
    [v,i,r,a].forEach(el=>el?.classList.remove('skel'));
    v.textContent = t.vallas ?? 0;
    i.textContent = money(t.ingresos_mes);
    r.textContent = t.reservas_mes ?? 0;
    a.textContent = t.ads_destacados ?? 0;
  }

  function renderReservas(items){
    const L = document.getElementById('list-reservas'); L.innerHTML='';
    (items||[]).forEach(x=>{
      L.insertAdjacentHTML('beforeend', `
        <article class="rounded-lg border border-[var(--border-color)] p-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-semibold text-[15px]">${x.valla||'-'}</p>
              <p class="text-[12px] text-[var(--text-secondary)]">${x.cliente||'-'}</p>
              <p class="text-[12px] mt-1">${x.desde||'-'} - ${x.hasta||'-'}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold">${x.monto_form||money(x.monto)}</p>
              <div class="mt-1">${pill(x.estado)}</div>
            </div>
          </div>
        </article>
      `);
    });
    const T = document.getElementById('tbody-reservas'); T.innerHTML='';
    (items||[]).forEach(r=>{
      T.insertAdjacentHTML('beforeend', `
        <tr class="border-b border-[var(--border-color)] hover:bg-gray-50 dark:hover:bg-gray-800/50">
          <td class="px-4 py-3 font-medium whitespace-nowrap">${r.valla||'-'}</td>
          <td class="px-4 py-3">${r.cliente||'-'}</td>
          <td class="px-4 py-3">${r.desde||'-'} - ${r.hasta||'-'}</td>
          <td class="px-4 py-3">${r.monto_form||money(r.monto)}</td>
          <td class="px-4 py-3 text-center">${pill(r.estado)}</td>
        </tr>
      `);
    });
  }

  function renderLicencias(items){
    const L = document.getElementById('list-licencias'); L.innerHTML='';
    (items||[]).forEach(x=>{
      L.insertAdjacentHTML('beforeend', `
        <div class="flex items-center justify-between rounded-lg border border-[var(--border-color)] px-3 py-2">
          <span class="text-sm font-medium">${x.valla||'-'}</span>
          <span class="text-xs text-[var(--text-secondary)]">${x.vence_en||'-'}</span>
        </div>
      `);
    });
    const T = document.getElementById('tbody-licencias'); T.innerHTML='';
    (items||[]).forEach(x=>{
      T.insertAdjacentHTML('beforeend', `
        <tr class="border-b border-[var(--border-color)]">
          <td class="px-4 py-3 font-medium">${x.valla||'-'}</td>
          <td class="px-4 py-3">${x.vence_en||'-'}</td>
        </tr>
      `);
    });
  }

  function renderVallas(items){
    const L = document.getElementById('list-vallas'); L.innerHTML='';
    (items||[]).forEach(v=>{
      L.insertAdjacentHTML('beforeend', `
        <div class="flex items-center justify-between rounded-lg border border-[var(--border-color)] px-3 py-2">
          <div>
            <p class="text-sm font-semibold">${v.nombre||'-'}</p>
            <p class="text-xs text-[var(--text-secondary)]">${v.tipo||''}</p>
          </div>
          <span class="text-xs">${v.fecha||''}</span>
        </div>
      `);
    });
    const T = document.getElementById('tbody-vallas'); T.innerHTML='';
    (items||[]).forEach(v=>{
      T.insertAdjacentHTML('beforeend', `
        <tr class="border-b border-[var(--border-color)]">
          <td class="px-4 py-3 font-medium">${v.nombre||'-'}</td>
          <td class="px-4 py-3"><span class="text-xs font-medium mr-2 px-2.5 py-0.5 rounded-full">${v.tipo||''}</span></td>
          <td class="px-4 py-3">${v.fecha||''}</td>
        </tr>
      `);
    });
  }

  async function loadAll(){
    const r = await fetch('/console/portal/ajax/dashboard.php', {headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF}});
    const j = await r.json();
    DATA = j;
    renderKPIs(j.totals||{});
    renderReservas(j.reservas||[]);
    renderLicencias(j.licencias||[]);
    renderVallas(j.vallas||[]);
    await ensureCharts();
  }

  document.getElementById('refresh-btn')?.addEventListener('click', ()=>loadAll());
  (async function init(){ await loadAll(); })();
})();

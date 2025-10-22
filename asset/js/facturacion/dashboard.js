/* /console/asset/js/facturacion/dashboard.js */
(function(){
  'use strict';
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

  // Theme
  let revenueChart;
  function setIcons(){
    const dark = document.documentElement.classList.contains('dark');
    document.getElementById('theme-toggle-dark-icon')?.classList.toggle('hidden', dark);
    document.getElementById('theme-toggle-light-icon')?.classList.toggle('hidden', !dark);
  }
  function applyTheme(t){
    document.documentElement.classList.toggle('dark', t==='dark');
    try{ localStorage.setItem('theme', t); }catch{}
    setIcons();
    updateChartTheme(t);
  }
  const saved = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark':'light');
  applyTheme(saved);
  document.getElementById('theme-toggle')?.addEventListener('click', ()=>applyTheme(document.documentElement.classList.contains('dark')?'light':'dark'));

  // Fetch helpers
  const jGET=(url,params={})=>{
    const u=new URL(url,location.origin);
    Object.entries(params).forEach(([k,v])=>{ if(v!=='' && v!=null) u.searchParams.set(k,v); });
    return fetch(u,{credentials:'same-origin'}).then(r=>r.json());
  };

  // KPIs + Chart
  function money(n){ try{ return new Intl.NumberFormat('es-DO',{style:'currency',currency:'DOP',minimumFractionDigits:0}).format(+n||0);}catch{return 'RD$ '+(Number(n)||0).toLocaleString('es-DO');}}
  function int(n){ return Number.parseInt(n,10)||0; }

  function renderActividad(items){
    const wrap = document.getElementById('actividad-list'); if(!wrap) return;
    wrap.innerHTML = '';
    (items||[]).slice(0,6).forEach(row=>{
      const icon = row.tipo==='pago' ? 'check' : (row.tipo==='nueva' ? 'plus' : 'hourglass-start');
      const colorBg = row.tipo==='pago' ? 'green' : (row.tipo==='nueva' ? 'blue' : 'yellow');
      const colorTxt = row.tipo==='pago' ? 'green-500' : (row.tipo==='nueva' ? 'blue-500' : 'yellow-500');
      const div=document.createElement('div');
      div.className='flex items-center gap-4';
      div.innerHTML = `
        <div class="bg-${colorBg}-100 dark:bg-${colorBg}-500/20 w-10 h-10 flex-shrink-0 rounded-full flex items-center justify-center">
          <i class="fas fa-${icon} text-${colorTxt} dark:text-${colorBg}-300"></i>
        </div>
        <div class="flex-grow">
          <p class="font-medium text-[var(--text-primary)]">${row.texto||''}</p>
          <p class="text-sm text-[var(--text-secondary)]">${row.detalle||''}</p>
        </div>
        <p class="text-sm text-[var(--text-secondary)] flex-shrink-0">${row.hace||''}</p>`;
      wrap.appendChild(div);
    });
  }

  function buildChart(){
    const ctx = document.getElementById('revenueChart')?.getContext('2d');
    if(!ctx) return;
    revenueChart = new Chart(ctx, {
      type: 'doughnut',
      data: { labels:['Cobrado','Pendiente'], datasets:[{ label:'Monto', data:[0,0], borderWidth:0, hoverOffset:8 }] },
      options: {
        responsive:true, maintainAspectRatio:false, cutout:'80%',
        plugins:{
          legend:{ position:'bottom', labels:{ padding:20, usePointStyle:true, pointStyle:'circle' } },
          tooltip:{ callbacks:{ label:(ctx)=>`${ctx.label}: ${money(ctx.parsed)}` } }
        }
      },
      plugins:[{
        id:'centerText',
        afterDraw:(chart)=>{
          const {ctx, chartArea} = chart; if(!chartArea) return;
          const cx=(chartArea.left+chartArea.right)/2, cy=(chartArea.top+chartArea.bottom)/2;
          const isDark = document.documentElement.classList.contains('dark');
          ctx.save();
          ctx.textAlign='center'; ctx.textBaseline='middle';
          ctx.font='14px Inter,sans-serif'; ctx.fillStyle = isDark?'#9CA3AF':'#6B7280'; ctx.fillText('Total Facturado', cx, cy-15);
          const total = (chart.data.datasets[0].data||[]).reduce((a,b)=>a+(+b||0),0);
          ctx.font='bold 30px Inter,sans-serif'; ctx.fillStyle = isDark?'#F9FAFB':'#1F2937';
          try{ ctx.fillText(new Intl.NumberFormat('es-DO',{style:'currency',currency:'DOP',minimumFractionDigits:0}).format(total), cx, cy+15); }
          catch{ ctx.fillText('RD$ '+total.toLocaleString('es-DO'), cx, cy+15); }
          ctx.restore();
        }
      }]
    });
    updateChartTheme(document.documentElement.classList.contains('dark')?'dark':'light');
  }

  function updateChartTheme(theme){
    if(!revenueChart) return;
    const isDark = theme==='dark';
    const textColor = isDark ? '#D1D5DB' : '#4B5563';
    const cobradoColor = isDark ? '#059669' : '#10b981';
    const pendienteColor = isDark ? '#d97706' : '#f59e0b';
    revenueChart.data.datasets[0].backgroundColor = [cobradoColor, pendienteColor];
    revenueChart.options.plugins.legend.labels.color = textColor;
    revenueChart.update();
  }

  async function loadKPI(){
    try{
      const r = await jGET('/console/facturacion/ajax/kpi.php');
      // KPIs
      document.getElementById('kpi-cobrado').textContent = money(r?.totals?.cobrado || 0);
      document.getElementById('kpi-pendiente').textContent = money(r?.totals?.pendiente || 0);
      document.getElementById('kpi-vencidas').textContent = int(r?.totals?.vencidas || 0);
      document.getElementById('kpi-nuevas').textContent = int(r?.totals?.nuevas_mes || 0);
      document.getElementById('kpi-pendiente-det').textContent = `En ${int(r?.totals?.pendientes_count||0)} facturas`;
      document.getElementById('kpi-cobrado-vs').textContent = r?.vs?.cobrado ?? '—';
      document.getElementById('kpi-vencidas-vs').textContent = r?.vs?.vencidas ?? '—';
      document.getElementById('kpi-nuevas-vs').textContent = r?.vs?.nuevas ?? '—';
      // Chart
      if(revenueChart){
        revenueChart.data.datasets[0].data = [Number(r?.totals?.cobrado||0), Number(r?.totals?.pendiente||0)];
        revenueChart.update();
      }
      // Actividad
      renderActividad(r?.actividad||[]);
    }catch{
      // noop
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    buildChart();
    loadKPI();
  });
})();

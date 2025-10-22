(function(){
  const $ = s => document.querySelector(s);
  const fmt = new Intl.NumberFormat('es-DO',{style:'currency',currency:'DOP'});

  async function j(url){
    const r = await fetch(url,{credentials:'include'});
    if(!r.ok) throw new Error(r.status);
    return r.json();
  }

  function card(icon,title,value,sub=''){
    return `
    <div class="kpi">
      <div class="kpi-i"><i class="fas ${icon}"></i></div>
      <div class="kpi-b">
        <div class="kpi-t">${title}</div>
        <div class="kpi-v">${value}</div>
        ${sub?`<div class="kpi-s">${sub}</div>`:''}
      </div>
    </div>`;
  }

  function rowReserva(it){
    return `
      <div class="row">
        <div class="row-l">
          <div class="row-t">${it.valla_nombre ?? 'Valla '+(it.valla_id||'-')}</div>
          <div class="row-s">${it.nombre_cliente ?? '—'}</div>
        </div>
        <div class="row-r">
          <span class="pill">${it.estado}</span>
          <div class="row-s">${it.fecha_inicio} → ${it.fecha_fin}</div>
        </div>
      </div>`;
  }

  function rowFactura(it){
    return `
      <div class="row">
        <div class="row-l">
          <div class="row-t">${it.cliente_nombre || '—'}</div>
          <div class="row-s">#${it.id} · ${new Date(it.creado).toLocaleString()}</div>
        </div>
        <div class="row-r">
          <span class="pill">${it.estado}</span>
          <div class="row-s">${fmt.format(it.total||0)}</div>
        </div>
      </div>`;
  }

  async function load(){
    try{
      const s = await j('/console/empleados/ajax/stats.php');
      if(s.ok){
        const x = s.stats;
        $('#kpi-cards').innerHTML = [
          card('fa-ad','Vallas', x.vallas_total, `${x.vallas_activas} activas · ${x.vallas_inactivas} inactivas`),
          card('fa-calendar-check','Reservas', x.reservas_total, `${x.reservas_activas} activas`),
          card('fa-file-invoice-dollar','Pagado', fmt.format(x.fact_pagadas||0)),
          card('fa-hourglass-half','Pendiente', fmt.format(x.fact_pendientes||0)),
        ].join('');
      }
    }catch(e){}

    try{
      const r = await j('/console/empleados/ajax/reservas_recientes.php');
      $('#res-list').innerHTML = r.ok && r.items.length ? r.items.map(rowReserva).join('') : `<div class="empty">Sin datos</div>`;
    }catch(e){ $('#res-list').innerHTML = `<div class="empty">Error</div>`; }

    try{
      const f = await j('/console/empleados/ajax/facturas_recientes.php');
      $('#fac-list').innerHTML = f.ok && f.items.length ? f.items.map(rowFactura).join('') : `<div class="empty">Sin datos</div>`;
    }catch(e){ $('#fac-list').innerHTML = `<div class="empty">Error</div>`; }
  }

  // sidebar helpers already injected by sidebar.php
  document.addEventListener('click', (e)=>{
    const el = e.target.closest('#mobile-menu-button'); if(!el) return;
    e.preventDefault(); if (window.sidebarOpen) window.sidebarOpen();
  });

  // theme toggle from base.css
  (function initThemeBtn(){
    const btn = document.getElementById('theme-toggle');
    if(!btn) return;
    const moon = document.getElementById('theme-toggle-dark-icon');
    const sun  = document.getElementById('theme-toggle-light-icon');
    function sync(){
      const dark = document.documentElement.classList.contains('dark');
      moon.classList.toggle('hidden', dark);
      sun.classList.toggle('hidden', !dark);
    }
    btn.addEventListener('click', ()=>{ document.documentElement.classList.toggle('dark'); sync(); });
    sync();
  })();

  load();
})();

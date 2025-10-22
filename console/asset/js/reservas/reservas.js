/* /console/asset/js/reservas/reservas.js */
(function () {
  'use strict';

  // ---------- refs ----------
  const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>Array.from(c.querySelectorAll(s));
  const CSRF=$('meta[name="csrf"]')?.content||'';
  const boot=window.__RESERVAS_BOOT__||{center:[18.7357,-70.1627],zoom:9};

  const elVallaFilter=$('#valla-filter');
  const elEstadoFilter=$('#estado-filter');
  const btnNew=$('#new-reserva-btn');

  const modal=$('#reserva-modal');
  const modalBox=$('#reserva-modal-container');
  const btnClose=$('#close-modal-btn');
  const btnCancel=$('#cancel-reserva-btn');
  const form=$('#reserva-form');

  const fId=$('#reserva-id');
  const fValla=$('#valla-select');
  const fCliente=$('#cliente-nombre');
  const fIni=$('#fecha-inicio');
  const fFin=$('#fecha-fin');
  const fEstado=$('#estado-select');
  const fMotivo=$('#motivo-bloqueo');

  const note=$('#notification');
  const noteMsg=$('#notification-message');

  const AJAX={
    listar:'/console/reservas/ajax/listar.php',
    guardar:'/console/reservas/ajax/guardar.php',
    gcalStatus:'/console/reservas/ajax/gcal_status.php',
    gcalStart:'/console/reservas/ajax/gcal_start.php',
    gcalEvents:'/console/reservas/ajax/gcal_events.php',
    gcalDisconnect:'/console/reservas/ajax/gcal_disconnect.php'
  };

  // ---------- THEME (sin tocar sidebar) ----------
  let calendar;
  (function themeOnly(){
    const btnTheme=$('#theme-toggle');
    const moon=$('#theme-toggle-dark-icon');
    const sun=$('#theme-toggle-light-icon');

    function setIcons(){
      const dark=document.documentElement.classList.contains('dark');
      moon?.classList.toggle('hidden',dark);
      sun?.classList.toggle('hidden',!dark);
    }
    function applyTheme(t){
      document.documentElement.classList.toggle('dark',t==='dark');
      try{ localStorage.setItem('theme',t); }catch{}
      setIcons();
      if(calendar) setTimeout(()=>calendar.render(),1);
    }
    const saved=(()=>{ try{return localStorage.getItem('theme');}catch{return null;} })();
    applyTheme(saved ?? (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'));
    btnTheme?.addEventListener('click',()=>applyTheme(document.documentElement.classList.contains('dark')?'light':'dark'));
  })();

  // ---------- helpers ----------
  function notify(msg,ok=true,ms=2500){
    if(!note) return;
    noteMsg.textContent=msg;
    note.className='fixed top-5 right-5 text-white py-2 px-4 rounded-lg shadow-md transition-transform z-50 '+(ok?'bg-green-500':'bg-red-500');
    note.classList.remove('hidden','translate-x-full');
    setTimeout(()=>{ note.classList.add('translate-x-full'); setTimeout(()=>note.classList.add('hidden'),300); }, ms);
  }
  const jGET=(url,params={})=>{
    const u=new URL(url,location.origin);
    Object.entries(params).forEach(([k,v])=>{ if(v!==''&&v!=null) u.searchParams.set(k,v); });
    return fetch(u,{credentials:'same-origin'}).then(r=>r.ok?r.json():Promise.reject());
  };
  const jPOST=(url,data)=>fetch(url,{
    method:'POST',credentials:'same-origin',
    headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
    body:JSON.stringify(data)
  }).then(r=>r.ok?r.json():Promise.reject());

  function fillSelect(el,items,ph){
    if(!el) return;
    const old=el.value;
    el.innerHTML='';
    if(ph) el.append(new Option(ph,''));
    (items||[]).forEach(it=>el.append(new Option(it.nombre,it.id)));
    if(old && [...el.options].some(o=>o.value===old)) el.value=old;
  }
  function setReservaMode(tipo){
    const b=(tipo==='bloqueo');
    fCliente.disabled=b; fCliente.required=!b;
    fMotivo.disabled=!b;
    if(b) fEstado.value='bloqueo';
    else if(fEstado.value==='bloqueo') fEstado.value='pendiente';
  }

  // ---------- Calendar ----------
  let gcalSource=null, gcalLoading=false;
  function buildCalendar(){
    const el=$('#calendar'); if(!el) return;
    calendar=new FullCalendar.Calendar(el,{
      initialView:'dayGridMonth',
      locale:'es',
      height:'auto',
      headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,listWeek'},
      buttonText:{today:'today',month:'month',week:'week',list:'list'},
      selectable:true,
      eventDisplay:'block',
      select:sel=>openForCreate({valla_id:elVallaFilter?.value||'',fecha_inicio:sel.startStr,fecha_fin:sel.endStr}),
      datesSet:()=>loadGcalIfConnected(),
      eventClick:info=>openForEdit(info.event.extendedProps._raw||{}),
      eventClassNames:(arg)=>{
        const e=arg.event.extendedProps._raw||{}, tipo=e.tipo||'reserva', estado=(e.estado||'').toLowerCase(), cls=[];
        if(tipo==='bloqueo') cls.push('status-bloqueo');
        if(estado) cls.push(`status-${estado}`);
        if(e.source==='gcal') cls.push('gcal-event');
        return cls;
      }
    });
    calendar.render();
  }
  function mapEvents(data){
    return (data.eventos||[]).map(ev=>({
      id:`${ev.tipo||'reserva'}:${ev.id}`,
      title:ev.title||'Reserva',
      start:ev.start,
      end:ev.end,
      allDay:true,
      extendedProps:{_raw:ev}
    }));
  }

  // ---------- Map ----------
  let map, markersLayer;
  function buildMap(){
    const el=$('#map'); if(!el) return;
    map=L.map(el).setView(boot.center,boot.zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
    markersLayer=L.layerGroup().addTo(map);
  }
  function setMarkers(markers){
    if(!markersLayer) return;
    markersLayer.clearLayers();
    const bounds=[];
    (markers||[]).forEach(m=>{
      if(typeof m.lat!=='number'||typeof m.lng!=='number') return;
      const icon=L.divIcon({className:m.disponible?'marker-available':'marker-occupied',html:'<div class="w-4 h-4 rounded-full border-2 border-white shadow-lg"></div>',iconSize:[16,16],iconAnchor:[8,8]});
      const mk=L.marker([m.lat,m.lng],{icon}).addTo(markersLayer);
      mk.bindPopup(`<b>${m.nombre||'Valla'}</b><br>${m.estado_valla||''}`);
      bounds.push([m.lat,m.lng]);
      mk.on('click',()=>{ if(elVallaFilter){ elVallaFilter.value=String(m.id); filterAndReload(); } map.setView([m.lat,m.lng],15); });
    });
    if(bounds.length) try{ map.fitBounds(bounds,{padding:[20,20]}); }catch{}
  }

  // ---------- Data internos ----------
  async function loadInternos(){
    const params={valla_id:elVallaFilter?.value||'',estado:elEstadoFilter?.value||''};
    const res=await jGET(AJAX.listar,params).catch(()=>null);
    if(!res||res.ok!==true){ notify('Error al cargar',false); return; }
    fillSelect(elVallaFilter,res.vallas||[],'Todas las Vallas');
    fillSelect(fValla,res.vallas||[],'Seleccione una valla');
    if(params.valla_id) elVallaFilter.value=String(params.valla_id);
    if(params.estado) elEstadoFilter.value=String(params.estado);
    setMarkers(res.markers||[]);
    const events=mapEvents(res);
    calendar.removeAllEvents();
    calendar.addEventSource(events);
    await loadGcalIfConnected();
  }
  const filterAndReload=()=>loadInternos();

  // ---------- Modal ----------
  function openForCreate(seed={}){
    form.reset(); fId.value='';
    if(seed.valla_id) fValla.value=String(seed.valla_id);
    if(seed.fecha_inicio) fIni.value=seed.fecha_inicio;
    if(seed.fecha_fin) fFin.value=seed.fecha_fin;
    fEstado.value='pendiente'; fMotivo.value='';
    setReservaMode('reserva');
    $('#modal-title').textContent='Nueva Reserva';
    openModal();
  }
  function openForEdit(ev){
    form.reset(); fId.value=ev.id||'';
    fValla.value=ev.valla_id?String(ev.valla_id):'';
    if(ev.tipo==='bloqueo'){ fCliente.value=''; fMotivo.value=ev.title||ev.motivo||'Bloqueo'; fEstado.value='bloqueo'; setReservaMode('bloqueo'); }
    else { fCliente.value=ev.title||ev.nombre_cliente||''; fEstado.value=ev.estado||'pendiente'; fMotivo.value=''; setReservaMode('reserva'); }
    fIni.value=ev.start?String(ev.start).substring(0,10):'';
    fFin.value=ev.end?String(ev.end).substring(0,10):'';
    $('#modal-title').textContent=ev.tipo==='bloqueo'?'Editar Bloqueo':'Editar Reserva';
    openModal();
  }
  function openModal(){ modal.classList.remove('hidden'); requestAnimationFrame(()=>{ modal.classList.remove('opacity-0'); modalBox.classList.remove('scale-95'); }); }
  function closeModal(){ modalBox.classList.add('scale-95'); modal.classList.add('opacity-0'); setTimeout(()=>modal.classList.add('hidden'),150); }

  // ---------- Wire ----------
  function wireUI(){
    elVallaFilter?.addEventListener('change',filterAndReload);
    elEstadoFilter?.addEventListener('change',filterAndReload);
    btnNew?.addEventListener('click',e=>{e.preventDefault(); openForCreate();});
    btnClose?.addEventListener('click',closeModal);
    btnCancel?.addEventListener('click',closeModal);
    modal?.addEventListener('click',e=>{ if(e.target===modal) closeModal(); });

    if(window.flatpickr){
      if(window.flatpickr.l10ns?.es) window.flatpickr.localize(window.flatpickr.l10ns.es);
      window.flatpickr(fIni,{dateFormat:'Y-m-d',allowInput:true});
      window.flatpickr(fFin,{dateFormat:'Y-m-d',allowInput:true});
    }

    fEstado?.addEventListener('change',()=>setReservaMode(fEstado.value==='bloqueo'?'bloqueo':'reserva'));

    form?.addEventListener('submit',async e=>{
      e.preventDefault();
      const p={
        csrf:CSRF,
        id:fId.value?parseInt(fId.value,10):0,
        valla_id:fValla.value?parseInt(fValla.value,10):0,
        nombre_cliente:fCliente.value.trim(),
        fecha_inicio:fIni.value.trim(),
        fecha_fin:fFin.value.trim(),
        estado:fEstado.value,
        motivo:fMotivo.value.trim()
      };
      if(!p.valla_id) return notify('Seleccione una valla',false);
      if(!/^\d{4}-\d{2}-\d{2}$/.test(p.fecha_inicio)) return notify('Fecha inicio inválida',false);
      if(!/^\d{4}-\d{2}-\d{2}$/.test(p.fecha_fin)) return notify('Fecha fin inválida',false);
      if(new Date(p.fecha_fin) < new Date(p.fecha_inicio)) return notify('Rango de fechas inválido',false);
      if(p.estado!=='bloqueo' && p.nombre_cliente==='') return notify('Cliente requerido',false);

      try{
        const r=await jPOST(AJAX.guardar,p);
        if(!r.ok) return notify(r.msg||'Error',false);
        notify('Guardado');
        closeModal();
        await loadInternos();
      }catch{ notify('Error de red',false); }
    });

    // Google Calendar
    const btn=$('#google-calendar-btn');
    const dot=$('#gcal-status');
    const txt=(function(){ if(!btn) return null; const spans=[...btn.querySelectorAll('span')].filter(s=>s.id!=='gcal-status'); return spans[0]||null; })();

    async function setGcalUI(connected){
      dot?.classList.toggle('bg-green-500',connected);
      dot?.classList.toggle('bg-red-500',!connected);
      if(txt) txt.textContent = connected ? 'Calendario Conectado' : 'Conectar Calendario';
      btn?.setAttribute('data-connected', connected ? '1':'0');
      btn?.setAttribute('aria-pressed', connected ? 'true':'false');
    }
    async function checkGcal(){
      try{
        const r=await jGET(AJAX.gcalStatus);
        await setGcalUI(r.connected===true);
        if(r.connected) await loadGcalIfConnected();
      }catch{}
    }
    btn?.addEventListener('click', async (e)=>{
      e.preventDefault();
      const connected = btn.getAttribute('data-connected')==='1';
      if(!connected){
        const r=await jGET(AJAX.gcalStart);
        if(r.ok && r.url){ location.href=r.url; }
        else notify(r.msg||'No se pudo iniciar OAuth',false);
      }else{
        const r=await jPOST(AJAX.gcalDisconnect,{});
        if(r.ok){
          await setGcalUI(false);
          if(gcalSource){ gcalSource.remove(); gcalSource=null; }
          notify('Desconectado');
        } else notify(r.msg||'No se pudo desconectar',false);
      }
    });
    checkGcal();
  }

  // ---------- GCal loader ----------
  async function loadGcalIfConnected(){
    if(!calendar || gcalLoading) return;
    try{
      const st=await jGET(AJAX.gcalStatus); if(!st.connected) return;
      gcalLoading=true;
      const view=calendar.view;
      const time_min=view.activeStart.toISOString();
      const time_max=view.activeEnd.toISOString();
      const data=await jGET(AJAX.gcalEvents,{time_min,time_max});
      if(gcalSource){ gcalSource.remove(); gcalSource=null; }
      const events=(data.items||[]).map(ev=>({
        id:`gcal:${ev.id}`,
        title:ev.summary||'(sin título)',
        start:ev.start?.dateTime||ev.start?.date,
        end:ev.end?.dateTime||ev.end?.date,
        allDay:!!ev.start?.date,
        extendedProps:{_raw:{id:ev.id,start:ev.start?.dateTime||ev.start?.date,end:ev.end?.dateTime||ev.end?.date,tipo:'reserva',estado:'confirmada',source:'gcal'}}
      }));
      gcalSource=calendar.addEventSource(events);
    }catch{} finally{ gcalLoading=false; }
  }

  // ---------- init ----------
  document.addEventListener('DOMContentLoaded',async()=>{
    buildCalendar();
    buildMap();
    wireUI();
    await loadInternos();
  });
})();

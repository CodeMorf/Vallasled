// /console/asset/js/vallas_agregar.js
(function(){
  const $ = (s,el=document)=>el.querySelector(s);
  const CSRF = $('meta[name="csrf"]')?.content || '';
  const GMAPS_KEY = $('meta[name="gmaps-key"]')?.content || '';

  // ---- API helpers (dominio dinámico, sesión + CSRF) ----
  const API = {
    async get(url) {
      const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
      if (!r.ok) throw new Error('GET '+url+' '+r.status);
      return r.json();
    },
    async postForm(url, data) {
      const r = await fetch(url, {
        method:'POST',
        headers:{
          'Content-Type':'application/x-www-form-urlencoded',
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF': CSRF
        },
        body: data.toString()
      });
      if (!r.ok) throw new Error('POST '+url+' '+r.status);
      return r.json();
    }
  };

  let PROVINCIAS = [];   // [{id,nombre}]
  let ZONAS_ALL  = [];   // [{id,nombre,provincia_id}]
  const norm = s => (s||'').toString().normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase().trim();

  // PWA
  if ('serviceWorker' in navigator) { window.addEventListener('load', ()=>navigator.serviceWorker.register('/console/pwa/sw.js').catch(()=>{})); }

  // Tema
  const btnTheme = $('#theme-toggle'), darkI=$('#theme-toggle-dark-icon'), lightI=$('#theme-toggle-light-icon');
  function applyTheme(t){ if(t==='dark'){document.documentElement.classList.add('dark');darkI?.classList.remove('hidden');lightI?.classList.add('hidden');} else {document.documentElement.classList.remove('dark');lightI?.classList.remove('hidden');darkI?.classList.add('hidden');} }
  let theme = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'); applyTheme(theme);
  btnTheme?.addEventListener('click',()=>{ theme=document.documentElement.classList.contains('dark')?'light':'dark'; localStorage.setItem('theme',theme); applyTheme(theme); });

  // Sidebar móvil
  const body=document.body, overlay=$('#sidebar-overlay');
  $('#mobile-menu-button')?.addEventListener('click',e=>{e.stopPropagation(); body.classList.toggle('sidebar-open'); overlay?.classList.toggle('hidden');});
  overlay?.addEventListener('click',()=>{ body.classList.remove('sidebar-open'); overlay?.classList.add('hidden'); });
  $('#sidebar-toggle-desktop')?.addEventListener('click',()=>{ body.classList.toggle('sidebar-collapsed'); setTimeout(()=>window.dispatchEvent(new Event('resize')),200); });

  // Flatpickr
  if (window.flatpickr) {
    flatpickr("#ads-start",{dateFormat:"Y-m-d"});
    flatpickr("#ads-end",{dateFormat:"Y-m-d"});
    flatpickr("#fecha_vencimiento",{dateFormat:"Y-m-d"});
  }

  // ADS toggle
  const adsT = $('#toggle-ads'), adsBox = $('#ads-date-range');
  adsT?.addEventListener('change',()=>adsBox.classList.toggle('hidden', !adsT.checked));

  // Imagen preview
  const dz = $('#image-drop-zone'), finp=$('#image-upload'), prevC=$('#image-preview-container'), prevI=$('#image-preview'), fname=$('#image-filename'), fsize=$('#image-filesize'), prompt=$('#upload-prompt');
  function preview(file){
    const rd=new FileReader();
    rd.onload=ev=>{ prevI.src=ev.target.result; fname.textContent=file.name; fsize.textContent=(file.size/1024).toFixed(1)+' KB'; prevC.classList.remove('hidden'); prompt.classList.add('hidden'); };
    rd.readAsDataURL(file);
  }
  dz?.addEventListener('click',()=>finp.click());
  dz?.addEventListener('dragover',e=>{e.preventDefault(); dz.classList.add('dragover');});
  dz?.addEventListener('dragleave',()=>dz.classList.remove('dragover'));
  dz?.addEventListener('drop',e=>{e.preventDefault(); dz.classList.remove('dragover'); if(e.dataTransfer.files[0]) preview(e.dataTransfer.files[0]);});
  finp?.addEventListener('change',e=>{ if(e.target.files[0]) preview(e.target.files[0]); });

  // ---- Catálogos: provincias y zonas ----
  async function loadProvincias() {
    const j = await API.get('/api/provincias/index.php?per_page=1000&sort=nombre&dir=asc');
    PROVINCIAS = Array.isArray(j?.data) ? j.data : [];
    const sel = $('#provincia');
    sel.innerHTML = '<option value="">Seleccionar…</option>' +
      PROVINCIAS.map(p=>`<option value="${p.id}">${p.nombre}</option>`).join('');
  }

  async function loadZonas() {
    const j = await API.get('/api/zonas/index.php?per_page=1000&sort=nombre&dir=asc');
    ZONAS_ALL = Array.isArray(j?.data) ? j.data : [];
    repoblarZonas();
  }

  function repoblarZonas() {
    const selProv = $('#provincia');
    const provId = parseInt(selProv.value || '0', 10);
    const selZona = $('#zona');
    const lista = provId ? ZONAS_ALL.filter(z => Number(z.provincia_id||0) === provId) : ZONAS_ALL;
    selZona.innerHTML = '<option value="">Seleccionar…</option>' +
      lista.map(z=>`<option value="${z.nombre}">${z.nombre}</option>`).join('');
  }

  $('#provincia')?.addEventListener('change', repoblarZonas);

  // Crear zona al vuelo
  $('#add-zone-btn')?.addEventListener('click', async () => {
    const provId = parseInt($('#provincia')?.value || '0', 10);
    if (!provId) { alert('Selecciona una provincia antes de crear la zona.'); return; }

    const nombre = prompt('Nombre de la nueva zona:');
    if (!nombre || !nombre.trim()) return;

    try {
      const form = new URLSearchParams();
      form.set('nombre', nombre.trim());
      form.set('provincia_id', String(provId));
      form.set('csrf', CSRF);

      const z = await API.postForm('/api/zonas/index.php', form);
      if (z && z.id) {
        ZONAS_ALL.push({id:z.id, nombre:z.nombre || nombre.trim(), provincia_id: provId});
        repoblarZonas();
        $('#zona').value = z.nombre || nombre.trim();
      } else {
        await loadZonas();
        $('#zona').value = nombre.trim();
      }
    } catch(e) {
      alert('No se pudo crear la zona.');
    }
  });

  // ---- Google Maps ----
  let gmap=null, gmarker=null, svp=null, unlocked=false, geoTimer=null;
  const latI=$('#lat'), lngI=$('#lng'), ubicI=$('#ubicacion');
  const lockBtn = $('#map-lock-btn'), lockState=$('#map-lock-state');
  const defaultPos = {lat:18.4861, lng:-69.9312};

  function setLock(stateUnlocked){
    unlocked = stateUnlocked;
    if (gmarker) gmarker.setDraggable(unlocked);
    lockBtn.innerHTML = unlocked ? '<i class="fas fa-lock-open"></i><span class="ml-2 hidden sm:inline">Bloquear</span>' : '<i class="fas fa-lock"></i><span class="ml-2 hidden sm:inline">Editar ubicación</span>';
    lockState.textContent = unlocked ? 'Mapa editable' : 'Mapa bloqueado';
    lockState.className = 'map-lock-badge ' + (unlocked?'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300':'');
  }

  function updateStreetView(lat,lng){
    if (!svp) return;
    const pov = {heading: 235, pitch: 10};
    svp.setPosition({lat,lng});
    svp.setPov(pov);
    $('#street-view-placeholder')?.classList.add('hidden');
  }

  async function reverseGeocode(lat,lng){
    $('#address-loader')?.classList.remove('hidden');
    try{
      const geocoder = new google.maps.Geocoder();
      const {results} = await geocoder.geocode({location:{lat,lng}});
      if (results && results[0]) ubicI.value = results[0].formatted_address;
    }catch(_){/* noop */}finally{$('#address-loader')?.classList.add('hidden');}
  }

  function syncAll(lat,lng,pan=true){
    latI.value = Number(lat).toFixed(6);
    lngI.value = Number(lng).toFixed(6);
    if (gmarker) gmarker.setPosition({lat,lng});
    if (pan && gmap) gmap.panTo({lat,lng});
    updateStreetView(lat,lng);
    clearTimeout(geoTimer); geoTimer=setTimeout(()=>reverseGeocode(lat,lng), 600);
  }

  function findProvinciaIdByName(nombreLike) {
    const t = norm(nombreLike);
    const hit = PROVINCIAS.find(p => norm(p.nombre) === t) || PROVINCIAS.find(p => norm(p.nombre).startsWith(t));
    return hit?.id || null;
  }

  function setProvinciaFromAddressComponents(components) {
    const compAL1 = (components||[]).find(c => (c.types||[]).includes('administrative_area_level_1'));
    const compAL2 = (components||[]).find(c => (c.types||[]).includes('administrative_area_level_2'));
    const cand = compAL1?.long_name || compAL2?.long_name;
    if (!cand) return;
    const pid = findProvinciaIdByName(cand);
    if (pid) {
      $('#provincia').value = String(pid);
      repoblarZonas();
    }
  }

  function setupPlaceAutocomplete() {
    if (!window.google?.maps?.places) return;
    const input = $('#ubicacion');
    const autocomplete = new google.maps.places.Autocomplete(input, {
      componentRestrictions: { country: 'do' },
      fields: ['formatted_address','geometry','address_components'],
    });
    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place || !place.geometry) return;
      const lat = place.geometry.location.lat();
      const lng = place.geometry.location.lng();
      syncAll(lat, lng);
      if (place.formatted_address) $('#ubicacion').value = place.formatted_address;
      setProvinciaFromAddressComponents(place.address_components || []);
    });
  }

  window.initGMap = function(){
    if (!GMAPS_KEY) return;
    gmap = new google.maps.Map($('#map'), {center: defaultPos, zoom: 14, gestureHandling: 'greedy', disableDoubleClickZoom: !unlocked});
    gmarker = new google.maps.Marker({position: defaultPos, map: gmap, draggable: false});
    svp = new google.maps.StreetViewPanorama($('#street-view'), {position: defaultPos, pov:{heading:235,pitch:10}, visible:true});
    gmap.setStreetView(svp);

    gmarker.addListener('dragend', ()=>{ const p=gmarker.getPosition(); syncAll(p.lat(), p.lng()); });
    gmap.addListener('dblclick', (e)=>{ if(!unlocked) return; const p=e.latLng; syncAll(p.lat(), p.lng()); });

    setLock(false);
    syncAll(defaultPos.lat, defaultPos.lng);
    setupPlaceAutocomplete();
  };

  lockBtn?.addEventListener('click', ()=>setLock(!unlocked));
  $('#lat')?.addEventListener('input',()=>{ const lat=parseFloat($('#lat').value), lng=parseFloat($('#lng').value); if(!isNaN(lat)&&!isNaN(lng)) syncAll(lat,lng,false); });
  $('#lng')?.addEventListener('input',()=>{ const lat=parseFloat($('#lat').value), lng=parseFloat($('#lng').value); if(!isNaN(lat)&&!isNaN(lng)) syncAll(lat,lng,false); });

  // ---- IA helpers (documental; key externa si la usas) ----
  async function generateText(systemPrompt,userQuery){
    const apiKey=""; // coloca tu key si vas a usar Gemini
    if (!apiKey) return "";
    const apiUrl=`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=${apiKey}`;
    const payload={systemInstruction:{parts:[{text:systemPrompt}]},contents:[{parts:[{text:userQuery}]}]};
    const r=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    if(!r.ok) throw new Error('API request failed'); const j=await r.json();
    return j?.candidates?.[0]?.content?.parts?.[0]?.text || "";
  }
  $('#ai-suggest-btn')?.addEventListener('click',async()=>{
    const nombre=$('#nombre').value, prov=$('#provincia').value, zona=$('#zona').value, ubi=$('#ubicacion').value;
    if(!nombre||!ubi||!prov||!zona){ alert('Completa Nombre, Provincia, Zona y Ubicación.'); return; }
    const btn=$('#ai-suggest-btn'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Generando…';
    try{
      const sys="Experto en marketing OOH. 2-3 párrafos cortos, comerciales, en español.";
      const usr=`Nombre:${nombre}\nProvincia:${prov}\nZona:${zona}\nDirección:${ubi}`;
      const txt=await generateText(sys,usr); $('#descripcion').value=(txt||'').trim();
    }catch(_){ } finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-magic"></i> Sugerir'; }
  });
  $('#ai-suggest-name-btn')?.addEventListener('click',async()=>{
    const ubi=$('#ubicacion').value, prov=$('#provincia').value;
    if(!ubi||!prov){ alert('Completa Ubicación y Provincia.'); return; }
    const btn=$('#ai-suggest-name-btn'); btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    try{
      const sys="Creativo. Devuelve SOLO un nombre corto en español.";
      const usr=`Ubicación: ${ubi}\nProvincia: ${prov}`;
      const txt=await generateText(sys,usr); $('#nombre').value=(txt||'').trim();
    }catch(_){ } finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-wand-magic-sparkles"></i>'; }
  });

  // ---- Envío real: upload -> create -> media principal -> ADS opcional ----
  document.addEventListener('DOMContentLoaded', async () => {
    try { await Promise.all([loadProvincias(), loadZonas()]); } catch(_) {}
  });

  $('#form-valla')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const f = e.currentTarget;

    // Validaciones mínimas
    const req = (id,msg) => { const v = (f.querySelector('#'+id)?.value || '').trim(); if(!v){ alert(msg); throw new Error('valid'); } };
    try {
      req('nombre','Falta el nombre de la valla.');
      req('provincia','Selecciona una provincia.');
      req('ubicacion','Falta la dirección.');
      const latN = parseFloat($('#lat').value), lngN = parseFloat($('#lng').value);
      if (Number.isNaN(latN) || Number.isNaN(lngN)) { alert('Latitud/Longitud inválidas.'); throw new Error('valid'); }
    } catch(_) { return; }

    const file = $('#image-upload')?.files?.[0] || null;

    let imageUrl = null;
    if (file) {
      const fd = new FormData();
      fd.append('file', file);
      const up = await fetch('/console/vallas/ajax/upload.php', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF}, body:fd});
      const uj = await up.json();
      if (!uj.ok) { alert(uj.error||'Error al subir imagen'); return; }
      imageUrl = uj.url;
    }

    // payload create
    const payload = new URLSearchParams();
    const val = (id)=> (f.querySelector('#'+id)?.value || '').trim();
    const on  = (id)=> f.querySelector('#'+id)?.checked ? '1':'0';

    payload.set('tipo', val('tipo-valla') || 'impresa');
    payload.set('nombre', val('nombre'));
    payload.set('provincia_id', val('provincia'));
    payload.set('ubicacion', val('ubicacion'));
    payload.set('lat', val('lat'));
    payload.set('lng', val('lng'));
    payload.set('medida', val('medida'));
    payload.set('precio', val('precio'));
    payload.set('zona', val('zona'));
    payload.set('descripcion', val('descripcion'));
    payload.set('audiencia_mensual', val('audiencia_mensual'));
    payload.set('spot_time_seg', val('spot_time_seg'));
    payload.set('url_stream_pantalla', val('url_stream_pantalla'));
    payload.set('url_stream_trafico', val('url_stream_trafico'));
    payload.set('mostrar_precio_cliente', on('toggle-precio'));
    payload.set('estado_valla', on('toggle-estado'));
    payload.set('visible_publico', on('toggle-visible'));
    payload.set('disponible', '1');
    payload.set('numero_licencia', val('numero_licencia'));
    payload.set('fecha_vencimiento', val('fecha_vencimiento'));

    const cr = await fetch('/console/vallas/ajax/create.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF},
      body: payload.toString()
    });
    const cj = await cr.json();
    if (!cj.ok) { alert(cj.error || 'Error al crear valla'); return; }
    const vallaId = cj.valla_id;

    if (imageUrl) {
      await fetch('/console/vallas/ajax/media_set_principal.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF},
        body: new URLSearchParams({valla_id:String(vallaId), url:imageUrl})
      }).then(r=>r.json()).catch(()=>null);
    }

    if ($('#toggle-ads')?.checked) {
      const a1 = val('ads-start'), a2=val('ads-end');
      if (a1 && a2) {
        await fetch('/console/vallas/ajax/destacados_create.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF':CSRF},
          body: new URLSearchParams({valla_id:String(vallaId), fecha_inicio:a1, fecha_fin:a2})
        }).then(r=>r.json()).catch(()=>null);
      }
    }

    location.href = '/console/vallas/?created='+encodeURIComponent(vallaId);
  });
})();

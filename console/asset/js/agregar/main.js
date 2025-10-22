// /console/asset/js/agregar/main.js
(function () {
  'use strict';

  /* ---------------- UI base ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    document.getElementById('sidebar-toggle-desktop')?.addEventListener('click', () => body.classList.toggle('sidebar-collapsed'));

    const themeToggleBtn = document.getElementById('theme-toggle');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');
    const applyTheme = (t) => {
      if (t === 'dark') { document.documentElement.classList.add('dark'); darkIcon?.classList.remove('hidden'); lightIcon?.classList.add('hidden'); }
      else { document.documentElement.classList.remove('dark'); darkIcon?.classList.add('hidden'); lightIcon?.classList.remove('hidden'); }
    };
    const saved = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
    themeToggleBtn?.addEventListener('click', () => { const nt = document.documentElement.classList.contains('dark') ? 'light' : 'dark'; localStorage.setItem('theme', nt); applyTheme(nt); });
  });

  function notify(msg, type='success') {
    const n = document.getElementById('notification');
    const m = document.getElementById('notification-message');
    if (!n || !m) return;
    m.textContent = msg;
    n.classList.remove('bg-green-500','bg-red-500');
    n.classList.add(type==='success'?'bg-green-500':'bg-red-500');
    n.classList.remove('hidden','translate-x-full');
    setTimeout(()=>{ n.classList.add('translate-x-full'); setTimeout(()=>n.classList.add('hidden'),300); }, 4000);
  }
  window.notify = notify; // <-- exporta notify a global

  /* --- CSRF helper global --- */
  function getCSRF(){
    return document.querySelector('meta[name="csrf"]')?.content
        || document.getElementById('csrf')?.value
        || '';
  }

  /* ---------------- Datos (provincias, zonas) ---------------- */
  async function loadProvinces() {
    try {
      const r = await fetch('/console/vallas/agregar/ajax/google-mapas.php?action=provincias', {cache:'no-store', credentials:'same-origin'});
      const j = await r.json();
      const sel = document.getElementById('provincia');
      if (!sel) return;
      sel.innerHTML = '<option value="">Seleccione una provincia</option>';
      if (j.ok && Array.isArray(j.provincias)) {
        j.provincias.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id || p.nombre;
          opt.textContent = p.nombre;
          sel.appendChild(opt);
        });
      } else {
        ['Distrito Nacional','Santo Domingo','Santiago','La Romana','La Altagracia','Puerto Plata'].forEach((n,i)=>{
          const opt=document.createElement('option'); opt.value=String(i+1); opt.textContent=n; sel.appendChild(opt);
        });
      }
    } catch {}
  }

  async function fetchZones() {
    try {
      const r = await fetch('/console/vallas/agregar/ajax/google-mapas.php?action=zones', {cache:'no-store', credentials:'same-origin'});
      const j = await r.json();
      return j.ok && Array.isArray(j.zones) ? j.zones : [];
    } catch { return []; }
  }

  function setupZonesAutocomplete(zones) {
    const input = document.getElementById('zona');
    const results = document.getElementById('zona-autocomplete-results');
    if (!input || !results) return;
    let t;

    const render = (items) => {
      results.innerHTML = '';
      if (!items.length) { results.classList.add('hidden'); return; }
      results.classList.remove('hidden');
      items.forEach(z => {
        const div = document.createElement('div');
        div.textContent = z;
        div.className = 'autocomplete-item';
        div.addEventListener('click', () => { input.value = z; results.classList.add('hidden'); });
        results.appendChild(div);
      });
    };

    input.addEventListener('input', () => {
      clearTimeout(t);
      const q = input.value.trim().toLowerCase();
      t = setTimeout(() => {
        if (!q) { results.classList.add('hidden'); return; }
        const filtered = zones.filter(z => z.toLowerCase().includes(q));
        render(filtered.slice(0, 12));
      }, 150);
    });

    document.getElementById('add-zone-btn')?.addEventListener('click', () => {
      const v = (input.value || '').trim();
      if (!v) { notify('Escribe un nombre de zona.', 'error'); return; }
      const exists = zones.some(z => z.toLowerCase() === v.toLowerCase());
      if (exists) { notify('La zona ya existe. No se permiten duplicados.', 'error'); return; }
      zones.push(v);
      zones.sort((a,b)=>a.localeCompare(b, 'es', {sensitivity:'base'}));
      notify('Zona lista para guardar con la valla.','success');
    });

    document.addEventListener('click', (e) => {
      if (!results.contains(e.target) && e.target !== input) results.classList.add('hidden');
    });
  }

  /* ---------------- Flatpickr + ocupación ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    if (window.flatpickr) {
      flatpickr("#ads-start", { dateFormat:"Y-m-d" });
      flatpickr("#ads-end", { dateFormat:"Y-m-d" });
      flatpickr("#fecha_vencimiento", { dateFormat:"Y-m-d" });
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    const cap = document.getElementById('capacidad_reservas');
    const occ = document.getElementById('slots_ocupados');
    const bar = document.getElementById('booking-progress-bar');
    const txt = document.getElementById('booking-percentage-text');
    if (!cap || !occ || !bar || !txt) return;
    function upd(){
      const c = parseInt(cap.value)||0;
      const o = Math.min(Math.max(parseInt(occ.value)||0,0), Math.max(c,0));
      const pct = c>0 ? (o/c)*100 : 0;
      bar.style.width = `${pct}%`;
      txt.textContent = c>0 ? `${pct.toFixed(0)}% Lleno. (Faltan ${c-o} slots)` : 'Define una capacidad válida.';
    }
    cap.addEventListener('input', upd); occ.addEventListener('input', upd); upd();
  });

  /* ---------------- Imagen: preview + upload ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    const dz = document.getElementById('image-drop-zone');
    const fi = document.getElementById('image-upload');
    const cont = document.getElementById('image-preview-container');
    const img = document.getElementById('image-preview');
    const fn = document.getElementById('image-filename');
    const fs = document.getElementById('image-filesize');
    const up = document.getElementById('upload-prompt');
    const hiddenUrl = document.getElementById('imagen_url');

    function preview(file){
      const r = new FileReader();
      r.onload = e => {
        img.src = e.target.result;
        fn.textContent = file.name;
        fs.textContent = `${(file.size/1024).toFixed(1)} KB`;
        cont.classList.remove('hidden'); up.classList.add('hidden');
      };
      r.readAsDataURL(file);
    }

    async function upload(file){
      const fd = new FormData();
      fd.append('image-upload', file);
      const csrf = getCSRF();
      if (csrf) fd.append('csrf', csrf);

      const btn = document.querySelector('button[type="submit"]');
      try{
        btn && (btn.disabled = true);
        const res = await fetch('/console/vallas/agregar/ajax/upload-imagen.php', {
          method:'POST',
          body: fd,
          credentials:'same-origin',
          cache:'no-store'
        });
        // soporta HTML por error del servidor
        let j=null;
        const ct = res.headers.get('content-type')||'';
        if (ct.includes('application/json')) { j = await res.json().catch(()=>null); }
        else { const t = await res.text(); try{ j = JSON.parse(t);}catch{ j=null; console.error('Upload RAW:', t);} }
        if (!res.ok || !j || !j.ok) {
          alert('Error al subir: ' + (j?.error || ('HTTP '+res.status)));
          throw new Error(j?.error || ('HTTP_'+res.status));
        }
        if (hiddenUrl) hiddenUrl.value = j.relpath || j.url || '';
        notify('Imagen subida','success');
      }catch(e){
        notify('Error al subir imagen','error');
        if (hiddenUrl) hiddenUrl.value = '';
        console.error('upload-imagen:', e);
      }finally{
        btn && (btn.disabled = false);
      }
    }

    function handle(file){ if (!file) return; preview(file); upload(file); }
    dz?.addEventListener('click', ()=>fi?.click());
    fi?.addEventListener('change', e => handle(e.target.files[0]));
    dz?.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragover'); });
    dz?.addEventListener('dragleave', ()=>dz.classList.remove('dragover'));
    dz?.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('dragover'); handle(e.dataTransfer.files[0]); });
  });

  /* ---------------- Google Maps + StreetView ---------------- */
  let map, marker, panorama;
  let isLocked = false;
  const defaultLat = 18.4861, defaultLng = -69.9312;

  function updateFields(lat, lng){
    const a=document.getElementById('lat'), b=document.getElementById('lng');
    if (a) a.value = Number(lat).toFixed(6);
    if (b) b.value = Number(lng).toFixed(6);
  }

  function updateStreetView(lat,lng){
    const svDiv = document.getElementById('street-view');
    const ph = document.getElementById('street-view-placeholder');
    if (!svDiv) return;
    if (!panorama) {
      panorama = new google.maps.StreetViewPanorama(svDiv, { position: {lat, lng}, pov:{heading:235, pitch:10}, visible:true });
      ph?.classList.add('hidden');
    } else {
      panorama.setPosition({lat,lng});
      ph?.classList.add('hidden');
    }
  }

  function reverseGeocode(lat,lng){
    const geocoder = new google.maps.Geocoder();
    const ubicacion = document.getElementById('ubicacion');
    if (!ubicacion) return;
    geocoder.geocode({ location:{lat,lng}}, (res, status)=>{
      if (status === 'OK' && res && res[0]) ubicacion.value = res[0].formatted_address || ubicacion.value;
    });
  }

  function geocodeAddress(text){
    return new Promise((resolve,reject)=>{
      if (!window.google || !google.maps) return reject(new Error('GMAPS_NOT_READY'));
      const gc = new google.maps.Geocoder();
      gc.geocode({ address: text, componentRestrictions:{ country: ['do'] } }, (res, status)=>{
        if (status === 'OK' && res && res[0] && res[0].geometry && res[0].geometry.location) {
          const loc = res[0].geometry.location;
          resolve({ lat: loc.lat(), lng: loc.lng(), formatted: res[0].formatted_address || text });
        } else {
          reject(new Error('GEOCODE_'+status));
        }
      });
    });
  }

  function setMarker(lat,lng,source){
    marker.setPosition({lat,lng});
    map.panTo({lat,lng});
    updateFields(lat,lng);
    updateStreetView(lat,lng);
    if (source!=='geocode') reverseGeocode(lat,lng);
  }

  function attachPlaces(){
    const inp = document.getElementById('ubicacion_search');
    if (!inp) return;
    const ac = new google.maps.places.Autocomplete(inp, {
      componentRestrictions:{country:['do']},
      fields:['geometry','formatted_address'],
    });
    ac.addListener('place_changed', ()=>{
      const p = ac.getPlace();
      if (p && p.geometry && p.geometry.location) {
        const lat = p.geometry.location.lat();
        const lng = p.geometry.location.lng();
        const t = document.getElementById('ubicacion');
        if (t) t.value = p.formatted_address || '';
        setMarker(lat,lng,'geocode');
      }
    });

    // geocodificar con Enter
    inp.addEventListener('keydown', async (ev)=>{
      if (ev.key === 'Enter') {
        ev.preventDefault();
        const q = inp.value.trim();
        if (!q) return;
        try{
          const r = await geocodeAddress(q);
          document.getElementById('ubicacion').value = r.formatted;
          setMarker(r.lat, r.lng, 'geocode');
        }catch{}
      }
    });
  }

  // geocodificar al salir del textarea si faltan coords
  document.getElementById('ubicacion')?.addEventListener('blur', async ()=>{
    const v = (document.getElementById('ubicacion').value||'').trim();
    const la = parseFloat(document.getElementById('lat')?.value||'');
    const ln = parseFloat(document.getElementById('lng')?.value||'');
    if (!v) return;
    if (Number.isFinite(la) && Number.isFinite(ln)) return;
    try{
      const r = await geocodeAddress(v);
      setMarker(r.lat, r.lng, 'geocode');
    }catch{}
  });

  function initMapWhenReady(){
    if (!window.google || !window.google.maps) { notify('Error Google Maps. Revisa key y facturación.', 'error'); return; }
    const latI = document.getElementById('lat');
    const lngI = document.getElementById('lng');
    const startLat = parseFloat(latI?.value)||defaultLat;
    const startLng = parseFloat(lngI?.value)||defaultLng;

    map = new google.maps.Map(document.getElementById('map'), {
      center:{lat:startLat, lng:startLng}, zoom:14,
      mapTypeControl:false, streetViewControl:false, fullscreenControl:false
    });

    marker = new google.maps.Marker({ position:{lat:startLat,lng:startLng}, map, draggable:true });

    marker.addListener('dragend', ()=>{
      const p = marker.getPosition(); setMarker(p.lat(), p.lng(), 'marker');
    });

    map.addListener('dblclick', (e)=>{
      if (isLocked) return;
      setMarker(e.latLng.lat(), e.latLng.lng(), 'map');
    });

    function updateFromInputs(){
      const la = parseFloat(latI.value), ln = parseFloat(lngI.value);
      if (!Number.isFinite(la) || !Number.isFinite(ln)) return;
      setMarker(la,ln,'inputs');
    }
    latI?.addEventListener('input', updateFromInputs);
    lngI?.addEventListener('input', updateFromInputs);

    const lockBtn = document.getElementById('lock-map-btn');
    lockBtn?.addEventListener('click', ()=>{
      isLocked = !isLocked;
      marker.setDraggable(!isLocked);
      map.setOptions({ draggable: !isLocked, disableDoubleClickZoom: isLocked });
      const i = lockBtn.querySelector('i');
      if (!i) return;
      if (isLocked){ i.classList.replace('fa-lock-open','fa-lock'); lockBtn.classList.replace('text-green-500','text-red-500'); }
      else { i.classList.replace('fa-lock','fa-lock-open'); lockBtn.classList.replace('text-red-500','text-green-500'); }
    });

    document.getElementById('fullscreen-map-btn')?.addEventListener('click', ()=>{
      const el = document.getElementById('map');
      if (!document.fullscreenElement) el.requestFullscreen?.();
      else document.exitFullscreen?.();
      setTimeout(()=>google.maps.event.trigger(map,'resize'), 350);
    });

    updateStreetView(startLat,startLng);
    attachPlaces();
    reverseGeocode(startLat,startLng);
  }

  /* ---------------- Sugerencias (API propia) ---------------- */
  async function apiSugerir(target, text, max=160, temperature=0.2) {
    const csrf = getCSRF();
    const payload = { text, target, max_tokens: max, temperature };
    try {
      if (csrf) {
        const r = await fetch('/api/openai/sugerencia/index.php', {
          method:'POST',
          headers:{ 'Content-Type':'application/json', 'X-CSRF': csrf },
          body: JSON.stringify(payload),
          credentials:'same-origin',
          cache:'no-store'
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'API');
        return Array.isArray(j.items) ? j.items : [];
      } else {
        const qs = new URLSearchParams({ text, target, max_tokens:String(max), temperature:String(temperature) });
        const r = await fetch('/api/openai/sugerencia/index.php?'+qs.toString(), {method:'GET', credentials:'same-origin', cache:'no-store'});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'API');
        return Array.isArray(j.items) ? j.items : [];
      }
    } catch {
      notify('Error al obtener sugerencias','error');
      return [];
    }
  }

  function ctx() {
    const nombre = document.getElementById('nombre')?.value?.trim() || '';
    const provinciaSel = document.getElementById('provincia');
    const provincia = provinciaSel && provinciaSel.selectedIndex>0 ? provinciaSel.options[provinciaSel.selectedIndex].text : '';
    const zona = document.getElementById('zona')?.value?.trim() || '';
    const ubicacion = document.getElementById('ubicacion')?.value?.trim() || '';
    const lat = document.getElementById('lat')?.value || '';
    const lng = document.getElementById('lng')?.value || '';
    return { nombre, provincia, zona, ubicacion, lat, lng };
  }

  // Descripción + SEO
  document.getElementById('ai-suggest-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('ai-suggest-btn');
    const d = document.getElementById('descripcion');
    const k = document.getElementById('keywords_seo');
    const { nombre, provincia, zona, ubicacion } = ctx();
    if (!nombre || !ubicacion || !provincia || !zona) { notify('Completa Nombre, Provincia, Zona y Ubicación.', 'error'); return; }
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';

    const base = `Valla: ${nombre}\nProvincia: ${provincia}\nZona: ${zona}\nDirección: ${ubicacion}`;
    const descs = await apiSugerir('descripcion', base, 220, 0.25);
    if (descs[0]) d.value = descs[0];

    const kws = await apiSugerir('generico', base + '\nNecesito palabras clave SEO separadas por comas.', 120, 0.2);
    if (kws.length) k.value = kws.join(', ');

    if (!document.getElementById('nombre').value.trim()) {
      const ts = await apiSugerir('titulo', base, 90, 0.2);
      if (ts[0]) document.getElementById('nombre').value = ts[0].replace(/[."]/g,'');
    }

    btn.disabled = false; btn.innerHTML = '<i class="fas fa-magic"></i> Sugerir con IA';
  });

  // Título
  document.getElementById('ai-suggest-name-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('ai-suggest-name-btn');
    const { provincia, ubicacion, zona } = ctx();
    if (!ubicacion || !provincia) { notify('Completa Ubicación y Provincia.','error'); return; }
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true;

    const text = `Ubicación: ${ubicacion}\nProvincia: ${provincia}\nZona: ${zona || '(no definida)'}\nNecesito un título corto y comercial.`;
    const items = await apiSugerir('titulo', text, 90, 0.2);
    if (items[0]) document.getElementById('nombre').value = items[0].trim().replace(/[."]/g,'');

    btn.innerHTML = '<i class="fas fa-wand-magic-sparkles ai-sparkle"></i>'; btn.disabled = false;
  });

  // Zona
  document.getElementById('ai-suggest-zone-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('ai-suggest-zone-btn');
    const { ubicacion, provincia } = ctx();
    if (!ubicacion || !provincia) { notify('Completa Ubicación y Provincia.','error'); return; }
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true;

    const zitems = await apiSugerir('zona', `Dirección: ${ubicacion}\nProvincia: ${provincia}`, 80, 0.1);
    if (zitems[0]) {
      const limpio = zitems[0].trim().replace(/[."]/g,'');
      const input = document.getElementById('zona');
      const current = (input.value||'').trim().toLowerCase();
      if (current && current === limpio.toLowerCase()) notify('La zona sugerida coincide con la actual.','success');
      else { input.value = limpio; notify('Zona sugerida aplicada.','success'); }
    }

    btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i>'; btn.disabled = false;
  });

  /* ---------------- Init ---------------- */
  document.addEventListener('DOMContentLoaded', async ()=>{
    await loadProvinces();
    const zones = await fetchZones();
    setupZonesAutocomplete(zones);
  });

  window.addEventListener('load', ()=>{
    const i = setInterval(()=>{ if (window.google && window.google.maps){ clearInterval(i); initMapWhenReady(); } }, 200);
    setTimeout(()=>clearInterval(i), 10000);
  });

  // ADS toggle
  document.addEventListener('DOMContentLoaded', () => {
    const t = document.getElementById('toggle-ads');
    const c = document.getElementById('ads-details-container');
    t?.addEventListener('change', ()=> c.classList.toggle('hidden', !t.checked));
  });

  /* ---------------- Envío: validación y normalización ---------------- */
  async function ensureLocationBeforeSubmit(fd){
    let ubic = (document.getElementById('ubicacion')?.value||'').trim();
    let la = parseFloat(document.getElementById('lat')?.value||'');
    let ln = parseFloat(document.getElementById('lng')?.value||'');

    if (!Number.isFinite(la) || !Number.isFinite(ln)) {
      if (ubic) {
        try{
          const r = await geocodeAddress(ubic);
          la = r.lat; ln = r.lng; ubic = r.formatted;
          updateFields(la, ln);
          document.getElementById('ubicacion').value = ubic;
        }catch{
          throw new Error('No se pudo geocodificar la dirección.');
        }
      } else {
        throw new Error('Faltan Ubicación, Latitud o Longitud.');
      }
    }

    fd.set('lat', String(la));
    fd.set('lng', String(ln));
    fd.set('ln', String(ln)); // compat
    fd.set('ubicacion', ubic);
  }

  // NUEVO: validar y anexar ADS
  function validateAndAppendAds(fd){
    const t = document.getElementById('toggle-ads');
    const on = !!t && t.checked;
    fd.set('is_ads', on ? '1' : '0');
    if (!on) return;

    const s = (document.getElementById('ads-start')?.value||'').trim();
    const e = (document.getElementById('ads-end')?.value||'').trim();

    if (!s || !e) throw new Error('Completa fechas de ADS.');
    const sd = new Date(s+'T00:00:00');
    const ed = new Date(e+'T00:00:00');
    if (!(sd instanceof Date) || isNaN(sd) || !(ed instanceof Date) || isNaN(ed) || ed < sd) {
      throw new Error('Rango de fechas de ADS inválido.');
    }
    fd.set('ads_start', s);
    fd.set('ads_end', e);
  }

  // Envío REAL a PHP
  document.addEventListener('DOMContentLoaded', ()=>{
    const f = document.getElementById('create-valla-form');
    f?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const b = f.querySelector('button[type="submit"]');
      const fd = new FormData(f);
      const csrf = getCSRF();
      try{
        await ensureLocationBeforeSubmit(fd);
        validateAndAppendAds(fd); // <-- NUEVO

        b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        const r = await fetch('/console/vallas/agregar/ajax/guardar.php', {
          method:'POST',
          headers: csrf ? { 'X-CSRF': csrf } : {},
          body: fd,
          credentials:'same-origin',
          cache:'no-store'
        });

        const j = await r.json().catch(()=>null);
        if (!r.ok || !j || !j.ok) {
          if (j && j.error === 'VALIDATION' && j.fields) {
            console.error('Campos con error:', j.fields);
            alert('Campos inválidos: ' + Object.keys(j.fields).join(', '));
          } else if (j && j.error) {
            alert('Error: ' + j.error);
          } else {
            alert('Error HTTP ' + r.status);
          }
          throw new Error(j?.error || ('HTTP_'+r.status));
        }

        notify('Valla guardada','success');
        const goAds = document.getElementById('toggle-ads')?.checked === true; // <-- NUEVO
        if (goAds) {
          window.location.href = '/console/ads/?from=valla&id=' + j.id;       // <-- NUEVO
        } else {
          window.location.href = '/console/vallas/?saved=1&id=' + j.id;
        }
      }catch(err){
        notify('Error al guardar','error');
        console.error(err);
      }finally{
        b.disabled = false; b.textContent = 'Guardar Valla';
      }
    });
  });

})();

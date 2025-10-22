// /console/asset/js/mapa/mapa.js
(function(){
  'use strict';
  const $  = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));
  const num= v => Number((v ?? '').toString().replace(',', '.'));
  const esc= s => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const csrfMeta = () => $('meta[name="csrf"]')?.content || '';

  const jGET = async (url, params = {}) => {
    const u = new URL(url, location.origin);
    Object.entries(params).forEach(([k,v])=>{ if(v!=='' && v!=null) u.searchParams.set(k,v); });
    const r = await fetch(u, {credentials:'same-origin'});
    return r.ok ? r.json() : { ok:false };
  };

  // jPOST robusto: evita "Error de red" y muestra cuerpo no-JSON
  const jPOST = async (url, data = {}) => {
    try {
      const r = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfMeta(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch {
        return { ok: false, msg: 'Respuesta no JSON', status: r.status, body: text };
      }
    } catch (e) {
      return { ok: false, msg: 'network', error: (e && e.message) ? e.message : String(e) };
    }
  };

  // Google helpers
  async function loadScript(src){return new Promise((ok,ko)=>{const s=document.createElement('script');s.src=src;s.async=true;s.onload=ok;s.onerror=ko;document.head.appendChild(s);});}
  async function ensureGoogle(key){
    if(!window.google || !window.google.maps){
      await loadScript(`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`);
    }
    if(!(L.gridLayer && L.gridLayer.googleMutant)){
      await loadScript('https://unpkg.com/leaflet.gridlayer.googlemutant@0.14.0/dist/Leaflet.GoogleMutant.js');
    }
  }
  const gType = code => {
    const s = (code||'').split('.').pop();
    return ({roadmap:'roadmap', satellite:'satellite', hybrid:'hybrid', terrain:'terrain'})[s] || 'roadmap';
  };

  const CFG = window.MAP_CFG || {};
  let SETTINGS = { provider_code:'osm', style_code:'osm.standard', lat: CFG.fallback?.lat ?? 18.486058, lng: CFG.fallback?.lng ?? -69.931212, zoom: CFG.fallback?.zoom ?? 12 };
  let TOKEN = ''; // Google API Key
  let PROVIDERS = [{code:'osm',name:'OpenStreetMap'}];
  let STYLES = [{
    provider_code:'osm',
    style_code:'osm.standard',
    style_name:'OSM Standard',
    tile_url:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    subdomains:'abc', attribution:'&copy; OpenStreetMap contributors',
    preview_image:'https://placehold.co/400x300/e2e8f0/4a5568?text=OSM+Standard', is_default:1
  }];

  const el = {
    map:      $('#map'),
    provider: $('#map-provider'),
    lat:      $('#lat-input'),
    lng:      $('#lng-input'),
    zoom:     $('#zoom-input'),
    key:      $('#gmaps-key'),
    gallery:  $('#styles-gallery'),
    btnSave:  $('#btn-guardar')
  };

  async function loadOptions(){
    const url = CFG.endpoints?.opciones; if(!url) return;
    try{
      const j = await jGET(url);
      if(j?.ok){
        if(Array.isArray(j.providers) && j.providers.length) PROVIDERS = j.providers;
        if(Array.isArray(j.styles) && j.styles.length) STYLES = j.styles;
        if(j.settings){
          SETTINGS = {
            provider_code: j.settings.provider_code ?? SETTINGS.provider_code,
            style_code:    j.settings.style_code ?? SETTINGS.style_code,
            lat:           Number(j.settings.lat ?? SETTINGS.lat),
            lng:           Number(j.settings.lng ?? SETTINGS.lng),
            zoom:          parseInt(j.settings.zoom ?? SETTINGS.zoom, 10)
          };
          TOKEN = j.settings.token || '';
        }
      }
    }catch(_e){}
  }

  function renderProviders(){
    if(!el.provider) return;
    el.provider.innerHTML = PROVIDERS.map(p =>
      `<option value="${esc(p.code)}"${p.code===SETTINGS.provider_code?' selected':''}>${esc(p.name)}</option>`
    ).join('');
  }

  function renderStyles(){
    if(!el.gallery) return;
    el.gallery.innerHTML = STYLES.map(st => {
      const checked = st.style_code === SETTINGS.style_code ? 'checked' : '';
      return `
        <label class="map-style-card relative block">
          <input type="radio" name="map-style" value="${esc(st.style_code)}" ${checked}>
          <div class="ms-body overflow-hidden">
            <img src="${esc(st.preview_image || '')}" alt="${esc(st.style_name)}" class="w-full h-32 object-cover"
                 onerror="this.onerror=null;this.src='https://dev.vallasled.com/console/asset/mapas/Googleroadmap.png'">
            <div class="p-3">
              <h4 class="font-semibold text-sm">${esc(st.style_name)}</h4>
              <p class="text-xs text-sec">${esc((st.provider_code||'').toUpperCase())}</p>
            </div>
          </div>
        </label>`;
    }).join('');
  }

  let map, marker, currentTileLayer;
  const styleByCode = c => STYLES.find(s => s.style_code === c);
  const stylesByProv= p => STYLES.filter(s => s.provider_code === p);

  async function applyStyle(code){
    const st = styleByCode(code); if(!st) return;
    if(currentTileLayer){ map.removeLayer(currentTileLayer); currentTileLayer=null; }

    if(st.provider_code === 'google'){
      if(!TOKEN){ alert('Falta Google API Key'); return; }
      await ensureGoogle(TOKEN);
      currentTileLayer = L.gridLayer.googleMutant({ type: gType(st.style_code) }).addTo(map);
      if(el.provider && el.provider.value !== 'google') el.provider.value = 'google';
      return;
    }

    const opts = { maxZoom:19, attribution: st.attribution || '' };
    if(st.subdomains) opts.subdomains = st.subdomains;
    currentTileLayer = L.tileLayer(st.tile_url, opts).addTo(map);
    if(st.provider_code && el.provider && el.provider.value !== st.provider_code){
      el.provider.value = st.provider_code;
    }
  }

  function recenterFromInputs(){
    const lat = num(el.lat?.value), lng = num(el.lng?.value);
    let z = parseInt(el.zoom?.value || '12', 10); if(!Number.isFinite(z)) z = 12; z = Math.max(1, Math.min(19, z));
    if(Number.isFinite(lat) && Number.isFinite(lng)){ map.setView([lat,lng], z); marker.setLatLng([lat,lng]); }
  }

  function bindInputs(){
    el.lat?.addEventListener('change', recenterFromInputs);
    el.lng?.addEventListener('change', recenterFromInputs);
    el.zoom?.addEventListener('change', recenterFromInputs);

    el.provider?.addEventListener('change', () => {
      const list = stylesByProv(el.provider.value);
      if(list.length){
        const next = list[0].style_code;
        $$('input[name="map-style"]', el.gallery).forEach(r => r.checked = (r.value === next));
        applyStyle(next);
      }
    });

    el.gallery?.addEventListener('change', (e) => {
      const t = e.target;
      if(t && t.name === 'map-style') applyStyle(t.value);
    });
  }

  function fillInputs(){
    el.lat && (el.lat.value  = String(SETTINGS.lat ?? 18.486058));
    el.lng && (el.lng.value  = String(SETTINGS.lng ?? -69.931212));
    el.zoom&& (el.zoom.value = String(SETTINGS.zoom ?? 12));
    el.key && (el.key.value  = TOKEN || '');
  }

  function initMap(){
    map = L.map('map').setView([SETTINGS.lat, SETTINGS.lng], SETTINGS.zoom);
    marker = L.marker([SETTINGS.lat, SETTINGS.lng], { draggable:true }).addTo(map);
    marker.on('dragend', () => {
      const { lat, lng } = marker.getLatLng();
      if(el.lat) el.lat.value = lat.toFixed(6);
      if(el.lng) el.lng.value = lng.toFixed(6);
    });
    applyStyle(SETTINGS.style_code);
  }

  function bindSave(){
    el.btnSave?.addEventListener('click', async () => {
      const selected = $('input[name="map-style"]:checked', el.gallery);
      const payload = {
        csrf: CFG.csrf || csrfMeta(),
        provider_code: el.provider?.value || SETTINGS.provider_code,
        style_code: selected ? selected.value : SETTINGS.style_code,
        lat: el.lat?.value ?? SETTINGS.lat,
        lng: el.lng?.value ?? SETTINGS.lng,
        zoom: el.zoom?.value ?? SETTINGS.zoom,
        token: el.key?.value ?? ''
      };
      const url = CFG.endpoints?.guardar; if(!url){ alert('Endpoint no disponible'); return; }
      const j = await jPOST(url, payload);
      if(j?.ok){ TOKEN = payload.token || TOKEN; alert(j.msg || 'Guardado'); }
      else { alert(j?.msg || 'No se pudo guardar'); }
    });
  }

  window.addEventListener('DOMContentLoaded', async () => {
    await loadOptions();
    renderProviders();
    renderStyles();
    fillInputs();
    initMap();
    bindInputs();
    bindSave();
  });
})();


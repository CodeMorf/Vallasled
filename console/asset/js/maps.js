// /console/asset/js/maps.js
(function(){
  const qs=(s,el=document)=>el.querySelector(s);
  const qid=(id)=>document.getElementById(id);

  let gLoaded=false, gLoading=false, gKey='';
  function loadScriptOnce(src){
    return new Promise((resolve,reject)=>{
      if (gLoaded) return resolve();
      if (gLoading) { window.__gcb__ = ()=>resolve(); return; }
      gLoading=true;
      const s=document.createElement('script');
      s.src=src; s.async=true; s.defer=true;
      s.onerror=()=>reject(new Error('GMAPS_LOAD_ERROR'));
      window.__gcb__=()=>{ gLoaded=true; resolve(); };
      document.head.appendChild(s);
    });
  }

  async function fetchKey(){
    if (gKey) return gKey;
    try{
      const r = await fetch('/console/vallas/ajax/gmaps-key.php', {headers:{'X-Requested-With':'XMLHttpRequest'}});
      const j = await r.json();
      if (j.ok && j.key) { gKey=j.key; return gKey; }
    }catch(_){}
    throw new Error('NO_GMAPS_KEY');
  }

  async function ensureGmaps(){
    const key = await fetchKey();
    const url = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=__gcb__`;
    await loadScriptOnce(url);
  }

  // Public init for "Crear Valla" form
  window.initVallaMap = async function(opts){
    const {
      mapId='map',
      svId='street-view-container',
      latId='lat', lngId='lng',
      addrId='ubicacion',
      unlockBtnId='fullscreen-map-btn', // reuse button as unlock if quieres otro id cámbialo
      lockInitially=true
    } = (opts||{});

    await ensureGmaps();

    const latIn=qid(latId), lngIn=qid(lngId), addrIn=qid(addrId);
    const mapEl=qid(mapId), svEl=qid(svId), unlockBtn=qid(unlockBtnId);

    let lat = parseFloat(latIn?.value)||18.4861;
    let lng = parseFloat(lngIn?.value)||-69.9312;

    const map = new google.maps.Map(mapEl, {center:{lat,lng}, zoom: 15, gestureHandling: 'greedy', mapTypeControl:false, streetViewControl:false});
    const marker = new google.maps.Marker({position:{lat,lng}, map, draggable: !lockInitially});
    const geocoder = new google.maps.Geocoder();
    const sv = new google.maps.StreetViewPanorama(svEl, {
      position:{lat,lng}, pov:{heading:235, pitch:10}, zoom:1, addressControl:false, clickToGo:true, linksControl:true
    });
    map.setStreetView(sv);

    // Lock overlay behavior
    let locked = !!lockInitially;
    if (unlockBtn){
      unlockBtn.title = locked?'Desbloquear mapa':'Bloquear mapa';
      unlockBtn.addEventListener('click', ()=>{
        locked=!locked;
        marker.setDraggable(!locked);
        unlockBtn.classList.toggle('ring-2', !locked);
        unlockBtn.title = locked?'Desbloquear mapa':'Bloquear mapa';
      });
    }

    function updateFields(p){
      latIn && (latIn.value = p.lat().toFixed(6));
      lngIn && (lngIn.value = p.lng().toFixed(6));
      sv.setPosition({lat:p.lat(), lng:p.lng()});
      // Geocode
      if (addrIn){
        geocoder.geocode({location:{lat:p.lat(), lng:p.lng()}}, (res, status)=>{
          if (status==='OK' && res && res[0]) addrIn.value = res[0].formatted_address;
        });
      }
    }

    marker.addListener('dragend', ()=>updateFields(marker.getPosition()));
    map.addListener('dblclick', (e)=>{ if(!locked){ marker.setPosition(e.latLng); map.panTo(e.latLng); updateFields(e.latLng); }});

    // If user types coords
    function tryInputs(){
      const a=parseFloat(latIn?.value), b=parseFloat(lngIn?.value);
      if (!isNaN(a)&&!isNaN(b)){
        const p=new google.maps.LatLng(a,b);
        marker.setPosition(p); map.panTo(p); updateFields(p);
      }
    }
    latIn && latIn.addEventListener('change', tryInputs);
    lngIn && lngIn.addEventListener('change', tryInputs);
  };

})();

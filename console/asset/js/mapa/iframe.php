<?php
// /api/mapa/iframe.php
declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

/* Defaults RD */
$S = ['lat'=>18.486058,'lng'=>-69.931212,'zoom'=>12,'token'=>''];
$provider = 'carto';
$style    = 'carto.dark_matter';

/* Lee settings admin */
if (isset($conn) && $conn instanceof mysqli) {
  try {
    if ($rs = $conn->query("SELECT provider_code,style_code,lat,lng,zoom,token FROM map_settings ORDER BY id DESC LIMIT 1")) {
      if ($row = $rs->fetch_assoc()) {
        $provider = $row['provider_code'] ?: $provider;
        $style    = $row['style_code']    ?: $style;
        if (is_numeric($row['lat']))  $S['lat']  = (float)$row['lat'];
        if (is_numeric($row['lng']))  $S['lng']  = (float)$row['lng'];
        if (is_numeric($row['zoom'])) $S['zoom'] = (int)$row['zoom'];
        $S['token'] = (string)($row['token'] ?? '');
      }
    }
  } catch (Throwable $e) {}
}

/* QS permitidos (no cambian proveedor/estilo) */
$q=$_GET;
if (isset($q['lat']))  $S['lat']  = (float)str_replace(',', '.', (string)$q['lat']);
if (isset($q['lng']))  $S['lng']  = (float)str_replace(',', '.', (string)$q['lng']);
if (isset($q['zoom'])) $S['zoom'] = max(2,(int)$q['zoom']);
if (!empty($q['key'])) $S['token']=(string)$q['key'];
$SRC = !empty($q['src']) ? (string)$q['src'] : '/api/buscador.php';

/* Derivar Google type */
$isGoogle = (strtolower($provider)==='google' || strncmp($style,'google.',7)===0);
$gType = ltrim(strrchr($style,'.')?:'.roadmap','.');
if (!in_array($gType,['roadmap','satellite','hybrid','terrain'],true)) $gType='roadmap';

/* Tiles oscuros no-Google */
$tileUrl = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
$subdom  = 'abcd';
$attrib  = '&copy; OSM contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mapa</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin>
<style>
  :root{--bg:#0b1220;--text:#e5e7eb}
  html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Helvetica,Arial,sans-serif}
  #map{position:absolute;inset:0}
  .leaflet-popup-content-wrapper{background:#0f172a;color:#e5e7eb;border-radius:.5rem}
  .leaflet-popup-tip{background:#0f172a}
  .popup{display:grid;grid-template-columns:96px 1fr;gap:.75rem;align-items:start;max-width:340px}
  .popup img{width:96px;height:72px;object-fit:cover;border-radius:.35rem;border:1px solid rgba(255,255,255,.08)}
  .popup h3{margin:.1rem 0;font-size:1rem;line-height:1.3}
  .popup .meta{font-size:.82rem;color:#cbd5e1}
  .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .45rem;border-radius:9999px;font-weight:700;font-size:.72rem;border:1px solid rgba(255,255,255,.12)}
  .b-led{background:rgba(59,130,246,.15);color:#93c5fd}
  .b-imp{background:rgba(16,185,129,.15);color:#86efac}
  .b-mov{background:rgba(245,158,11,.15);color:#fde68a}
  .b-veh{background:rgba(244,63,94,.15);color:#fecdd3}
  .live{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.45);color:#fecaca}
  .live .dot{width:.55rem;height:.55rem;border-radius:9999px;background:#ef4444;position:relative}
  .live .dot::after{content:"";position:absolute;inset:-6px;border-radius:9999px;border:2px solid #ef4444;opacity:.8;animation:pulse 1.2s ease-out infinite}
  @keyframes pulse{0%{transform:scale(.6);opacity:.9}70%{transform:scale(1.3);opacity:.1}100%{transform:scale(1.5);opacity:0}}
</style>
</head>
<body>
<div id="map" role="region" aria-label="Mapa de vallas"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
<?php if ($isGoogle): ?>
<script src="https://unpkg.com/leaflet.gridlayer.googlemutant@0.14.0/dist/Leaflet.GoogleMutant.js"></script>
<?php endif; ?>
<script>
(function(){
  const center=[<?=json_encode((float)$S['lat'])?>,<?=json_encode((float)$S['lng'])?>];
  const zoom=<?=json_encode((int)$S['zoom'])?>;
  const map=L.map('map').setView(center,zoom);

  const isGoogle=<?=json_encode($isGoogle)?>;
  const gType=<?=json_encode($gType)?>;
  const gKey=<?=json_encode($S['token'])?>;

  // Estilo oscuro para Google ROADMAP (no satélite)
  const GOOGLE_DARK_STYLE=[{"elementType":"geometry","stylers":[{"color":"#1d2a35"}]},{"elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"elementType":"labels.text.fill","stylers":[{"color":"#8ec3b9"}]},{"elementType":"labels.text.stroke","stylers":[{"color":"#1a3646"}]},{"featureType":"administrative.country","elementType":"geometry.stroke","stylers":[{"color":"#4b6878"}]},{"featureType":"administrative.land_parcel","stylers":[{"visibility":"off"}]},{"featureType":"administrative.locality","elementType":"labels.text.fill","stylers":[{"color":"#c4d6e3"}]},{"featureType":"poi","elementType":"labels.text.fill","stylers":[{"color":"#c4d6e3"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"color":"#263c3f"}]},{"featureType":"poi.park","elementType":"labels.text.fill","stylers":[{"color":"#6b9a76"}]},{"featureType":"road","elementType":"geometry","stylers":[{"color":"#304a7d"}]},{"featureType":"road","elementType":"labels.text.fill","stylers":[{"color":"#98a5be"}]},{"featureType":"road","elementType":"labels.text.stroke","stylers":[{"color":"#1d2c4d"}]},{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#2c6675"}]},{"featureType":"road.highway","elementType":"geometry.stroke","stylers":[{"color":"#255763"}]},{"featureType":"road.highway","elementType":"labels.text.fill","stylers":[{"color":"#b0d5ce"}]},{"featureType":"transit","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"geometry","stylers":[{"color":"#0e1626"}]},{"featureType":"water","elementType":"labels.text.fill","stylers":[{"color":"#4e6d70"}]}];

  if(isGoogle && gKey){
    const s=document.createElement('script');
    s.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(gKey);
    s.async=true;
    s.onload=()=>{
      const opts=(gType==='roadmap')?{type:'roadmap',styles:GOOGLE_DARK_STYLE}:{type:gType};
      const gl=L.gridLayer.googleMutant(opts).addTo(map);
      // intento de tilt suave si el motor lo permite (no crítico)
      try{ if(gl && gl.mutant && gl.mutant.setTilt) gl.mutant.setTilt(45); }catch(_){}
    };
    s.onerror=()=>addDarkTiles();
    document.head.appendChild(s);
  }else{
    addDarkTiles();
  }

  function addDarkTiles(){
    L.tileLayer(<?=json_encode($tileUrl)?>,{attribution:<?=json_encode($attrib)?>,subdomains:<?=json_encode($subdom)?>,maxZoom:20}).addTo(map);
  }

  function circle(lat,lng,tipo){
    const c={led:'#3b82f6',impresa:'#10b981',movilled:'#f59e0b',vehiculo:'#f43f5e'};
    return L.circleMarker([lat,lng],{radius:8,color:'#ffffff',weight:2,opacity:1,fillColor:c[(tipo||'').toLowerCase()]||'#93c5fd',fillOpacity:.95});
  }

  const SRC=<?=json_encode($SRC)?>;
  fetch(SRC,{credentials:'same-origin'})
    .then(r=>r.ok?r.json():Promise.reject(r.status))
    .then(d=>{
      const items=d.items||d.data||d.results||[];
      const b=[];
      items.forEach(v=>{
        const lat=v.lat??v.latitude??v.Latitud??v.latitud, lng=v.lng??v.longitude??v.Longitud??v.longitud;
        if(lat==null||lng==null) return;
        const tipo=(v.tipo||'').toString().toLowerCase();
        const img=(v.media?.[0]?.url)||v.imagen_previa||v.imagen1||v.imagen||v.imagen2||v.imagen_cuarta||v.imagen_tercera||'';
        const name=v.nombre||'Valla';
        const prov=v.provincia||v.provincia_nombre||v.zona||'';
        const live=(parseInt(v.en_vivo?1:0,10)===1);
        const badge=tipo==='led'?'b-led':(tipo==='impresa'?'b-imp':(tipo==='movilled'?'b-mov':'b-veh'));
        const imgHtml=img?`<img src="${img}" alt="Previa">`:`<div style="width:96px;height:72px;border:1px dashed rgba(148,163,184,.6);border-radius:.35rem;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.72rem">Sin imagen</div>`;
        const liveHtml=live?`<span class="badge live"><span class="dot"></span>EN VIVO</span>`:'';
        const popup=`<div class="popup">${imgHtml}<div><h3>${name}</h3><div class="meta">${prov?prov+' · ':''}${(tipo||'').toUpperCase()}</div><div style="margin-top:.35rem;display:flex;gap:.35rem;flex-wrap:wrap"><span class="badge ${badge}">${(tipo||'').toUpperCase()||'—'}</span>${liveHtml}</div><div style="margin-top:.6rem;display:flex;gap:.4rem;flex-wrap:wrap"><a href="${tipo==='led'?'/detalles-led/?id='+(v.id||''):'/detalles-vallas/?id='+(v.id||'')}" target="_top" style="text-decoration:none;padding:.35rem .55rem;border-radius:.35rem;background:#334155;color:#e5e7eb;border:1px solid #475569">Detalles</a><a href="/carritos/?a=add&id=${v.id||''}" target="_top" style="text-decoration:none;padding:.35rem .55rem;border-radius:.35rem;background:#1f2937;color:#e5e7eb;border:1px solid #374151">Agregar</a></div></div></div>`;
        circle(lat,lng,tipo).addTo(map).bindPopup(popup);
        b.push([lat,lng]);
      });
      if(b.length) map.fitBounds(b,{padding:[30,30]});
    })
    .catch(()=>{});
})();
</script>
</body>
</html>

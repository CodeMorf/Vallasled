<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/mapas.php';

start_session_safe();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?= e($_SESSION['csrf']) ?>">
<title>Panel - Crear Valla</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<link rel="stylesheet" href="/console/asset/css/agregar/style.css">
<script>tailwind.config={darkMode:'class'}</script>
<style>
#image-preview-container.show{display:block!important}
.ai-list{position:relative;margin-top:.5rem;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:.5rem;max-height:220px;overflow:auto}
.ai-item{padding:.5rem .75rem;cursor:pointer}
.ai-item:hover{background:rgba(99,102,241,.08)}
.ai-sparkle{animation:pulse .9s infinite alternate}
@keyframes pulse{from{opacity:.7}to{opacity:1}}
</style>
</head>
<body class="overflow-x-hidden">

<div id="notification" class="fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-md hidden transition-transform translate-x-full z-50">
  <p id="notification-message"></p>
</div>

<div class="flex min-h-[100dvh] relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold">Crear Nueva Valla</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <form id="create-valla-form" autocomplete="off">
          <input type="hidden" name="csrf" id="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="imagen_url" id="imagen_url" value="">

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-6">
              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Información General</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium mb-1">
                      Nombre de la Valla <span class="text-red-500">*</span>
                      <button type="button" id="ai-titulo" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                        <i class="fas fa-wand-magic-sparkles ai-sparkle"></i>
                      </button>
                    </label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: Pantalla Higüey Centro" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    <div id="ai-titulo-list" class="ai-list hidden"></div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Proveedor <span class="text-red-500">*</span></label>
                    <select id="proveedor" name="proveedor" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      <option value="">Seleccione un proveedor</option>
                      <option value="1">Fox Publicidad</option>
                      <option value="3">VALLAS PAUL</option>
                      <option value="4">Grupoohla srl</option>
                      <option value="5">Alfrit srl</option>
                      <option value="7">Vallas Universal</option>
                      <option value="8">admin</option>
                      <option value="9">Captiva</option>
                      <option value="10">Colorin</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Provincia <span class="text-red-500">*</span></label>
                    <select id="provincia" name="provincia_id" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]" data-current="">
                      <option value="">Cargando...</option>
                    </select>
                  </div>

                  <div class="relative">
                    <label class="block text-sm font-medium mb-1">Zona <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                      <div class="relative w-full">
                        <input type="text" id="zona" name="zona" placeholder="Escriba o seleccione" autocomplete="off" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      </div>
                      <button type="button" id="add-zone-btn" class="px-3 py-2 bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-800">
                        <i class="fas fa-plus"></i>
                      </button>
                    </div>
                    <div id="zona-autocomplete-results" class="autocomplete-results hidden"></div>
                  </div>

                  <div class="md:col-span-2 relative">
                    <label class="block text-sm font-medium mb-1">Ubicación (Dirección) <span class="text-red-500">*</span></label>
                    <textarea id="ubicacion" name="ubicacion" rows="2" placeholder="Busca o selecciona en el mapa..." class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]"></textarea>
                    <input id="ubicacion_search" type="text" class="absolute top-8 right-3 w-1/2 md:w-1/3 px-3 py-1.5 bg-white/90 border border-[var(--border-color)] rounded-md text-sm" placeholder="Buscar dirección (Google)">
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Latitud <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="lat" name="lat" placeholder="18.486058" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Longitud <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="lng" name="lng" placeholder="-69.931212" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div class="relative">
                  <label class="block text-sm font-medium mb-1">Mapa de Ubicación</label>
                  <div id="map"></div>
                  <button type="button" id="fullscreen-map-btn" class="absolute top-10 right-10 bg-[var(--card-bg)] text-[var(--text-primary)] p-2 rounded-md shadow-md z-[401]"><i class="fas fa-expand"></i></button>
                  <button type="button" id="lock-map-btn" class="absolute top-10 right-2 bg-[var(--card-bg)] text-green-500 p-2 rounded-md shadow-md z-[401]"><i class="fas fa-lock-open"></i></button>
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Vista de Calle 360°</label>
                  <div id="street-view" class="flex items-center justify-center rounded-lg overflow-hidden"></div>
                  <p id="street-view-placeholder" class="text-sm text-[var(--text-secondary)]">Mueve el marcador para ver</p>
                </div>
              </div>
            </div>

            <div class="lg:col-span-1 space-y-6">
              <div>
                <label class="block text-sm font-medium mb-2">Imagen Principal</label>
                <div id="image-drop-zone" class="image-drop-zone flex flex-col items-center justify-center w-full min-h-40 rounded-lg cursor-pointer bg-[var(--main-bg)] p-4">
                  <div id="upload-prompt" class="flex flex-col items-center justify-center text-center">
                    <i class="fas fa-cloud-upload-alt fa-3x text-[var(--text-secondary)]"></i>
                    <p class="mb-2 text-sm text-[var(--text-secondary)]"><span class="font-semibold">Clic para subir</span> o arrastra</p>
                  </div>
                  <div id="image-preview-container" class="hidden w-full text-center">
                    <img id="image-preview" src="" alt="Previsualización" class="max-h-32 mx-auto rounded-lg mb-2">
                    <p id="image-filename" class="text-xs font-semibold text-[var(--text-primary)] truncate"></p>
                    <p id="image-filesize" class="text-xs text-[var(--text-secondary)]"></p>
                  </div>
                  <input id="image-upload" name="image-upload" type="file" class="hidden" accept="image/*">
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Detalles Técnicos y Comerciales</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-1">Tipo de Valla <span class="text-red-500">*</span></label>
                    <select id="tipo-valla" name="tipo" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      <option value="led">LED</option>
                      <option value="impresa">Impresa</option>
                      <option value="movilled">MoviLED</option>
                      <option value="vehiculo">Vehículo</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Medida <span class="text-red-500">*</span></label>
                    <input type="text" id="medida" name="medida" placeholder="Ej: 50x20 Pies" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Precio por Mes (DOP)</label>
                    <input type="number" id="precio" name="precio" placeholder="50000" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Audiencia Mensual</label>
                    <input type="number" id="audiencia_mensual" name="audiencia_mensual" placeholder="700000" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Duración Spot (seg)</label>
                    <input type="number" id="spot_time_seg" name="spot_time_seg" placeholder="10" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div id="stream-urls-container" class="sm:col-span-2 space-y-6">
                    <div>
                      <label class="block text-sm font-medium mb-1">URL Stream Pantalla</label>
                      <input type="url" id="url_stream_pantalla" name="url_stream_pantalla" placeholder="https://..." class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">URL Stream Tráfico</label>
                      <input type="url" id="url_stream_trafico" name="url_stream_trafico" placeholder="https://..." class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Optimización y Capacidad</h3>
                <div>
                  <label class="flex items-center gap-2 text-sm font-medium mb-1">
                    Palabras Clave (SEO)
                    <button type="button" id="ai-seo" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                      <i class="fas fa-sparkles ai-sparkle"></i>
                    </button>
                  </label>
                  <input type="text" id="keywords_seo" name="keywords_seo" placeholder="valla led, publicidad santiago, pantalla digital" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  <p class="text-xs text-[var(--text-secondary)] mt-1">Separa con comas.</p>
                  <div id="ai-seo-list" class="ai-list hidden"></div>
                </div>
                <div class="pt-2">
                  <label class="block text-sm font-medium mb-2">Estimación de Ocupación</label>
                  <div class="grid grid-cols-2 gap-4 mb-2">
                    <div>
                      <label class="block text-xs font-medium mb-1">Capacidad de Slots</label>
                      <input type="number" id="capacidad_reservas" name="capacidad_reservas" value="10" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Slots Ocupados (Demo)</label>
                      <input type="number" id="slots_ocupados" value="0" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                    <div id="booking-progress-bar" class="bg-indigo-600 h-2.5 rounded-full" style="width:0%;transition:width .5s ease-in-out;"></div>
                  </div>
                  <p id="booking-percentage-text" class="text-sm text-center font-medium text-[var(--text-secondary)] mt-2"></p>
                </div>
              </div>

              <div class="relative">
                <label class="flex items-center gap-2 text-sm font-medium mb-1">
                  Descripción
                  <button type="button" id="ai-descripcion" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                    <i class="fas fa-magic ai-sparkle"></i>
                  </button>
                </label>
                <textarea id="descripcion" name="descripcion" rows="5" placeholder="Detalles de la valla" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]"></textarea>
                <div id="ai-descripcion-list" class="ai-list hidden"></div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Licencia y Publicación</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-1">Número Licencia</label>
                    <input type="text" id="numero_licencia" name="numero_licencia" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Vencimiento Licencia</label>
                    <input type="text" id="fecha_vencimiento" name="fecha_vencimiento" placeholder="Seleccionar fecha" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Estado (Activa / Inactiva)</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="estado_valla" id="toggle-estado" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                    <label for="toggle-estado" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Visible en web pública</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="visible_publico" id="toggle-visible" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                    <label for="toggle-visible" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Publicar Precio al Cliente</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="mostrar_precio_cliente" id="toggle-precio" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" checked>
                    <label for="toggle-precio" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Marcar como ADS</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="ads" id="toggle-ads" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer">
                    <label for="toggle-ads" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div id="ads-details-container" class="hidden space-y-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium mb-1">Inicio ADS</label>
                      <input type="text" id="ads-start" name="ads_start" placeholder="Desde" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Fin ADS</label>
                      <input type="text" id="ads-end" name="ads_end" placeholder="Hasta" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium mb-1">Monto Pagado (DOP)</label>
                      <input type="number" step="0.01" id="monto-pagado" name="monto_pagado" placeholder="900.00" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Orden</label>
                      <input type="number" id="orden" name="orden" placeholder="1" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="mt-8 pt-6 border-t border-[var(--border-color)] flex justify-end gap-4 lg:col-span-3">
            <a href="/console/vallas/" class="px-6 py-2.5 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 font-semibold rounded-lg shadow-sm hover:bg-gray-300 dark:hover:bg-gray-500">Cancelar</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700">Guardar Valla</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden"></div>
</div>

<script>
  window.__GMAPS_KEY__ = "<?= e($GOOGLE_MAPS_API_KEY) ?>";
</script>
<script src="/console/asset/js/agregar/main.js"></script>

<!-- IA: título / descripción / SEO -->
<script>
(function(){
  const EP = '/api/openai/sugerencia/index.php';

  function g(id){ return document.getElementById(id); }
  function selTxt(id){
    const s=g(id);
    return s && s.options && s.selectedIndex>=0 ? s.options[s.selectedIndex].text : '';
  }
  function ctx(){
    return {
      ubicacion:g('ubicacion')?.value?.trim()||'',
      provincia:selTxt('provincia'),
      zona:g('zona')?.value?.trim()||'',
      tipo:g('tipo-valla')?.value||'',
      medida:g('medida')?.value?.trim()||'',
      audiencia:g('audiencia_mensual')?.value||'',
      precio:g('precio')?.value||'',
      spot:g('spot_time_seg')?.value||''
    };
  }
  function spin(btn,on){
    const i=btn.querySelector('i'); if(!i) return;
    if(on){ i.classList.remove('fa-wand-magic-sparkles','fa-magic','fa-sparkles'); i.classList.add('fa-spinner','fa-spin'); }
    else  { i.classList.remove('fa-spinner','fa-spin'); i.classList.add('ai-sparkle','fa-magic'); }
  }
  function renderList(el, items, pick){
    el.innerHTML='';
    if(!items.length){ el.classList.add('hidden'); return; }
    el.classList.remove('hidden');
    items.forEach(t=>{
      const d=document.createElement('div');
      d.className='ai-item'; d.textContent=t;
      d.addEventListener('click',()=>{ pick(t); el.classList.add('hidden'); });
      el.appendChild(d);
    });
  }
  async function sugerir(target, text){
    const u=new URL(EP, location.origin);
    u.searchParams.set('target',target);
    u.searchParams.set('max_tokens','160');
    u.searchParams.set('temperature','0.2');
    u.searchParams.set('text',text);

    const abort = new AbortController();
    const tid = setTimeout(()=>abort.abort(), 12000);

    try{
      const r = await fetch(u,{headers:{'Accept':'application/json'}, signal:abort.signal});
      const j = await r.json().catch(()=>null);
      if (!r.ok || !j || !j.ok) throw new Error(j?.error || ('HTTP_'+r.status));
      return Array.isArray(j.items)?j.items:[];
    } finally { clearTimeout(tid); }
  }

  g('ai-titulo')?.addEventListener('click', async (e)=>{
    const btn=e.currentTarget, list=g('ai-titulo-list');
    try{
      spin(btn,true);
      const c=ctx();
      const base=`Ubicación: ${c.ubicacion}. Provincia: ${c.provincia}. Zona: ${c.zona}. Tipo: ${c.tipo}. Medida: ${c.medida}.`;
      const items=await sugerir('titulo', base);
      renderList(list, items, v=>{ g('nombre').value=v; });
    }catch(err){ alert('IA no disponible'); } finally{ spin(btn,false); }
  });

  g('ai-descripcion')?.addEventListener('click', async (e)=>{
    const btn=e.currentTarget, list=g('ai-descripcion-list');
    try{
      spin(btn,true);
      const c=ctx();
      const base=`Valla en ${c.ubicacion} (${c.provincia}, ${c.zona}). Tipo: ${c.tipo}. Medida: ${c.medida}. Audiencia: ${c.audiencia}. Precio: ${c.precio}. Spot: ${c.spot}s.`;
      const items=await sugerir('descripcion', base);
      renderList(list, items, v=>{ g('descripcion').value=v; });
    }catch(err){ alert('IA no disponible'); } finally{ spin(btn,false); }
  });

  g('ai-seo')?.addEventListener('click', async (e)=>{
    const btn=e.currentTarget, list=g('ai-seo-list');
    try{
      spin(btn,true);
      const c=ctx();
      const base=`Palabras clave para valla en ${c.ubicacion} (${c.provincia}, ${c.zona}). Tipo ${c.tipo}.`;
      const items=await sugerir('generico', base);
      renderList(list, items, v=>{
        const el=g('keywords_seo');
        const cur=el.value.trim();
        const parts=(cur?cur.split(','):[]).map(s=>s.trim()).filter(Boolean);
        v.split(',').map(s=>s.trim()).forEach(x=>{
          if(x && !parts.some(p=>p.toLowerCase()===x.toLowerCase())) parts.push(x);
        });
        el.value=parts.join(', ');
      });
    }catch(err){ alert('IA no disponible'); } finally{ spin(btn,false); }
  });

  document.addEventListener('click',(ev)=>{
    ['ai-titulo-list','ai-descripcion-list','ai-seo-list'].forEach(id=>{
      const el=g(id);
      const btn=g(id.replace('-list',''));
      if(el && !el.classList.contains('hidden') && !el.contains(ev.target) && ev.target!==btn) el.classList.add('hidden');
    });
  });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  if (window.flatpickr){
    flatpickr('#fecha_vencimiento',{dateFormat:'Y-m-d'});
    flatpickr('#ads-start',{dateFormat:'Y-m-d'});
    flatpickr('#ads-end',{dateFormat:'Y-m-d'});
  }

  // Provincias autoselect cuando main.js las cargue
  const sel = document.getElementById('provincia');
  const wanted = sel?.dataset.current || '';
  if (sel && wanted){
    const t = setInterval(()=>{
      if (sel.options.length>1){
        for (const o of sel.options){ if (o.value==wanted || o.text==wanted){ sel.value=o.value; break; } }
        clearInterval(t);
      }
    },150);
    setTimeout(()=>clearInterval(t),3000);
  }

  // Toggle ADS
  const adsT = document.getElementById('toggle-ads');
  const adsBox = document.getElementById('ads-details-container');
  const syncAds = ()=>{ adsT?.checked ? adsBox?.classList.remove('hidden') : adsBox?.classList.add('hidden'); };
  adsT?.addEventListener('change', syncAds); syncAds();

  // Progreso ocupación demo
  const cap = document.getElementById('capacidad_reservas');
  const occ = document.getElementById('slots_ocupados');
  const bar = document.getElementById('booking-progress-bar');
  const txt = document.getElementById('booking-percentage-text');
  function upd(){
    const c = Math.max(0, parseInt(cap.value||'0',10));
    const o = Math.max(0, parseInt(occ.value||'0',10));
    const p = c>0 ? Math.min(100, Math.round((o/c)*100)) : 0;
    if (bar) bar.style.width = p+'%';
    if (txt) txt.textContent = p+'% ocupado';
  }
  cap?.addEventListener('input',upd);
  occ?.addEventListener('input',upd);
  upd();

  // Submit -> AJAX crear
  const f = document.getElementById('create-valla-form');
  f?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const b = f.querySelector('button[type="submit"]');
    const fd = new FormData(f);
    const csrf = document.querySelector('meta[name="csrf"]')?.content || document.getElementById('csrf')?.value || '';
    try{
      b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
      const r = await fetch('/console/vallas/agregar/ajax/guardar.php', {
        method:'POST',
        headers: csrf ? { 'X-CSRF': csrf } : {},
        body: fd,
        cache:'no-store',
        credentials:'same-origin'
      });
      const j = await r.json().catch(()=>null);
      if (!r.ok || !j || !j.ok) {
        if (j && j.error === 'VALIDATION' && j.fields) {
          alert('Campos inválidos: ' + Object.keys(j.fields).join(', '));
        } else {
          alert('Error: ' + (j?.error || ('HTTP_'+r.status)));
        }
        throw new Error(j?.error || ('HTTP_'+r.status));
      }
      if (typeof notify==='function') notify('Valla creada','success');
      window.location.href = '/console/vallas/editar.php/?id=' + encodeURIComponent(j.id);
    }catch(err){
      if (typeof notify==='function') notify('Error al guardar','error');
      console.error(err);
    }finally{
      b.disabled = false; b.textContent = 'Guardar Valla';
    }
  });

  // Mostrar nombre si ya hay imagen_url precargada
  const url = document.getElementById('imagen_url')?.value || '';
  if (url){
    const fn = document.getElementById('image-filename');
    if (fn && !fn.textContent.trim()){ try{ fn.textContent = url.split('/').pop(); }catch{} }
  }
});
</script>

<script defer src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($GOOGLE_MAPS_API_KEY) ?>&libraries=places&v=quarterly"></script>
</body>
</html>

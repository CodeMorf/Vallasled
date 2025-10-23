<?php
// /console/vallas/agregar/index.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$branding = load_branding($conn);
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

/* Google Maps API Key: desde config_global.google_maps_api_key o env */
$gmaps_key = '';
try {
  if ($conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT valor FROM config_global WHERE clave='google_maps_api_key' AND activo=1 ORDER BY id DESC LIMIT 1");
    $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) $gmaps_key = trim((string)$row['valor']);
  } else {
    $stmt = $conn->prepare("SELECT valor FROM config_global WHERE clave='google_maps_api_key' AND activo=1 ORDER BY id DESC LIMIT 1");
    $stmt->execute(); $row = $stmt->fetch(PDO::FETCH_ASSOC); if ($row) $gmaps_key = trim((string)$row['valor']);
  }
} catch (Throwable $e) {}
if (!$gmaps_key && !empty($_ENV['GOOGLE_MAPS_API_KEY'])) $gmaps_key = (string)$_ENV['GOOGLE_MAPS_API_KEY'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($title)?> · Crear Valla</title>
<link rel="icon" href="<?=$fav?>"><meta name="theme-color" content="#111827"/>
<meta name="csrf" content="<?=htmlspecialchars($csrf)?>">
<meta name="gmaps-key" content="<?=htmlspecialchars($gmaps_key)?>">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/vallas_agregar.css">
<script>tailwind.config={darkMode:'class'}</script>
<style>
  /* UI mínima para zonas/provincias */
  .tag{display:inline-flex;align-items:center;gap:.4rem;background:var(--input-bg);border:1px solid var(--border-color);padding:.15rem .5rem;border-radius:.5rem;font-size:.75rem}
  .btn-xs{padding:.35rem .55rem;border-radius:.45rem;border:1px solid var(--border-color);background:var(--card-bg)}
</style>
</head>
<body class="overflow-x-hidden">
<div class="flex min-h-[100dvh] relative page-wrap">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--card-bg)] p-3 sm:p-4 flex justify-between items-center sticky top-0 z-20 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-3 sm:gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold">Crear Nueva Valla</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-3 sm:p-4 lg:p-6">
      <div class="card rounded-xl p-4 sm:p-6">
        <form id="form-valla" autocomplete="off">
          <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <!-- Columna izquierda -->
            <section class="lg:col-span-2 space-y-6">
              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Información General</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                  <div class="md:col-span-2 relative">
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Nombre de la Valla</label>
                    <input id="nombre" name="nombre" class="inp pr-10" placeholder="Ej: Pantalla Higüey Centro" maxlength="120">
                    <button type="button" id="ai-suggest-name-btn" class="ai-mini" title="Sugerir nombre"><i class="fas fa-wand-magic-sparkles"></i></button>
                  </div>

                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Provincia</label>
                    <select id="provincia" name="provincia_id" class="inp-select">
                      <option value="">Cargando…</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Zona</label>
                    <div class="flex gap-2">
                      <select id="zona" name="zona" class="inp-select">
                        <option value="">Cargando…</option>
                      </select>
                      <button type="button" id="add-zone-btn" class="btn btn-xs" title="Agregar zona">
                        <i class="fas fa-plus"></i>
                      </button>
                    </div>
                    <p id="zona-hint" class="text-xs text-[var(--text-secondary)] mt-1">Se evita crear duplicados. Búsqueda insensible a mayúsculas y tildes.</p>
                  </div>

                  <div class="md:col-span-2 relative">
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Ubicación (Dirección)</label>
                    <textarea id="ubicacion" name="ubicacion" rows="2" class="inp" placeholder="Se completará desde el mapa…"></textarea>
                    <i id="address-loader" class="fas fa-spinner fa-spin absolute right-3 top-[3.2rem] text-indigo-500 hidden"></i>
                  </div>

                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Latitud</label>
                    <input id="lat" name="lat" type="number" step="any" class="inp" placeholder="18.486058">
                  </div>
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Longitud</label>
                    <input id="lng" name="lng" type="number" step="any" class="inp" placeholder="-69.931212">
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div class="relative">
                  <span id="map-lock-state" class="map-lock-badge">Mapa bloqueado</span>
                  <button type="button" id="map-lock-btn" class="map-lock-btn btn bg-[var(--card-bg)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
                    <i class="fas fa-lock"></i> <span class="ml-2 hidden sm:inline">Editar ubicación</span>
                  </button>
                  <label class="block text-sm mb-1 text-[var(--text-secondary)] mt-8">Mapa de Ubicación</label>
                  <div id="map"></div>
                </div>
                <div>
                  <label class="block text-sm mb-1 text-[var(--text-secondary)]">Street View</label>
                  <div id="street-view-container" class="sv-container">
                    <div id="street-view" class="w-full h-full rounded-lg"></div>
                    <p id="street-view-placeholder" class="sv-ph">Mueve el marcador para ver</p>
                  </div>
                </div>
              </div>
            </section>

            <!-- Columna derecha -->
            <section class="lg:col-span-1 space-y-6">
              <div>
                <label class="block text-sm mb-2 text-[var(--text-secondary)]">Imagen Principal</label>
                <div id="image-drop-zone" class="image-drop-zone">
                  <div id="upload-prompt" class="upload-prompt">
                    <i class="fas fa-cloud-upload-alt fa-3x"></i>
                    <p class="mb-2 text-sm"><span class="font-semibold">Clic para subir</span> o arrastra</p>
                  </div>
                  <div id="image-preview-container" class="hidden w-full text-center">
                    <img id="image-preview" src="" alt="Previsualización" class="max-h-32 mx-auto rounded-lg mb-2">
                    <p id="image-filename" class="text-xs font-semibold truncate"></p>
                    <p id="image-filesize" class="text-xs text-[var(--text-secondary)]"></p>
                  </div>
                  <input id="image-upload" type="file" class="hidden" accept="image/*"/>
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Detalles Técnicos y Comerciales</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Tipo de Valla</label>
                    <select id="tipo-valla" name="tipo" class="inp-select"><option value="led">LED</option><option value="impresa">Impresa</option></select>
                  </div>
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Medida</label>
                    <input id="medida" name="medida" class="inp" placeholder="Ej: 50x20 Pies" maxlength="50">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Precio por Mes (DOP)</label>
                    <input id="precio" name="precio" type="number" min="0" step="0.01" class="inp" placeholder="50000">
                  </div>
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Audiencia Mensual</label>
                    <input id="audiencia_mensual" name="audiencia_mensual" type="number" min="0" class="inp" placeholder="700000">
                  </div>
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Duración Spot (seg)</label>
                    <input id="spot_time_seg" name="spot_time_seg" type="number" min="5" max="120" class="inp" placeholder="10">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">URL Stream Pantalla</label>
                    <input id="url_stream_pantalla" name="url_stream_pantalla" type="url" class="inp" placeholder="https://...">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">URL Stream Tráfico</label>
                    <input id="url_stream_trafico" name="url_stream_trafico" type="url" class="inp" placeholder="https://...">
                  </div>
                </div>
              </div>

              <div class="space-y-4">
                <label class="block text-sm mb-1 text-[var(--text-secondary)]">Descripción</label>
                <div class="relative">
                  <textarea id="descripcion" name="descripcion" rows="5" class="inp" placeholder="Añade detalles o genera con IA…"></textarea>
                  <button type="button" id="ai-suggest-btn" class="ai-btn"><i class="fas fa-magic"></i> Sugerir</button>
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Licencia y Publicación</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Número Licencia</label>
                    <input id="numero_licencia" name="numero_licencia" class="inp" maxlength="80">
                  </div>
                  <div>
                    <label class="block text-sm mb-1 text-[var(--text-secondary)]">Vencimiento Licencia</label>
                    <input id="fecha_vencimiento" name="fecha_vencimiento" class="inp" placeholder="YYYY-MM-DD">
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm text-[var(--text-secondary)]">Estado (Activa / Inactiva)</span>
                  <label class="switch">
                    <input type="checkbox" id="toggle-estado" name="estado_valla" checked>
                    <span></span>
                  </label>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-[var(--text-secondary)]">Visible en web pública</span>
                  <label class="switch">
                    <input type="checkbox" id="toggle-visible" name="visible_publico" checked>
                    <span></span>
                  </label>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-[var(--text-secondary)]">Publicar Precio al Cliente</span>
                  <label class="switch">
                    <input type="checkbox" id="toggle-precio" name="mostrar_precio_cliente" checked>
                    <span></span>
                  </label>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-[var(--text-secondary)]">Marcar como ADS</span>
                  <label class="switch">
                    <input type="checkbox" id="toggle-ads">
                    <span></span>
                  </label>
                </div>
                <div id="ads-date-range" class="hidden grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs mb-1 text-[var(--text-secondary)]">Inicio ADS</label>
                    <input id="ads-start" name="ads_start" class="inp" placeholder="Desde">
                  </div>
                  <div>
                    <label class="block text-xs mb-1 text-[var(--text-secondary)]">Fin ADS</label>
                    <input id="ads-end" name="ads_end" class="inp" placeholder="Hasta">
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="mt-6 pt-6 border-t border-[var(--border-color)] flex flex-col sm:flex-row justify-end gap-3">
            <a href="/console/vallas/" class="btn bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-500">Cancelar</a>
            <button type="submit" class="btn bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Guardar Valla</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Google Maps JS -->
<?php if ($gmaps_key): ?>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?=urlencode($gmaps_key)?>&libraries=places&callback=initGMap"></script>
<?php endif; ?>

<!-- JS específico: provincias/zonas con deduplicación -->
<script>
(function(){
  const $ = (s,ctx=document)=>ctx.querySelector(s);
  const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));
  const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
  const base = location.origin;

  const provinciaSel = $('#provincia');
  const zonaSel = $('#zona');
  const addZoneBtn = $('#add-zone-btn');

  function norm(s){
    return (s||'').toString().trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'') // sin tildes
      .toLowerCase().replace(/\s+/g,' ');
  }

  async function fetchJSON(url, opts={}){
    const res = await fetch(url, opts);
    if(!res.ok) throw new Error('HTTP '+res.status);
    const ct = res.headers.get('content-type')||'';
    if(ct.includes('application/json')) return res.json();
    return res.text();
  }

  async function loadProvincias(){
    try{
      provinciaSel.innerHTML = '<option value="">Cargando…</option>';
      const data = await fetchJSON(`${base}/api/provincias/`);
      // esperados: [{id:1,nombre:'Distrito Nacional'}, ...]
      const items = Array.isArray(data)?data:(data.items||[]);
      provinciaSel.innerHTML = '<option value="">Seleccionar…</option>' +
        items.map(p=>`<option value="${String(p.id)}">${String(p.nombre)}</option>`).join('');
    }catch(e){
      provinciaSel.innerHTML = '<option value="">Error al cargar</option>';
      console.error(e);
    }
  }

  let zonasCache = [];
  async function loadZonas(q=''){
    try{
      zonaSel.innerHTML = '<option value="">Cargando…</option>';
      const url = q ? `${base}/api/zonas/?q=${encodeURIComponent(q)}` : `${base}/api/zonas/`;
      const data = await fetchJSON(url);
      const items = Array.isArray(data)?data:(data.items||[]);
      zonasCache = items.map(z=>({ id: String(z.id ?? z.value ?? ''), nombre: String(z.nombre ?? z.label ?? '') }))
                        .filter(z=>z.nombre && z.id);
      // eliminar duplicados por nombre normalizado
      const seen = new Set();
      const unique = [];
      for(const z of zonasCache){
        const key = norm(z.nombre);
        if(!seen.has(key)){ seen.add(key); unique.push(z); }
      }
      zonasCache = unique;
      zonaSel.innerHTML = '<option value="">Seleccionar…</option>' +
        zonasCache.map(z=>`<option value="${z.nombre}">${z.nombre}</option>`).join('');
    }catch(e){
      zonaSel.innerHTML = '<option value="">Error al cargar</option>';
      console.error(e);
    }
  }

  addZoneBtn?.addEventListener('click', async ()=>{
    const nombre = prompt('Nueva zona (ej. Zona Oriental):','');
    const n = norm(nombre||'');
    if(!n) return;
    // ¿ya existe?
    const exists = zonasCache.find(z => norm(z.nombre) === n);
    if(exists){
      // Seleccionar la existente
      zonaSel.value = exists.nombre;
      // feedback visual
      $('#zona-hint').innerHTML = `<span class="tag"><i class="fa-solid fa-check"></i> Zona existente seleccionada</span>`;
      return;
    }
    // Crear vía API evitando duplicados
    try{
      // Último chequeo en backend por si otra sesión la creó
      const check = await fetchJSON(`${base}/api/zonas/?q=${encodeURIComponent(nombre)}`);
      const list = Array.isArray(check)?check:(check.items||[]);
      const found = (list||[]).find(it => norm(it.nombre||it.label||'')===n);
      if(found){
        zonaSel.value = String(found.nombre || found.label);
        $('#zona-hint').innerHTML = `<span class="tag"><i class="fa-solid fa-check"></i> Zona existente seleccionada</span>`;
        return;
      }

      const fd = new URLSearchParams();
      fd.set('nombre', nombre.trim());
      // opcional: synonyms como JSON
      // fd.set('synonyms','[]');

      const res = await fetch(`${base}/api/zonas/`, {
        method: 'POST',
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF': csrf,
          'Content-Type':'application/x-www-form-urlencoded'
        },
        body: fd.toString()
      });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const j = await res.json().catch(()=>({}));
      if(j && j.ok){
        // refrescar y seleccionar
        await loadZonas('');
        zonaSel.value = nombre.trim();
        $('#zona-hint').innerHTML = `<span class="tag"><i class="fa-solid fa-plus"></i> Zona creada</span>`;
      }else{
        throw new Error(j && j.error ? j.error : 'error_crear_zona');
      }
    }catch(e){
      console.error(e);
      alert('No se pudo crear la zona. Ver consola.');
    }
  });

  // filtros básicos de búsqueda al escribir en el select usando input paralelo
  zonaSel?.addEventListener('change', ()=>{
    // no-op. Mantén compatibilidad con tu JS externo si existe.
  });

  // Inicialización
  loadProvincias();
  loadZonas('');

})();
</script>

<script src="/console/asset/js/vallas_agregar.js"></script>
</body>
</html>

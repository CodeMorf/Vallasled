<?php
// /console/mapa/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title    = (($branding['title'] ?? 'Panel') ?: 'Panel') . ' - Configuración de Mapas';
$fav      = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<meta name="theme-color" content="#111827">
<title><?=h($title)?></title>
<link rel="icon" href="<?=$fav?>">

<script>
(function(){try{
  var t=localStorage.getItem('theme');
  if(!t){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
  if(t==='dark') document.documentElement.classList.add('dark');
}catch(e){}})();
</script>

<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/mapa/mapa.css">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous"/>
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-20 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Configuración de Mapas</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
        <button id="btn-guardar" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
          <i class="fas fa-save"></i><span class="hidden sm:inline">Guardar Cambios</span>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold mb-4">Previsualización y Coordenadas</h3>
          <div id="map" class="map-canvas"></div>
        </div>
        <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold mb-4">Ajustes Generales</h3>
          <div class="space-y-4">
            <div>
              <label for="map-provider" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Proveedor</label>
              <select id="map-provider" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
            </div>
            <div>
              <label for="lat-input" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Latitud</label>
              <input id="lat-input" type="text" inputmode="decimal" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label for="lng-input" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Longitud</label>
              <input id="lng-input" type="text" inputmode="decimal" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label for="zoom-input" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Zoom</label>
              <input id="zoom-input" type="number" min="1" max="19" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label for="gmaps-key" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Google API Key</label>
              <input id="gmaps-key" type="text" autocomplete="off" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="AIza...">
              <p class="text-xs text-[var(--text-secondary)] mt-1">Requerida para estilos “Google”.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h3 class="text-xl font-bold mb-4">Seleccionar Estilo</h3>
        <div id="styles-gallery" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6"></div>
      </div>
    </div>
  </main>

  <!-- overlay sobre todo -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-50 md:hidden hidden"></div>
</div>

<script>
window.MAP_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    opciones: "/console/mapa/ajax/opciones.php",
    guardar:  "/console/mapa/ajax/guardar.php"
  },
  fallback: { lat: 18.486058, lng: -69.931212, zoom: 12 }
});
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous" defer></script>
<script src="/console/asset/js/mapa/mapa.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Solo tema. El sidebar lo maneja sidebar.php
  const btn  = document.getElementById('theme-toggle');
  const moon = document.getElementById('theme-toggle-dark-icon');
  const sun  = document.getElementById('theme-toggle-light-icon');
  const apply = (t) => { const d = t==='dark'; document.documentElement.classList.toggle('dark', d); moon?.classList.toggle('hidden', !d); sun?.classList.toggle('hidden', d); };
  const saved = localStorage.getItem('theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  apply(saved);
  btn?.addEventListener('click', (e) => { e.preventDefault(); const next = document.documentElement.classList.contains('dark') ? 'light':'dark'; localStorage.setItem('theme', next); apply(next); });
});
</script>
</body>
</html>

<?php
// /console/ads/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Anuncios Destacados';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/ads/ads.css">
<style>
  .modal-backdrop{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5);z-index:60}
  .modal{width:95%;max-width:720px;background:var(--card-bg);color:var(--text-primary);border:1px solid var(--border-color);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,.35)}
  .modal.show{display:flex}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-color)}
  .modal-body{padding:16px}
  .toast{position:fixed;right:16px;bottom:16px;z-index:70}
</style>
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Anuncios Destacados</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="btn-nuevo" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
          <i class="fas fa-plus"></i><span class="hidden sm:inline">Agregar Anuncio</span>
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <!-- Filtros -->
      <div class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div class="relative">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="f-q" type="text" placeholder="Buscar por nombre o ID de valla..." class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <select id="f-prov" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Todos los proveedores</option>
          </select>
          <button id="f-clear" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 font-semibold">
            <i class="fas fa-times mr-2"></i>Limpiar
          </button>
        </div>
      </div>

      <!-- Grid -->
      <div id="ads-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6"></div>
      <div id="no-results" class="hidden text-center py-16 text-[var(--text-secondary)]">
        <i class="fas fa-search fa-3x mb-4"></i>
        <p>No se encontraron anuncios.</p>
      </div>

      <!-- Paginación -->
      <div id="ads-pager" class="mt-6 flex items-center justify-between hidden">
        <button id="pg-prev" class="px-3 py-2 rounded-lg border border-[var(--border-color)]"><i class="fa fa-chevron-left mr-2"></i>Prev</button>
        <div class="text-sm text-[var(--text-secondary)]"><span id="pg-info">—</span></div>
        <button id="pg-next" class="px-3 py-2 rounded-lg border border-[var(--border-color)]">Next<i class="fa fa-chevron-right ml-2"></i></button>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Crear/Editar -->
<div id="dlg-ad" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 id="dlg-title" class="text-lg font-semibold">Crear Anuncio</h3>
      <button id="dlg-close" class="p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" aria-label="Cerrar"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ad-id" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Valla</label>
          <select id="ad-valla" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2"></select>
        </div>
        <div>
          <label class="block text-sm mb-1">Orden</label>
          <input id="ad-orden" type="number" min="1" value="1" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <div>
          <label class="block text-sm mb-1">Desde</label>
          <input id="ad-desde" type="date" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Hasta</label>
          <input id="ad-hasta" type="date" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <div>
          <label class="block text-sm mb-1">Monto pagado</label>
          <input id="ad-monto" type="number" step="0.01" min="0" placeholder="0.00" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Observación</label>
          <input id="ad-obs" type="text" maxlength="200" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2" placeholder="Opcional">
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button id="ad-cancel" class="px-3 py-1.5 rounded-lg border border-[var(--border-color)]">Cancelar</button>
        <button id="ad-save" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast" style="display:none">
  <div class="px-4 py-2 rounded-lg bg-[var(--card-bg)] border border-[var(--border-color)] shadow" id="toast-msg"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Tema
  const btnTheme = document.getElementById('theme-toggle');
  const iconMoon = document.getElementById('theme-toggle-dark-icon');
  const iconSun  = document.getElementById('theme-toggle-light-icon');
  const applyTheme = t => {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    iconMoon?.classList.toggle('hidden', !dark);
    iconSun?.classList.toggle('hidden', dark);
  };
  const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(saved);
  btnTheme?.addEventListener('click', (e) => {
    e.preventDefault();
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', next); applyTheme(next);
  });

  // Botón nuevo
  document.getElementById('btn-nuevo')?.addEventListener('click', (e) => {
    e.preventDefault();
    window.ADS && ADS.openCreate();
  });
});
</script>

<script>
window.ADS_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar:   "/console/ads/ajax/listar.php",
    guardar:  "/console/ads/ajax/guardar.php",
    eliminar: "/console/ads/ajax/eliminar.php",
    opciones: "/console/ads/ajax/opciones.php"
  },
  page: { limit: 24 }
});
</script>
<script src="/console/asset/js/ads/ads.js" defer></script>
</body>
</html>

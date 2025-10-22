<?php
// /console/licencias/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Gestión de Licencias';
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
<link rel="stylesheet" href="/console/asset/css/licencias/licencias.css">
<style>
  .modal-backdrop{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5);z-index:60}
  .modal{width:95%;max-width:850px;background:var(--card-bg);color:var(--text-primary);border:1px solid var(--border-color);border-radius:14px;box-shadow:0 20px 40px rgba(0,0,0,.35)}
  .modal.show{display:flex}
  .modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-color)}
  .modal-body{padding:16px}
  .chip{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;border:1px solid var(--border-color)}
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Licencias</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
        <button id="btn-nueva" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
          <i class="fas fa-plus"></i><span class="hidden sm:inline">Nueva Licencia</span>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-check-circle"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Aprobadas</p><p id="kpi-aprob" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-exclamation-triangle"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Por vencer</p><p id="kpi-porv" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-times-circle"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Vencidas</p><p id="kpi-venc" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-gray-100 dark:bg-gray-500/20 text-gray-600 dark:text-gray-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-file-alt"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Borrador</p><p id="kpi-borr" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
      </div>

      <!-- Filtros + Tabla -->
      <div class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
          <div class="relative w-full md:max-w-xs">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="f-q" type="text" placeholder="Buscar por ciudad, entidad, proveedor..." class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="flex gap-2 sm:gap-4 flex-wrap w-full md:w-auto">
            <select id="f-estado" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Estado</option>
              <option value="aprobada">Aprobada</option>
              <option value="enviada">Enviada</option>
              <option value="borrador">Borrador</option>
              <option value="rechazada">Rechazada</option>
              <option value="vencida">Vencida</option>
              <option value="por_vencer">Por Vencer</option>
            </select>
            <input id="f-desde" type="date" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input id="f-hasta" type="date" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button id="btn-filtrar" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 font-semibold">
              <i class="fas fa-filter mr-2"></i>Filtrar
            </button>
            <button id="btn-export" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-600 hover:bg-slate-500 font-semibold">
              <i class="fas fa-file-export mr-2"></i>Exportar
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left responsive-table">
            <thead>
              <tr class="border-b border-[var(--border-color)] text-sm font-semibold text-[var(--text-secondary)]">
                <th class="px-6 py-3">ID / Valla</th>
                <th class="px-6 py-3">Proveedor / Cliente</th>
                <th class="px-6 py-3">Ciudad / Entidad</th>
                <th class="px-6 py-3">Fechas</th>
                <th class="px-6 py-3 text-center">Estado</th>
                <th class="px-6 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody id="lic-tbody" class="text-sm divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="lic-empty" class="hidden text-center py-12">
            <i class="fas fa-box-open fa-2x text-[var(--text-secondary)] mb-3"></i>
            <p class="font-semibold">Sin resultados</p>
            <p class="text-[var(--text-secondary)]">Ajusta filtros.</p>
          </div>
        </div>

        <div id="lic-pager" class="flex justify-center items-center mt-6"></div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Detalle -->
<div id="dlg-detalle" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 id="dlg-title" class="text-lg font-semibold">Detalle</h3>
      <div class="flex items-center gap-2">
        <button id="dlg-edit" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm"><i class="fa fa-pen mr-1"></i>Editar</button>
        <button id="dlg-close" class="p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" aria-label="Cerrar"><i class="fa fa-times"></i></button>
      </div>
    </div>
    <div class="modal-body">
      <div id="dlg-body" class="space-y-3 text-sm"></div>
    </div>
  </div>
</div>

<!-- Confirmación eliminar -->
<div id="dlg-confirm" class="modal-backdrop">
  <div class="modal max-w-md">
    <div class="modal-header">
      <h3 class="text-lg font-semibold">Confirmar</h3>
      <button id="c-close" class="p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body">
      <p id="c-text" class="mb-4">¿Eliminar?</p>
      <div class="flex justify-end gap-2">
        <button id="c-cancel" class="px-3 py-1.5 rounded-lg border border-[var(--border-color)]">Cancelar</button>
        <button id="c-ok" class="px-3 py-1.5 rounded-lg bg-red-600 text-white">Eliminar</button>
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

  // Nueva
  document.getElementById('btn-nueva')?.addEventListener('click', (e) => {
    e.preventDefault(); location.href = '/console/licencias/crear/index.php';
  });
});
</script>

<script>
window.LIC_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar:   "/console/licencias/ajax/listar.php",
    kpis:     "/console/licencias/ajax/kpis.php",
    opciones: "/console/licencias/ajax/opciones.php",
    exportar: "/console/licencias/ajax/exportar.php",
    detalle:  "/console/licencias/ajax/detalle.php",
    eliminar: "/console/licencias/ajax/eliminar.php"
  },
  page: { limit: 200 }
});
</script>
<script src="/console/asset/js/licencias/licencias.js" defer></script>
</body>
</html>   
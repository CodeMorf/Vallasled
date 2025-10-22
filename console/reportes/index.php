<?php
// /console/reportes/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Reportes';
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
<link rel="stylesheet" href="/console/asset/css/reportes/reportes.css">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Reportes</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-database"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Registros</p><p id="kpi-reg" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-check-circle"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">OK</p><p id="kpi-ok" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-hourglass-half"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Pendientes</p><p id="kpi-pend" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow"><div class="flex items-center gap-4">
          <div class="bg-gray-100 dark:bg-gray-500/20 text-gray-600 dark:text-gray-300 rounded-full h-12 w-12 flex items-center justify-center"><i class="fas fa-layer-group"></i></div>
          <div><p class="text-sm font-medium text-[var(--text-secondary)]">Total</p><p id="kpi-total" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        </div></div>
      </div>

      <!-- Filtros -->
      <div class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
          <div>
            <label for="report-type" class="block text-sm font-semibold mb-1">Tipo de Reporte</label>
            <select id="report-type" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="facturas" selected>Facturas</option>
              <option value="vallas">Vallas</option>
              <option value="licencias">Licencias</option>
              <option value="clientes">Clientes</option>
              <option value="proveedores">Proveedores</option>
            </select>
          </div>
          <div>
            <label for="date-from" class="block text-sm font-semibold mb-1">Desde</label>
            <input id="date-from" type="date" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label for="date-to" class="block text-sm font-semibold mb-1">Hasta</label>
            <input id="date-to" type="date" class="w-full border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="flex gap-2">
            <button id="generate-report" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
              <i class="fas fa-sync-alt"></i><span>Generar</span>
            </button>
            <button id="export-csv" class="bg-green-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2" disabled>
              <i class="fas fa-file-csv"></i><span>Exportar</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Resultados -->
      <div class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md">
        <h2 id="report-title" class="text-lg sm:text-xl font-bold text-[var(--text-primary)] mb-4">Resultados del Reporte</h2>
        <div id="report-container" class="overflow-auto max-h-[65vh] relative">
          <div id="report-placeholder" class="text-center py-16 text-[var(--text-secondary)]">
            <i class="fas fa-file-alt fa-3x mb-4"></i>
            <p>Elija filtros y pulse Generar.</p>
          </div>
          <table id="report-table" class="w-full text-left hidden">
            <thead class="sticky top-0 z-10 bg-[var(--card-bg)]"></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="report-pager" class="mt-4 flex items-center justify-between hidden">
          <button id="pg-prev" class="px-3 py-2 rounded-lg border border-[var(--border-color)]"><i class="fa fa-chevron-left mr-2"></i>Prev</button>
          <div class="text-sm text-[var(--text-secondary)]"><span id="pg-info">—</span></div>
          <button id="pg-next" class="px-3 py-2 rounded-lg border border-[var(--border-color)]">Next<i class="fa fa-chevron-right ml-2"></i></button>
        </div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Tema (igual que licencias)
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
});
</script>

<script>
window.REPORTES_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    generar: "/console/reportes/ajax/generar.php",
    resumen: "/console/reportes/ajax/resumen.php",
    opciones: "/console/reportes/ajax/opciones.php",
    exportar: "/console/reportes/ajax/export.php"
  },
  page: { limit: 200 }
});
</script>
<script src="/console/asset/js/reportes/reportes.js" defer></script>
</body>
</html>

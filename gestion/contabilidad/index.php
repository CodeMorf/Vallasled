<?php
// /console/gestion/contabilidad/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title' => 'Panel'];
$title = (($brand['title'] ?? 'Panel') ?: 'Panel') . ' - Contabilidad';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<meta name="theme-color" content="#111827">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- CSS base + módulo -->
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/contabilidad/index.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require dirname(__DIR__, 2) . '/asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <!-- Header -->
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar sidebar">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Contabilidad</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-3">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg"></i>
        </button>
        <button id="btn-nueva" class="hidden px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Nueva Transacción
        </button>
      </div>
    </header>

    <!-- Contenido -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
      <!-- KPIs -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-300"><i class="fas fa-arrow-up"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Ingresos</p>
            <p id="kpi-ingresos" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-300"><i class="fas fa-arrow-down"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Egresos</p>
            <p id="kpi-egresos" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-300"><i class="fas fa-hand-holding-dollar"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Comisiones Pagadas</p>
            <p id="kpi-comisiones" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300"><i class="fas fa-balance-scale"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Balance</p>
            <p id="kpi-balance" class="text-2xl font-bold text-emerald-500">—</p>
          </div>
        </div>
      </section>

      <!-- Filtros + Tabla -->
      <section class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
          <div class="flex gap-2 sm:gap-3 flex-wrap w-full md:w-auto">
            <input id="f-desde" type="date" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input id="f-hasta" type="date" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select id="f-tipo" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Tipos</option>
              <option value="ingreso">Ingreso</option>
              <option value="egreso">Egreso</option>
            </select>
            <select id="f-cat" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Categorías</option>
              <option value="venta_publicidad">Venta de Publicidad</option>
              <option value="comision_vendor">Comisión de Vendor</option>
            </select>
            <div class="relative w-full sm:w-60">
              <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
              <input id="f-q" type="search" placeholder="Buscar..." class="w-full pl-9 pr-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
          </div>
          <button id="btn-export" class="w-full md:w-auto px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 font-semibold">
            <i class="fas fa-file-export mr-2"></i>Exportar
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left responsive-table">
            <thead>
              <tr class="border-b border-[var(--border-color)] text-sm font-semibold text-[var(--text-secondary)]">
                <th class="px-6 py-3">Fecha</th>
                <th class="px-6 py-3">Descripción</th>
                <th class="px-6 py-3">Categoría</th>
                <th class="px-6 py-3 text-right">Monto</th>
                <th class="px-6 py-3 text-center">Tipo</th>
                <th class="px-6 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody id="tx-tbody" class="text-sm divide-y divide-[var(--border-color)]"></tbody>
          </table>

          <div id="tx-empty" class="hidden text-center py-12">
            <i class="fas fa-box-open fa-2x text-[var(--text-secondary)] mb-3"></i>
            <p class="font-semibold">Sin resultados</p>
            <p class="text-[var(--text-secondary)]">Ajusta filtros.</p>
          </div>
        </div>

        <div id="tx-pager" class="flex justify-center items-center mt-6"></div>
      </section>
    </div>
  </main>

  <!-- No crees otro overlay aquí. sidebar.php lo inyecta si falta -->
</div>

<script>
/* Solo tema + enlaces a API del sidebar universal */
document.addEventListener('DOMContentLoaded', () => {
  // Tema
  const btnTheme = document.getElementById('theme-toggle');
  const iconMoon = document.getElementById('theme-toggle-dark-icon');
  const iconSun  = document.getElementById('theme-toggle-light-icon');
  const applyTheme = t => {
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    iconMoon?.classList.toggle('hidden', !dark);
    iconSun?.classList.toggle('hidden',  dark);
  };
  const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(saved);
  btnTheme?.addEventListener('click', () => {
    const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', next); applyTheme(next);
  });

  // Enlaces al sidebar universal (definido en /asset/sidebar.php)
  const btnMobile = document.getElementById('mobile-menu-button');
  const btnToggle = document.getElementById('sidebar-toggle-desktop');

  btnMobile?.addEventListener('click', () => { if (window.sidebarToggle) window.sidebarToggle(); });
  btnToggle?.addEventListener('click', () => { if (window.sidebarToggle) window.sidebarToggle(); });

  // Cerrar al hacer click en overlay (el universal lo crea con id=sidebar-overlay)
  const tryHookOverlay = () => {
    const ov = document.getElementById('sidebar-overlay');
    if (!ov) { requestAnimationFrame(tryHookOverlay); return; }
    ov.addEventListener('click', () => { if (window.sidebarClose) window.sidebarClose(); });
  };
  tryHookOverlay();
});
</script>

<script>
window.CONTAB_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar:   "/console/gestion/contabilidad/ajax/listar.php",
    kpis:     "/console/gestion/contabilidad/ajax/kpis.php",
    exportar: "/console/gestion/contabilidad/ajax/exportar.php"
  },
  page: { limit: 20 }
});
</script>
<script src="/console/asset/js/contabilidad/index.js" defer></script>
</body>
</html>

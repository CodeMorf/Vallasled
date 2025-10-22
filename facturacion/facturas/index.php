<?php
// /console/facturacion/facturas/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$branding = load_branding($conn);
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

/** Proveedores para filtro */
$proveedores = [];
try {
  $res = $conn->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC");
  while ($res && ($row = $res->fetch_assoc())) $proveedores[] = $row;
  if ($res) $res->free();
} catch (Throwable $e) {
  $proveedores = [];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<meta name="theme-color" content="#111827">
<title><?=$title?> - Todas las Facturas</title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind / Icons / Fonts -->
<script>tailwind=undefined</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

<!-- CSS propio de la lista (no toca el sidebar) -->
<link rel="stylesheet" href="/console/asset/css/facturacion/lista.css">
<link rel="stylesheet" href="/console/asset/css/base.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">

<div class="flex h-screen relative">
  <?php require dirname(__DIR__, 2) . '/asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <!-- Estos IDs los usa el sidebar universal -->
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Todas las Facturas</h1>
      </div>
      <div class="flex items-center space-x-4">
        <a href="/console/facturacion/facturas/crear.php" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center gap-2">
          <i class="fas fa-plus"></i><span class="hidden sm:inline">Crear Factura</span>
        </a>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <!-- Filtros + Totales -->
        <div class="flex flex-col lg:flex-row justify-between items-start gap-4 mb-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 w-full filters-wrap">
            <input type="text" id="flt-rango" placeholder="Rango de Fechas" class="w-full px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" autocomplete="off">
            <select id="flt-proveedor" class="w-full px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los Proveedores</option>
              <?php foreach ($proveedores as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <select id="flt-estado" class="w-full px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los Estados</option>
              <option value="pagado">Pagado</option>
              <option value="pendiente">Pendiente</option>
            </select>
            <input id="flt-q" type="text" placeholder="Buscar por cliente o Nº..." class="w-full px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" autocomplete="off">
          </div>

          <div class="flex items-center gap-4 w-full lg:w-auto flex-shrink-0">
            <div class="kpi-card bg-yellow-100 dark:bg-yellow-500/20 text-center">
              <p class="text-sm font-medium text-yellow-600 dark:text-yellow-300">Pendiente</p>
              <p id="kpi-pendiente-sum" class="text-lg font-bold text-yellow-800 dark:text-yellow-200">—</p>
            </div>
            <div class="kpi-card bg-green-100 dark:bg-green-500/20 text-center">
              <p class="text-sm font-medium text-green-600 dark:text-green-300">Cobrado</p>
              <p id="kpi-cobrado-sum" class="text-lg font-bold text-green-800 dark:text-green-200">—</p>
            </div>
          </div>
        </div>

        <!-- Acciones en Lote -->
        <div class="flex items-center gap-4 mb-4 border-t border-[var(--border-color)] pt-4">
          <button id="btn-bulk-pagar" class="px-4 py-2 text-sm font-semibold bg-green-500 text-white rounded-lg shadow-sm hover:bg-green-600 disabled:bg-gray-300 dark:disabled:bg-gray-600" disabled>
            <i class="fas fa-check-circle mr-2"></i>Marcar como Pagadas
          </button>
          <button id="btn-bulk-eliminar" class="px-4 py-2 text-sm font-semibold bg-red-500 text-white rounded-lg shadow-sm hover:bg-red-600 disabled:bg-gray-300 dark:disabled:bg-gray-600" disabled>
            <i class="fas fa-trash-alt mr-2"></i>Eliminar Seleccionadas
          </button>
        </div>

        <!-- Tabla -->
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b-2 border-[var(--border-color)]" id="thead">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4 font-semibold w-12"><input type="checkbox" id="chk-all" class="rounded"></th>
                <th class="py-3 px-4 font-semibold" data-sort="id">Nº</th>
                <th class="py-3 px-4 font-semibold">Cliente</th>
                <th class="py-3 px-4 font-semibold hidden lg:table-cell">Proveedor</th>
                <th class="py-3 px-4 font-semibold hidden md:table-cell" data-sort="monto">Monto</th>
                <th class="py-3 px-4 font-semibold hidden xl:table-cell">Comisión</th>
                <th class="py-3 px-4 font-semibold" data-sort="estado">Estado</th>
                <th class="py-3 px-4 font-semibold hidden md:table-cell" data-sort="fecha_generada">Fecha</th>
                <th class="py-3 px-4 font-semibold text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-rows" class="divide-y divide-[var(--border-color)]">
              <!-- Render por JS -->
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <div class="flex flex-col md:flex-row justify-between items-center mt-6">
          <p id="list-stats" class="text-sm text-[var(--text-secondary)] mb-4 md:mb-0">Mostrando 0 a 0 de 0 facturas</p>
          <div id="paginador" class="flex items-center gap-1"></div>
        </div>
      </div>
    </div>
  </main>

  <!-- El sidebar universal ya autoinyecta overlay si falta; mantener uno solo con este id -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
/* Config visible para lista.js */
window.FACTURAS_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar: "/console/facturacion/facturas/ajax/listar.php",
    bulk_estado: "/console/facturacion/facturas/ajax/bulk_actualizar_estado.php",
    eliminar: "/console/facturacion/facturas/ajax/eliminar.php",
    cambiar_estado: "/console/facturacion/facturas/ajax/cambiar_estado.php"
  },
  paging: { limitDefault: 20, limitMax: 100 },
  sort: { field: "fecha_generada", order: "desc" }
});

/* Sólo tema + datepicker. El sidebar lo maneja /asset/sidebar.php */
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('theme-toggle');
  const d = document.getElementById('theme-toggle-dark-icon');
  const l = document.getElementById('theme-toggle-light-icon');

  const apply = (t) => {
    if (t === 'dark') { document.documentElement.classList.add('dark'); d.classList.remove('hidden'); l.classList.add('hidden'); }
    else { document.documentElement.classList.remove('dark'); d.classList.add('hidden'); l.classList.remove('hidden'); }
  };
  const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark':'light');
  apply(saved);
  btn?.addEventListener('click', () => {
    const nt = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('theme', nt); apply(nt);
  });

  flatpickr("#flt-rango", { mode:"range", dateFormat:"Y-m-d", locale:"es" });
});
</script>

<!-- JS único del módulo -->
<script src="/console/asset/js/facturacion/lista.js" defer></script>
</body>
</html>

<?php
// /console/gestion/pagos/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title' => 'Panel'];
$title = ($brand['title'] ?: 'Panel') . ' - Gestión de Pagos';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?></title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind y fuentes -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- CSS base + módulo Pagos (no afecta sidebar) -->
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/pagos/index.css">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Pagos</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
      <!-- Resumen -->
      <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="card flex items-center gap-4">
          <div class="pill pill-green"><i class="fas fa-check-circle fa-2x"></i></div>
          <div><p class="muted">Total Pagado</p><p id="sum-pagado" class="kpi">$0.00</p></div>
        </div>
        <div class="card flex items-center gap-4">
          <div class="pill pill-yellow"><i class="fas fa-hourglass-half fa-2x"></i></div>
          <div><p class="muted">Total Pendiente</p><p id="sum-pendiente" class="kpi">$0.00</p></div>
        </div>
        <div class="card flex items-center gap-4">
          <div class="pill pill-red"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
          <div><p class="muted">Facturas Vencidas</p><p id="sum-vencidas" class="kpi">0</p></div>
        </div>
        <div class="card flex items-center gap-4">
          <div class="pill pill-blue"><i class="fas fa-chart-line fa-2x"></i></div>
          <div><p class="muted">Facturado (30d)</p><p id="sum-30d" class="kpi">$0.00</p></div>
        </div>
      </section>

      <!-- Listado de facturas/pagos -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
          <div class="relative w-full md:w-1/3">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="pay-search" type="search" placeholder="Buscar por cliente o #factura..."
              class="w-full pl-9 pr-3 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
          </div>
          <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <select id="pay-estado" class="px-3 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Estado</option><option value="pagado">Pagado</option><option value="pendiente">Pendiente</option>
            </select>
            <input id="pay-desde" type="date" class="px-3 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <button id="pay-export" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg hover:bg-indigo-700 flex items-center gap-2">
              <i class="fas fa-file-export"></i><span>Exportar</span>
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b-2 border-[var(--border-color)]">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4">Factura #</th>
                <th class="py-3 px-4 hidden md:table-cell">Cliente</th>
                <th class="py-3 px-4 hidden lg:table-cell">F. Emisión</th>
                <th class="py-3 px-4 hidden lg:table-cell">F. Pago</th>
                <th class="py-3 px-4">Total</th>
                <th class="py-3 px-4">Estado</th>
                <th class="py-3 px-4 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="pay-tbody" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="pay-empty" class="hidden text-center py-10 text-[var(--text-secondary)]">Sin registros</div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center mt-6">
          <p id="pay-counter" class="text-sm text-[var(--text-secondary)]"></p>
          <div id="pay-pager" class="pager mt-3 md:mt-0"></div>
        </div>
      </section>

      <!-- Cuentas bancarias -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
          <h2 class="text-xl font-bold text-[var(--text-primary)]">Cuentas Bancarias para Pagos</h2>
          <button id="acct-add" class="bg-blue-600 text-white font-semibold px-4 py-2.5 rounded-lg hover:bg-blue-700 flex items-center gap-2">
            <i class="fas fa-plus"></i><span>Agregar Cuenta</span>
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b-2 border-[var(--border-color)]">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4">Banco</th>
                <th class="py-3 px-4 hidden sm:table-cell">Titular</th>
                <th class="py-3 px-4 hidden md:table-cell">Tipo</th>
                <th class="py-3 px-4 hidden lg:table-cell">Número</th>
                <th class="py-3 px-4">Estado</th>
                <th class="py-3 px-4 text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="acct-tbody" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="acct-empty" class="hidden text-center py-10 text-[var(--text-secondary)]">Sin cuentas</div>
        </div>
      </section>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal cuenta bancaria -->
<div id="acctModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-[var(--modal-overlay-bg)] opacity-0 pointer-events-none">
  <div class="modal-content bg-[var(--card-bg)] w-full max-w-md rounded-xl shadow-2xl p-6 md:p-8 transform scale-95">
    <div class="flex justify-between items-center mb-6">
      <h3 id="acct-modal-title" class="text-2xl font-bold">Agregar Cuenta</h3>
      <button type="button" data-close-modal="acctModal" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]" aria-label="Cerrar">&times;</button>
    </div>
    <form id="acct-form" class="space-y-4" autocomplete="off" novalidate>
      <input type="hidden" id="acct-id">
      <div>
        <label class="font-semibold block mb-1">Banco *</label>
        <input id="acct-banco" maxlength="100" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <div>
        <label class="font-semibold block mb-1">Titular *</label>
        <input id="acct-titular" maxlength="100" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <div>
        <label class="font-semibold block mb-1">Tipo de Cuenta *</label>
        <select id="acct-tipo" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
          <option value="Ahorros">Ahorros</option>
          <option value="Corriente">Corriente</option>
        </select>
      </div>
      <div>
        <label class="font-semibold block mb-1">Número de Cuenta *</label>
        <input id="acct-numero" maxlength="50" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="****">
      </div>
      <label class="inline-flex items-center gap-2">
        <input id="acct-activo" type="checkbox" checked><span>Activa</span>
      </label>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" data-close-modal="acctModal" class="py-2 px-4 rounded-lg bg-[var(--sidebar-active-bg)] hover:opacity-90">Cancelar</button>
        <button type="submit" class="py-2 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
window.PAGOS_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    pagos_listar: "/console/gestion/pagos/ajax/pagos_listar.php",
    resumen_get: "/console/gestion/pagos/ajax/resumen_get.php",
    export_csv: "/console/gestion/pagos/ajax/export_csv.php",
    factura_pagado: "/console/gestion/pagos/ajax/factura_marcar_pagado.php",
    factura_anular: "/console/gestion/pagos/ajax/factura_anular.php",
    cuentas_listar: "/console/gestion/pagos/ajax/cuentas_listar.php",
    cuenta_guardar: "/console/gestion/pagos/ajax/cuenta_guardar.php",
    cuenta_eliminar: "/console/gestion/pagos/ajax/cuenta_eliminar.php"
  },
  page: { limit: 20 }
});
</script>
<script src="/console/asset/js/pagos/pagos.js" defer></script>
</body>
</html>

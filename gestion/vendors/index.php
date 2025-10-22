<?php
// /console/gestion/vendors/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title' => 'Panel'];
$title = (($brand['title'] ?? 'Panel') ?: 'Panel') . ' - Gestión de Vendors';
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
<link rel="stylesheet" href="/console/asset/css/vendors/index.css">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Vendors</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="create-vendor-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Nuevo Vendor
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg"></i>
        </button>
      </div>
    </header>

    <!-- Contenido -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
      <!-- Métricas -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300"><i class="fas fa-store"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Total Vendors</p>
            <p id="kpi-total" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-300"><i class="fas fa-check-circle"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Planes Activos</p>
            <p id="kpi-planes" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-300"><i class="fas fa-ad"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Vallas Activas</p>
            <p id="kpi-vallas" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
        <div class="bg-[var(--card-bg)] p-5 rounded-xl shadow flex items-center gap-4">
          <div class="rounded-full h-12 w-12 flex items-center justify-center bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-300"><i class="fas fa-file-invoice-dollar"></i></div>
          <div>
            <p class="text-sm font-medium text-[var(--text-secondary)]">Comisiones Pendientes</p>
            <p id="kpi-pendiente" class="text-2xl font-bold text-[var(--text-primary)]">—</p>
          </div>
        </div>
      </section>

      <!-- Filtros + Listado -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-4">
          <div class="relative w-full sm:max-w-xs">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="f-q" type="search" placeholder="Buscar por nombre o email" class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
          </div>
          <div class="flex flex-wrap gap-3">
            <select id="f-estado" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los estados</option>
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
            <select id="f-plan" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los planes</option>
            </select>
            <select id="f-feature" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Cualquier feature</option>
              <option value="crm">CRM</option>
              <option value="facturacion">Facturación</option>
              <option value="mapa">Mapa</option>
              <option value="export">Exportar</option>
              <option value="ncf">Soporte NCF</option>
            </select>
            <select id="f-sort" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="recientes">Recientes</option>
              <option value="nombre">Nombre</option>
              <option value="plan">Plan</option>
              <option value="vallas">Vallas</option>
              <option value="pagado">Pagado</option>
              <option value="pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <!-- Desktop: tabla -->
        <div class="md:block hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="border-b border-[var(--border-color)] text-sm font-semibold text-[var(--text-secondary)]">
                <tr>
                  <th class="px-6 py-3">Proveedor</th>
                  <th class="px-6 py-3">Plan</th>
                  <th class="px-6 py-3 text-center">Vallas</th>
                  <th class="px-6 py-3">Facturación</th>
                  <th class="px-6 py-3 text-center">Estado</th>
                  <th class="px-6 py-3">Acciones</th>
                </tr>
              </thead>
              <tbody id="vendors-tbody" class="text-sm divide-y divide-[var(--border-color)]"></tbody>
            </table>
          </div>
          <div id="vendors-empty" class="hidden text-center py-12">
            <i class="fas fa-box-open fa-2x text-[var(--text-secondary)] mb-3"></i>
            <p class="font-semibold">Sin resultados</p>
            <p class="text-[var(--text-secondary)]">Ajusta búsqueda o filtros.</p>
          </div>
        </div>

        <!-- Móvil: cards -->
        <div id="vendors-cards" class="md:hidden grid gap-4"></div>
        <div id="vendors-empty-cards" class="md:hidden hidden text-center py-12">
          <i class="fas fa-box-open fa-2x text-[var(--text-secondary)] mb-3"></i>
          <p class="font-semibold">Sin resultados</p>
          <p class="text-[var(--text-secondary)]">Ajusta búsqueda o filtros.</p>
        </div>

        <div id="vendors-pager" class="flex justify-center items-center mt-6"></div>
      </section>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Crear/Editar Vendor -->
<div id="vendorModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-[var(--modal-overlay-bg,rgba(0,0,0,.55))] opacity-0 pointer-events-none">
  <div class="modal-content bg-[var(--card-bg)] w-full max-w-5xl rounded-xl shadow-2xl p-6 md:p-8 transform scale-95">
    <div class="flex justify-between items-center mb-6">
      <h3 id="vendor-modal-title" class="text-2xl font-bold">Nuevo Vendor</h3>
      <button type="button" data-close-modal="vendorModal" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]" aria-label="Cerrar">&times;</button>
    </div>

    <form id="vendor-form" class="space-y-5" autocomplete="off" novalidate>
      <input type="hidden" name="id" id="vendor-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="font-semibold block mb-1">Nombre *</label>
          <input id="v-nombre" name="nombre" required maxlength="120" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Contacto</label>
          <input id="v-contacto" name="contacto" maxlength="120" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Email *</label>
          <input id="v-email" name="email" type="email" required maxlength="160" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Teléfono</label>
          <input id="v-telefono" name="telefono" maxlength="40" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div class="md:col-span-2">
          <label class="font-semibold block mb-1">Dirección</label>
          <textarea id="v-direccion" name="direccion" rows="2" maxlength="255" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="font-semibold block mb-1">Plan</label>
          <select id="v-plan" name="plan_id" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
        </div>
        <div>
          <label class="font-semibold block mb-1">Inicio</label>
          <input id="v-inicio" name="fecha_inicio" type="date" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Fin</label>
          <input id="v-fin" name="fecha_fin" type="date" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
      </div>

      <div class="flex items-center justify-between">
        <label class="font-semibold">Activo</label>
        <label class="inline-flex items-center gap-2">
          <input id="v-estado" name="estado" type="checkbox" checked>
          <span>Habilitado</span>
        </label>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" data-close-modal="vendorModal" class="py-2 px-4 rounded-lg bg-[var(--sidebar-active-bg)] hover:opacity-90">Cancelar</button>
        <button type="submit" class="py-2 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
window.VENDORS_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar: "/console/gestion/vendors/ajax/listar.php",
    guardar: "/console/gestion/vendors/ajax/guardar.php",
    eliminar: "/console/gestion/vendors/ajax/eliminar.php",
    planes: "/console/gestion/planes/ajax/planes_listar.php",
    global_stats: "/console/gestion/vendors/ajax/kpis.php"
  },
  page: { limit: 20 }
});
</script>
<script src="/console/asset/js/vendors/index.js" defer></script>
</body>
</html>

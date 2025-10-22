<?php
// /console/gestion/planes/index.php
declare(strict_types=1);

// rutas robustas con open_basedir
require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title' => 'Panel'];
$title = ($brand['title'] ?: 'Panel') . ' - Planes y Comisiones';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<meta name="theme-color" content="#111827">
<title><?=$title?></title>
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
<link rel="stylesheet" href="/console/asset/css/planes/index.css">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Planes y Comisiones</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="add-plan-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Nuevo Plan
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg"></i>
        </button>
      </div>
    </header>

    <!-- Contenido -->
    <div class="p-4 sm:p-6 lg:p-8 space-y-8">
      <!-- Planes -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <h2 class="text-xl font-bold text-[var(--text-primary)]">Planes de Membresía</h2>
          <div class="flex items-center gap-2">
            <input id="plan-search" type="search" placeholder="Buscar planes"
                   class="w-full sm:w-64 px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <select id="plan-tipo-filter" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos</option>
              <option value="gratis">Gratis</option>
              <option value="mensual">Mensual</option>
              <option value="trimestral">Trimestral</option>
              <option value="anual">Anual</option>
              <option value="comision">Comisión</option>
            </select>
          </div>
        </div>

        <div id="plans-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5 gap-6"></div>

        <div id="plans-empty" class="hidden text-center py-16">
          <i class="fas fa-box-open fa-3x text-[var(--text-secondary)] mb-4"></i>
          <p class="font-semibold text-lg">No se encontraron planes</p>
          <p class="text-[var(--text-secondary)]">Ajusta búsqueda o filtros.</p>
        </div>

        <div id="plans-pager" class="flex justify-center items-center mt-6"></div>
      </section>

      <!-- Comisiones -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
          <h2 class="text-xl font-bold text-[var(--text-primary)]">Reglas de Comisión</h2>
          <div class="flex gap-2">
            <button id="edit-global-commission" class="px-3 py-2 rounded-lg bg-slate-200 text-slate-900 dark:bg-slate-600 dark:text-slate-100 hover:opacity-90">
              Editar Comisión Global
            </button>
            <button id="add-commission-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
              <i class="fas fa-plus mr-1"></i>Nueva Regla
            </button>
          </div>
        </div>

        <div class="mb-6 bg-[var(--main-bg)] p-4 rounded-lg flex flex-col sm:flex-row justify-between items-center gap-2">
          <div>
            <p class="text-sm text-[var(--text-secondary)]">Comisión Global por Defecto</p>
            <p id="commission-global-value" class="text-2xl font-bold text-indigo-500">—</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b-2 border-[var(--border-color)]">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4 font-semibold">Aplica a</th>
                <th class="py-3 px-4 font-semibold hidden sm:table-cell">Nombre</th>
                <th class="py-3 px-4 font-semibold">Comisión</th>
                <th class="py-3 px-4 font-semibold hidden md:table-cell">Vigencia</th>
                <th class="py-3 px-4 font-semibold text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="rules-tbody" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>

          <div id="rules-empty" class="hidden text-center py-12">
            <i class="fas fa-percent fa-2x text-[var(--text-secondary)] mb-3"></i>
            <p class="font-semibold">Sin reglas personalizadas</p>
          </div>
        </div>

        <div id="rules-pager" class="flex justify-center items-center mt-6"></div>
      </section>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Plan -->
<div id="planModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-[var(--modal-overlay-bg)] opacity-0 pointer-events-none">
  <div class="modal-content bg-[var(--card-bg)] w-full max-w-2xl rounded-xl shadow-2xl p-6 md:p-8 transform scale-95">
    <div class="flex justify-between items-center mb-6">
      <h3 id="plan-modal-title" class="text-2xl font-bold">Nuevo Plan</h3>
      <button type="button" data-close-modal="planModal" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]" aria-label="Cerrar">&times;</button>
    </div>

    <form id="plan-form" class="space-y-4" autocomplete="off" novalidate>
      <input type="hidden" name="id" id="plan-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="font-semibold block mb-1">Nombre *</label>
          <input id="plan-nombre" name="nombre" required maxlength="100" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Tipo *</label>
          <select id="plan-tipo" name="tipo" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <option value="gratis">Gratis</option>
            <option value="mensual">Mensual</option>
            <option value="trimestral">Trimestral</option>
            <option value="anual">Anual</option>
            <option value="comision">Comisión</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="font-semibold block mb-1">Precio</label>
          <input id="plan-precio" name="precio" type="number" step="0.01" min="0" value="0.00" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Límite Vallas</label>
          <input id="plan-limite" name="limite_vallas" type="number" min="0" value="0" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Días de Prueba</label>
          <input id="plan-prueba" name="dias_prueba" type="number" min="0" value="0" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="font-semibold block mb-1">Activo</label>
          <label class="inline-flex items-center gap-2">
            <input id="plan-activo" name="activo" type="checkbox" checked>
            <span>Habilitado</span>
          </label>
        </div>
      </div>

      <div>
        <label class="font-semibold block mb-1">Descripción</label>
        <textarea id="plan-descripcion" name="descripcion" rows="2" maxlength="255" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
      </div>

      <div>
        <h4 class="font-bold text-lg mt-2 mb-2">Características</h4>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 p-4 bg-[var(--main-bg)] rounded-lg">
          <label class="flex items-center gap-2"><input id="f_access_crm" name="access_crm" type="checkbox"> Acceso CRM</label>
          <label class="flex items-center gap-2"><input id="f_access_fact" name="access_facturacion" type="checkbox"> Facturación</label>
          <label class="flex items-center gap-2"><input id="f_access_mapa" name="access_mapa" type="checkbox"> Mapa</label>
          <label class="flex items-center gap-2"><input id="f_export" name="exportar_datos" type="checkbox"> Exportar Datos</label>
          <label class="flex items-center gap-2"><input id="f_ncf" name="soporte_ncf" type="checkbox"> Soporte NCF</label>
          <label class="flex items-center gap-2"><input id="f_fact_auto" name="factura_auto" type="checkbox"> Fact. Automática</label>
        </div>
      </div>

      <div>
        <h4 class="font-bold text-lg mt-2 mb-2">Modelo de Comisión</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <label class="flex items-center gap-2"><input type="radio" name="comision_model" value="none" checked> Ninguna</label>
          <label class="flex items-center gap-2"><input type="radio" name="comision_model" value="pct"> Porcentaje</label>
          <label class="flex items-center gap-2"><input type="radio" name="comision_model" value="flat"> Fijo</label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
          <div>
            <label class="font-semibold block mb-1">% Comisión</label>
            <input id="f_comision_pct" name="comision_pct" type="number" step="0.01" min="0" max="100" value="0.00" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" disabled>
          </div>
          <div>
            <label class="font-semibold block mb-1">Monto Fijo</label>
            <input id="f_comision_flat" name="comision_flat" type="number" step="0.01" min="0" value="0.00" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" disabled>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" data-close-modal="planModal" class="py-2 px-4 rounded-lg bg-[var(--sidebar-active-bg)] hover:opacity-90">Cancelar</button>
        <button type="submit" class="py-2 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Regla de Comisión -->
<div id="commissionModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-[var(--modal-overlay-bg)] opacity-0 pointer-events-none">
  <div class="modal-content bg-[var(--card-bg)] w-full max-w-xl rounded-xl shadow-2xl p-6 md:p-8 transform scale-95">
    <div class="flex justify-between items-center mb-6">
      <h3 id="rule-modal-title" class="text-2xl font-bold">Nueva Regla de Comisión</h3>
      <button type="button" data-close-modal="commissionModal" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]" aria-label="Cerrar">&times;</button>
    </div>

    <form id="rule-form" class="space-y-4" autocomplete="off" novalidate>
      <input type="hidden" name="id" id="rule-id">

      <div>
        <label class="font-semibold block mb-2">Tipo de Regla *</label>
        <div class="flex flex-wrap gap-6">
          <label class="flex items-center gap-2"><input type="radio" name="rule_type" value="proveedor" checked> Por Proveedor</label>
          <label class="flex items-center gap-2"><input type="radio" name="rule_type" value="valla"> Por Valla</label>
        </div>
      </div>

      <div id="rule-scope-proveedor" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="font-semibold block mb-1">Proveedor *</label>
          <select id="rule-proveedor" name="proveedor_id" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
        </div>
        <div class="hidden" id="rule-valla-wrap">
          <label class="font-semibold block mb-1">Valla *</label>
          <select id="rule-valla" name="valla_id" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="font-semibold block mb-1">% Comisión *</label>
          <input id="rule-pct" name="comision_pct" type="number" step="0.01" min="0" max="100" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="font-semibold block mb-1">Vigente Desde *</label>
            <input id="rule-desde" name="desde" type="date" required class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
          </div>
          <div>
            <label class="font-semibold block mb-1">Vigente Hasta</label>
            <input id="rule-hasta" name="hasta" type="date" class="w-full p-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" data-close-modal="commissionModal" class="py-2 px-4 rounded-lg bg-[var(--sidebar-active-bg)] hover:opacity-90">Cancelar</button>
        <button type="submit" class="py-2 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
window.PLANES_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    planes_listar: "/console/gestion/planes/ajax/planes_listar.php",
    plan_guardar: "/console/gestion/planes/ajax/plan_guardar.php",
    plan_eliminar: "/console/gestion/planes/ajax/plan_eliminar.php",
    comisiones_listar: "/console/gestion/planes/ajax/comisiones_listar.php",
    comision_guardar: "/console/gestion/planes/ajax/comision_guardar.php",
    comision_eliminar: "/console/gestion/planes/ajax/comision_eliminar.php",
    proveedores: "/console/gestion/planes/ajax/proveedores.php",
    vallas_por_proveedor: "/console/gestion/planes/ajax/vallas_por_proveedor.php",
    global_get: "/console/gestion/planes/ajax/global_get.php",
    global_set: "/console/gestion/planes/ajax/global_set.php"
  },
  page: { limit: 20 }
});
</script>
<script src="/console/asset/js/planes/planes.js" defer></script>
</body>
</html>

<?php
declare(strict_types=1);

// includes seguros dentro de open_basedir
//console/gestion/proveedores//
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3), '/');
require_once $DOCROOT . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = $brand['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?> - Gestión de Proveedores</title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind + Inter + FontAwesome -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- CSS del módulo (sin sidebar) -->
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/proveedores/index.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">

<div class="flex h-screen relative">
  <?php // El sidebar SIEMPRE se integra aquí, nunca en CSS/JS
  require $DOCROOT . '/console/asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar sidebar">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Proveedores</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="add-provider-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Nuevo
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
          <input id="search-filter" type="search" placeholder="Buscar por nombre, contacto, email…" class="input md:w-1/2 lg:w-1/3">
        </div>

        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Proveedor</th>
                <th class="hidden md:table-cell">Contacto</th>
                <th class="hidden lg:table-cell">Plan actual</th>
                <th>Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="providers-tbody" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="empty-state" class="hidden text-center py-16">
            <i class="fas fa-truck-field fa-3x text-[var(--text-secondary)] mb-4"></i>
            <p class="font-semibold text-lg">No se encontraron proveedores</p>
            <p class="text-[var(--text-secondary)]">Agrega un proveedor para comenzar.</p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Crear/Editar -->
<!-- Necesario para que el JS abra/cierre correctamente: hidden + opacity-0 en overlay, scale-95 en box -->
<div id="provider-modal" class="modal hidden opacity-0">
  <div id="provider-modal-container" class="modal__box scale-95">
    <div class="flex justify-between items-center p-6 border-b" style="border-color:var(--border-color)">
      <h2 id="modal-title" class="text-xl font-bold text-[var(--text-primary)]"></h2>
      <button id="close-modal-btn" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-2xl" aria-label="Cerrar">&times;</button>
    </div>

    <form id="provider-form" class="p-6 space-y-4" autocomplete="off">
      <input type="hidden" id="provider-id">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Nombre *</label>
          <input id="nombre" required class="input mt-1">
        </div>
        <div>
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Contacto</label>
          <input id="contacto" class="input mt-1">
        </div>
        <div>
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Email</label>
          <input id="email" type="email" class="input mt-1">
        </div>
        <div>
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Teléfono</label>
          <input id="telefono" class="input mt-1">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Dirección</label>
          <textarea id="direccion" rows="2" class="input mt-1"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-[var(--text-secondary)]">Plan</label>
          <select id="plan_id" class="select mt-1"></select>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-3">
        <button type="button" id="cancel-btn" class="px-5 py-2 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 rounded-lg">Cancelar</button>
        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
window.PROVEEDORES_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  listar:  "/console/gestion/proveedores/ajax/listar.php",
  planes:  "/console/gestion/proveedores/ajax/planes.php",
  guardar: "/console/gestion/proveedores/ajax/guardar.php",
  eliminar:"/console/gestion/proveedores/ajax/eliminar.php",
});
</script>
<script src="/console/asset/js/proveedores/index.js" defer></script>
</body>
</html>

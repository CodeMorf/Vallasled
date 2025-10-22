<?php
// /console/facturacion/datos-bancarios/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = $brand['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?> - Datos Bancarios</title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind / Icons / Fonts -->
<script>tailwind=undefined</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Base + módulo -->
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/facturacion/bancos.css">

<style>
:root{
  --sidebar-bg:#F9FAFB; --sidebar-text:#4B5563; --sidebar-text-hover:#111827;
  --sidebar-active-bg:#E5E7EB; --sidebar-active-text:#1F2937;
  --main-bg:#F3F4F6; --header-bg:#FFFFFF; --card-bg:#FFFFFF;
  --text-primary:#1F2937; --text-secondary:#6B7280;
  --border-color:#E5E7EB; --input-bg:#FFFFFF;
  --ok:#16a34a; --err:#dc2626;
}
html.dark{
  --sidebar-bg:#111827; --sidebar-text:#9CA3AF; --sidebar-text-hover:#F9FAFB;
  --sidebar-active-bg:#374151; --sidebar-active-text:#F9FAFB;
  --main-bg:#1F2937; --header-bg:#111827; --card-bg:#374151;
  --text-primary:#F9FAFB; --text-secondary:#D1D5DB;
  --border-color:#4B5563; --input-bg:#4B5563;
}
body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--main-bg);color:var(--text-primary)}
#sidebar-overlay{pointer-events:auto}
@media (max-width:767.98px){ html,body{overflow-x:hidden} body.sidebar-open{overflow:hidden} .flex.h-screen{height:100dvh;max-width:100%;contain:layout} input,select,button{font-size:16px} }
</style>
</head>
<body class="overflow-x-hidden">

<div class="flex h-screen relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Datos Bancarios</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="add-account-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Agregar
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex flex-col sm:flex-row gap-3 mb-4 justify-between">
          <div class="flex gap-2 items-center w-full sm:w-auto">
            <input id="flt-q" type="search" placeholder="Buscar banco, titular o cuenta" class="w-full sm:w-80 px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" autocomplete="off">
            <select id="flt-estado" class="px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </div>
          <div class="text-sm text-[var(--text-secondary)]">Total: <span id="stat-total">0</span></div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="border-b-2 border-[var(--border-color)]">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4 font-semibold">Banco</th>
                <th class="py-3 px-4 font-semibold">Titular</th>
                <th class="py-3 px-4 font-semibold hidden md:table-cell">Tipo</th>
                <th class="py-3 px-4 font-semibold hidden lg:table-cell">Número</th>
                <th class="py-3 px-4 font-semibold">Estado</th>
                <th class="py-3 px-4 font-semibold text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="accounts-tbody" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="empty-state" class="hidden text-center py-16">
            <i class="fas fa-university fa-3x text-[var(--text-secondary)] mb-4"></i>
            <p class="font-semibold text-lg">Sin registros</p>
            <p class="text-[var(--text-secondary)]">Agrega tu primera cuenta.</p>
          </div>
        </div>

        <div class="flex justify-center items-center mt-6" id="pager"></div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- MODAL -->
<div id="account-modal" class="modal-overlay fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50 opacity-0">
  <div id="account-modal-container" class="modal-container bg-[var(--card-bg)] w-full max-w-lg p-6 rounded-xl shadow-lg transform scale-95">
    <div class="flex justify-between items-center mb-4">
      <h2 id="modal-title" class="text-lg font-bold text-[var(--text-primary)]">Agregar Cuenta</h2>
      <button id="close-modal-btn" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i class="fa fa-times"></i></button>
    </div>
    <form id="account-form" class="space-y-4" autocomplete="off">
      <input type="hidden" id="account-id" value="">
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">Banco *</label>
        <input id="banco" class="w-full px-3 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)]" required>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Titular *</label>
          <input id="titular" class="w-full px-3 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)]" required>
        </div>
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Tipo de cuenta *</label>
          <select id="tipo-cuenta" class="w-full px-3 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)]">
            <option>Corriente</option>
            <option>Ahorros</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">Número de cuenta *</label>
        <input id="numero-cuenta" class="w-full px-3 py-2 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)]" required>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="cancel-btn" class="px-3 py-2 rounded-lg bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-100">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
  window.BANCOS_CFG = Object.freeze({
    csrf: "<?=$csrf?>",
    listar: "/console/facturacion/datos-bancarios/ajax/listar.php",
    guardar: "/console/facturacion/datos-bancarios/ajax/guardar.php",
    estados: "/console/facturacion/datos-bancarios/ajax/estados.php",
    page: {limit: 20}
  });
</script>

<script src="/console/asset/js/facturacion/bancos.js" defer></script>
<!-- No registrar SW aquí: PWA global ya lo hace /config/db.php -->
</body>
</html>

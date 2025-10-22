<?php
// /console/facturacion/clientes/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
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
<title><?=$title?> - Clientes</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/cliente/clientes.lista.css">
</head>
<body class="overflow-x-hidden">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú"><i class="fas fa-bars fa-lg"></i></button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú"><i class="fas fa-bars fa-lg"></i></button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Clientes</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="new-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-file-import mr-1"></i>Importar
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md border border-[var(--border-color)]">

        <!-- Filtros -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
          <div class="flex flex-1 items-center gap-3">
            <input id="cli-search" type="search" placeholder="Buscar nombre, email, teléfono…" class="w-full md:w-96 px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <select id="cli-proveedor" class="px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
            <!-- Botón Import icon-only -->
            <button id="cli-import-btn" class="p-2.5 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)] text-[var(--text-secondary)]" title="Importar">
              <i class="fa fa-file-import"></i>
            </button>
          </div>
          <div class="text-sm text-[var(--text-secondary)]">
            <span>Resultados: </span><span id="total-clientes" data-total="0">0</span>
          </div>
        </div>

        <!-- Panel Importar -->
        <div id="cli-import-panel" class="hidden mb-6">
          <div class="bg-[var(--card-bg)] border border-[var(--border-color)] rounded-xl p-5">
            <h3 class="font-semibold mb-3">Importar clientes</h3>
            <div id="cli-dropzone" class="dropzone p-6 text-center rounded-xl">
              <p class="text-[var(--text-secondary)]">Arrastra un CSV o pega texto abajo</p>
            </div>
            <textarea id="cli-paste" rows="4" class="w-full mt-4 p-3 rounded-lg border border-[var(--border-color)] bg-[var(--input-bg)]" placeholder="Nombre | Email | Teléfono | Empresa"></textarea>
            <div class="flex justify-between items-center mt-2 text-sm text-[var(--text-secondary)]">
              <div>Válidas: <span id="valid-rows">0</span> · Errores: <span id="error-rows">0</span></div>
            </div>
          </div>
        </div>

        <!-- Grid -->
        <div id="cli-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"></div>
        <div id="cli-empty-state" class="hidden text-center py-16">
          <i class="fas fa-users fa-3x text-[var(--text-secondary)] mb-4"></i>
          <p class="font-semibold text-lg">Sin clientes</p>
          <p class="text-[var(--text-secondary)]">Ajusta búsqueda o usa “Importar”.</p>
        </div>

        <!-- Pager -->
        <div id="cli-pager" class="flex justify-center items-center mt-8"></div>

      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
  window.CLIENTES_CFG = Object.freeze({
    listar: "/console/facturacion/clientes/ajax/listar.php",
    importar: "/console/facturacion/clientes/ajax/importar.php",
    page: { limit: 12 }
  });
</script>
<script src="/console/asset/js/cliente/clientes.lista.js" defer></script>
</body>
</html>

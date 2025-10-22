<?php
// /console/vallas/index.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$branding = load_branding($conn);
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=htmlspecialchars($title)?> · Vallas</title>
<link rel="icon" href="<?=$fav?>"><meta name="theme-color" content="#111827"/>
<meta name="csrf" content="<?=htmlspecialchars($csrf)?>">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/vallas.css">
<script>tailwind.config={darkMode:'class'}</script>
</head>
<body class="overflow-x-hidden">
<div class="flex min-h-[100dvh] relative">

  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] p-3 sm:p-4 flex justify-between items-center sticky top-0 z-20 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-3 sm:gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold">Gestión de Vallas</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]"><i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i><i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i></button>
      </div>
    </header>

    <div class="p-3 sm:p-4 lg:p-6">
      <section class="card rounded-xl p-4 sm:p-6">
        <!-- Filtros -->
        <div class="flex flex-col xl:flex-row justify-between items-start gap-4 mb-5">
          <div class="relative w-full xl:w-1/3">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="f-q" type="text" placeholder="Buscar por nombre o ubicación…" class="w-full pl-9 pr-3 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="flex flex-col md:flex-row items-center gap-3 w-full xl:w-auto">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 w-full">
              <select id="f-proveedor" class="inp-select"><option value="">Proveedor</option></select>
              <select id="f-disp" class="inp-select">
                <option value="">Disponibilidad</option><option value="1">Disponible</option><option value="0">Ocupado</option>
              </select>
              <select id="f-publico" class="inp-select">
                <option value="">Precio Público</option><option value="1">Sí</option><option value="0">No</option>
              </select>
              <select id="f-ads" class="inp-select">
                <option value="">Publicidad</option><option value="1">Destacadas</option><option value="0">Todas</option>
              </select>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
              <div class="view-toggle flex items-center border border-[var(--border-color)] rounded-lg p-1 bg-[var(--input-bg)]">
                <button id="grid-view-btn" class="view-btn active" title="Cuadrícula"><i class="fas fa-th-large"></i></button>
                <button id="list-view-btn" class="view-btn" title="Lista"><i class="fas fa-list"></i></button>
              </div>
              <button id="drag-lock-btn" type="button" class="lock-btn" title="Bloquear/Desbloquear arrastre">
                <i class="fas fa-lock"></i><i class="fas fa-lock-open"></i>
              </button>
              <a href="/console/vallas/editar.php" class="btn-primary"><i class="fas fa-plus"></i><span class="hidden xl:inline">&nbsp;Agregar</span></a>
            </div>
          </div>
        </div>

        <!-- Grid -->
        <div id="grid-view" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>

        <!-- Lista -->
        <div id="list-view" class="overflow-x-auto hidden">
          <table class="w-full text-left min-w-[840px]">
            <thead class="border-b-2 border-[var(--border-color)]">
              <tr class="text-sm text-[var(--text-secondary)]">
                <th class="py-3 px-4 font-semibold">Valla</th>
                <th class="py-3 px-4 font-semibold hidden lg:table-cell">Tipo</th>
                <th class="py-3 px-4 font-semibold hidden md:table-cell">Proveedor</th>
                <th class="py-3 px-4 font-semibold hidden lg:table-cell">Precio/Mes</th>
                <th class="py-3 px-4 font-semibold">Estado</th>
                <th class="py-3 px-4 font-semibold text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="tbody-list" class="divide-y divide-[var(--border-color)]"></tbody>
          </table>
        </div>

        <!-- Paginación -->
        <div id="pager" class="flex flex-col md:flex-row justify-between items-center mt-6 gap-3"></div>
      </section>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="/console/asset/js/vallas.js"></script>
</body>
</html>

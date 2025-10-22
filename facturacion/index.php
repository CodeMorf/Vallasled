<?php
// /console/facturacion/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?> - Dashboard de Facturación</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/facturacion/dashboard.css">
<script>tailwind.config={darkMode:'class'}</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="overflow-x-hidden">

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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Dashboard de Facturación</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <!-- KPIs -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-[var(--text-secondary)]">Total Cobrado</p>
              <p id="kpi-cobrado" class="text-2xl font-bold text-[var(--text-primary)] mt-1">—</p>
            </div>
            <div class="bg-green-100 dark:bg-green-500/20 p-3 rounded-full">
              <i class="fas fa-check-circle fa-lg text-green-500 dark:text-green-300"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i><span id="kpi-cobrado-vs">—</span>
          </div>
        </div>

        <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-[var(--text-secondary)]">Pendiente de Cobro</p>
              <p id="kpi-pendiente" class="text-2xl font-bold text-[var(--text-primary)] mt-1">—</p>
            </div>
            <div class="bg-yellow-100 dark:bg-yellow-500/20 p-3 rounded-full">
              <i class="fas fa-hourglass-half fa-lg text-yellow-500 dark:text-yellow-300"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center text-sm text-[var(--text-secondary)]">
            <span id="kpi-pendiente-det">—</span>
          </div>
        </div>

        <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-[var(--text-secondary)]">Facturas Vencidas</p>
              <p id="kpi-vencidas" class="text-2xl font-bold text-[var(--text-primary)] mt-1">—</p>
            </div>
            <div class="bg-red-100 dark:bg-red-500/20 p-3 rounded-full">
              <i class="fas fa-exclamation-triangle fa-lg text-red-500 dark:text-red-300"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center text-sm text-red-500">
            <i class="fas fa-arrow-down mr-1"></i><span id="kpi-vencidas-vs">—</span>
          </div>
        </div>

        <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-[var(--text-secondary)]">Nuevas Facturas (Mes)</p>
              <p id="kpi-nuevas" class="text-2xl font-bold text-[var(--text-primary)] mt-1">—</p>
            </div>
            <div class="bg-blue-100 dark:bg-blue-500/20 p-3 rounded-full">
              <i class="fas fa-file-invoice-dollar fa-lg text-blue-500 dark:text-blue-300"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i><span id="kpi-nuevas-vs">—</span>
          </div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 space-y-6">
          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">Resumen Financiero</h2>
            <div class="h-80 relative">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>

          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">Actividad Reciente</h2>
            <div id="actividad-list" class="space-y-4">
              <!-- Rellenado por JS -->
            </div>
          </div>
        </div>

        <div class="lg:col-span-2 bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">Accesos Rápidos</h2>
          <div class="space-y-3">
            <a href="/console/facturacion/facturas/crear.php" class="flex items-center justify-between p-4 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] transition-colors group">
              <div class="flex items-center gap-4">
                <i class="fas fa-plus-circle fa-lg text-indigo-500"></i><span class="font-medium">Crear Nueva Factura</span>
              </div>
              <i class="fas fa-arrow-right text-transparent group-hover:text-[var(--text-secondary)] transition-colors"></i>
            </a>
            <a href="/console/facturacion/facturas/" class="flex items-center justify-between p-4 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] transition-colors group">
              <div class="flex items-center gap-4">
                <i class="fas fa-list-alt fa-lg text-indigo-500"></i><span class="font-medium">Ver Todas las Facturas</span>
              </div>
              <i class="fas fa-arrow-right text-transparent group-hover:text-[var(--text-secondary)] transition-colors"></i>
            </a>
            <a href="/console/facturacion/clientes/" class="flex items-center justify-between p-4 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] transition-colors group">
              <div class="flex items-center gap-4">
                <i class="fas fa-users fa-lg text-indigo-500"></i><span class="font-medium">Gestionar Clientes</span>
              </div>
              <i class="fas fa-arrow-right text-transparent group-hover:text-[var(--text-secondary)] transition-colors"></i>
            </a>
            <a href="/console/facturacion/ncf/" class="flex items-center justify-between p-4 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] transition-colors group">
              <div class="flex items-center gap-4">
                <i class="fas fa-file-alt fa-lg text-indigo-500"></i><span class="font-medium">Gestionar NCF</span>
              </div>
              <i class="fas fa-arrow-right text-transparent group-hover:text-[var(--text-secondary)] transition-colors"></i>
            </a>
            <a href="/console/facturacion/datos-bancarios" class="flex items-center justify-between p-4 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] transition-colors group">
              <div class="flex items-center gap-4">
                <i class="fas fa-university fa-lg text-indigo-500"></i><span class="font-medium">Cuentas Bancarias</span>
              </div>
              <i class="fas fa-arrow-right text-transparent group-hover:text-[var(--text-secondary)] transition-colors"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden"></div>
</div>

<script src="/console/asset/js/facturacion/dashboard.js"></script>
</body>
</html>

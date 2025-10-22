<?php
// Staff dashboard simple
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || !in_array($_SESSION['tipo'] ?? '', ['staff','admin'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel','logo_url'=>null];
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?> · Mi Panel</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/empleado/panel.css">
<script>tailwind.config={darkMode:'class'}</script>
</head>
<body class="overflow-x-hidden">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Mi Panel</h1>
      </div>
      <div class="flex items-center gap-3">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
      <!-- KPI cards -->
      <section>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" id="kpi-cards">
          <!-- JS rellena -->
        </div>
      </section>

      <!-- Listas rápidas -->
      <section class="grid gap-6 lg:grid-cols-2">
        <div class="panel">
          <div class="panel-h">
            <h2 class="panel-title"><i class="fas fa-calendar-check mr-2"></i>Reservas recientes</h2>
          </div>
          <div id="res-list" class="divide-y divide-[var(--border-color)]"></div>
          <div class="panel-f"><a class="link" href="/console/reservas/">Ver todas</a></div>
        </div>

        <div class="panel">
          <div class="panel-h">
            <h2 class="panel-title"><i class="fas fa-file-invoice-dollar mr-2"></i>Facturas recientes</h2>
          </div>
          <div id="fac-list" class="divide-y divide-[var(--border-color)]"></div>
          <div class="panel-f"><a class="link" href="/console/facturacion/">Ir a facturación</a></div>
        </div>
      </section>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 md:hidden hidden"></div>
</div>

<script src="/console/asset/js/empleado/panel.js"></script>
</body>
</html>

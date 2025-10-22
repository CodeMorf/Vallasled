<?php
// /console/portal/index.php
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
<title><?=htmlspecialchars($title)?> · Dashboard</title>
<link rel="icon" href="<?=$fav?>"><meta name="theme-color" content="#111827"/>
<meta name="csrf" content="<?=htmlspecialchars($csrf)?>">
<link rel="manifest" href="/console/pwa/manifest.json">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/dashboard.css">
<script>tailwind.config={darkMode:'class'}</script>
</head>
<body class="overflow-x-hidden">
<div class="flex min-h-[100dvh] relative">

  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="header bg-[var(--header-bg)] p-3 sm:p-4 flex justify-between items-center sticky top-0 z-30 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-3 sm:gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:flex p-2 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars"></i>
        </button>
        <div class="min-w-0">
          <h1 class="text-[clamp(18px,2.5vw,22px)] font-bold truncate">Dashboard</h1>
          <p class="hidden sm:block text-[var(--text-secondary)] text-xs sm:text-sm mt-0.5">Bienvenido de nuevo</p>
        </div>
      </div>
      <div class="flex items-center gap-2 sm:gap-3">
        <button class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" title="Notificaciones">
          <i class="fas fa-bell"></i>
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" title="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun hidden"></i>
        </button>
      </div>
    </header>

    <div class="main p-3 sm:p-4 lg:p-6 space-y-4 sm:space-y-6">

      <!-- KPIs -->
      <section class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
        <div class="card rounded-xl p-3 sm:p-5 flex items-center justify-between">
          <div><p class="kpi-label">Vallas Totales</p><p id="k_vallas" class="kpi skel"></p></div>
          <i class="fas fa-object-group kpi-icon"></i>
        </div>
        <div class="card rounded-xl p-3 sm:p-5 flex items-center justify-between">
          <div><p class="kpi-label">Ingresos del Mes</p><p id="k_ingresos" class="kpi skel"></p></div>
          <i class="fas fa-dollar-sign kpi-icon"></i>
        </div>
        <div class="card rounded-xl p-3 sm:p-5 flex items-center justify-between">
          <div><p class="kpi-label">Nuevas Reservas</p><p id="k_reservas" class="kpi skel"></p></div>
          <i class="fas fa-calendar-plus kpi-icon"></i>
        </div>
        <div class="card rounded-xl p-3 sm:p-5 flex items-center justify-between">
          <div><p class="kpi-label">Ads Destacados</p><p id="k_ads" class="kpi skel"></p></div>
          <i class="fas fa-star kpi-icon"></i>
        </div>
      </section>

      <!-- Gráficos -->
      <section class="grid grid-cols-1 lg:grid-cols-5 gap-4 sm:gap-6">
        <div class="card lg:col-span-3 rounded-xl p-3 sm:p-6">
          <h3 class="sec-title">Ingresos (6 meses)</h3>
          <div class="chart-wrap">
            <canvas id="revenueChart" class="chart"></canvas>
            <div id="skel-rev" class="skel skel-chart"></div>
          </div>
        </div>
        <div class="card lg:col-span-2 rounded-xl p-3 sm:p-6">
          <h3 class="sec-title">Tipos de Vallas</h3>
          <div class="chart-wrap center">
            <div class="w-full max-w-[18rem]">
              <canvas id="billboardTypeChart" class="chart"></canvas>
            </div>
            <div id="skel-typ" class="skel skel-chart"></div>
          </div>
        </div>
      </section>

      <!-- Reservas: cards móvil, tabla desktop -->
      <section class="card rounded-xl p-3 sm:p-6">
        <h3 class="sec-title">Reservas Recientes</h3>

        <!-- Cards móvil -->
        <div id="list-reservas" class="sm:hidden space-y-3">
          <div class="card-item skel-block"></div>
          <div class="card-item skel-block"></div>
        </div>

        <!-- Tabla desktop -->
        <div class="hidden sm:block overflow-x-auto -mx-1">
          <table class="w-full text-sm text-left min-w-[720px]">
            <thead class="thead">
              <tr>
                <th class="th">Valla</th><th class="th">Cliente</th><th class="th">Fechas</th><th class="th">Monto</th><th class="th text-center">Estado</th>
              </tr>
            </thead>
            <tbody id="tbody-reservas"></tbody>
          </table>
        </div>
      </section>

      <!-- Secundarias -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <div class="card rounded-xl p-3 sm:p-6">
          <h3 class="sec-title">Licencias Próximas a Vencer</h3>
          <div id="list-licencias" class="space-y-3 sm:hidden">
            <div class="skel h-4 w-2/3 rounded"></div><div class="skel h-4 w-1/2 rounded"></div>
          </div>
          <div class="hidden sm:block overflow-y-auto max-h-64">
            <table class="w-full text-sm text-left">
              <thead class="thead-sm"><tr><th class="th-sm">Valla</th><th class="th-sm">Vence en</th></tr></thead>
              <tbody id="tbody-licencias"></tbody>
            </table>
          </div>
        </div>
        <div class="card rounded-xl p-3 sm:p-6">
          <h3 class="sec-title">Últimas Vallas Agregadas</h3>
          <div id="list-vallas" class="space-y-3 sm:hidden">
            <div class="skel h-4 w-2/3 rounded"></div><div class="skel h-4 w-1/2 rounded"></div>
          </div>
          <div class="hidden sm:block overflow-y-auto max-h-64">
            <table class="w-full text-sm text-left">
              <thead class="thead-sm"><tr><th class="th-sm">Nombre</th><th class="th-sm">Tipo</th><th class="th-sm">Fecha</th></tr></thead>
              <tbody id="tbody-vallas"></tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </main>

  <!-- Bottom app bar móvil -->
  <nav class="md:hidden fixed bottom-0 left-0 right-0 appbar bg-[var(--card-bg)] border-t border-[var(--border-color)] z-40">
    <div class="flex items-center">
      <a href="/console/portal/" class="active flex flex-col items-center justify-center py-2"><i class="fa fa-gauge"></i><span>Inicio</span></a>
      <a href="/console/vallas/" class="flex flex-col items-center justify-center py-2"><i class="fa fa-ad"></i><span>Vallas</span></a>
      <a href="/console/reservas/" class="flex flex-col items-center justify-center py-2"><i class="fa fa-calendar-check"></i><span>Reservas</span></a>
      <a href="/console/reportes/" class="flex flex-col items-center justify-center py-2"><i class="fa fa-chart-pie"></i><span>Reportes</span></a>
      <a href="/console/sistema/usuarios/" class="flex flex-col items-center justify-center py-2"><i class="fa fa-user-shield"></i><span>Perfil</span></a>
    </div>
  </nav>

  <!-- FAB refrescar -->
  <button id="refresh-btn" class="md:hidden fab" title="Actualizar">
    <i class="fa fa-rotate-right"></i>
  </button>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/console/asset/js/dashboard.js"></script>
</body>
</html>

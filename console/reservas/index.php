<?php
// /console/reservas/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mapas.php';

start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = load_branding($conn);
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

/* Centro mapa: soporta map_settings.lat/lng o center_lat/center_lng */
$centerLat=18.7357; $centerLng=-70.1627; $centerZoom=9;
try {
  if ($conn->query("SHOW TABLES LIKE 'map_settings'")->num_rows) {
    $hasLat  = $conn->query("SHOW COLUMNS FROM map_settings LIKE 'lat'")->num_rows>0;
    $hasLng  = $conn->query("SHOW COLUMNS FROM map_settings LIKE 'lng'")->num_rows>0;
    $hasCLat = $conn->query("SHOW COLUMNS FROM map_settings LIKE 'center_lat'")->num_rows>0;
    $hasCLng = $conn->query("SHOW COLUMNS FROM map_settings LIKE 'center_lng'")->num_rows>0;
    $sql = $hasLat&&$hasLng
      ? "SELECT COALESCE(lat,18.7357) cLat, COALESCE(lng,-70.1627) cLng, COALESCE(zoom,9) z FROM map_settings LIMIT 1"
      : ($hasCLat&&$hasCLng
         ? "SELECT COALESCE(center_lat,18.7357) cLat, COALESCE(center_lng,-70.1627) cLng, COALESCE(zoom,9) z FROM map_settings LIMIT 1"
         : null);
    if ($sql) {
      $rs=$conn->query($sql);
      if ($rs && $rs->num_rows) { $r=$rs->fetch_assoc(); $centerLat=(float)$r['cLat']; $centerLng=(float)$r['cLng']; $centerZoom=(int)$r['z']; }
    }
  }
} catch (\Throwable $e) { /* defaults */ }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=htmlspecialchars($title,ENT_QUOTES)?> · Gestión de Reservas</title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind + UI -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/reservas/reservas.css">
<script>tailwind.config={darkMode:'class'}</script>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
</head>
<body class="overflow-x-hidden">

<!-- Notificación -->
<div id="notification" class="fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-md hidden transition-transform translate-x-full z-50">
  <p id="notification-message"></p>
</div>

<div class="flex h-screen relative">
  <!-- Sidebar -->
  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <!-- Main -->
  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Reservas</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="google-calendar-btn" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-[var(--text-secondary)] bg-[var(--input-bg)] border border-[var(--border-color)] rounded-lg hover:bg-[var(--sidebar-active-bg)]">
          <i class="fab fa-google"></i>
          <span class="hidden sm:inline">Conectar Calendario</span>
          <span id="gcal-status" class="w-2 h-2 bg-red-500 rounded-full"></span>
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema claro/oscuro">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <!-- Filtros -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
          <div class="flex flex-col sm:flex-row gap-4 w-full">
            <select id="valla-filter" class="w-full sm:w-52 px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Todas las Vallas</option>
            </select>
            <select id="estado-filter" class="w-full sm:w-48 px-4 py-2.5 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Todos los Estados</option>
              <option value="confirmada">Confirmada</option>
              <option value="pendiente">Pendiente</option>
              <option value="activa">Activa</option>
              <option value="bloqueo">Bloqueado</option>
            </select>
          </div>
          <button id="new-reserva-btn" class="w-full sm:w-auto bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2 flex-shrink-0">
            <i class="fas fa-plus"></i><span>Nueva Reserva</span>
          </button>
        </div>

        <!-- Calendario y Mapa -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div id="calendar-container" class="lg:col-span-2 bg-[var(--card-bg)] p-4 rounded-lg">
            <div id="calendar"></div>
          </div>
          <div id="map-container" class="lg:col-span-1 bg-[var(--card-bg)] p-4 rounded-lg flex flex-col min-h-[400px]">
            <h3 class="text-lg font-semibold mb-4 text-center">Ubicación de Vallas</h3>
            <div id="map" class="flex-grow rounded-lg w-full h-full min-h-[300px]"></div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Overlay móvil -->
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Crear/Editar -->
<div id="reserva-modal" class="modal-overlay fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50 hidden opacity-0">
  <div id="reserva-modal-container" class="modal-container bg-[var(--card-bg)] w-full max-w-lg p-6 rounded-xl shadow-lg transform scale-95 border border-[var(--border-color)]">
    <div class="flex justify-between items-center mb-4">
      <h2 id="modal-title" class="text-xl font-bold text-[var(--text-primary)]">Nueva Reserva</h2>
      <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Cerrar">
        <i class="fas fa-times fa-lg"></i>
      </button>
    </div>
    <form id="reserva-form" autocomplete="off">
      <input type="hidden" id="reserva-id" name="id">
      <div class="space-y-4">
        <div>
          <label for="valla-select" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Valla <span class="text-red-500">*</span></label>
          <select id="valla-select" name="valla_id" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Seleccione una valla</option>
          </select>
        </div>
        <div>
          <label for="cliente-nombre" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Nombre del Cliente <span class="text-red-500">*</span></label>
          <input type="text" id="cliente-nombre" name="nombre_cliente" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="fecha-inicio" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Fecha Inicio <span class="text-red-500">*</span></label>
            <input type="text" id="fecha-inicio" name="fecha_inicio" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="YYYY-MM-DD">
          </div>
          <div>
            <label for="fecha-fin" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Fecha Fin <span class="text-red-500">*</span></label>
            <input type="text" id="fecha-fin" name="fecha_fin" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="YYYY-MM-DD">
          </div>
        </div>
        <div>
          <label for="estado-select" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Estado</label>
          <select id="estado-select" name="estado" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="pendiente">Pendiente</option>
            <option value="confirmada">Confirmada</option>
            <option value="activa">Activa</option>
            <option value="bloqueo">Bloqueo (No Disponible)</option>
          </select>
        </div>
        <div>
          <label for="motivo-bloqueo" class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Motivo (si es bloqueo)</label>
          <input type="text" id="motivo-bloqueo" name="motivo" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Ej: Mantenimiento">
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-4">
        <button type="button" id="cancel-reserva-btn" class="px-4 py-2 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 font-semibold rounded-lg shadow-sm hover:bg-gray-300 dark:hover:bg-gray-500">Cancelar</button>
        <button type="submit" id="save-reserva-btn" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
  window.__RESERVAS_BOOT__ = { center:[<?=$centerLat?>,<?=$centerLng?>], zoom: <?=$centerZoom?> };
</script>
<script src="/console/asset/js/reservas/reservas.js"></script>
</body>
</html>

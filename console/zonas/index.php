<?php
// /console/zonas/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Gestión de Zonas';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/zonas/zonas.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Zonas</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
        <button id="btn-nueva" class="bg-indigo-600 text-white font-semibold px-4 py-2.5 rounded-lg shadow-md hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2">
          <i class="fas fa-plus"></i><span class="hidden sm:inline">Nueva Zona</span>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="card-kpi"><div class="kpi-icon kpi-green"><i class="fas fa-layer-group"></i></div><div><p class="kpi-label">Zonas</p><p id="kpi-zonas" class="kpi-value">—</p></div></div>
        <div class="card-kpi"><div class="kpi-icon kpi-amber"><i class="fas fa-clone"></i></div><div><p class="kpi-label">Grupos duplicados</p><p id="kpi-dups" class="kpi-value">—</p></div></div>
        <div class="card-kpi"><div class="kpi-icon kpi-blue"><i class="fas fa-billboard"></i></div><div><p class="kpi-label">Vallas asignadas</p><p id="kpi-vallas" class="kpi-value">—</p></div></div>
        <div class="card-kpi"><div class="kpi-icon kpi-gray"><i class="fas fa-check"></i></div><div><p class="kpi-label">Sin asignación</p><p id="kpi-noasig" class="kpi-value">—</p></div></div>
      </div>

      <!-- Filtros + Tabla -->
      <div class="bg-[var(--card-bg)] p-4 sm:p-6 rounded-xl shadow-md">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4">
          <div class="relative w-full md:max-w-xs">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
            <input id="f-q" type="text" placeholder="Buscar zona..." class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="flex gap-2 sm:gap-4 flex-wrap w-full md:w-auto">
            <select id="f-dup" class="w-full sm:w-auto border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Todas</option>
              <option value="1">Solo duplicados</option>
            </select>
            <button id="btn-filtrar" class="btn-secondary"><i class="fas fa-filter mr-2"></i>Filtrar</button>
            <button id="btn-merge" class="btn-primary"><i class="fas fa-link mr-2"></i>Unificar seleccionadas</button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left responsive-table">
            <thead>
              <tr class="border-b border-[var(--border-color)] text-sm font-semibold text-[var(--text-secondary)]">
                <th class="px-6 py-3 w-10"><input id="chk-all" type="checkbox"></th>
                <th class="px-6 py-3">Zona</th>
                <th class="px-6 py-3">Normalizada</th>
                <th class="px-6 py-3 text-center">Vallas</th>
                <th class="px-6 py-3">Acciones</th>
              </tr>
            </thead>
            <tbody id="zonas-tbody" class="text-sm divide-y divide-[var(--border-color)]"></tbody>
          </table>
          <div id="zonas-empty" class="hidden text-center py-12">
            <i class="fas fa-box-open fa-2x text-[var(--text-secondary)] mb-3"></i>
            <p class="font-semibold">Sin resultados</p>
            <p class="text-[var(--text-secondary)]">Ajusta filtros.</p>
          </div>
        </div>

        <div id="zonas-pager" class="flex justify-center items-center mt-6"></div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- Modal: crear/editar -->
<div id="dlg-zona" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 id="dlg-title" class="text-lg font-semibold">Nueva Zona</h3>
      <button id="dlg-close" class="p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]" aria-label="Cerrar"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body">
      <label class="block text-sm mb-1">Nombre</label>
      <input id="z-nombre" type="text" class="inp" placeholder="Ej: Zona Este">
      <div class="mt-4 flex justify-end gap-2">
        <button id="z-cancel" class="btn-outline">Cancelar</button>
        <button id="z-save" class="btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: asignar valla -->
<div id="dlg-asignar" class="modal-backdrop">
  <div class="modal max-w-md">
    <div class="modal-header">
      <h3 class="text-lg font-semibold">Asignar valla a zona</h3>
      <button id="a-close" class="p-2 rounded-lg hover:bg-[var(--sidebar-active-bg)]"><i class="fa fa-times"></i></button>
    </div>
    <div class="modal-body">
      <label class="block text-sm mb-1">ID de valla</label>
      <input id="a-valla" type="number" class="inp" placeholder="Ej: 25">
      <label class="block text-sm mt-3 mb-1">Zona</label>
      <select id="a-zona" class="inp"></select>
      <div class="mt-4 flex justify-end gap-2">
        <button id="a-cancel" class="btn-outline">Cancelar</button>
        <button id="a-ok" class="btn-primary">Asignar</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast" style="display:none">
  <div class="px-4 py-2 rounded-lg bg-[var(--card-bg)] border border-[var(--border-color)] shadow" id="toast-msg"></div>
</div>

<script>
window.ZONAS_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    listar:   "/console/zonas/ajax/listar.php",
    merge:    "/console/zonas/ajax/merge.php",
    asignar:  "/console/zonas/ajax/asignar.php",
    opciones: "/console/zonas/ajax/opciones.php"
  },
  page: { limit: 100 }
});
</script>
<script src="/console/asset/js/zonas/zonas.js" defer></script>
</body>
</html>

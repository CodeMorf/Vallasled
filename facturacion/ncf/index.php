<?php
// /console/facturacion/ncf/index.php
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
<title><?=$title?> - NCF</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/cliente/nfc.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú"><i class="fas fa-bars fa-lg"></i></button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú"><i class="fas fa-bars fa-lg"></i></button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de NCF</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="emitir-ncf-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-plus mr-1"></i>Emitir NCF
        </button>
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="kpi card p-5"><p class="text-sm text-[var(--text-secondary)]">Total emitidos</p><p id="kpi-emitidos" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        <div class="kpi card p-5"><p class="text-sm text-[var(--text-secondary)]">Secuencias disponibles</p><p id="kpi-secuencias" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
        <div class="kpi card p-5"><p class="text-sm text-[var(--text-secondary)]">Anulados</p><p id="kpi-anulados" class="text-2xl font-bold text-[var(--text-primary)]">—</p></div>
      </div>

      <div class="card p-6">
        <div class="border-b border-[var(--border-color)] mb-4">
          <nav class="flex -mb-px space-x-6" aria-label="Tabs">
            <button class="tab-button active py-4 px-1 text-center border-b-2 font-medium text-sm" data-tab="emitidos">NCF Emitidos</button>
            <button class="tab-button text-[var(--text-secondary)] border-transparent py-4 px-1 text-center border-b-2 font-medium text-sm" data-tab="secuencias">Secuencias Disponibles</button>
          </nav>
        </div>

        <!-- TAB: EMITIDOS -->
        <div id="tab-content-emitidos">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <input id="em-q" type="text" placeholder="Buscar por NCF, cliente o RNC" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <select id="em-tipo" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los tipos</option>
              <option value="B01">B01</option><option value="B02">B02</option>
            </select>
            <select id="em-estado" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              <option value="">Todos los estados</option>
              <option value="generado">Generado</option>
              <option value="anulado">Anulado</option>
            </select>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="border-b-2 border-[var(--border-color)]">
                <tr class="text-sm text-[var(--text-secondary)]">
                  <th class="py-3 px-4 font-semibold">NCF</th>
                  <th class="py-3 px-4 font-semibold">Factura</th>
                  <th class="py-3 px-4 font-semibold">Cliente</th>
                  <th class="py-3 px-4 font-semibold hidden lg:table-cell">Monto</th>
                  <th class="py-3 px-4 font-semibold hidden md:table-cell">Fecha</th>
                  <th class="py-3 px-4 font-semibold">Estado</th>
                  <th class="py-3 px-4 font-semibold text-center">Acciones</th>
                </tr>
              </thead>
              <tbody id="em-rows" class="divide-y divide-[var(--border-color)]"></tbody>
            </table>
          </div>
          <div id="em-pager" class="flex justify-center items-center mt-6"></div>
        </div>

        <!-- TAB: SECUENCIAS -->
        <div id="tab-content-secuencias" class="hidden">
          <div class="flex items-center justify-between gap-2 mb-4">
            <div class="flex gap-2">
              <select id="seq-filter-tipo" class="px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <option value="">Todos</option>
                <option value="B01">B01</option><option value="B02">B02</option>
              </select>
            </div>
            <button id="seq-add-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
              <i class="fa fa-plus mr-1"></i>Nueva secuencia
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="border-b-2 border-[var(--border-color)]">
                <tr class="text-sm text-[var(--text-secondary)]">
                  <th class="py-3 px-4 font-semibold">Tipo</th>
                  <th class="py-3 px-4 font-semibold">Serie</th>
                  <th class="py-3 px-4 font-semibold">Desde</th>
                  <th class="py-3 px-4 font-semibold">Hasta</th>
                  <th class="py-3 px-4 font-semibold">Vence</th>
                  <th class="py-3 px-4 font-semibold">Estado</th>
                  <th class="py-3 px-4 font-semibold text-center">Acciones</th>
                </tr>
              </thead>
              <tbody id="seq-rows" class="divide-y divide-[var(--border-color)]"></tbody>
            </table>
          </div>
          <div id="seq-pager" class="flex justify-center items-center mt-6"></div>
        </div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<!-- MODAL EMITIR -->
<div id="ncf-modal" class="modal-overlay fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50 opacity-0">
  <div id="ncf-modal-container" class="modal-container bg-[var(--card-bg)] w-full max-w-lg p-6 rounded-xl shadow-lg transform scale-95">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-bold text-[var(--text-primary)]">Emitir NCF</h2>
      <button id="close-ncf-modal-btn" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i class="fa fa-times"></i></button>
    </div>
    <form id="ncf-form" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Factura</label>
          <input id="ncf-factura-id" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="#2025-022">
        </div>
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Tipo</label>
          <select id="ncf-tipo" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <option value="B01">B01</option><option value="B02">B02</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">Cliente</label>
        <input id="ncf-cliente" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">RNC/Cédula</label>
        <input id="ncf-rnc" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">Monto</label>
        <input id="ncf-monto" type="number" step="0.01" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <label class="inline-flex items-center gap-2 text-sm text-[var(--text-secondary)]">
        <input id="ncf-itbis" type="checkbox" class="h-4 w-4"> Aplica ITBIS
      </label>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="cancel-ncf-btn" class="px-3 py-2 rounded-lg bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-100">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Generar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL SECUENCIA -->
<div id="seq-modal" class="modal-overlay fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50 opacity-0">
  <div id="seq-modal-container" class="modal-container bg-[var(--card-bg)] w-full max-w-md p-6 rounded-xl shadow-lg transform scale-95">
    <div class="flex justify-between items-center mb-4">
      <h2 id="seq-modal-title" class="text-lg font-bold text-[var(--text-primary)]">Nueva secuencia</h2>
      <button id="seq-close" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i class="fa fa-times"></i></button>
    </div>
    <form id="seq-form" class="space-y-3">
      <input type="hidden" id="seq-id" value="">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Tipo</label>
          <select id="seq-tipo" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            <option value="B01">B01</option><option value="B02">B02</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Serie</label>
          <input id="seq-serie" maxlength="1" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="B">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Desde</label>
          <input id="seq-desde" type="number" min="1" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
        <div>
          <label class="block text-sm text-[var(--text-secondary)] mb-1">Hasta</label>
          <input id="seq-hasta" type="number" min="1" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
        </div>
      </div>
      <div>
        <label class="block text-sm text-[var(--text-secondary)] mb-1">Vence</label>
        <input id="seq-vence" type="date" class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
      </div>
      <label class="inline-flex items-center gap-2 text-sm text-[var(--text-secondary)]">
        <input id="seq-activo" type="checkbox" class="h-4 w-4" checked> Activo
      </label>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" id="seq-cancel" class="px-3 py-2 rounded-lg bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-100">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
  window.NCF_CFG = Object.freeze({
    listar: "/console/facturacion/ncf/ajax/listar.php",
    crud:   "/console/facturacion/ncf/ajax/crud.php",
    page:   { limit: 20 },
  });
</script>

<!-- Sidebar móvil + tema universal (sin conflictos) -->
<script>
(function () {
  'use strict';
  const body = document.body;
  const overlay = document.getElementById('sidebar-overlay');
  const hasAPI = typeof window.sidebarOpen === 'function'
              && typeof window.sidebarClose === 'function'
              && typeof window.sidebarToggle === 'function';
  const lsKey = 'sidebarCollapsed';

  function open()  { hasAPI ? window.sidebarOpen()  : (body.classList.add('sidebar-open'),  overlay?.classList.remove('hidden')); }
  function close() { hasAPI ? window.sidebarClose() : (body.classList.remove('sidebar-open'), overlay?.classList.add('hidden')); }
  function toggle(){ hasAPI ? window.sidebarToggle() : (body.classList.contains('sidebar-open') ? close() : open()); }

  // init desktop state
  try { if (localStorage.getItem(lsKey) === '1') body.classList.add('sidebar-collapsed'); } catch {}

  // mobile toggle
  document.getElementById('mobile-menu-button')?.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); toggle(); });
  overlay?.addEventListener('click', close);

  // desktop collapse with persistence
  document.getElementById('sidebar-toggle-desktop')?.addEventListener('click', () => {
    body.classList.toggle('sidebar-collapsed');
    try { localStorage.setItem(lsKey, body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch {}
    setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
  });

  // submenu safe
  document.querySelectorAll('.submenu-trigger').forEach(trigger => trigger.addEventListener('click', () => {
    if (body.classList.contains('sidebar-collapsed')) return;
    trigger.nextElementSibling?.classList.toggle('hidden');
    trigger.classList.toggle('submenu-open');
  }));

  // close on link tap in mobile
  document.querySelector('.sidebar')?.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    if (window.matchMedia('(max-width: 767.98px)').matches) close();
  });

  // ESC
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  // theme universal
  const tBtn = document.getElementById('theme-toggle');
  const darkI = document.getElementById('theme-toggle-dark-icon');
  const lightI = document.getElementById('theme-toggle-light-icon');
  function applyTheme(t){
    const dark = t === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    darkI?.classList.toggle('hidden', !dark);
    lightI?.classList.toggle('hidden', dark);
    try{ localStorage.setItem('theme', t); }catch{}
  }
  const saved = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(saved);
  tBtn?.addEventListener('click', () => applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark'));

  // sync on breakpoint changes
  const mq = window.matchMedia('(min-width: 768px)');
  mq.addEventListener('change', () => { overlay?.classList.add('hidden'); body.classList.remove('sidebar-open'); });
})();
</script>

<script src="/console/asset/js/cliente/nfc.js" defer></script>
</body>
</html>

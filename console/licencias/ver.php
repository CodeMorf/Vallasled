<?php
// /console/licencias/ver.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
start_session_safe();

// auth
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) { header('Location: /console/licencias/'); exit; }

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Licencia #' . $id;
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
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">

  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-3">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <a href="/console/licencias/" class="px-3 py-2 rounded-lg bg-[var(--main-bg)] hover:bg-[var(--sidebar-active-bg)] text-[var(--text-primary)]">
          <i class="fa fa-arrow-left mr-2"></i>Volver
        </a>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Licencia #<?= (int)$id ?></h1>
        <span id="lc-estado-badge" class="ml-2 px-2 py-1 rounded text-xs font-semibold bg-gray-200 dark:bg-gray-700"></span>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
        <a id="btn-editar" href="/console/licencias/editar.php?id=<?= (int)$id ?>" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
          <i class="fa fa-pen mr-2"></i>Editar
        </a>
        <button id="btn-eliminar" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold">
          <i class="fa fa-trash mr-2"></i>Eliminar
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">

      <!-- Identificación -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4">Identificación</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="md:col-span-2">
            <div class="text-sm text-[var(--text-secondary)]">Título</div>
            <div id="fld-titulo" class="font-semibold text-[var(--text-primary)]">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Periodicidad</div>
            <div id="fld-period" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Estado</div>
            <div id="fld-estado" class="font-medium">—</div>
          </div>
        </div>
      </section>

      <!-- Relaciones -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4">Relaciones</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Proveedor</div>
            <div id="fld-prov" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Valla</div>
            <div id="fld-valla" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Cliente</div>
            <div id="fld-cliente" class="font-medium">—</div>
          </div>
        </div>
      </section>

      <!-- Ubicación y Entidad -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4">Ubicación y Entidad</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Ciudad</div>
            <div id="fld-ciudad" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Entidad</div>
            <div id="fld-entidad" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Tipo de licencia</div>
            <div id="fld-tipo" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Dirección</div>
            <div id="fld-dir" class="font-medium truncate">—</div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Latitud</div>
            <div id="fld-lat" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Longitud</div>
            <div id="fld-lon" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Alto (m)</div>
            <div id="fld-alto" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Ancho (m)</div>
            <div id="fld-ancho" class="font-medium">—</div>
          </div>
        </div>
      </section>

      <!-- Fechas y Costos -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4">Fechas y Costos</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Solicitud</div>
            <div id="fld-solicitud" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Emisión</div>
            <div id="fld-emision" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Vencimiento</div>
            <div id="fld-venc" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Recordatorio (días)</div>
            <div id="fld-rem" class="font-medium">—</div>
          </div>
          <div>
            <div class="text-sm text-[var(--text-secondary)]">Costo</div>
            <div id="fld-costo" class="font-medium">—</div>
          </div>
        </div>
      </section>

      <!-- Notas -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <h2 class="text-lg font-semibold mb-4">Notas</h2>
        <div id="fld-notas" class="whitespace-pre-wrap text-[var(--text-primary)]">—</div>
      </section>

      <!-- Archivos -->
      <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Archivos</h2>
          <div class="text-sm text-[var(--text-secondary)]" id="files-count">0 archivos</div>
        </div>
        <div id="files-list" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
          <!-- items -->
        </div>
      </section>

    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');
  const ID   = <?= (int)$id ?>;

  const byId = (id)=>document.getElementById(id);
  const fmt = (d)=> d ? String(d).substring(0,10) : '—';
  const money = (v)=> (v===null || v===undefined) ? '—' : Number(v).toFixed(2);

  const badge = byId('lc-estado-badge');
  function paintBadge(estado){
    const map = {
      aprobada: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
      enviada: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
      borrador: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
      rechazada: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
      vencida: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
      por_vencer: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
    };
    badge.className = 'ml-2 px-2 py-1 rounded text-xs font-semibold ' + (map[estado] || 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200');
    badge.textContent = estado || '';
  }

  async function loadDetalle(){
    const url = `/console/licencias/ajax/detalle.php?id=${ID}`;
    const res = await fetch(url, {headers:{'X-CSRF-Token': csrf}});
    if (!res.ok) {
      alert('No se pudo cargar el detalle'); return;
    }
    const j = await res.json();
    if (!j.ok || !j.data) { alert('Registro no encontrado'); return; }
    const d = j.data;

    byId('fld-titulo').textContent  = d.titulo || `Licencia #${ID}`;
    byId('fld-period').textContent  = d.periodicidad || '—';
    byId('fld-estado').textContent  = d.estado || '—';
    paintBadge(d.estado || '');

    byId('fld-prov').textContent    = d.proveedor?.nombre || `ID: ${d.proveedor?.id ?? '—'}`;
    byId('fld-valla').textContent   = d.valla?.nombre ? `${d.valla.nombre} (ID ${d.valla.id})` : (d.valla?.id ? `ID ${d.valla.id}` : '—');
    byId('fld-cliente').textContent = d.cliente?.nombre || (d.cliente?.id ? `ID: ${d.cliente.id}` : '—');

    byId('fld-ciudad').textContent  = d.ciudad || '—';
    byId('fld-entidad').textContent = d.entidad || '—';
    byId('fld-tipo').textContent    = d.tipo_licencia || '—';
    byId('fld-dir').textContent     = d.direccion || '—';

    byId('fld-lat').textContent     = (d.lat ?? '') !== '' ? d.lat : '—';
    byId('fld-lon').textContent     = (d.lon ?? '') !== '' ? d.lon : '—';
    byId('fld-alto').textContent    = (d.alto ?? '') !== '' ? d.alto : '—';
    byId('fld-ancho').textContent   = (d.ancho ?? '') !== '' ? d.ancho : '—';

    byId('fld-solicitud').textContent = fmt(d.fecha_solicitud);
    byId('fld-emision').textContent   = fmt(d.fecha_emision);
    byId('fld-venc').textContent      = fmt(d.fecha_vencimiento);
    byId('fld-rem').textContent       = (d.reminder_days ?? '') !== '' ? d.reminder_days : '—';
    byId('fld-costo').textContent     = money(d.costo);
    byId('fld-notas').textContent     = d.notas || '—';

    // archivos
    const list = byId('files-list');
    list.innerHTML = '';
    const files = Array.isArray(d.files) ? d.files : [];
    byId('files-count').textContent = files.length + (files.length === 1 ? ' archivo' : ' archivos');
    if (!files.length) {
      list.innerHTML = '<div class="text-[var(--text-secondary)]">Sin archivos adjuntos.</div>';
    } else {
      for (const f of files) {
        const a = document.createElement('a');
        a.href = f.ruta;
        a.target = '_blank';
        a.rel = 'noopener';
        a.className = 'block p-4 rounded-lg border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]';
        a.innerHTML = `
          <div class="font-semibold">${(f.nombre || 'archivo')}</div>
          <div class="text-sm text-[var(--text-secondary)]">${f.mime || ''} · ${f.tamano ? (Number(f.tamano)/1024).toFixed(1)+' KB' : ''}</div>
          <div class="text-xs text-[var(--text-secondary)] mt-1">${f.creado ? String(f.creado).replace('T',' ').slice(0,19) : ''}</div>
        `;
        list.appendChild(a);
      }
    }
  }

  async function eliminar(){
    if (!confirm('¿Eliminar esta licencia de forma permanente?')) return;
    const res = await fetch('/console/licencias/ajax/eliminar.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token': csrf},
      body: JSON.stringify({id: ID, csrf})
    });
    const j = await res.json().catch(()=>({ok:false}));
    if (!res.ok || !j.ok) { alert('No se pudo eliminar'); return; }
    location.href = '/console/licencias/';
  }

  document.getElementById('btn-eliminar').addEventListener('click', eliminar);
  loadDetalle();
})();
</script>

<script>
// tema básico (mismo patrón del resto)
(function(){
  const d = document, eDark = d.getElementById('theme-toggle-dark-icon'), eLight=d.getElementById('theme-toggle-light-icon');
  function setIcons(){ if (d.documentElement.classList.contains('dark')) { eLight.classList.remove('hidden'); eDark.classList.add('hidden'); } else { eDark.classList.remove('hidden'); eLight.classList.add('hidden'); } }
  setIcons();
  document.getElementById('theme-toggle').addEventListener('click',()=>{ d.documentElement.classList.toggle('dark'); setIcons(); });
})();
</script>

</body>
</html>

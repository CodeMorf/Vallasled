<?php
// /console/gestion/empleados/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Solo admin */
if (empty($_SESSION['uid']) || ($_SESSION['tipo']!=='admin')) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

/* Branding */
$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = $branding['title'] ?: 'Panel';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

/* Base URL sin declarar funciones (evita redeclare con sidebar) */
$__scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$__scheme = is_string($__scheme) ? strtolower($__scheme) : 'http';
$__host   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
if (strpos($__host, ':') !== false) { $__host = explode(':', $__host, 2)[0]; }
$__port   = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? null;
$__port   = $__port !== null ? (int)$__port : null;
$__def    = ($__scheme === 'https') ? 443 : 80;
$__portP  = ($__port !== null && $__port !== $__def) ? (':' . $__port) : '';
$__prefix = $_SERVER['HTTP_X_FORWARDED_PREFIX'] ?? '';
$__prefix = $__prefix ? ('/' . ltrim($__prefix, '/')) : '';
$__BASE   = $__scheme . '://' . $__host . $__portP . rtrim($__prefix, '/');
$LOGIN_URL = rtrim($__BASE, '/') . '/console/auth/login/';

/* 1) Listado estático 100% alineado con tu sidebar actual */
$STATIC = [
  // Raíces
  '/console/portal/'                => ['Dashboard','fa-tachometer-alt'],
  '/console/empleados/'             => ['Mi Panel (Staff)','fa-user'],
  // Módulos
  '/console/vallas/'                => ['Vallas · Listado','fa-ad'],
  '/console/vallas/agregar/'        => ['Vallas · Agregar','fa-plus-circle'],
  '/console/reservas/'              => ['Reservas','fa-calendar-check'],
  '/console/facturacion/'           => ['Facturación','fa-file-invoice-dollar'],
  '/console/licencias/'             => ['Licencias','fa-file-contract'],
  '/console/mapa/'                  => ['Mapa','fa-map-marked-alt'],
  '/console/zonas/'                 => ['Zonas','fa-layer-group'],
  '/console/reportes/'              => ['Reportes','fa-chart-pie'],
  '/console/ads/'                   => ['ADS','fa-rectangle-ad'],
  // Gestión
  '/console/gestion/clientes/'      => ['Gestión · Clientes','fa-users'],
  '/console/gestion/proveedores/'   => ['Gestión · Proveedores','fa-truck-field'],
  '/console/gestion/empleados/'     => ['Gestión · Empleados','fa-user-tie'],
  '/console/gestion/planes/'        => ['Gestión · Planes','fa-cubes'],
  '/console/gestion/pagos/'         => ['Gestión · Pagos','fa-credit-card'],
  '/console/gestion/web/'           => ['Gestión · Web','fa-globe'],
  '/console/gestion/vendors/'       => ['Gestión · Vendors','fa-store'],
  '/console/gestion/contabilidad/'  => ['Gestión · Contabilidad','fa-calculator'],
  // Sistema
  '/console/sistema/usuarios/'      => ['Sistema · Usuarios','fa-user-shield'],
  '/console/sistema/config/'        => ['Sistema · Configuración','fa-cogs'],
];

/* 2) Extra: rutas definidas en roles_permisos (url:/console/...) */
$dbUrls = [];
try {
  $sql = "SELECT DISTINCT SUBSTRING(permiso,5) AS path
          FROM roles_permisos
          WHERE permiso LIKE 'url:/console/%'";
  if ($rs = $conn->query($sql)) {
    while ($r = $rs->fetch_assoc()) {
      $p = trim((string)$r['path']);
      if ($p !== '' && $p !== '/console/*') $dbUrls[] = $p;
    }
  }
} catch (Throwable $e) {}

/* 3) Unión estático + DB, sin duplicados. Fallback de etiqueta si no está en el mapa. */
$urlsMap = $STATIC;
$pretty = function(string $path): string {
  $leaf = trim($path,'/'); $parts = explode('/',$leaf); $leaf = end($parts) ?: 'inicio';
  return ucwords(str_replace(['-','_'],' ',$leaf));
};
foreach ($dbUrls as $p) {
  if (!isset($urlsMap[$p])) $urlsMap[$p] = [$pretty($p),'fa-circle'];
}
/* Normaliza a array de objetos {path,label,icon} ordenado por path */
$urls = [];
ksort($urlsMap, SORT_NATURAL);
foreach ($urlsMap as $p => $arr) {
  $urls[] = ['path'=>$p, 'label'=>$arr[0], 'icon'=>$arr[1]];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=$title?> - Gestión de Empleados</title>
<link rel="icon" href="<?=$fav?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/empleado/gestion.css">
<script>tailwind.config={darkMode:'class'}</script>

<!-- Presets de URLs permitibles -->
<script type="application/json" id="ACL_PRESETS_JSON">
<?=json_encode(['urls'=>$urls], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>
</script>

<!-- Endpoints AJAX -->
<meta name="ajax-list" content="/console/gestion/empleados/ajax/listar.php">
<meta name="ajax-acl-get" content="/console/gestion/empleados/ajax/acl_get.php">
<meta name="ajax-acl-save" content="/console/gestion/empleados/ajax/acl_save.php">
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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Gestión de Empleados</h1>
      </div>
      <div class="flex items-center space-x-3">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Listado + búsqueda -->
        <section class="lg:col-span-2 bg-[var(--card-bg)] rounded-xl border border-[var(--border-color)]">
          <div class="p-4 border-b border-[var(--border-color)] flex items-center justify-between gap-3">
            <div class="flex-1">
              <label for="emp-q" class="sr-only">Buscar</label>
              <div class="flex items-center gap-2">
                <i class="fas fa-search text-[var(--text-secondary)]"></i>
                <input id="emp-q" type="search" placeholder="Buscar empleado por nombre o email"
                  class="w-full bg-[var(--main-bg)] border border-[var(--border-color)] rounded-md px-3 py-2 text-sm">
              </div>
            </div>
            <a id="emp-add" href="#" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 text-sm">
              <i class="fas fa-user-plus"></i><span>Nuevo</span>
            </a>
          </div>
          <div id="emp-list" class="p-2 max-h-[70vh] overflow-y-auto divide-y divide-[var(--border-color)]"></div>
          <div id="emp-empty" class="p-6 text-center text-sm text-[var(--text-secondary)] hidden">Sin resultados.</div>
        </section>

        <!-- ACL -->
        <section class="lg:col-span-3 bg-[var(--card-bg)] rounded-xl border border-[var(--border-color)]">
          <div class="p-4 border-b border-[var(--border-color)] flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-[var(--text-primary)]">Accesos del Empleado</h2>
              <p id="emp-selected-label" class="text-sm text-[var(--text-secondary)]">Selecciona un empleado para configurar.</p>
            </div>
            <div class="flex items-center gap-2">
              <button id="acl-preset-basic" class="px-3 py-2 rounded-md border border-[var(--border-color)] text-sm hover:bg-[var(--sidebar-active-bg)]">Preset básico</button>
              <button id="acl-preset-clear" class="px-3 py-2 rounded-md border border-[var(--border-color)] text-sm hover:bg-[var(--sidebar-active-bg)]">Quitar todo</button>
            </div>
          </div>

          <form id="acl-form" class="p-4 space-y-5" autocomplete="off">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="empleado_id" id="empleado_id" value="">
            <div>
              <h3 class="text-sm font-semibold mb-2 text-[var(--text-primary)]">URLs permitidas</h3>
              <div id="acl-urls" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
            </div>
            <div class="hidden" id="acl-perms-group">
              <h3 class="text-sm font-semibold mb-2 text-[var(--text-primary)]">Permisos</h3>
              <div id="acl-perms" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
            </div>
            <div class="flex items-center justify-between pt-2">
              <div id="acl-msg" class="text-sm text-[var(--text-secondary)]"></div>
              <div class="flex gap-2">
                <button id="acl-save" type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500">
                  <i class="fas fa-save"></i><span>Guardar</span>
                </button>
                <button id="acl-reload" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]">
                  <i class="fas fa-rotate-right"></i><span>Recargar</span>
                </button>
              </div>
            </div>
          </form>

          <div class="px-4 pb-4 text-xs text-[var(--text-secondary)]">
            El backend debe poblar <code>$_SESSION['permisos']</code> con entradas tipo <code>url:/ruta/</code> en el login de staff.
          </div>
        </section>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden"></div>
</div>

<!-- Modal Crear Empleado -->
<div id="modal-create" class="fixed inset-0 z-40 hidden">
  <div class="absolute inset-0 bg-black/50" data-close="1"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-xl border border-[var(--border-color)] bg-[var(--card-bg)] shadow-xl">
      <div class="px-5 py-4 border-b border-[var(--border-color)] flex justify-between items-center">
        <h3 class="text-lg font-semibold">Nuevo empleado</h3>
        <button class="p-2 rounded hover:bg-[var(--sidebar-active-bg)]" data-close="1" aria-label="Cerrar"><i class="fa fa-times"></i></button>
      </div>
      <form id="form-create" class="px-5 pt-4 pb-5 space-y-4" autocomplete="off">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="create">
        <div>
          <label class="block text-sm mb-1">Nombre de usuario</label>
          <input name="usuario" type="text" required maxlength="100" class="w-full bg-[var(--main-bg)] border border-[var(--border-color)] rounded-md px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Email</label>
          <input name="email" type="email" required maxlength="150" class="w-full bg-[var(--main-bg)] border border-[var(--border-color)] rounded-md px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Contraseña</label>
          <div class="flex gap-2">
            <input id="pwd" name="password" type="text" required minlength="8" maxlength="128" class="flex-1 bg-[var(--main-bg)] border border-[var(--border-color)] rounded-md px-3 py-2">
            <button id="btn-gen" type="button" class="px-3 rounded-md border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]">Generar</button>
          </div>
          <p class="text-xs text-[var(--text-secondary)] mt-1">Se mostrará solo una vez. Cópiala y compártela al empleado.</p>
        </div>
        <div>
          <label class="block text-sm mb-1">Rol predefinido</label>
          <select name="rol" class="w-full bg-[var(--main-bg)] border border-[var(--border-color)] rounded-md px-3 py-2">
            <option value="">Sin rol (solo URLs específicas)</option>
            <option value="staff_basico">staff_basico</option>
            <option value="staff_operaciones">staff_operaciones</option>
            <option value="staff_full">staff_full</option>
          </select>
        </div>
        <div class="flex items-center justify-between pt-2">
          <div id="create-msg" class="text-sm text-[var(--text-secondary)]"></div>
          <div class="flex gap-2">
            <a href="<?=$LOGIN_URL?>" target="_blank" class="px-3 py-2 rounded-md border border-[var(--border-color)] hover:bg-[var(--sidebar-active-bg)]">Login</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500">Crear</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* Modal Crear Empleado */
(function(){
  const $ = (s, d=document)=>d.querySelector(s);
  const listURL  = document.querySelector('meta[name="ajax-list"]')?.content || '/console/gestion/empleados/ajax/listar.php';
  const modal = $('#modal-create');
  const openBtn = $('#emp-add');
  const form = $('#form-create');
  const msg  = $('#create-msg');
  const pwd  = $('#pwd');

  function open(){ modal.classList.remove('hidden'); }
  function close(){ modal.classList.add('hidden'); msg.textContent=''; form.reset(); }

  document.addEventListener('click', (e)=>{
    const el = e.target.closest('[data-close]');
    if (el && el.getAttribute('data-close')) { e.preventDefault(); close(); }
  });
  if (openBtn) openBtn.addEventListener('click', (e)=>{ e.preventDefault(); gen(); open(); });

  function gen(){
    const chars = 'ABCDEFGHJKLMNPQRSTUVXYZabcdefghijkmnopqrstuvwxyz23456789!@$%*-';
    let out=''; for(let i=0;i<12;i++){ out += chars[Math.floor(Math.random()*chars.length)]; }
    pwd.value = out;
    try { navigator.clipboard.writeText(out); } catch(e){}
  }
  $('#btn-gen')?.addEventListener('click', gen);

  async function postCreate(fd){
    const r = await fetch(listURL, {
      method:'POST',
      headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams(fd)
    });
    const t = await r.text();
    try { return JSON.parse(t); } catch { throw new Error(t.slice(0,180)); }
  }

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    msg.textContent = 'Creando…';
    const fd = new FormData(form);
    try{
      const j = await postCreate(fd);
      if (j.ok) {
        msg.textContent = 'Empleado creado. Recargando…';
        setTimeout(()=>window.location.href='/console/gestion/empleados/', 600);
      } else {
        msg.textContent = j.error || 'Error';
      }
    }catch(err){
      msg.textContent = String(err.message||err);
    }
  });
})();
</script>

<script src="/console/asset/js/empleado/gestion.js"></script>
</body>
</html>

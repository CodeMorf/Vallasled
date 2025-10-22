<?php
// /console/licencias/crear/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  header('Location: /console/auth/login/'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title = ($branding['title'] ?: 'Panel') . ' - Nueva Licencia';
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
<link rel="stylesheet" href="/console/asset/css/licencias/licencias.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Nueva Licencia</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
        <button id="btn-guardar" form="form-lic" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
          <i class="fa fa-save mr-2"></i>Guardar
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <form id="form-lic" class="space-y-6" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=$csrf?>">

        <!-- Identificación -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold mb-4">Identificación</h2>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
              <label class="font-semibold block mb-1">Título / Referencia *</label>
              <input name="titulo" id="lc-titulo" required maxlength="120"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="font-semibold block mb-1">Estado *</label>
              <select name="estado" id="lc-estado" required
                      class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <option value="aprobada">Aprobada</option>
                <option value="enviada">Enviada</option>
                <option value="borrador" selected>Borrador</option>
                <option value="rechazada">Rechazada</option>
                <option value="vencida">Vencida</option>
              </select>
            </div>
            <div>
              <label class="font-semibold block mb-1">Periodicidad</label>
              <select name="periodicidad" id="lc-period"
                      class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <option value="anual" selected>Anual</option>
                <option value="mensual">Mensual</option>
                <option value="puntual">Puntual</option>
              </select>
            </div>
          </div>
        </section>

        <!-- Relaciones -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold mb-4">Relaciones</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="font-semibold block mb-1">Proveedor *</label>
              <select name="proveedor_id" id="lc-prov" required
                      class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
            </div>
            <div>
              <label class="font-semibold block mb-1">Valla</label>
              <select name="valla_id" id="lc-valla"
                      class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <option value="">Seleccione proveedor primero</option>
              </select>
            </div>
            <div>
              <label class="font-semibold block mb-1">Cliente</label>
              <select name="cliente_id" id="lc-cliente"
                      class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></select>
            </div>
          </div>
        </section>

        <!-- Ubicación y entidad -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold mb-4">Ubicación y Entidad</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="font-semibold block mb-1">Ciudad *</label>
              <input name="ciudad" id="lc-ciudad" required maxlength="80"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="font-semibold block mb-1">Entidad reguladora *</label>
              <input name="entidad" id="lc-entidad" required maxlength="120"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"
                     placeholder="Ayuntamiento, INTRANT, MOPC…">
            </div>
            <div>
              <label class="font-semibold block mb-1">Tipo de Licencia</label>
              <input name="tipo_licencia" id="lc-tipo" maxlength="120"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"
                     placeholder="Publicidad exterior, permiso especial…">
            </div>
          </div>
        </section>

        <!-- Fechas -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold mb-4">Fechas</h2>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="font-semibold block mb-1">Emisión *</label>
              <input type="date" name="fecha_emision" id="lc-emision" required
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="font-semibold block mb-1">Vencimiento *</label>
              <input type="date" name="fecha_venc" id="lc-venc" required
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="font-semibold block mb-1">Recordatorio (días antes)</label>
              <input type="number" min="0" max="365" name="reminder_days" id="lc-reminder" value="30"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="font-semibold block mb-1">Costo (opcional)</label>
              <input type="number" step="0.01" min="0" name="costo"
                     class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" value="0.00">
            </div>
          </div>
        </section>

        <!-- Documentos y notas -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h2 class="text-lg font-semibold mb-4">Documentos y Notas</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="font-semibold block mb-1">Adjuntos (PDF/JPG/PNG)</label>
              <input type="file" name="files[]" id="lc-files" multiple accept=".pdf,.jpg,.jpeg,.png"
                     class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                            file:bg-[var(--sidebar-active-bg)] file:text-[var(--text-primary)] hover:file:opacity-90">
            </div>
            <div>
              <label class="font-semibold block mb-1">Observaciones</label>
              <textarea name="notas" id="lc-notas" rows="3"
                        class="w-full px-3 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"
                        placeholder="Detalles adicionales, condiciones, folio interno…"></textarea>
            </div>
          </div>
        </section>

        <div class="flex items-center justify-end gap-3">
          <a href="/console/licencias/" class="px-4 py-2 rounded-lg bg-[var(--sidebar-active-bg)] hover:opacity-90">Cancelar</a>
          <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Guardar</button>
        </div>
      </form>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
  // Config exclusiva de "crear"
  window.LIC_CREATE = Object.freeze({
    csrf: "<?=$csrf?>",
    endpoints: {
      opciones: "/console/licencias/ajax/opciones.php",
      guardar:  "/console/licencias/ajax/guardar.php"
    }
  });
</script>
<script src="/console/asset/js/licencias/crear.js" defer></script>
</body>
</html>


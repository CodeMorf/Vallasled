<?php
declare(strict_types=1);
/** /console/facturacion/facturas/crear.php */

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
require_auth(['admin','staff']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$branding = load_branding($conn);
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

/* ---------- helpers DB ---------- */
function db_row(mysqli $c, string $sql, string $types = '', array $params = []) : ?array {
  $st = $c->prepare($sql);
  if ($types !== '' && $params) { $st->bind_param($types, ...$params); }
  $st->execute();
  $res = $st->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $st->close();
  return $row ?: null;
}
function get_valla(mysqli $c, int $id) : ?array {
  return db_row($c, "SELECT id, nombre, proveedor_id FROM vallas WHERE id=? LIMIT 1", "i", [$id]);
}
function get_proveedor(mysqli $c, int $id) : ?array {
  return db_row($c, "SELECT id, nombre FROM proveedores WHERE id=? LIMIT 1", "i", [$id]);
}
function get_cliente_base(mysqli $c, int $id) : ?array {
  // ajusta tabla/columnas si tu base usa otro nombre
  return db_row($c, "SELECT id, COALESCE(nombre, email) AS label, email, rnc FROM clientes WHERE id=? LIMIT 1", "i", [$id]);
}
function get_cliente_crm(mysqli $c, int $id) : ?array {
  return db_row($c, "SELECT id, empresa, nombre, email, rnc, proveedor_id FROM crm_clientes WHERE id=? LIMIT 1", "i", [$id]);
}

/* ---------- prefill por GET ---------- */
$valla_id = isset($_GET['valla_id']) ? (int)$_GET['valla_id'] : 0;
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
$cliente_base_id = isset($_GET['cliente_base_id']) ? (int)$_GET['cliente_base_id'] : 0;
$crm_cliente_id = isset($_GET['crm_cliente_id']) ? (int)$_GET['crm_cliente_id'] : 0;

$pref_valla = $valla_id ? get_valla($conn, $valla_id) : null;
$pref_proveedor = $proveedor_id ? get_proveedor($conn, $proveedor_id) : null;
$pref_base = $cliente_base_id ? get_cliente_base($conn, $cliente_base_id) : null;
$pref_crm = $crm_cliente_id ? get_cliente_crm($conn, $crm_cliente_id) : null;

/* si viene crm sin proveedor y no hay valla, deduce proveedor */
if (!$pref_proveedor && !$pref_valla && $pref_crm && !empty($pref_crm['proveedor_id'])) {
  $pref_proveedor = get_proveedor($conn, (int)$pref_crm['proveedor_id']);
}

/** Sidebar dinámico */
$sidebarPath = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/console/asset/sidebar.php';
if (!is_file($sidebarPath)) { $sidebarPath = realpath(__DIR__ . '/../../../asset/sidebar.php'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel - Crear Nueva Factura</title>
<meta name="csrf" content="<?=$csrf?>">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode:'class' };</script>

<!-- Iconos + fuente -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- CSS módulo -->
<link rel="stylesheet" href="/console/asset/css/facturacion/crear.css">
<style>
  :root{
    --sidebar-bg:#F9FAFB;--sidebar-text:#4B5563;--sidebar-text-hover:#111827;--sidebar-active-bg:#E5E7EB;--sidebar-active-text:#1F2937;
    --main-bg:#F3F4F6;--header-bg:#FFFFFF;--card-bg:#FFFFFF;--text-primary:#1F2937;--text-secondary:#6B7280;--border-color:#E5E7EB;--input-bg:#FFFFFF;
  }
  html.dark{
    --sidebar-bg:#111827;--sidebar-text:#9CA3AF;--sidebar-text-hover:#F9FAFB;--sidebar-active-bg:#374151;--sidebar-active-text:#F9FAFB;
    --main-bg:#1F2937;--header-bg:#111827;--card-bg:#374151;--text-primary:#F9FAFB;--text-secondary:#D1D5DB;--border-color:#4B5563;--input-bg:#4B5563;
  }
  body{font-family:'Inter',sans-serif;background-color:var(--main-bg);color:var(--text-primary)}
  .tab-button.active{border-color:#4f46e5;color:#4f46e5;background-color:rgba(79,70,229,.1)}
  html.dark .tab-button.active{background-color:rgba(99,102,241,.2)}
  .modal-overlay{transition:opacity .3s ease}.modal-container{transition:all .3s ease}
</style>
</head>
<body class="overflow-x-hidden">
<div class="flex h-screen relative">

  <?php if ($sidebarPath && is_file($sidebarPath)) { require $sidebarPath; } ?>

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
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Crear Factura</h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <form id="create-invoice-form" class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Formulario -->
        <div class="xl:col-span-2 space-y-6">

          <!-- Paso 1 -->
          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-semibold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3 mb-4 flex items-center gap-3">
              <span class="bg-indigo-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">1</span>
              Información del Cliente
            </h3>

            <div class="mb-4 border-b border-[var(--border-color)]">
              <nav class="flex -mb-px" aria-label="Tabs">
                <button type="button" class="tab-button active w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm" data-tab="existente">Cliente Existente</button>
                <button type="button" class="tab-button w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm text-[var(--text-secondary)] border-transparent" data-tab="nuevo">Nuevo Cliente</button>
              </nav>
            </div>

            <div id="tab-content-existente">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Buscar Cliente (Base)</label>
                  <select id="cliente-base-select" style="width: 100%;" class="select2-search">
                    <?php if ($pref_base): ?>
                      <option value="<?= (int)$pref_base['id'] ?>" selected>
                        <?= htmlspecialchars($pref_base['label'] ?? ('Cliente #'.$pref_base['id']), ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endif; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Buscar Cliente (CRM por Proveedor)</label>
                  <select id="cliente-crm-select" style="width: 100%;" class="select2-search">
                    <?php if ($pref_crm): ?>
                      <option value="<?= (int)$pref_crm['id'] ?>" selected>
                        <?= htmlspecialchars(($pref_crm['empresa'] ? ($pref_crm['empresa'].' ('.($pref_crm['nombre'] ?? '-').')') : ($pref_crm['nombre'] ?? ('CRM #'.$pref_crm['id']))), ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
            </div>

            <div id="tab-content-nuevo" class="hidden">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input id="nuevo-empresa" type="text" placeholder="Nombre de la Empresa" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <input id="nuevo-responsable" type="text" placeholder="Nombre Responsable" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <input id="nuevo-email" type="email" placeholder="Correo Electrónico" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <input id="nuevo-telefono" type="tel" placeholder="Teléfono" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <input id="nuevo-rnc" type="text" placeholder="RNC/Cédula (opcional)" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <div class="md:col-span-2">
                  <textarea id="nuevo-direccion" placeholder="Dirección (opcional)" rows="2" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
                </div>
                <div class="md:col-span-2">
                  <button type="button" id="btn-crear-cliente" class="w-full bg-green-600 text-white font-semibold px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fa fa-user-plus mr-2"></i>Crear cliente
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Paso 2 -->
          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-semibold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3 mb-4 flex items-center gap-3">
              <span class="bg-indigo-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">2</span>
              Detalles de la Factura
            </h3>
            <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Valla Asociada (Opcional)</label>
                  <select id="valla-item-select" style="width: 100%;" class="select2-search">
                    <?php if ($pref_valla): ?>
                      <option value="<?= (int)$pref_valla['id'] ?>" selected>
                        <?= htmlspecialchars($pref_valla['nombre'] ?? ('Valla #'.$pref_valla['id']), ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endif; ?>
                  </select>
                  <p class="text-xs text-[var(--text-secondary)] mt-1">Los precios se precargan si la valla tiene tarifa.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Proveedor (si no eliges valla)</label>
                  <select id="proveedor-select" style="width: 100%;" class="select2-search">
                    <?php if ($pref_proveedor): ?>
                      <option value="<?= (int)$pref_proveedor['id'] ?>" selected>
                        <?= htmlspecialchars($pref_proveedor['nombre'] ?? ('Proveedor #'.$pref_proveedor['id']), ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endif; ?>
                  </select>
                  <p class="text-xs text-[var(--text-secondary)] mt-1">Se usa para crear cliente CRM y asociar la factura.</p>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Monto Base (DOP)</label>
                  <input type="number" id="monto" placeholder="50000" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                </div>
                <div>
                  <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Descuento (DOP)</label>
                  <input type="number" id="descuento" value="0" placeholder="0" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                </div>
              </div>

              <div class="bg-[var(--main-bg)] p-4 rounded-lg">
                <p class="text-sm text-[var(--text-secondary)]">Comisión del proveedor (10%):
                  <span id="comision-preview" class="font-semibold text-[var(--text-primary)]">$0.00</span>
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Notas (opcional)</label>
                <input id="notas" type="text" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="Referencia interna, condiciones, etc.">
              </div>
            </div>
          </div>

          <!-- Paso 3 -->
          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
            <h3 class="text-lg font-semibold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3 mb-4 flex items-center gap-3">
              <span class="bg-indigo-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">3</span>
              Método de Pago
            </h3>
            <div class="bg-blue-50 dark:bg-blue-500/10 p-4 rounded-lg">
              <div class="flex justify-between items-center">
                <p class="font-semibold text-blue-800 dark:text-blue-300">Método: Transferencia Bancaria</p>
                <button type="button" id="copy-bank-details" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200">
                  <i class="fas fa-copy mr-1"></i> Copiar
                </button>
              </div>
              <div id="bank-details" class="mt-2 text-sm text-blue-700 dark:text-blue-400 space-y-1 border-t border-blue-200 dark:border-blue-500/20 pt-2">
                <p><span class="font-semibold">Banco:</span> Banco Popular</p>
                <p><span class="font-semibold">Cuenta:</span> 82993283 (Corriente)</p>
                <p><span class="font-semibold">Titular:</span> <?= htmlspecialchars($branding['title'] ?? 'Empresa', ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Vista previa -->
        <div class="xl:col-span-1">
          <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md sticky top-24">
            <h3 class="text-lg font-semibold text-[var(--text-primary)] mb-4">Vista Previa</h3>
            <div class="border border-[var(--border-color)] rounded-lg p-6">
              <div class="flex justify-between items-start pb-4 border-b border-[var(--border-color)]">
                <div>
                  <p class="font-bold text-xl"><?= htmlspecialchars($branding['title'] ?? 'VallasLed', ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="text-xs text-[var(--text-secondary)]">—</p>
                  <p class="text-xs text-[var(--text-secondary)]">RNC: —</p>
                </div>
                <img src="<?= htmlspecialchars($branding['logo_url'] ?: 'https://placehold.co/150x50/111827/FFFFFF?text=Vallas+Admin', ENT_QUOTES, 'UTF-8') ?>" alt="Logo Empresa" class="h-8">
              </div>
              <div class="flex justify-between items-start py-4">
                <div>
                  <p class="text-sm font-medium text-[var(--text-secondary)]">FACTURAR A:</p>
                  <p id="preview-cliente-nombre" class="font-semibold">
                    <?php
                      if ($pref_base)       { echo htmlspecialchars($pref_base['label'] ?? 'Cliente', ENT_QUOTES, 'UTF-8'); }
                      elseif ($pref_crm)    {
                        $lbl = $pref_crm['empresa'] ? ($pref_crm['empresa'].' ('.($pref_crm['nombre'] ?? '-').')') : ($pref_crm['nombre'] ?? ('CRM #'.$pref_crm['id']));
                        echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8');
                      } else { echo 'Seleccione un cliente'; }
                    ?>
                  </p>
                  <p id="preview-cliente-rnc" class="text-sm text-[var(--text-secondary)]">
                    <?php
                      $rnc = $pref_base['rnc'] ?? ($pref_crm['rnc'] ?? '');
                      echo $rnc ? ('RNC: '.htmlspecialchars($rnc, ENT_QUOTES, 'UTF-8')) : '-';
                    ?>
                  </p>
                </div>
                <div>
                  <p class="text-sm font-medium text-[var(--text-secondary)]">FACTURA Nº</p>
                  <p class="font-semibold text-right">—</p>
                  <p class="text-sm text-[var(--text-secondary)] text-right">Fecha: <span id="preview-fecha"></span></p>
                </div>
              </div>
              <div class="mt-4">
                <table class="w-full text-sm">
                  <thead class="bg-[var(--main-bg)]">
                    <tr><th class="p-2 text-left font-semibold">Descripción</th><th class="p-2 text-right font-semibold">Total</th></tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-[var(--border-color)]">
                      <td id="preview-item-desc" class="p-2">
                        <?= htmlspecialchars($pref_valla['nombre'] ?? 'Servicio de Publicidad', ENT_QUOTES, 'UTF-8') ?>
                      </td>
                      <td id="preview-item-monto" class="p-2 text-right">$0.00</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr><td class="p-2 text-right font-medium">Subtotal:</td><td id="preview-subtotal" class="p-2 text-right">$0.00</td></tr>
                    <tr><td class="p-2 text-right font-medium">Descuento:</td><td id="preview-descuento" class="p-2 text-right">$0.00</td></tr>
                    <tr class="font-bold text-lg text-[var(--text-primary)]"><td class="p-2 text-right">Total:</td><td id="preview-total" class="p-2 text-right">$0.00</td></tr>
                  </tfoot>
                </table>
              </div>
            </div>
            <div class="mt-6 flex flex-col gap-3">
              <button type="submit" class="w-full bg-indigo-600 text-white font-semibold px-4 py-3 rounded-lg shadow-md hover:bg-indigo-700">Crear Factura</button>
              <a href="/console/facturacion/facturas/" class="w-full text-center bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 font-semibold px-4 py-3 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500">Cancelar</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden hidden"></div>
</div>

<!-- Modal share -->
<div id="share-modal" class="modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden opacity-0">
  <div id="share-modal-container" class="modal-container bg-[var(--card-bg)] w-full max-w-md p-6 rounded-xl shadow-lg text-center transform scale-95">
    <div class="bg-green-100 dark:bg-green-500/20 w-16 h-16 rounded-full mx-auto flex items-center justify-center mb-4">
      <i class="fas fa-check-circle fa-3x text-green-500"></i>
    </div>
    <h2 class="text-xl font-bold text-[var(--text-primary)] mb-2">Factura Creada</h2>
    <p id="share-modal-text" class="text-[var(--text-secondary)] mb-4"></p>
    <div class="relative bg-[var(--main-bg)] p-3 rounded-lg">
      <input id="share-url" type="text" readonly class="w-full bg-transparent text-sm text-[var(--text-secondary)] pr-10 focus:outline-none">
      <button id="copy-url-btn" class="absolute top-1/2 right-2 -translate-y-1/2 p-2 text-[var(--text-secondary)] hover:text-[var(--text-primary)]">
        <i class="fas fa-copy"></i>
      </button>
    </div>
    <button id="close-share-modal-btn" class="mt-6 w-full bg-indigo-600 text-white font-semibold px-4 py-2 rounded-lg">Cerrar</button>
  </div>
</div>

<!-- Toast -->
<div id="notification" class="fixed bottom-5 right-5 bg-gray-900 text-white py-2 px-4 rounded-lg hidden transition-opacity z-50"></div>

<!-- JS módulo -->
<script src="/console/asset/js/facturacion/facturas_crear.js" defer></script>
</body>
</html>

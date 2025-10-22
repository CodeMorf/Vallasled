<?php
// /console/sistema/config/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');

$branding = function_exists('load_branding') ? load_branding($conn) : ['title'=>'Panel'];
$title    = (($branding['title'] ?? 'Panel') ?: 'Panel') . ' - Configuración del Sistema';
$fav      = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<title><?=h($title)?></title>
<link rel="icon" href="<?=$fav?>">
<script>(function(){try{var t=localStorage.getItem('theme')|| (matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'); if(t==='dark') document.documentElement.classList.add('dark');}catch(e){}})();</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/config/config.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require __DIR__ . '/../../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-20 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Configuración del Sistema</h1>
      </div>
      <div class="flex items-center space-x-2 sm:space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] rounded-xl shadow-md">
        <div class="border-b border-[var(--border-color)]">
          <div class="tab-buttons flex whitespace-nowrap px-4 sm:px-6">
            <button data-tab="general"      class="tab-button active"> <i class="fas fa-cog mr-2"></i>General</button>
            <button data-tab="apis"         class="tab-button"> <i class="fas fa-key mr-2"></i>API Keys</button>
            <button data-tab="smtp"         class="tab-button"> <i class="fas fa-envelope mr-2"></i>Correo (SMTP)</button>
            <button data-tab="payments"     class="tab-button"> <i class="fab fa-stripe-s mr-2"></i>Pagos</button>
            <button data-tab="appearance"   class="tab-button"> <i class="fas fa-paint-brush mr-2"></i>Apariencia</button>
            <button data-tab="maps"         class="tab-button"> <i class="fas fa-map-marked-alt mr-2"></i>Mapas</button>
            <button data-tab="integrations" class="tab-button"> <i class="fas fa-puzzle-piece mr-2"></i>Integraciones</button>
            <button data-tab="accounting"   class="tab-button"> <i class="fas fa-file-invoice-dollar mr-2"></i>Contabilidad</button>
          </div>
        </div>

        <div class="p-6">
          <!-- General -->
          <div id="general" class="tab-content space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Nombre del Sitio</label>
                <input id="site-name" type="text" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">Email del Admin</label>
                <input id="admin-email" type="email" class="inp"></div>
            </div>
            <div><label class="font-semibold block mb-1 text-sm">Descripción del Sitio</label>
              <textarea id="site-desc" class="inp" rows="3"></textarea></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Teléfono Empresa</label>
                <input id="company-phone" type="tel" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">WhatsApp Soporte</label>
                <input id="support-whatsapp" type="tel" class="inp"></div>
            </div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-general" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- APIs -->
          <div id="apis" class="tab-content hidden space-y-6">
            <div><label class="font-semibold block mb-1 text-sm">Google Maps API Key</label>
              <input id="google-maps-api" type="password" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">OpenAI API Key</label>
              <input id="openai-api" type="password" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">Modelo OpenAI</label>
              <input id="openai-model" type="text" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">Cron Key</label>
              <input id="cron-key" type="text" class="inp"></div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-apis" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- SMTP -->
          <div id="smtp" class="tab-content hidden space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Host SMTP</label>
                <input id="smtp-host" type="text" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">Puerto SMTP</label>
                <input id="smtp-port" type="number" class="inp" min="1" max="65535"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Usuario SMTP</label>
                <input id="smtp-user" type="text" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">Contraseña SMTP</label>
                <input id="smtp-pass" type="password" class="inp"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Email Remitente</label>
                <input id="smtp-from-email" type="email" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">Nombre Remitente</label>
                <input id="smtp-from-name" type="text" class="inp"></div>
            </div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-smtp" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- Payments -->
          <div id="payments" class="tab-content hidden space-y-6">
            <div><label class="font-semibold block mb-1 text-sm">Stripe Public Key</label>
              <input id="stripe-pk" type="password" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">Stripe Secret Key</label>
              <input id="stripe-sk" type="password" class="inp"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Moneda</label>
                <input id="stripe-currency" type="text" class="inp" maxlength="3" placeholder="usd"></div>
              <div><label class="font-semibold block mb-1 text-sm">Comisión por Defecto (%)</label>
                <input id="vendor-comision" type="number" step="0.01" min="0" max="100" class="inp"></div>
            </div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-payments" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- Appearance -->
          <div id="appearance" class="tab-content hidden space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">URL del Logo</label>
                <input id="logo-url" type="text" class="inp"></div>
              <div><label class="font-semibold block mb-1 text-sm">URL del Favicon</label>
                <input id="favicon-url" type="text" class="inp"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Color Primario</label>
                <input id="primary-color" type="color" class="inp h-11 p-1"></div>
              <div><label class="font-semibold block mb-1 text-sm">Color Secundario</label>
                <input id="secondary-color" type="color" class="inp h-11 p-1"></div>
            </div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-appearance" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- Maps -->
          <div id="maps" class="tab-content hidden space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Proveedor de Mapas</label>
                <select id="map-provider" class="inp">
                  <option value="google">Google Maps</option>
                  <option value="osm">OpenStreetMap</option>
                  <option value="carto">CARTO</option>
                </select></div>
              <div><label class="font-semibold block mb-1 text-sm">Estilo de Mapa</label>
                <select id="map-style" class="inp">
                  <option value="google.roadmap">Roadmap</option>
                  <option value="google.satellite">Satellite</option>
                  <option value="google.hybrid">Hybrid</option>
                  <option value="google.terrain">Terrain</option>
                </select></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div><label class="font-semibold block mb-1 text-sm">Latitud por Defecto</label>
                <input id="map-lat" type="text" class="inp" inputmode="decimal"></div>
              <div><label class="font-semibold block mb-1 text-sm">Longitud por Defecto</label>
                <input id="map-lng" type="text" class="inp" inputmode="decimal"></div>
              <div><label class="font-semibold block mb-1 text-sm">Zoom por Defecto</label>
                <input id="map-zoom" type="number" class="inp" min="1" max="19"></div>
            </div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-maps" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- Integrations -->
          <div id="integrations" class="tab-content hidden space-y-6">
            <div><label class="font-semibold block mb-1 text-sm">Google OAuth Client ID</label>
              <input id="g-client-id" type="password" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">Google OAuth Client Secret</label>
              <input id="g-client-secret" type="password" class="inp"></div>
            <div><label class="font-semibold block mb-1 text-sm">Redirect URI</label>
              <input id="g-redirect-uri" type="text" class="inp"></div>
            <div class="flex justify-end pt-4">
              <button id="btn-save-integrations" class="btn-primary"><i class="fas fa-save mr-2"></i>Guardar Cambios</button>
            </div>
          </div>

          <!-- Accounting (solo UI demo) -->
          <div id="accounting" class="tab-content hidden space-y-2">
            <p class="text-[var(--text-secondary)] text-sm">Tablas de ejemplo. Persistencia futura.</p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-50 md:hidden hidden"></div>
</div>

<script>
window.CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    opciones: "/console/sistema/config/ajax/opciones.php",
    guardar:  "/console/sistema/config/ajax/guardar.php"
  }
});
</script>
<script src="/console/asset/js/config/config.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn  = document.getElementById('theme-toggle');
  const moon = document.getElementById('theme-toggle-dark-icon');
  const sun  = document.getElementById('theme-toggle-light-icon');
  const apply = (t) => { const d = t==='dark'; document.documentElement.classList.toggle('dark', d); moon?.classList.toggle('hidden', !d); sun?.classList.toggle('hidden', d); };
  const saved = localStorage.getItem('theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  apply(saved);
  btn?.addEventListener('click', (e) => { e.preventDefault(); const next = document.documentElement.classList.contains('dark') ? 'light':'dark'; localStorage.setItem('theme', next); apply(next); });
});
</script>
</body>
</html>

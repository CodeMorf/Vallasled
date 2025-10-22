<?php
// /console/gestion/web/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

$csrf  = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$brand = function_exists('load_branding') ? load_branding($conn) : ['title' => 'Panel'];
$title = (($brand['title'] ?? 'Panel') ?: 'Panel') . ' - Configuración Web';
$fav   = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?=$csrf?>">
<meta name="theme-color" content="#111827">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
<link rel="icon" href="<?=$fav?>">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- CSS base + módulo (sin tocar sidebar) -->
<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/web/index.css">
</head>
<body class="overflow-x-hidden" style="font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
<div class="flex h-screen relative">
  <?php require dirname(__DIR__, 2) . '/asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <!-- Header -->
    <header class="bg-[var(--header-bg)] shadow-sm p-4 flex justify-between items-center sticky top-0 z-20 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar sidebar">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold text-[var(--text-primary)]">Configuración Web</h1>
      </div>
      <div class="flex items-center gap-2">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Tema">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg"></i>
        </button>
        <button id="web-save-btn" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
          <i class="fa fa-save mr-1"></i><span class="hidden sm:inline">Guardar Cambios</span>
        </button>
      </div>
    </header>

    <!-- Contenido -->
    <form id="web-form" class="p-4 sm:p-6 lg:p-8 grid grid-cols-1 xl:grid-cols-3 gap-6" autocomplete="off" novalidate>
      <!-- Columna izquierda -->
      <div class="xl:col-span-2 space-y-6">
        <!-- Identidad -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Identidad del Sitio</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Nombre del Sitio</label>
              <input type="text" id="site_name" data-key="site_name" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Descripción (SEO)</label>
              <textarea id="site_description" data-key="site_description" rows="3" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL del Sitio</label>
              <input type="text" id="site_url" data-key="site_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Localización (p. ej. es_DO)</label>
              <input type="text" id="site_locale" data-key="site_locale" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
          </div>
        </section>

        <!-- Hero -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Sección Principal (Hero)</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Título Superior</label>
              <input type="text" id="hero_title_top" data-key="hero_title_top" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Título Inferior</label>
              <input type="text" id="hero_title_bottom" data-key="hero_title_bottom" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Subtítulo</label>
              <textarea id="hero_subtitle" data-key="hero_subtitle" rows="2" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Texto del CTA</label>
              <input type="text" id="hero_cta_text" data-key="hero_cta_text" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL del CTA</label>
              <input type="text" id="hero_cta_url" data-key="hero_cta_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
          </div>
        </section>

        <!-- Redes -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Redes Sociales</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Usuario de Twitter</label>
              <div class="relative">
                <i class="fab fa-twitter absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
                <input type="text" id="site_twitter" data-key="site_twitter" class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL de Facebook</label>
              <div class="relative">
                <i class="fab fa-facebook absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
                <input type="text" id="site_facebook" data-key="site_facebook" class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="https://facebook.com/usuario">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL de Instagram</label>
              <div class="relative">
                <i class="fab fa-instagram absolute left-4 top-1/2 -translate-y-1/2 text-[var(--text-secondary)]"></i>
                <input type="text" id="site_instagram" data-key="site_instagram" class="w-full pl-11 pr-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="https://instagram.com/usuario">
              </div>
            </div>
          </div>
        </section>

        <!-- WhatsApp -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Plantillas de Mensajes (WhatsApp)</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Interés en una valla</label>
              <textarea id="wa_tpl_valla" data-key="wa_tpl_valla" rows="4" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Interés en carrito</label>
              <textarea id="wa_tpl_cart" data-key="wa_tpl_cart" rows="3" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Contacto personalizado</label>
              <textarea id="wa_tpl_personal" data-key="wa_tpl_personal" rows="4" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
            </div>
          </div>
        </section>
      </div>

      <!-- Columna derecha -->
      <div class="space-y-6">
        <!-- Branding -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Branding y Logos</h3>
          <div class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-2">Logo Principal</label>
              <div class="flex items-center gap-4">
                <img id="logo_preview" src="" alt="Logo preview" class="h-12 w-auto bg-gray-200 dark:bg-gray-700 p-1 rounded-md object-contain">
                <input type="text" id="logo_url" data-key="logo_url" placeholder="https://.../logo.png" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-2">Favicon</label>
              <div class="flex items-center gap-4">
                <img id="favicon_preview" src="" alt="Favicon preview" class="h-12 w-12 bg-gray-200 dark:bg-gray-700 p-1 rounded-md object-contain">
                <input type="text" id="favicon_url" data-key="favicon_url" placeholder="https://.../favicon.ico" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Ancho Logo (px)</label>
                <input type="number" id="logo_width_px" data-key="logo_width_px" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
              <div>
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Alto Logo (px)</label>
                <input type="number" id="logo_height_px" data-key="logo_height_px" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
            </div>
          </div>
        </section>

        <!-- Apariencia -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Colores y Apariencia</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Color Primario</label>
              <div class="relative w-full h-10 flex items-center border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <span id="primary_color_preview" class="w-8 h-full rounded-l-lg"></span>
                <input type="text" id="theme_primary_color" data-key="theme_primary_color" class="w-full px-4 py-2 bg-transparent focus:outline-none" placeholder="#007bff">
                <div class="absolute right-0 top-0 h-full w-12">
                  <input type="color" id="primary_color_picker" class="w-full h-full opacity-0 cursor-pointer">
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Color Secundario</label>
              <div class="relative w-full h-10 flex items-center border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <span id="secondary_color_preview" class="w-8 h-full rounded-l-lg"></span>
                <input type="text" id="theme_secondary_color" data-key="theme_secondary_color" class="w-full px-4 py-2 bg-transparent focus:outline-none" placeholder="#131925">
                <div class="absolute right-0 top-0 h-full w-12">
                  <input type="color" id="secondary_color_picker" class="w-full h-full opacity-0 cursor-pointer">
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Color Fondo Footer</label>
              <div class="relative w-full h-10 flex items-center border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <span id="footer_bg_preview" class="w-8 h-full rounded-l-lg"></span>
                <input type="text" id="footer_bg_color" data-key="footer_bg_color" class="w-full px-4 py-2 bg-transparent focus:outline-none" placeholder="#0b1220">
                <div class="absolute right-0 top-0 h-full w-12">
                  <input type="color" id="footer_bg_picker" class="w-full h-full opacity-0 cursor-pointer">
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Color Texto Footer</label>
              <div class="relative w-full h-10 flex items-center border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
                <span id="footer_text_preview" class="w-8 h-full rounded-l-lg"></span>
                <input type="text" id="footer_text_color" data-key="footer_text_color" class="w-full px-4 py-2 bg-transparent focus:outline-none" placeholder="#edf8f1">
                <div class="absolute right-0 top-0 h-full w-12">
                  <input type="color" id="footer_text_picker" class="w-full h-full opacity-0 cursor-pointer">
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Radio de Borde (px)</label>
              <input type="number" id="border_radius_px" data-key="border_radius_px" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg" placeholder="2">
            </div>
          </div>
        </section>

        <!-- Banner -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Banner Principal</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-[var(--text-primary)]">Habilitar Banner en Inicio</span>
              <label class="relative inline-block w-10 h-6">
                <input id="home_banner_enabled" data-key="home_banner_enabled" type="checkbox" class="sr-only">
                <span class="absolute inset-0 bg-gray-300 rounded-full transition"></span>
                <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition"></span>
              </label>
            </div>

            <div id="banner-options">
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-2">Modo del Banner</label>
              <div class="flex gap-4 mb-4">
                <label class="flex items-center">
                  <input type="radio" name="home_banner_mode" value="video" data-key="home_banner_mode" class="mr-2"> Video
                </label>
                <label class="flex items-center">
                  <input type="radio" name="home_banner_mode" value="image" data-key="home_banner_mode" class="mr-2"> Imagen
                </label>
              </div>

              <div id="banner-video-section">
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URLs de Videos (una por línea)</label>
                <textarea id="home_banner_video_urls" data-key="home_banner_video_urls" rows="4" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg"></textarea>
              </div>

              <div id="banner-image-section" class="hidden">
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL de Imagen</label>
                <input type="text" id="home_banner_image_url" data-key="home_banner_image_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>

              <div class="mt-4">
                <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Altura del Banner (px)</label>
                <input type="number" id="home_banner_height" data-key="home_banner_height" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
              </div>
            </div>
          </div>
        </section>

        <!-- Información -->
        <section class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
          <h3 class="text-xl font-bold text-[var(--text-primary)] mb-4">Información y Enlaces</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Teléfono Principal</label>
              <input type="tel" id="company_phone" data-key="company_phone" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">WhatsApp de Soporte</label>
              <input type="tel" id="support_whatsapp" data-key="support_whatsapp" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">Email de Soporte</label>
              <input type="email" id="support_email" data-key="support_email" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL Términos</label>
              <input type="text" id="legal_terms_url" data-key="legal_terms_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL Privacidad</label>
              <input type="text" id="legal_privacy_url" data-key="legal_privacy_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL Registro Vendor</label>
              <input type="text" id="vendor_register_url" data-key="vendor_register_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
            <div>
              <label class="block text-sm font-medium text-[var(--text-secondary)] mb-1">URL Login Vendor</label>
              <input type="text" id="vendor_login_url" data-key="vendor_login_url" class="w-full px-4 py-2 border border-[var(--border-color)] bg-[var(--input-bg)] rounded-lg">
            </div>
          </div>
        </section>
      </div>
    </form>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>
window.WEB_CFG = Object.freeze({
  csrf: "<?=$csrf?>",
  endpoints: {
    settings_get: "/console/gestion/web/ajax/settings_get.php",
    settings_set: "/console/gestion/web/ajax/settings_set.php"
  }
});
</script>
<script src="/console/asset/js/web/web.js" defer></script>
</body>
</html>

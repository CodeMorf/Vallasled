<?php
// /console/vallas/editar.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mapas.php';

start_session_safe();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo 'ID inválido'; exit; }

$stmt = $conn->prepare("SELECT * FROM vallas WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$vr = $stmt->get_result();
$v = $vr->fetch_assoc();
if (!$v) { http_response_code(404); echo 'No encontrada'; exit; }

$lat = is_numeric($v['lat'] ?? null) ? (float)$v['lat'] : 18.4861;
$lng = is_numeric($v['lng'] ?? null) ? (float)$v['lng'] : -69.9312;
$fv  = !empty($v['fecha_vencimiento']) ? date('Y-m-d', strtotime($v['fecha_vencimiento'])) : '';
$estadoActiva   = ($v['estado_valla'] ?? 'activa') === 'activa';
$visiblePublico = (int)($v['visible_publico'] ?? 1) === 1;
$mostrarPrecio  = (int)($v['mostrar_precio_cliente'] ?? 1) === 1;
$imgActual      = (string)($v['imagen'] ?? '');

// ADS prefill
$adsChecked=false; $adsStartVal=''; $adsEndVal=''; $adsMonto=''; $adsOrden='1';
$adsq = $conn->prepare("SELECT fecha_inicio,fecha_fin,monto_pagado,`orden`
                        FROM vallas_destacadas_pagos
                        WHERE valla_id=? AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
                        ORDER BY fecha_inicio DESC LIMIT 1");
$adsq->bind_param('i', $id);
$adsq->execute();
$adsr = $adsq->get_result();
if ($row = $adsr->fetch_assoc()){
  $adsChecked = true;
  $adsStartVal = e((string)$row['fecha_inicio']);
  $adsEndVal   = e((string)$row['fecha_fin']);
  $adsMonto    = e((string)$row['monto_pagado']);
  $adsOrden    = e((string)$row['orden']);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Panel - Editar Valla #<?= (int)$id ?></title>
<meta name="csrf" content="<?= e($_SESSION['csrf']) ?>">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<link rel="stylesheet" href="/console/asset/css/base.css">
<link rel="stylesheet" href="/console/asset/css/agregar/style.css">
<script>tailwind.config={darkMode:'class'}</script>
<style>
#image-preview-container.show{display:block!important}
#map{height:300px;border:1px solid var(--border-color);border-radius:.75rem}
</style>
</head>
<body class="overflow-x-hidden">

<div id="notification" class="fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-md hidden transition-transform translate-x-full z-50">
  <p id="notification-message"></p>
</div>

<div class="flex min-h-[100dvh] relative">
  <?php require __DIR__ . '/../asset/sidebar.php'; ?>

  <main class="main-content flex-1 md:ml-64">
    <header class="bg-[var(--header-bg)] p-4 flex justify-between items-center sticky top-0 z-10 border-b border-[var(--border-color)]">
      <div class="flex items-center gap-4">
        <button id="mobile-menu-button" class="md:hidden p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Abrir menú">
          <i class="fas fa-bars"></i>
        </button>
        <button id="sidebar-toggle-desktop" class="hidden md:block p-2 rounded-md text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]" aria-label="Colapsar menú">
          <i class="fas fa-bars fa-lg"></i>
        </button>
        <h1 class="text-xl md:text-2xl font-bold">Editar Valla #<?= (int)$id ?></h1>
      </div>
      <div class="flex items-center space-x-4">
        <button id="theme-toggle" class="p-2 rounded-full text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--sidebar-active-bg)]">
          <i id="theme-toggle-dark-icon" class="fas fa-moon fa-lg hidden"></i>
          <i id="theme-toggle-light-icon" class="fas fa-sun fa-lg hidden"></i>
        </button>
      </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8">
      <div class="bg-[var(--card-bg)] p-6 rounded-xl shadow-md">
        <form id="edit-valla-form" autocomplete="off">
          <input type="hidden" name="csrf" id="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <input type="hidden" name="id" id="valla_id" value="<?= (int)$id ?>">
          <input type="hidden" name="imagen_url" id="imagen_url" value="<?= e($imgActual) ?>">
          <input type="hidden" name="estado_valla" id="estado_valla_hidden" value="<?= $estadoActiva?'activa':'inactiva' ?>">

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-6">
              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Información General</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium mb-1">
                      Nombre de la Valla <span class="text-red-500">*</span>
                      <button type="button" id="ai-titulo" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                        <i class="fas fa-wand-magic-sparkles ai-sparkle"></i>
                      </button>
                    </label>
                    <input type="text" id="nombre" name="nombre" value="<?= e($v['nombre'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    <div id="ai-titulo-list" class="ai-list hidden"></div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Proveedor <span class="text-red-500">*</span></label>
                    <select id="proveedor" name="proveedor" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      <?php
                      $opts = [1=>'Fox Publicidad',3=>'VALLAS PAUL',4=>'Grupoohla srl',5=>'Alfrit srl',7=>'Vallas Universal',8=>'admin',9=>'Captiva',10=>'Colorin'];
                      $pid = (int)($v['proveedor_id'] ?? 0);
                      echo '<option value="">Seleccione un proveedor</option>';
                      foreach ($opts as $k=>$txt) {
                        $sel = $pid === $k ? ' selected' : '';
                        echo '<option value="'.(int)$k.'"'.$sel.'>'.e($txt).'</option>';
                      }
                      ?>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Provincia <span class="text-red-500">*</span></label>
                    <select id="provincia" name="provincia_id" data-current="<?= e((string)($v['provincia_id'] ?? '')) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      <option value="">Cargando...</option>
                    </select>
                  </div>

                  <div class="relative">
                    <label class="block text-sm font-medium mb-1">Zona <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                      <div class="relative w-full">
                        <input type="text" id="zona" name="zona" value="<?= e($v['zona'] ?? '') ?>" autocomplete="off" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      </div>
                      <button type="button" id="add-zone-btn" class="px-3 py-2 bg-indigo-100 text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-800">
                        <i class="fas fa-plus"></i>
                      </button>
                    </div>
                    <div id="zona-autocomplete-results" class="autocomplete-results hidden"></div>
                  </div>

                  <div class="md:col-span-2 relative">
                    <label class="block text-sm font-medium mb-1">Ubicación (Dirección) <span class="text-red-500">*</span></label>
                    <textarea id="ubicacion" name="ubicacion" rows="2" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]"><?= e($v['ubicacion'] ?? '') ?></textarea>
                    <input id="ubicacion_search" type="text" class="absolute top-8 right-3 w-1/2 md:w-1/3 px-3 py-1.5 bg-white/90 border border-[var(--border-color)] rounded-md text-sm" placeholder="Buscar dirección (Google)">
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-1">Latitud <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="lat" name="lat" value="<?= e((string)$lat) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Longitud <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="lng" name="lng" value="<?= e((string)$lng) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div class="relative">
                  <label class="block text-sm font-medium mb-1">Mapa de Ubicación</label>
                  <div id="map"></div>
                  <button type="button" id="fullscreen-map-btn" class="absolute top-10 right-10 bg-[var(--card-bg)] text-[var(--text-primary)] p-2 rounded-md shadow-md z-[401]"><i class="fas fa-expand"></i></button>
                  <button type="button" id="lock-map-btn" class="absolute top-10 right-2 bg-[var(--card-bg)] text-green-500 p-2 rounded-md shadow-md z-[401]"><i class="fas fa-lock-open"></i></button>
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Vista de Calle 360°</label>
                  <div id="street-view" class="flex items-center justify-center rounded-lg overflow-hidden"></div>
                  <p id="street-view-placeholder" class="text-sm text-[var(--text-secondary)]">Mueve el marcador para ver</p>
                </div>
              </div>
            </div>

            <div class="lg:col-span-1 space-y-6">
              <div>
                <label class="block text-sm font-medium mb-2">Imagen Principal</label>
                <div id="image-drop-zone" class="image-drop-zone flex flex-col items-center justify-center w-full min-h-40 rounded-lg cursor-pointer bg-[var(--main-bg)] p-4">
                  <div id="upload-prompt" class="flex flex-col items-center justify-center text-center<?= $imgActual ? ' hidden' : '' ?>">
                    <i class="fas fa-cloud-upload-alt fa-3x text-[var(--text-secondary)]"></i>
                    <p class="mb-2 text-sm text-[var(--text-secondary)]"><span class="font-semibold">Clic para subir</span> o arrastra</p>
                  </div>
                  <div id="image-preview-container" class="<?= $imgActual ? 'show' : 'hidden' ?> w-full text-center">
                    <img id="image-preview" src="<?= $imgActual ? e($imgActual) : '' ?>" alt="Previsualización" class="max-h-32 mx-auto rounded-lg mb-2">
                    <p id="image-filename" class="text-xs font-semibold text-[var(--text-primary)] truncate"><?= $imgActual ? basename($imgActual) : '' ?></p>
                    <p id="image-filesize" class="text-xs text-[var(--text-secondary)]"></p>
                  </div>
                  <input id="image-upload" name="image-upload" type="file" class="hidden" accept="image/*">
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Detalles Técnicos y Comerciales</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-1">Tipo de Valla <span class="text-red-500">*</span></label>
                    <?php $tipo = $v['tipo'] ?? 'led'; ?>
                    <select id="tipo-valla" name="tipo" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                      <option value="led"      <?= $tipo==='led'?'selected':'' ?>>LED</option>
                      <option value="impresa"  <?= $tipo==='impresa'?'selected':'' ?>>Impresa</option>
                      <option value="movilled" <?= $tipo==='movilled'?'selected':'' ?>>MoviLED</option>
                      <option value="vehiculo" <?= $tipo==='vehiculo'?'selected':'' ?>>Vehículo</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Medida <span class="text-red-500">*</span></label>
                    <input type="text" id="medida" name="medida" value="<?= e($v['medida'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Precio por Mes (DOP)</label>
                    <input type="number" id="precio" name="precio" value="<?= e((string)($v['precio'] ?? '')) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Audiencia Mensual</label>
                    <input type="number" id="audiencia_mensual" name="audiencia_mensual" value="<?= e((string)($v['audiencia_mensual'] ?? '')) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Duración Spot (seg)</label>
                    <input type="number" id="spot_time_seg" name="spot_time_seg" value="<?= e((string)($v['spot_time_seg'] ?? '')) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div id="stream-urls-container" class="sm:col-span-2 space-y-6">
                    <div>
                      <label class="block text-sm font-medium mb-1">URL Stream Pantalla</label>
                      <input type="url" id="url_stream_pantalla" name="url_stream_pantalla" value="<?= e($v['url_stream_pantalla'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-1">URL Stream Tráfico</label>
                      <input type="url" id="url_stream_trafico" name="url_stream_trafico" value="<?= e($v['url_stream_trafico'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Optimización y Capacidad</h3>
                <div>
                  <label class="flex items-center gap-2 text-sm font-medium mb-1">
                    Palabras Clave (SEO)
                    <button type="button" id="ai-seo" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                      <i class="fas fa-sparkles ai-sparkle"></i>
                    </button>
                  </label>
                  <input type="text" id="keywords_seo" name="keywords_seo" value="<?= e($v['keywords_seo'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  <p class="text-xs text-[var(--text-secondary)] mt-1">Separa con comas.</p>
                  <div id="ai-seo-list" class="ai-list hidden"></div>
                </div>
                <div class="pt-2">
                  <label class="block text-sm font-medium mb-2">Estimación de Ocupación</label>
                  <div class="grid grid-cols-2 gap-4 mb-2">
                    <div>
                      <label class="block text-xs font-medium mb-1">Capacidad de Slots</label>
                      <input type="number" id="capacidad_reservas" name="capacidad_reservas" value="<?= e((string)($v['capacidad_reservas'] ?? 10)) ?>" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Slots Ocupados (Demo)</label>
                      <input type="number" id="slots_ocupados" value="0" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                    <div id="booking-progress-bar" class="bg-indigo-600 h-2.5 rounded-full" style="width:0%;transition:width .5s ease-in-out;"></div>
                  </div>
                  <p id="booking-percentage-text" class="text-sm text-center font-medium text-[var(--text-secondary)] mt-2"></p>
                </div>
              </div>

              <div class="relative">
                <label class="flex items-center gap-2 text-sm font-medium mb-1">
                  Descripción
                  <button type="button" id="ai-descripcion" class="ai-btn text-indigo-600 hover:text-indigo-800" title="Sugerir con IA">
                    <i class="fas fa-magic ai-sparkle"></i>
                  </button>
                </label>
                <textarea id="descripcion" name="descripcion" rows="5" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]"><?= e($v['descripcion'] ?? '') ?></textarea>
                <div id="ai-descripcion-list" class="ai-list hidden"></div>
              </div>

              <div class="space-y-4">
                <h3 class="text-lg font-semibold border-b border-[var(--border-color)] pb-2">Licencia y Publicación</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-1">Número Licencia</label>
                    <input type="text" id="numero_licencia" name="numero_licencia" value="<?= e($v['numero_licencia'] ?? '') ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-1">Vencimiento Licencia</label>
                    <input type="text" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= e($fv) ?>" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]" placeholder="Seleccionar fecha">
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Estado (Activa / Inactiva)</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" id="toggle-estado" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?= $estadoActiva?'checked':'' ?>>
                    <label for="toggle-estado" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Visible en web pública</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="visible_publico" id="toggle-visible" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?= $visiblePublico?'checked':'' ?>>
                    <label for="toggle-visible" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Publicar Precio al Cliente</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="mostrar_precio_cliente" id="toggle-precio" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?= $mostrarPrecio?'checked':'' ?>>
                    <label for="toggle-precio" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium">Marcar como ADS</span>
                  <div class="relative inline-block w-10 mr-2 align-middle">
                    <input type="checkbox" name="ads" id="toggle-ads" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?= $adsChecked ? 'checked' : '' ?>>
                    <label for="toggle-ads" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 dark:bg-gray-700 cursor-pointer"></label>
                  </div>
                </div>

                <div id="ads-details-container" class="<?= $adsChecked ? '' : 'hidden' ?> space-y-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium mb-1">Inicio ADS</label>
                      <input type="text" id="ads-start" name="ads_start" value="<?= $adsStartVal ?>" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Fin ADS</label>
                      <input type="text" id="ads-end" name="ads_end" value="<?= $adsEndVal ?>" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium mb-1">Monto Pagado (DOP)</label>
                      <input type="number" step="0.01" id="monto-pagado" name="monto_pagado" value="<?= $adsMonto ?>" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                    <div>
                      <label class="block text-xs font-medium mb-1">Orden</label>
                      <input type="number" id="orden" name="orden" value="<?= $adsOrden ?>" class="w-full px-3 py-2 text-sm border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>

          <div class="mt-8 pt-6 border-t border-[var(--border-color)] flex justify-end gap-4 lg:col-span-3">
            <a href="/console/vallas/" class="px-6 py-2.5 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 font-semibold rounded-lg shadow-sm hover:bg-gray-300 dark:hover:bg-gray-500">Cancelar</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700">Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-20 md:hidden hidden"></div>
</div>

<script>window.__GMAPS_KEY__="<?= e($GOOGLE_MAPS_API_KEY) ?>";</script>
<script src="/console/asset/js/agregar/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  // provincia seleccionada tras carga AJAX
  const sel = document.getElementById('provincia');
  const wanted = sel?.dataset.current || '';
  if (sel && wanted){
    const t = setInterval(()=>{
      if (sel.options.length>1){
        for (const o of sel.options){ if (o.value==wanted || o.text==wanted){ sel.value=o.value; break; } }
        clearInterval(t);
      }
    },150);
    setTimeout(()=>clearInterval(t),3000);
  }

  // estado enum
  const tog = document.getElementById('toggle-estado');
  const hid = document.getElementById('estado_valla_hidden');
  tog?.addEventListener('change',()=>{ if(hid) hid.value = tog.checked ? 'activa':'inactiva'; });

  // ADS UI
  const tAds = document.getElementById('toggle-ads');
  const box  = document.getElementById('ads-details-container');
  const s    = document.getElementById('ads-start');
  const e    = document.getElementById('ads-end');
  const sync = ()=>{ box?.classList.toggle('hidden', !tAds?.checked); };
  tAds?.addEventListener('change', sync); sync();

  // Datepickers
  if (window.flatpickr) {
    flatpickr('#fecha_vencimiento', {dateFormat:'Y-m-d'});
    if (s) flatpickr(s, {dateFormat:'Y-m-d'});
    if (e) flatpickr(e, {dateFormat:'Y-m-d'});
  }
});

// Submit editar
document.addEventListener('DOMContentLoaded', ()=>{
  const f = document.getElementById('edit-valla-form');
  f?.addEventListener('submit', async (ev)=>{
    ev.preventDefault();

    const tAds = document.getElementById('toggle-ads');
    const s    = document.getElementById('ads-start');
    const e2   = document.getElementById('ads-end');
    if (tAds?.checked) {
      const vs = s?.value?.trim() || '';
      const ve = e2?.value?.trim() || '';
      if (!/^\d{4}-\d{2}-\d{2}$/.test(vs) || !/^\d{4}-\d{2}-\d{2}$/.test(ve) || vs > ve) {
        alert('Fechas de ADS inválidas'); return;
      }
    }

    const b = f.querySelector('button[type="submit"]');
    const fd = new FormData(f);
    const csrf = document.querySelector('meta[name="csrf"]')?.content || document.getElementById('csrf')?.value || '';
    try{
      b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
      const r = await fetch('/console/vallas/editar/ajax/guardar.php', {
        method:'POST',
        headers: csrf ? { 'X-CSRF': csrf } : {},
        body: fd,
        cache:'no-store',
        credentials:'same-origin'
      });
      const j = await r.json().catch(()=>null);
      if (!r.ok || !j || !j.ok) {
        const det = j && j.detail ? ('\nDetalle: ' + j.detail) : '';
        if (j && j.error === 'VALIDATION' && j.fields) {
          alert('Campos inválidos: ' + Object.keys(j.fields).join(', ') + det);
        } else if (j && j.error) {
          alert('Error: ' + j.error + det);
        } else {
          alert('Error HTTP ' + r.status);
        }
        throw new Error((j?.error || ('HTTP_'+r.status)) + det);
      }
      if (typeof notify==='function') notify('Cambios guardados','success');
      window.location.href = '/console/vallas/?updated=1&id=' + encodeURIComponent(j.id || <?= (int)$id ?>);
    }catch(err){
      if (typeof notify==='function') notify('Error al guardar','error');
      console.error(err);
    }finally{
      b.disabled = false; b.textContent = 'Guardar Cambios';
    }
  });

  // nombre de imagen si hay previa
  const url = document.getElementById('imagen_url')?.value || '';
  if (url){
    const fn = document.getElementById('image-filename');
    if (fn && !fn.textContent.trim()){ try{ fn.textContent = url.split('/').pop(); }catch{} }
  }
});
</script>

<!-- Hotfix sidebar: abrir/cerrar móvil y colapso desktop -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const btnMobile = document.getElementById('mobile-menu-button');
  const btnClose  = document.getElementById('sidebar-close');
  const btnDesk   = document.getElementById('sidebar-toggle-desktop');

  function openMobile(){
    if (!sidebar) return;
    sidebar.style.transform = 'translateX(0)';
    overlay?.classList.remove('hidden');
    body.classList.add('sidebar-open','overflow-hidden');
  }
  function closeMobile(){
    if (!sidebar) return;
    sidebar.style.transform = '';
    overlay?.classList.add('hidden');
    body.classList.remove('sidebar-open','overflow-hidden');
  }

  btnMobile?.addEventListener('click', openMobile);
  btnClose?.addEventListener('click', closeMobile);
  overlay?.addEventListener('click', closeMobile);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobile(); });

  btnDesk?.addEventListener('click', () => {
    body.classList.toggle('sidebar-collapsed');
    setTimeout(()=>window.dispatchEvent(new Event('resize')),150);
  });
});
</script>

<script defer src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($GOOGLE_MAPS_API_KEY) ?>&libraries=places&v=quarterly"></script>
</body>
</html>

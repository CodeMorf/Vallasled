<?php declare(strict_types=1); ?>
<style>
  :root{
    --color-primary:#3b82f6; /* azul */
    --color-primary-600:#2563eb;
    --text:#0f172a;
    --muted:#64748b;
    --bg:#ffffff;
    --bg-soft:#f8fafc;
    --ring:#93c5fd;
    --border:#e5e7eb;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Helvetica,Arial,sans-serif;color:var(--text);background:var(--bg);line-height:1.5}
  a{color:var(--color-primary);text-decoration:none}
  a:hover{color:var(--color-primary-600)}
  img{max-width:100%;height:auto;display:block}

  /* Layout helpers mínimos para no depender de utilidades externas */
  .mx-auto{margin-left:auto;margin-right:auto}
  .px-4{padding-left:1rem;padding-right:1rem}
  .py-20{padding-top:5rem;padding-bottom:5rem}
  .py-12{padding-top:3rem;padding-bottom:3rem}
  .py-16{padding-top:4rem;padding-bottom:4rem}
  .mt-2{margin-top:.5rem}.mt-4{margin-top:1rem}.mt-5{margin-top:1.25rem}.mt-6{margin-top:1.5rem}.mt-8{margin-top:2rem}
  .mb-4{margin-bottom:1rem}
  .text-center{text-align:center}
  .text-white{color:#fff}
  .text-blue-100{color:#dbeafe}
  .text-gray-500{color:#6b7280}
  .text-gray-600{color:#4b5563}
  .font-bold{font-weight:700}
  .font-black{font-weight:900}
  .rounded{border-radius:.25rem}
  .rounded-lg{border-radius:.5rem}
  .rounded-xl{border-radius:.75rem}
  .shadow-sm{box-shadow:0 1px 2px rgba(0,0,0,.06)}
  .border{border:1px solid var(--border)}
  .border-gray-200{border-color:#e5e7eb}
  .border-gray-300{border-color:#d1d5db}
  .bg-white{background:#fff}
  .bg-gray-50{background:#f9fafb}
  .bg-white\/10{background:rgba(255,255,255,.10)}
  .border-white\/20{border-color:rgba(255,255,255,.20)}
  .max-w-5xl{max-width:64rem}
  .max-w-7xl{max-width:80rem}
  .text-4xl{font-size:2.25rem;line-height:1.2}
  .text-6xl{font-size:3.75rem;line-height:1.1}
  .text-3xl{font-size:1.875rem;line-height:1.3}
  .text-2xl{font-size:1.5rem;line-height:1.35}
  .text-lg{font-size:1.125rem}
  .tracking-tight{letter-spacing:-0.02em}
  .hidden{display:none}

  /* Responsive mínimos */
  @media (min-width:768px){
    .md\:text-6xl{font-size:3.75rem;line-height:1.1}
    .md\:text-4xl{font-size:2.25rem}
    .md\:text-3xl{font-size:1.875rem}
    .md\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .md\:grid-cols-6{grid-template-columns:repeat(6,minmax(0,1fr))}
    .md\:col-span-2{grid-column:span 2/span 2}
  }
  @media (min-width:1024px){
    .lg\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
  }

  /* Hero */
  .hero-bg{background:linear-gradient(180deg,#0b1220 0%,#0a0f1d 100%)}
  .text-transparent{color:transparent}
  .bg-clip-text{-webkit-background-clip:text;background-clip:text}

  /* Botones */
  .btn-primary{background:var(--color-primary);color:#fff;border:none}
  .btn-primary:hover{background:var(--color-primary-600)}
  .btn-primary,.btn-secondary{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;cursor:pointer}
  .btn-primary,.px-4{padding-left:1rem;padding-right:1rem}
  .btn-primary,.py-2{padding-top:.5rem;padding-bottom:.5rem}

  /* Inputs */
  input[type="text"],select,button{
    font:inherit;
  }
  input[type="text"],select{
    width:100%;padding:.5rem .75rem;border:1px solid var(--border);border-radius:.375rem;background:#fff;color:var(--text);
    outline:none;transition:border-color .15s, box-shadow .15s;
  }
  input[type="text"]:focus,select:focus{
    border-color:var(--color-primary);box-shadow:0 0 0 3px var(--ring);
  }
  kbd{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;border:1px solid var(--border);border-radius:.25rem;padding:0 .25rem;background:#fff}

  /* Grid y contenedores */
  .grid{display:grid;gap:2rem}
  .grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr))}
  .gap-8{gap:2rem}
  .flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}
  .justify-center{justify-content:center}
  .flex-col{flex-direction:column}
  .sm\:flex-row{flex-direction:row}
  .gap-3{gap:.75rem}

  /* Barra de filtros */
  #filters-bar{position:sticky;top:8px;z-index:20;background:#fff}

  /* Paginación básica */
  #pagination{gap:.5rem}
  #pagination a, #pagination button{
    padding:.5rem .75rem;border:1px solid var(--border);background:#fff;border-radius:.375rem;color:var(--text)
  }
  #pagination .active{background:var(--color-primary);color:#fff;border-color:var(--color-primary)}

  /* Tarjetas genéricas para vallas */
  .card{border:1px solid var(--border);border-radius:.75rem;overflow:hidden;background:#fff}
  .card-body{padding:1rem}

  /* Utilidades menores */
  .select-none{user-select:none}
  .z-20{z-index:20}
  .rounded-lg{border-radius:.5rem}
  .rounded-xl{border-radius:.75rem}
</style>

<section class="hero-bg text-white" aria-label="Hero">
  <div class="max-w-5xl mx-auto px-4 py-20 text-center">
    <h1 class="text-4xl md:text-6xl font-black tracking-tight">
      Publicidad Exterior
      <span class="text-transparent bg-clip-text" style="background-image:linear-gradient(90deg,var(--color-primary),#22d3ee);">Inteligente</span>
    </h1>
    <p class="mt-5 text-lg text-blue-100">Encuentra vallas disponibles por zona, provincia y tipo. Reserva con datos claros.</p>
    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
      <a href="#catalogo" class="btn-primary px-6 py-3 rounded-lg font-bold">Explorar Catálogo</a>
      <a href="#catalogo" class="px-6 py-3 rounded-lg bg-white/10 border border-white/20">Soy Proveedor</a>
    </div>
  </div>
</section>

<section id="destacadas" class="py-12 bg-white" aria-label="Valla destacada">
  <div class="max-w-7xl mx-auto px-4 ad-wrap">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl md:text-3xl font-black">Valla destacada</h2>
      <a href="#catalogo" class="font-semibold">Ver catálogo</a>
    </div>
    <div id="destacadas-strip" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>
    <div id="destacadas-empty" class="text-gray-500 text-sm hidden">Sin destacadas por ahora.</div>
  </div>
</section>

<section id="mapa" class="py-16 bg-gray-50" aria-label="Mapa">
  <?php
    require __DIR__ . '/../partials/mapas.php';
    // 0 = sin marcador; para centrar en una valla usa mapa_embed(<id>);
    mapa_embed(0, 0, 460, null);
  ?>
</section>

<section id="catalogo" class="py-16" aria-label="Catálogo">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-3xl md:text-4xl font-black">Encuentra tu valla ideal</h2>
        <p class="mt-2 text-gray-600">Filtros por nombre, tipo, zona, provincia y disponibilidad.</p>
      </div>
      <button id="toggleFiltros" class="px-4 py-2 rounded-lg border border-gray-300 font-semibold"
              aria-controls="filters-bar" aria-expanded="true">Ocultar filtros</button>
    </div>

    <div id="filters-bar" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="md:col-span-2">
          <label for="q" class="block text-sm font-medium">Buscar por nombre</label>
          <input id="q" type="text" placeholder="Ej: Av. Núñez de Cáceres" autocomplete="off">
        </div>
        <div>
          <label for="tipo" class="block text-sm font-medium">Tipo</label>
          <select id="tipo">
            <option value="">Todas</option>
            <option value="led">LED</option>
            <option value="impresa">Impresa</option>
            <option value="movilled">Móvil LED</option>
            <option value="vehiculo">Vehículo</option>
          </select>
        </div>
        <div>
          <label for="zona" class="block text-sm font-medium">Zona</label>
          <select id="zona">
            <option value="">Todas</option>
          </select>
        </div>
        <div>
          <label for="provincia" class="block text-sm font-medium">Provincia</label>
          <select id="provincia">
            <option value="">Todas</option>
          </select>
        </div>
        <div>
          <label for="disponible" class="block text-sm font-medium">Disponibilidad</label>
          <select id="disponible">
            <option value="">Todas</option>
            <option value="1">Disponible</option>
            <option value="0">Ocupado</option>
          </select>
        </div>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <button id="btnBuscar" class="btn-primary px-4 py-2 rounded-lg font-semibold">Buscar</button>
        <button id="btnLimpiar" class="px-4 py-2 rounded-lg border border-gray-200 font-semibold">Limpiar</button>
        <span class="text-xs text-gray-500" style="margin-left:auto">Atajo: tecla <kbd>f</kbd></span>
      </div>
    </div>

    <div id="vallas-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>
    <nav id="pagination" class="mt-8 flex items-center justify-center gap-2 select-none" aria-label="Paginación"></nav>
    <div id="loading-row" class="mt-6 text-center text-gray-500 hidden">Cargando…</div>
    <div id="empty-row" class="mt-6 text-center text-gray-500 hidden">No hay resultados.</div>
  </div>
</section>

<!-- Un solo JS, con defer -->
<script src="/js/app.min.js" defer></script>

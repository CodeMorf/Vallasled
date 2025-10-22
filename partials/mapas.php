<?php declare(strict_types=1);
/** Misma validación del proyecto */
$__db_config = __DIR__ . '/../config/db.php';
if (file_exists($__db_config)) { require_once $__db_config; }

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$style = isset($_GET['style']) ? preg_replace('~[^a-z0-9\._-]~i','', $_GET['style']) : 'carto.dark_matter';
$zoom  = isset($_GET['zoom'])  ? (int)$_GET['zoom'] : 8;
$height= isset($_GET['h'])     ? max(320,(int)$_GET['h']) : 560;
?>
<section id="mapa" class="py-16" aria-label="Mapa">
  <div class="max-w-7xl mx-auto px-4">
    <h2 class="text-3xl font-black text-center mb-8">Ubicaciones en el Mapa</h2>

    <div id="map-shell" class="shadow-lg rounded-xl border" style="position:relative;overflow:hidden">
      <iframe
        id="map-iframe"
        src="/api/mapa/iframe.php?style=<?=h($style)?>&zoom=<?=h((string)$zoom)?>"
        title="Mapa de vallas"
        loading="lazy"
        referrerpolicy="no-referrer"
        style="width:100%;height:<?=h((string)$height)?>px;border:0;display:block;pointer-events:none"
        tabindex="-1"
      ></iframe>

      <!-- Overlay bloqueador -->
      <button
        id="map-lock"
        type="button"
        aria-label="Mapa bloqueado. Haga doble clic para activar."
        title="Haz doble clic para activar el mapa"
        style="
          position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
          background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
          border:0;cursor:pointer;color:#e5e7eb;gap:.5rem;font-weight:700;letter-spacing:.02em;
        ">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="5" y="11" width="14" height="10" rx="2"/>
        </svg>
        <span id="map-lock-text">Doble clic para activar</span>
      </button>
    </div>
  </div>
</section>

<script>
(function(){
  const shell  = document.getElementById('map-shell');
  const iframe = document.getElementById('map-iframe');
  const lock   = document.getElementById('map-lock');
  const text   = document.getElementById('map-lock-text');

  let armed = false;        // tras primer clic
  let relockTimer = null;   // auto re-bloqueo

  function setHint(msg){
    if (text) text.textContent = msg;
  }

  function unlock(){
    armed = false;
    lock.style.display = 'none';
    iframe.style.pointerEvents = 'auto';
    // auto re-bloqueo en 25s
    clearTimeout(relockTimer);
    relockTimer = setTimeout(relock, 25000);
  }

  function relock(){
    clearTimeout(relockTimer);
    relockTimer = null;
    iframe.style.pointerEvents = 'none';
    lock.style.display = 'flex';
    setHint('Doble clic para activar');
    armed = false;
  }

  // Primer clic: armar. Segundo clic: desbloquear.
  lock.addEventListener('click', () => {
    if (!armed){
      armed = true;
      setHint('Ahora haz clic de nuevo para activar');
      // ventana para segundo clic
      setTimeout(()=>{ armed=false; setHint('Doble clic para activar'); }, 1200);
      return;
    }
    unlock();
  });

  // Doble clic directo
  lock.addEventListener('dblclick', (e) => {
    e.preventDefault();
    unlock();
  });

  // Teclado: Enter o Espacio desbloquean
  lock.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      unlock();
    }
  });

  // Salir del área vuelve a bloquear
  shell.addEventListener('mouseleave', () => {
    // si el usuario no interactúa más, re-bloquea suave
    setTimeout(()=>{ if (document.activeElement !== iframe) relock(); }, 600);
  });

  // Si la página pierde foco, re-bloquea
  window.addEventListener('blur', relock);
})();
</script>

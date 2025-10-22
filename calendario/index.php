<?php declare(strict_types=1);
@header('Content-Type: text/html; charset=utf-8');

/* === Config & tracking === */
$cfg = __DIR__ . '/../config/db.php';
$trk = __DIR__ . '/../config/tracking.php';
if (file_exists($cfg)) require $cfg;
if (file_exists($trk)) require $trk;

/* === Helpers === */
function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
if (!function_exists('h')) { function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
function only_digits(string $s): string { return preg_replace('/\D+/', '', $s) ?? ''; }

/* === Inputs === */
$id = i($_GET['id'] ?? 0);

/* === DB === */
$pdo = null;
try { $pdo = db(); } catch (Throwable $e){}

/* Nombre de la valla */
$name = 'Valla';
if ($pdo && $id>0) {
  try {
    $st = $pdo->prepare("SELECT nombre FROM vallas WHERE id=:id LIMIT 1");
    $st->execute([':id'=>$id]);
    $name = (string)($st->fetchColumn() ?: $name);
  } catch(Throwable $e){}
}

/* WhatsApp de soporte desde SQL (robusto contra distintos esquemas) */
$wa = '18090000000';
if ($pdo) {
  $tries = [
    ['t'=>'web_setting',  'k'=>'clave', 'v'=>'valor'],
    ['t'=>'web_settings', 'k'=>'clave', 'v'=>'valor'],
    ['t'=>'web_setting',  'k'=>'key',   'v'=>'value'],
    ['t'=>'web_settings', 'k'=>'key',   'v'=>'value'],
    ['t'=>'settings',     'k'=>'key',   'v'=>'value'],
    ['t'=>'settings',     'k'=>'name',  'v'=>'value'],
  ];
  foreach ($tries as $c) {
    try {
      $st = $pdo->prepare("SELECT {$c['v']} FROM {$c['t']} WHERE {$c['k']}=:k LIMIT 1");
      $st->execute([':k'=>'support_whatsapp']);
      $val = $st->fetchColumn();
      if ($val) { $wa = only_digits((string)$val) ?: $wa; break; }
    } catch(Throwable $e){ /* tabla no existe; probar siguiente */ }
  }
}

/* Título para header */
$page_title = 'Calendario · '.$name;

/* Header */
$__header = __DIR__ . '/../partials/header.php';
if (file_exists($__header)) { include $__header; }

/* Tracking body-level */
if (function_exists('tracking_body')) tracking_body();
?>
<!-- Tailwind (ligero para estilos modernos) -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; background:#0b1220; color:#e2e8f0; }
  .calendar-day{transition:background-color .2s, transform .1s}
  .calendar-day:active{transform:scale(.96)}
  @keyframes pop-in{0%{opacity:0;transform:scale(.8)}100%{opacity:1;transform:scale(1)}}
  .date-pill{animation:pop-in .25s ease-out forwards}
</style>

<div class="container mx-auto p-4 md:p-8 max-w-5xl">
  <header class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
    <div>
      <h1 class="text-2xl md:text-3xl font-extrabold text-white">Calendario de Disponibilidad</h1>
      <p class="text-slate-400"><?= h($name) ?> — ID <?= (int)$id ?></p>
    </div>
    <a href="/detalles-vallas/?id=<?= (int)$id ?>" class="w-full sm:w-auto text-center bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2 px-4 rounded-lg">
      ← Volver a Detalles
    </a>
  </header>

  <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Calendario -->
    <div class="lg:col-span-2 bg-slate-800 rounded-xl border border-slate-700 shadow">
      <div class="flex justify-between items-center p-4 border-b border-slate-700">
        <button id="prev-month" class="p-2 rounded-full hover:bg-slate-700" title="Mes anterior">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div id="current-month-year" class="text-lg font-bold text-white"></div>
        <button id="next-month" class="p-2 rounded-full hover:bg-slate-700" title="Mes siguiente">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>

      <div class="p-4">
        <div class="grid grid-cols-7 gap-2 text-center text-xs text-slate-400 font-semibold mb-2">
          <div>LUN</div><div>MAR</div><div>MIÉ</div><div>JUE</div><div>VIE</div><div>SÁB</div><div>DOM</div>
        </div>
        <div id="calendar-grid" class="grid grid-cols-7 gap-2"></div>
      </div>

      <div class="p-4 border-t border-slate-700 flex flex-wrap justify-center gap-4 text-xs text-slate-400">
        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-slate-700"></div><span>Disponible</span></div>
        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-sky-500"></div><span>Seleccionado</span></div>
        <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-rose-500/30 relative overflow-hidden"><div class="absolute w-full h-px bg-rose-400 top-1/2 -mt-px"></div></div><span>Ocupado</span></div>
      </div>
    </div>

    <!-- Panel lateral -->
    <aside class="lg:col-span-1">
      <div class="sticky top-8 bg-slate-800 p-6 rounded-xl border border-slate-700 space-y-6">
        <div>
          <h2 class="text-xl font-bold text-white mb-4">Fechas seleccionadas</h2>
          <div id="selected-dates" class="min-h-[84px] bg-slate-900/50 p-3 rounded-lg border border-slate-700 flex flex-wrap gap-2 content-start">
            <p id="no-dates" class="text-slate-500 text-sm">Toca días disponibles en el calendario.</p>
          </div>
        </div>

        <div class="space-y-3">
          <button id="btn-wa" class="w-full flex items-center justify-center gap-3 bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-extrabold py-3 px-4 rounded-lg transition-transform hover:scale-105 shadow-lg shadow-emerald-500/20 disabled:bg-slate-600 disabled:text-slate-400 disabled:shadow-none disabled:hover:scale-100 disabled:cursor-not-allowed" disabled>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 6.18 2 2 0 0 1 4.11 4h3a2 2 0 0 1 2 1.72c.1.96.38 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.1 9.9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.32 1.85.6 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Enviar por WhatsApp
          </button>
          <button id="btn-clear" class="w-full bg-slate-700 hover:bg-slate-600 text-slate-200 font-semibold py-2 px-4 rounded-lg">Limpiar selección</button>
        </div>

        <div class="text-xs text-slate-500 pt-2">
          Las fechas ocupadas vienen de <code>/api/disponibilidad.php</code>.
        </div>
      </div>
    </aside>
  </main>
</div>

<script>
(() => {
  const VALLA_ID   = <?= json_encode($id) ?>;
  const VALLA_NAME = <?= json_encode($name) ?>;
  const WA_NUMBER  = <?= json_encode($wa) ?>; // E164 desde SQL

  const grid   = document.getElementById('calendar-grid');
  const header = document.getElementById('current-month-year');
  const prev   = document.getElementById('prev-month');
  const next   = document.getElementById('next-month');

  const box    = document.getElementById('selected-dates');
  const noMsg  = document.getElementById('no-dates');
  const send   = document.getElementById('btn-wa');
  const clearB = document.getElementById('btn-clear');

  let current  = new Date(); current.setDate(1);
  let selected = new Set();
  let busySet  = new Set();

  function fmtISO(y,m,d){ return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }
  function today0(){ const t=new Date(); t.setHours(0,0,0,0); return t; }

  async function loadBusy(year, month){
    busySet = new Set();
    try{
      const url = `/api/disponibilidad.php?id=${encodeURIComponent(VALLA_ID)}&mes=${month+1}&anio=${year}`;
      const r = await fetch(url, { cache:'no-store' });
      const j = await r.json().catch(()=>({}));
      const arr = Array.isArray(j?.ocupado) ? j.ocupado : [];
      arr.forEach(d=>busySet.add(String(d)));
    }catch(_){}
  }

  function render(){
    grid.innerHTML = '';
    const y = current.getFullYear();
    const m = current.getMonth();
    header.textContent = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"][m] + ' ' + y;

    const fdow = new Date(y, m, 1).getDay();         // 0..6 dom..sáb
    const start = (fdow===0) ? 6 : fdow-1;           // lunes=0
    for (let i=0;i<start;i++) grid.insertAdjacentHTML('beforeend','<div></div>');

    const days = new Date(y, m+1, 0).getDate();
    const t0 = today0();

    for (let d=1; d<=days; d++){
      const date = new Date(y,m,d);
      const iso  = fmtISO(y, m+1, d);
      const past = date < t0;
      const busy = busySet.has(iso);

      const div  = document.createElement('div');
      div.className = 'calendar-day flex items-center justify-center h-12 rounded-full font-bold ' +
        (busy ? 'bg-rose-500/30 text-slate-500 cursor-not-allowed relative overflow-hidden' :
         past ? 'bg-slate-900/40 text-slate-600 cursor-not-allowed' :
         (selected.has(iso) ? 'bg-sky-500 hover:bg-sky-400 text-white cursor-pointer' :
                              'bg-slate-700 hover:bg-slate-600 text-white cursor-pointer'));
      div.textContent = d;
      div.dataset.date = iso;

      if (busy) {
        const line = document.createElement('div');
        line.className = 'absolute w-full h-px bg-rose-400 top-1/2 -mt-px';
        div.appendChild(line);
      }
      if (!busy && !past) {
        div.addEventListener('click', () => toggle(iso, div), { passive:true });
      }
      grid.appendChild(div);
    }
  }

  function paintSelected(){
    box.innerHTML = '';
    if (selected.size===0){
      box.appendChild(noMsg);
      send.disabled = true;
      return;
    }
    const sorted = Array.from(selected).sort();
    for (const s of sorted){
      const pill = document.createElement('div');
      pill.className = 'date-pill bg-sky-500/20 text-sky-300 text-sm font-semibold px-3 py-1 rounded-full';
      pill.textContent = s;
      box.appendChild(pill);
    }
    send.disabled = false;
  }

  function toggle(iso, el){
    if (selected.has(iso)) {
      selected.delete(iso);
      el.classList.remove('bg-sky-500','hover:bg-sky-400');
      el.classList.add('bg-slate-700','hover:bg-slate-600');
    } else {
      selected.add(iso);
      el.classList.remove('bg-slate-700','hover:bg-slate-600');
      el.classList.add('bg-sky-500','hover:bg-sky-400');
    }
    paintSelected();
  }

  async function changeMonth(delta){
    current.setMonth(current.getMonth()+delta);
    await loadBusy(current.getFullYear(), current.getMonth());
    render();
  }

  // WhatsApp directo (sin API): usa número E164 leído de SQL
  function sendWA(){
    if (selected.size===0) return;
    const dates = Array.from(selected).sort().join(', ');
    const msg = `Hola, me interesa reservar la valla "${VALLA_NAME}" (ID: ${VALLA_ID}) en: ${dates}\n\n¿Disponibilidad y siguientes pasos?`;
    const link = `https://api.whatsapp.com/send?phone=${encodeURIComponent(WA_NUMBER)}&text=${encodeURIComponent(msg)}`;
    window.open(link, '_blank', 'noopener');
  }

  prev.addEventListener('click', ()=>changeMonth(-1), { passive:true });
  next.addEventListener('click', ()=>changeMonth(1),  { passive:true });
  send.addEventListener('click', sendWA);
  clearB.addEventListener('click', ()=>{ selected.clear(); paintSelected(); render(); });

  (async function boot(){
    await loadBusy(current.getFullYear(), current.getMonth());
    render();
    paintSelected();
  })();
})();
</script>

<?php
$__footer = __DIR__ . '/../partials/footer.php';
if (file_exists($__footer)) { include $__footer; }

/* Tracking */
if (function_exists('track_pageview')) {
  track_pageview($_SERVER['REQUEST_URI'] ?? '/calendario');
}

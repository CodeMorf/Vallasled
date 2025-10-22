<?php declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/debug.php';
$ID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ctx=['title'=>"Calendario #$ID",'description'=>'Disponibilidad'];

require __DIR__ . '/../../partials/header.php';
?>
<main class="max-w-5xl mx-auto px-4 py-10">
  <h1 class="text-2xl font-black mb-4">Disponibilidad</h1>
  <div id="cal" class="rounded-xl border"></div>
</main>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const BASE = document.querySelector('meta[name=base-url]')?.content || '';
  const start = new Date(); start.setDate(1);
  const end = new Date(start.getFullYear(), start.getMonth()+2, 0);
  const from = start.toISOString().slice(0,10), to = end.toISOString().slice(0,10);

  const r = await fetch(`${BASE}/api/disponibilidad.php?id=<?= (int)$ID ?>&from=${from}&to=${to}`);
  const j = await r.json(); const busy = (j.busy||[]).map(b=>({title:b.tipo||'Ocupado', start:b.start, end:b.end}));

  const cal = new FullCalendar.Calendar(document.getElementById('cal'), {
    initialView:'dayGridMonth', height: 'auto', events: busy
  });
  cal.render();
});
</script>
<?php require __DIR__ . '/../../partials/footer.php'; ?>

<?php declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/debug.php';
$ID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ctx=['title'=>'Vista','description'=>'Visualizador'];

require __DIR__ . '/../partials/header.php';
?>
<main class="max-w-6xl mx-auto px-4 py-10">
  <div id="box" class="space-y-4"></div>
</main>
<script>
(async () => {
  const BASE = document.querySelector('meta[name=base-url]')?.content || '';
  const r = await fetch(`${BASE}/api/info-vallas/api.php?id=<?= (int)$ID ?>`);
  const j = await r.json(); if (!j.ok) { box.innerHTML = 'No encontrado'; return; }
  const v = j.data;
  const img = (v.media && v.media[0]) ? v.media[0].url : '';
  const live = (v.tipo==='led' && (v.url_stream_pantalla||v.url_stream)) ? `<iframe src="${(v.url_stream_pantalla||v.url_stream)}" class="w-full aspect-video rounded-xl border"></iframe>` : '';
  document.getElementById('box').innerHTML = `
    <h1 class="text-2xl font-black">${v.nombre||'Valla'}</h1>
    ${live || (img ? `<img src="${img}" class="w-full aspect-video object-cover rounded-xl border">` : '<div class="aspect-video bg-gray-100 rounded-xl"></div>')}
  `;
})();
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>

<?php declare(strict_types=1);
/**
 * /es/help/index.php
 * Centro de ayuda: Avatar asistente con IA + índice de URLs desde sitemap.xml
 */
$ROOT = dirname(__DIR__, 2);
require $ROOT . '/config/db.php';

/** Meta */
$ctx = [
  'title'       => 'Ayuda · Asistente con IA — ' . db_setting('site_name', 'VallasLed'),
  'description' => 'Cómo usar el avatar asistente que habla en tiempo real y navega el sitio.',
];
require $ROOT . '/asset/header.php';

/** ===== Carga sitemap.xml (remoto → local → vacío) ===== */
function fetch_sitemap_urls(string $remote, string $localFallback): array {
  $xmlStr = '';
  // 1) remoto con cURL
  try {
    $ch = curl_init($remote);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 8,
      CURLOPT_USERAGENT => 'VallasLed-Help/1.0',
    ]);
    $xmlStr = (string)curl_exec($ch);
    $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($xmlStr === '' || $code >= 400) $xmlStr = '';
  } catch (Throwable $e) { $xmlStr = ''; }

  // 2) local fallback
  if ($xmlStr === '' && is_file($localFallback)) {
    $xmlStr = (string)file_get_contents($localFallback);
  }

  // 3) parse
  $out = [];
  try {
    if ($xmlStr) {
      $sx = @simplexml_load_string($xmlStr);
      if ($sx && isset($sx->url)) {
        foreach ($sx->url as $u) {
          $loc = trim((string)$u->loc);
          if ($loc === '') continue;
          // dominio válido
          if (!preg_match('~^https?://~i', $loc)) continue;
          if (stripos($loc, 'vallasled.com') === false) continue;

          $lastmod  = isset($u->lastmod) ? (string)$u->lastmod : null;
          $priority = isset($u->priority) ? (string)$u->priority : null;

          $out[] = [
            'loc'      => $loc,
            'lastmod'  => $lastmod,
            'priority' => $priority,
          ];
        }
      }
    }
  } catch (Throwable $e) {}

  // Dedup + orden simple por lastmod desc si existe
  $seen = [];
  $clean = [];
  foreach ($out as $r) {
    $k = strtolower($r['loc']);
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $clean[] = $r;
  }
  usort($clean, function($a,$b){
    $ad = strtotime((string)($a['lastmod'] ?? '')) ?: 0;
    $bd = strtotime((string)($b['lastmod'] ?? '')) ?: 0;
    return $bd <=> $ad;
  });
  return array_slice($clean, 0, 200);
}

$urls = fetch_sitemap_urls(
  'https://vallasled.com/sitemap.xml',
  $ROOT . '/sitemap.xml'
);

/** Helpers de presentación */
function path_label(string $url): string {
  $p = parse_url($url, PHP_URL_PATH) ?? '/';
  $p = rtrim($p, '/');
  if ($p === '') $p = '/';
  if ($p === '/') return 'Inicio';
  $last = basename($p);
  $last = preg_replace('~[-_]+~', ' ', $last);
  $last = preg_replace('~\.[a-z0-9]{1,5}$~i','',$last);
  $last = trim($last);
  if ($last==='') $last='Sección';
  return mb_convert_case($last, MB_CASE_TITLE, 'UTF-8');
}
?>
<section class="py-12 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <header class="mb-8">
      <h1 class="text-3xl md:text-4xl font-black tracking-tight">Asistente con IA: voz en tiempo real</h1>
      <p class="text-gray-600 mt-2">
        El avatar flotante puede hablar y escuchar. Usa tu micrófono para consultar y navegar por cualquier sección del sitio.
      </p>
      <div class="mt-4 flex flex-wrap gap-3">
        <button id="open-asst" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-bold">
          Abrir asistente
        </button>
        <button id="test-mic" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white">
          Probar micrófono
        </button>
      </div>
      <div id="mic-result" class="text-sm text-gray-500 mt-2"></div>
    </header>

    <div class="grid md:grid-cols-2 gap-6 mb-10">
      <article class="p-5 rounded-xl border border-gray-200">
        <h2 class="text-xl font-bold">Cómo usarlo</h2>
        <ul class="list-disc pl-5 text-gray-700 mt-2 space-y-1">
          <li>Pulsa el botón flotante con el avatar o “Abrir asistente”.</li>
          <li>Concede <b>permiso de micrófono</b>.</li>
          <li>Habla natural. Ejemplo: “Muéstrame vallas LED en Santo Domingo”.</li>
          <li>Di “abrir” + el nombre de la sección para navegar.</li>
        </ul>
      </article>
      <article class="p-5 rounded-xl border border-gray-200">
        <h2 class="text-xl font-bold">Qué conoce</h2>
        <p class="text-gray-700 mt-2">
          El asistente conoce todas estas URLs del sitio (sitemap). También entiende consultas sobre ubicaciones, tipos de vallas y páginas de ayuda.
        </p>
        <p class="text-gray-500 text-sm mt-2">Fuente: <code>https://vallasled.com/sitemap.xml</code></p>
      </article>
    </div>

    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700">Filtrar páginas</label>
      <input id="filter" type="search" placeholder="Escribe para filtrar por ruta o título…"
             class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
    </div>

    <div id="url-list" class="space-y-3">
      <?php if (!$urls): ?>
        <div class="text-gray-500">No pudimos leer el sitemap. Aún así el asistente funciona para navegación básica.</div>
      <?php else: foreach ($urls as $r): $loc = (string)$r['loc']; ?>
        <article class="p-4 rounded-xl border border-gray-200 hover:border-gray-300 transition url-item" data-url="<?= h($loc) ?>">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <a class="text-emerald-700 font-semibold hover:underline" href="<?= h($loc) ?>"><?= h(path_label($loc)) ?></a>
            <span class="text-xs text-gray-500 break-all"><?= h($loc) ?></span>
          </div>
          <?php if (!empty($r['lastmod'])): ?>
            <div class="text-xs text-gray-400 mt-1">Actualizado: <?= h($r['lastmod']) ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<script>
  // Exponer URLs para el widget/IA si lo requiere
  window.__asst_urls = <?= json_encode(array_map(fn($x)=>$x['loc'], $urls), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  // Abrir el asistente flotante (si existe)
  document.getElementById('open-asst')?.addEventListener('click', () => {
    try {
      const fab = document.getElementById('va-fab');
      if (fab) fab.click();
    } catch(e){}
  });

  // Probar micrófono
  document.getElementById('test-mic')?.addEventListener('click', async () => {
    const out = document.getElementById('mic-result');
    try {
      await navigator.mediaDevices.getUserMedia({audio:true});
      out.textContent = 'Micrófono OK. Puedes usar el asistente.';
      out.className = 'text-sm text-emerald-600 mt-2';
    } catch (e) {
      out.textContent = 'No se pudo acceder al micrófono. Revisa permisos del navegador.';
      out.className = 'text-sm text-rose-600 mt-2';
    }
  });

  // Filtro en cliente
  (function(){
    const q = document.getElementById('filter');
    const items = Array.from(document.querySelectorAll('.url-item'));
    function norm(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }
    q.addEventListener('input', () => {
      const val = norm(q.value);
      items.forEach(it => {
        const u = norm(it.dataset.url || '');
        const t = norm(it.querySelector('a')?.textContent || '');
        it.style.display = (val==='' || u.includes(val) || t.includes(val)) ? '' : 'none';
      });
    });
  })();
</script>

<?php require $ROOT . '/asset/footer.php'; ?>

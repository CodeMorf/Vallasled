<?php declare(strict_types=1);
require __DIR__ . '/../config/db.php';
$ctx = [
  'title' => 'Blog - ' . db_setting('site_name', 'Vallasled.com'),
  'description' => 'Noticias y artículos sobre publicidad exterior y vallas digitales en RD.',
];
require __DIR__ . '/../asset/header.php';

/**
 * Data source priority:
 * 1) DB table blog_posts (id, slug, titulo, extracto, portada_url, publicado_en, estado='publicado')
 * 2) Local JSON /blog/sample.json
 */
$posts = [];
try {
  $sql = "SELECT id, slug, titulo, extracto, portada_url, publicado_en
          FROM blog_posts
          WHERE estado = 'publicado'
          ORDER BY publicado_en DESC
          LIMIT 50";
  $stmt = db()->query($sql);
  $posts = $stmt->fetchAll();
} catch (Throwable $e) {
  $json = __DIR__ . '/sample.json';
  if (is_file($json)) {
    $data = json_decode((string)file_get_contents($json), true);
    if (is_array($data)) { $posts = $data; }
  }
}
?>
<section class="py-14 bg-white">
  <div class="max-w-4xl mx-auto px-4">
    <h1 class="text-3xl md:text-4xl font-black tracking-tight mb-6">Blog</h1>
    <div class="space-y-6">
      <?php if (!$posts): ?>
        <div class="text-gray-500">No hay publicaciones aún.</div>
      <?php endif; ?>
      <?php foreach ($posts as $p): ?>
        <article class="p-5 rounded-xl border border-gray-200 hover:border-gray-300 transition">
          <div class="flex gap-4 items-start">
            <?php if (!empty($p['portada_url'])): ?>
              <img src="<?= h($p['portada_url']) ?>" alt="" class="w-28 h-28 object-cover rounded-lg">
            <?php endif; ?>
            <div class="flex-1">
              <h2 class="text-xl font-bold"><a href="#"><?= h($p['titulo']) ?></a></h2>
              <p class="text-gray-600 mt-1 text-sm"><?= h($p['extracto'] ?? '') ?></p>
              <div class="text-xs text-gray-500 mt-2"><?= h($p['publicado_en'] ?? '') ?></div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../asset/footer.php'; ?>

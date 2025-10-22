<?php
// /config/load.php
// Loader + caché de página completa (archivo).

declare(strict_types=1);

// Bandera maestra: por defecto DESACTIVADO.
if (!defined('LOAD_ENABLED')) {
  define('LOAD_ENABLED', false); // OFF
}

define('LOAD_TTL', 60); // si algún día lo activas, TTL corto
define('CACHE_DIR', dirname(__DIR__) . '/storage/cache'); // /storage/cache/

// ===== Helpers =====
function load_is_get_like(): bool {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  return in_array($m, ['GET','HEAD'], true);
}
function load_key(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  return 'page_' . sha1($uri);
}
function load_path(string $key): string {
  if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);
  return rtrim(CACHE_DIR,'/').'/'.$key.'.html';
}
function load_exists_fresh(string $key): bool {
  $p = load_path($key);
  return is_file($p) && (filemtime($p) + LOAD_TTL) > time();
}
function load_read(string $key): ?string {
  $p = load_path($key);
  return is_file($p) ? @file_get_contents($p) : null;
}
function load_write(string $key, string $html): void {
  $p = load_path($key);
  @file_put_contents($p, $html, LOCK_EX);
}
function load_bypass(): bool {
  // Siempre bypass para POST/AJAX o si piden no-cache
  return isset($_GET['nocache']) || !load_is_get_like();
}
function load_send_cached(string $html): void {
  header('X-Cache: HIT');
  header('Cache-Control: no-store, max-age=0, must-revalidate');
  echo $html;
  exit;
}

// ===== Purga =====
function load_purge_current(): void {
  $p = load_path(load_key());
  if (is_file($p)) @unlink($p);
}
function load_purge_all(): int {
  $n = 0;
  if (is_dir(CACHE_DIR)) {
    foreach (glob(CACHE_DIR.'/*.html') ?: [] as $f) {
      if (@unlink($f)) $n++;
    }
  }
  return $n;
}

// ===== Pipeline =====
$GLOBALS['__load_ob']  = false;
$GLOBALS['__load_key'] = null;

function load_boot(): void {
  if (!LOAD_ENABLED) {
    header('X-Cache: DISABLED');
    header('Cache-Control: no-store, max-age=0, must-revalidate');
    return;
  }

  if (isset($_GET['purge']) && $_GET['purge'] === '1') {
    $n = load_purge_all();
    header('X-Cache-PURGED: '.$n);
    header('Cache-Control: no-store, max-age=0, must-revalidate');
    return;
  }

  if (load_bypass()) {
    header('X-Cache: BYPASS');
    header('Cache-Control: no-store, max-age=0, must-revalidate');
    return;
  }

  $key = load_key();
  $GLOBALS['__load_key'] = $key;

  if (load_exists_fresh($key)) {
    $html = load_read($key);
    if ($html !== null) load_send_cached($html);
  }

  $GLOBALS['__load_ob'] = true;
  ob_start();
  header('X-Cache: MISS');
}

function load_commit(): void {
  if (!LOAD_ENABLED || !$GLOBALS['__load_ob']) return;

  $out = ob_get_clean();
  if ($out === false) $out = '';

  $key = $GLOBALS['__load_key'] ?? load_key();
  load_write($key, $out);

  // Clientes
  header('Cache-Control: no-store, max-age=0, must-revalidate');
  echo $out;
}

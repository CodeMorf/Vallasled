<?php declare(strict_types=1);

require_once __DIR__ . '/db.php';

function media_base(): string {
  $def = 'https://auth.vallasled.com/uploads';
  $b = db_setting('MEDIA_BASE_URL', $def) ?: $def;
  return rtrim($b, '/');
}

function media_norm(string $raw): string {
  $raw = trim($raw);
  if ($raw === '') return '';
  if (preg_match('~^https?://~i', $raw)) return $raw;
  if (strpos($raw, '//') === 0) return 'https:' . $raw;

  $raw = ltrim($raw, '/');            // quita / inicial
  // si viene "uploads/archivo.jpg" y el base termina en /uploads → quita el prefijo duplicado
  if (stripos($raw, 'uploads/') === 0 && preg_match('~/uploads$~i', media_base())) {
    $raw = substr($raw, 8);           // quita "uploads/"
  }
  return media_base() . '/' . $raw;
}

function is_img(string $u): bool {
  $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','webp','gif','avif','bmp'], true);
}
function is_vid(string $u): bool {
  $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
  return in_array($ext, ['mp4','webm','ogg','ogv'], true);
}

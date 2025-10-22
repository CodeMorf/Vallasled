<?php declare(strict_types=1);

/** Inyecta el widget solo en páginas HTML, no en /api ni AJAX */
(function () {
  if (PHP_SAPI === 'cli') return;

  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'OPTIONS') return;

  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  if (preg_match('~^/(api|ajax|console/ajax)/~i', $uri)) return;

  $xh = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  if ($xh === 'xmlhttprequest') return;

  $acc = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? 'text/html'));
  if (strpos($acc, 'text/html') === false) return;

  echo '<link rel="stylesheet" href="/_assistant/widget.css">';
  echo '<script defer src="/_assistant/widget.js"></script>';
})();

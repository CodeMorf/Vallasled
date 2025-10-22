<?php declare(strict_types=1);

/**
 * /config/debug.php
 * Depuración controlada por BD (tabla `configuracion`, clave `debug_mode`)
 * y sobreescrita por querystring ?debug=1. Solo afecta entorno web.
 */

require_once __DIR__ . '/db.php';

if (!function_exists('db_setting')) {
  function db_setting(string $k, ?string $d=null): ?string {
    static $cache = [];
    if (isset($cache[$k])) return $cache[$k];
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) return $d;
    $stmt = $conn->prepare("SELECT valor FROM configuracion WHERE clave=? LIMIT 1");
    if (!$stmt) return $d;
    $stmt->bind_param('s', $k);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $cache[$k] = ($res['valor'] ?? $d);
  }
}

$on = (db_setting('debug_mode','0') === '1') || (($_GET['debug'] ?? '0') === '1');

if (PHP_SAPI !== 'cli') {
  if ($on) {
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting(E_ALL);
    if (!headers_sent()) header('X-Debug: on');
  } else {
    ini_set('display_errors','0');
    ini_set('display_startup_errors','0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
  }
}

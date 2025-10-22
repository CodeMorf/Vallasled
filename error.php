<?php
declare(strict_types=1);
/* Puente público hacia el manejador unificado */
$cfg = __DIR__ . '/config/error.php';
if (is_file($cfg)) {
  require $cfg;
  if (function_exists('error_entrypoint')) error_entrypoint(null, null);
  exit;
}
http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo "Error handler missing.";

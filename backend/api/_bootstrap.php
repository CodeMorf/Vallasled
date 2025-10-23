<?php
declare(strict_types=1);

if (!defined('JSON_UNESCAPED_UNICODE')) define('JSON_UNESCAPED_UNICODE', 256);

function json_exit(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function only_methods(array $allowed): void {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if (!in_array($m, $allowed, true)) json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
}
function need_csrf_for_write(): void {
  // si usas POST, valida un token CSRF. Aquí se permite GET sin CSRF.
}
function cfg_get_map(mysqli $conn, array $claves): array {
  if (!$claves) return [];
  $place = implode(',', array_fill(0, count($claves), '?'));
  $stmt = $conn->prepare("SELECT clave, valor FROM config_global WHERE clave IN ($place) AND activo=1 ORDER BY id DESC");
  $types = str_repeat('s', count($claves));
  $stmt->bind_param($types, ...$claves);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($r = $res->fetch_assoc()) $out[$r['clave']] = $r['valor'];
  return $out;
}

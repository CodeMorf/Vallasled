<?php
declare(strict_types=1);

// /console/gestion/clientes/ajax/_common.php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=UTF-8');

function jresp(array $a): void {
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}
function bad(string $msg, array $extra = []): void {
  jresp(['ok'=>false,'msg'=>$msg] + $extra);
}
function ok(array $data = [], array $extra = []): void {
  jresp(['ok'=>true,'data'=>$data] + $extra);
}
function read_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function require_csrf(): void {
  $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $valid = function_exists('csrf_token') ? csrf_token() : '';
  if (!$valid || !hash_equals($valid, $hdr)) {
    bad('CSRF inválido');
  }
}

// helpers
function intval_or_null($v): ?int {
  if ($v === '' || $v === null) return null;
  $i = (int)$v; return $i === 0 ? null : $i;
}

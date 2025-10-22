<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
  $rs = $conn->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC");
  $out = [];
  while ($rs && ($r = $rs->fetch_assoc())) $out[] = ['id'=>(int)$r['id'], 'nombre'=>$r['nombre']];
  if ($rs) $rs->free();
  echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error proveedores']);
}

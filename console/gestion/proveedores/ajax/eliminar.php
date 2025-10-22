<?php
// /console/gestion/proveedores/ajax/eliminar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = strtolower($_SERVER['REQUEST_METHOD'] ?? '');
if ($method !== 'post') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']);
  exit;
}
if (!wants_json()) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Solo JSON']);
  exit;
}
if (!csrf_ok_from_header_or_post()) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) $in = [];
$id  = isset($in['id']) ? (int)$in['id'] : 0;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'ID inválido']);
  exit;
}

mysqli_begin_transaction($conn);
try {
  // Borra membresía por si no hay ON DELETE CASCADE
  $stmt = $conn->prepare("DELETE FROM vendor_membresias WHERE proveedor_id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();

  // Borra proveedor
  $stmt = $conn->prepare("DELETE FROM proveedores WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();

  if ($stmt->affected_rows < 1) {
    mysqli_rollback($conn);
    http_response_code(404);
    echo json_encode(['ok'=>false,'msg'=>'No encontrado']);
    exit;
  }

  mysqli_commit($conn);
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  mysqli_rollback($conn);
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'msg'=>'Error al eliminar',
    'error'=>$e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

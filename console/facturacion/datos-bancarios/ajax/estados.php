<?php
// /console/facturacion/datos-bancarios/ajax/estados.php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'METHOD']); exit; }
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $csrfHeader)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
$action = (string)($data['action'] ?? '');
$id = (int)($data['id'] ?? 0);
if (!$id || !in_array($action, ['activar','inactivar','eliminar'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'VALIDATION']); exit; }

try {
  if ($action === 'eliminar') {
    $stmt = $conn->prepare("DELETE FROM datos_bancarios WHERE id=?");
    $stmt->bind_param('i', $id);
  } else {
    $activo = $action === 'activar' ? 1 : 0;
    $stmt = $conn->prepare("UPDATE datos_bancarios SET activo=? WHERE id=?");
    $stmt->bind_param('ii', $activo, $id);
  }
  $ok = $stmt->execute(); $stmt->close();
  echo json_encode(['ok'=>$ok?true:false]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER','msg'=>'Error de estado']);
}

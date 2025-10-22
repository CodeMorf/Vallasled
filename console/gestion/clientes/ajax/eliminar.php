<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$hdr || !csrf_verify($hdr)) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'CSRF']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($in['id'] ?? 0);
if ($id <= 0){ echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

try{
  $stmt = $conn->prepare("DELETE FROM crm_clientes WHERE id=?");
  $stmt->bind_param('i',$id);
  $stmt->execute();
  $stmt->close();
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo eliminar']);
}

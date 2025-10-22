<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$hdr || !csrf_verify($hdr)) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'CSRF']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($in['id'] ?? 0);
$nombre = trim((string)($in['nombre'] ?? ''));
$empresa = trim((string)($in['empresa'] ?? ''));
$email = trim((string)($in['email'] ?? ''));
$telefono = trim((string)($in['telefono'] ?? ''));
$proveedor_id = (int)($in['proveedor_id'] ?? 0);

$err = [];
if ($id<=0) $err['id']='ID inválido';
if ($nombre === '' || mb_strlen($nombre) < 2) $err['nombre']='Requerido';
if ($proveedor_id <= 0) $err['proveedor_id']='Seleccione proveedor';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email']='Email inválido';
if ($err){ echo json_encode(['ok'=>false,'errors'=>$err]); exit; }

try{
  $stmt=$conn->prepare("UPDATE crm_clientes SET proveedor_id=?, nombre=?, email=?, telefono=?, empresa=? WHERE id=?");
  $stmt->bind_param('issssi', $proveedor_id,$nombre,$email,$telefono,$empresa,$id);
  $stmt->execute();
  $stmt->close();
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo editar']);
}

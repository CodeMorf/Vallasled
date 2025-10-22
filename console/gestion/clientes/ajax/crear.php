<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

// CSRF
$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$hdr || !csrf_verify($hdr)) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'CSRF']); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?? [];

$nombre = trim((string)($in['nombre'] ?? ''));
$empresa = trim((string)($in['empresa'] ?? ''));
$email = trim((string)($in['email'] ?? ''));
$telefono = trim((string)($in['telefono'] ?? ''));
$proveedor_id = (int)($in['proveedor_id'] ?? 0);

$err = [];
if ($nombre === '' || mb_strlen($nombre) < 2) $err['nombre']='Requerido';
if ($proveedor_id <= 0) $err['proveedor_id']='Seleccione proveedor';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email']='Email inválido';
if ($err){ echo json_encode(['ok'=>false,'errors'=>$err]); exit; }

try {
  $stmt = $conn->prepare("INSERT INTO crm_clientes(proveedor_id,nombre,email,telefono,empresa,usuario_id) VALUES(?,?,?,?,?,?)");
  $uid = (int)($_SESSION['uid'] ?? 0);
  $stmt->bind_param('issssi', $proveedor_id,$nombre,$email,$telefono,$empresa,$uid);
  $stmt->execute();
  $id = $stmt->insert_id;
  $stmt->close();
  echo json_encode(['ok'=>true,'data'=>['id'=>$id]]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'No se pudo crear']);
}

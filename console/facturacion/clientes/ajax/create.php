<?php
// /console/facturacion/clientes/ajax/create.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
}

$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$body = json_decode(file_get_contents('php://input') ?: '{}', true);
$csrf = $body['csrf'] ?? '';
if ((!$hdr && !$csrf) || (($hdr && $hdr!==($_SESSION['csrf']??'')) && ($csrf && $csrf!==($_SESSION['csrf']??'')))) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'csrf invalid']); exit;
}

$nombre = trim((string)($body['nombre'] ?? ''));
$correo = trim((string)($body['correo'] ?? ''));

if ($nombre===''){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'nombre requerido']); exit; }

$stmt = $conn->prepare("INSERT INTO clientes (nombre, correo) VALUES (?, ?)");
$stmt->bind_param('ss', $nombre, $correo);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'insert error']); exit; }
$id = $conn->insert_id;
$stmt->close();

echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);

<?php
// /console/facturacion/facturas/ajax/create.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$csrfBody = $body['csrf'] ?? '';

if (!$csrfHeader && !$csrfBody) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'csrf']); exit; }
if (($csrfHeader && $csrfHeader !== $_SESSION['csrf']) || ($csrfBody && $csrfBody !== $_SESSION['csrf'])) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'csrf']); exit; }

$cliente_id = isset($body['cliente_id']) ? (int)$body['cliente_id'] : 0;
$crm_cliente_id = isset($body['crm_cliente_id']) ? (int)$body['crm_cliente_id'] : 0;
$valla_id = isset($body['valla_id']) ? (int)$body['valla_id'] : null;

$monto = isset($body['monto']) ? (float)$body['monto'] : 0.0;
$descuento = isset($body['descuento']) ? max(0.0,(float)$body['descuento']) : 0.0;
$precio_personalizado = isset($body['precio_personalizado']) ? (float)$body['precio_personalizado'] : null;
$metodo_pago = 'transferencia';

if ($monto <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Monto inválido']); exit; }
if (!$cliente_id && !$crm_cliente_id) { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Cliente requerido']); exit; }

$stmt = $conn->prepare("INSERT INTO facturas (usuario_id, valla_id, cliente_id, crm_cliente_id, monto, descuento, precio_personalizado, estado, metodo_pago, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW())");
$uid = (int)$_SESSION['uid'];
$vid = $valla_id ?: null;
$clid = $cliente_id ?: null;
$crmid = $crm_cliente_id ?: null;
$ppa = $precio_personalizado;

$stmt->bind_param('iiii dd s s',
  $uid, $vid, $clid, $crmid, $monto, $descuento, $ppa, $metodo_pago
);
/* Nota: mysqli no admite espacios en tipos. Rehacemos bind con tipos correctos */
$stmt->close();
$stmt = $conn->prepare("INSERT INTO facturas (usuario_id, valla_id, cliente_id, crm_cliente_id, monto, descuento, precio_personalizado, estado, metodo_pago, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW())");
$stmt->bind_param('iiii dd s s', $uid,$vid,$clid,$crmid,$monto,$descuento,$ppa,$metodo_pago);
/* Algunos entornos fallan con espacios; segunda táctica: usar string explícito */
$stmt->close();
$stmt = $conn->prepare("INSERT INTO facturas (usuario_id, valla_id, cliente_id, crm_cliente_id, monto, descuento, precio_personalizado, estado, metodo_pago, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW())");
$stmt->bind_param('iiiiddds', $uid,$vid,$clid,$crmid,$monto,$descuento,$ppa,$metodo_pago);

if (!$stmt->execute()){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'DB error']); exit;
}
$id = (int)$stmt->insert_id;
$stmt->close();

// Leer valores calculados por triggers
$q = $conn->query("SELECT id, estado, comision_pct, comision_monto FROM facturas WHERE id=".$id);
$calc = $q ? $q->fetch_assoc() : null;

echo json_encode(['ok'=>true,'id'=>$id,'estado'=>$calc['estado']??'pendiente','comision_pct'=>isset($calc['comision_pct'])?(float)$calc['comision_pct']:null,'comision_monto'=>isset($calc['comision_monto'])?(float)$calc['comision_monto']:null], JSON_UNESCAPED_UNICODE);

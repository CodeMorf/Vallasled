<?php
// /console/facturacion/ncf/ajax/emitir.php
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

$factura_id = isset($body['factura_id']) ? (int)$body['factura_id'] : 0;
$tipo_ncf   = isset($body['tipo_ncf']) ? trim((string)$body['tipo_ncf']) : '';
$ncf        = isset($body['ncf']) ? trim((string)$body['ncf']) : null;
$rnc_cliente= isset($body['rnc_cliente']) ? trim((string)$body['rnc_cliente']) : null;
$aplica_itbis = isset($body['aplica_itbis']) ? (int)!!$body['aplica_itbis'] : 0;

if ($factura_id<=0 || $tipo_ncf===''){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'Datos NCF inválidos']); exit; }

// inserta en comprobantes (simple). Si manejas numeración en otra tabla, ajusta aquí.
$stmt = $conn->prepare("INSERT INTO comprobantes (factura_id, tipo_ncf, ncf, rnc_cliente, aplica_itbis, estado, monto, created_at)
SELECT f.id, ?, ?, ?, ?, 'emitido', (f.monto-IFNULL(f.descuento,0)), NOW()
FROM facturas f WHERE f.id=?");
$stmt->bind_param('sssii', $tipo_ncf, $ncf, $rnc_cliente, $aplica_itbis, $factura_id);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'DB error']); exit; }
$stmt->close();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

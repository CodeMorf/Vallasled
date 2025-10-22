<?php
// /console/facturacion/facturas/ajax/update.php
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

$id = isset($body['id']) ? (int)$body['id'] : 0;
if ($id<=0){ http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

$set=[]; $bind=[]; $types='';

if (isset($body['monto'])) { $set[]='monto=?'; $bind[]=(float)$body['monto']; $types.='d'; }
if (isset($body['descuento'])) { $set[]='descuento=?'; $bind[]=max(0.0,(float)$body['descuento']); $types.='d'; }
if (!empty($body['estado'])) {
  $estado = $body['estado']==='pagado' ? 'pagado' : 'pendiente';
  $set[]='estado=?'; $bind[]=$estado; $types.='s';
  if ($estado==='pagado'){
    // fecha_pago si existe
    $chk = $conn->query("SHOW COLUMNS FROM facturas LIKE 'fecha_pago'");
    if ($chk && $chk->num_rows) { $set[]='fecha_pago=NOW()'; }
  }
}
if (!empty($body['ref_transferencia'])) { 
  // si existe columna
  $chk = $conn->query("SHOW COLUMNS FROM facturas LIKE 'ref_transferencia'");
  if ($chk && $chk->num_rows) { $set[]='ref_transferencia=?'; $bind[]=(string)$body['ref_transferencia']; $types.='s'; }
}
if (isset($body['notas'])) {
  $chk = $conn->query("SHOW COLUMNS FROM facturas LIKE 'notas'");
  if ($chk && $chk->num_rows) { $set[]='notas=?'; $bind[]=(string)$body['notas']; $types.='s'; }
}

if (!$set){ echo json_encode(['ok'=>true,'msg'=>'sin cambios']); exit; }

$sql="UPDATE facturas SET ".implode(',', $set)." WHERE id=?";
$bind[]=$id; $types.='i';

$stmt=$conn->prepare($sql);
$stmt->bind_param($types, ...$bind);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'DB error']); exit; }
$stmt->close();

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

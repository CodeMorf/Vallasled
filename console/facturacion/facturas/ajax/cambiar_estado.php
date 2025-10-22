<?php
// /console/facturacion/facturas/ajax/cambiar_estado.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF'], 419);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) json_exit(['ok'=>false,'error'=>'INVALID_JSON'], 400);

$id = (int)($in['id'] ?? 0);
$estado = trim((string)($in['estado'] ?? ''));
if ($id<=0) json_exit(['ok'=>false,'error'=>'ID_REQUIRED'], 422);
if (!in_array($estado, ['pendiente','pagado'], true)) json_exit(['ok'=>false,'error'=>'ESTADO_INVALID'], 422);

if ($estado === 'pagado') {
  $stmt = $conn->prepare("UPDATE facturas SET estado='pagado', fecha_pago=NOW() WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
} else {
  $stmt = $conn->prepare("UPDATE facturas SET estado='pendiente', fecha_pago=NULL WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
}
$stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

json_exit(['ok'=>true,'id'=>$id,'affected'=>$aff,'estado'=>$estado]);

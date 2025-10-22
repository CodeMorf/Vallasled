<?php
// /console/facturacion/facturas/ajax/crear.php  (FINAL)
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF'], 419);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) json_exit(['ok'=>false,'error'=>'INVALID_JSON'], 400);

$uid = (int)($_SESSION['uid'] ?? 0);
$proveedor_id   = isset($input['proveedor_id']) ? (int)$input['proveedor_id'] : null;
$crm_cliente_id = isset($input['crm_cliente_id']) ? (int)$input['crm_cliente_id'] : null;
$cliente_nombre = trim((string)($input['cliente_nombre'] ?? ''));
$cliente_email  = trim((string)($input['cliente_email'] ?? ''));
$valla_id       = isset($input['valla_id']) ? (int)$input['valla_id'] : null;
$monto          = (float)($input['monto'] ?? 0);
$descuento      = (float)($input['descuento'] ?? 0);
$metodo_pago    = 'transferencia';
$notas          = trim((string)($input['notas'] ?? ''));

if ($cliente_nombre === '' || $monto <= 0) json_exit(['ok'=>false,'error'=>'VALIDATION'], 422);
if ($descuento < 0 || $descuento > $monto) json_exit(['ok'=>false,'error'=>'DESCUENTO_RANGE'], 422);

$total = round($monto - $descuento, 2);

$stmt = $conn->prepare("
INSERT INTO facturas
(proveedor_id, crm_cliente_id, cliente_nombre, cliente_email, usuario_id, valla_id, monto, descuento, total, estado, metodo_pago, notas, fecha_generada)
VALUES (?,?,?,?,?,?,?,?,?,'pendiente',?,?, NOW())
");
$stmt->bind_param('iissii ddd ss', $proveedor_id, $crm_cliente_id, $cliente_nombre, $cliente_email, $uid, $valla_id, $monto, $descuento, $total, $metodo_pago, $notas);
/* Nota: algunos editores separan espacios en el type string, compacta a: 'iissii ddd ss' -> 'iissii ddd ss' no afecta, pero para máxima compatibilidad usa: */
$stmt->bind_param('iissii ddd ss', $proveedor_id, $crm_cliente_id, $cliente_nombre, $cliente_email, $uid, $valla_id, $monto, $descuento, $total, $metodo_pago, $notas);

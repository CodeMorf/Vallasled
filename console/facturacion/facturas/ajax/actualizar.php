<?php
// /console/facturacion/facturas/ajax/actualizar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF'], 419);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) json_exit(['ok'=>false,'error'=>'INVALID_JSON'], 400);

$id = (int)($in['id'] ?? 0);
if ($id <= 0) json_exit(['ok'=>false,'error'=>'ID_REQUIRED'], 422);

$allowed_estado = ['pendiente','pagado'];
$allowed_metodo = ['transferencia']; // sin Stripe en este módulo

// Cargar actuales para validar cálculos
$stmt = $conn->prepare("SELECT monto, descuento, estado FROM facturas WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$cur = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cur) json_exit(['ok'=>false,'error'=>'NOT_FOUND'], 404);

// Normalizar entradas
$monto       = array_key_exists('monto', $in) ? (float)$in['monto'] : (float)$cur['monto'];
$descuento   = array_key_exists('descuento', $in) ? (float)$in['descuento'] : (float)$cur['descuento'];
$notas       = array_key_exists('notas', $in) ? trim((string)$in['notas']) : null;
$metodo_pago = array_key_exists('metodo_pago', $in) ? trim((string)$in['metodo_pago']) : null;
$estado      = array_key_exists('estado', $in) ? trim((string)$in['estado']) : null;

if ($monto <= 0) json_exit(['ok'=>false,'error'=>'MONTO_INVALID'], 422);
if ($descuento < 0 || $descuento > $monto) json_exit(['ok'=>false,'error'=>'DESCUENTO_RANGE'], 422);
if ($metodo_pago !== null && !in_array($metodo_pago, $allowed_metodo, true)) json_exit(['ok'=>false,'error'=>'METODO_PAGO_INVALID'], 422);
if ($estado !== null && !in_array($estado, $allowed_estado, true)) json_exit(['ok'=>false,'error'=>'ESTADO_INVALID'], 422);

$total = round($monto - $descuento, 2);

// Construir UPDATE dinámico
$sets = [];
$types = '';
$args = [];

if (array_key_exists('monto', $in))      { $sets[] = 'monto=?';      $types.='d'; $args[]=$monto; }
if (array_key_exists('descuento', $in))  { $sets[] = 'descuento=?';  $types.='d'; $args[]=$descuento; }
if (array_key_exists('monto', $in) || array_key_exists('descuento', $in)) { $sets[]='total=?'; $types.='d'; $args[]=$total; }
if ($notas !== null)        { $sets[]='notas=?';        $types.='s'; $args[]=$notas; }
if ($metodo_pago !== null)  { $sets[]='metodo_pago=?';  $types.='s'; $args[]=$metodo_pago; }
if ($estado !== null) {
  $sets[]='estado=?'; $types.='s'; $args[]=$estado;
  if ($estado === 'pagado') { $sets[]='fecha_pago=NOW()'; }
  if ($estado === 'pendiente') { $sets[]='fecha_pago=NULL'; }
}

if (!$sets) json_exit(['ok'=>true,'id'=>$id,'affected'=>0]); // nada que actualizar

$sql = "UPDATE facturas SET ".implode(', ',$sets)." WHERE id=? LIMIT 1";
$types .= 'i'; $args[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$args);
$stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

json_exit(['ok'=>true,'id'=>$id,'affected'=>$aff,'total'=>$total]);

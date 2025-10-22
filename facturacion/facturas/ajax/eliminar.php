<?php
// /console/facturacion/facturas/ajax/eliminar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF'], 419);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) json_exit(['ok'=>false,'error'=>'INVALID_JSON'], 400);

$id = (int)($in['id'] ?? 0);
if ($id<=0) json_exit(['ok'=>false,'error'=>'ID_REQUIRED'], 422);

// Bloquear si hay referencias
$refs = [
  ['sql'=>"SELECT COUNT(*) c FROM reservas WHERE factura_id=?", 'lbl'=>'reservas'],
  ['sql'=>"SELECT COUNT(*) c FROM comprobantes WHERE factura_id=?", 'lbl'=>'comprobantes'],
  ['sql'=>"SELECT COUNT(*) c FROM comprobantes_fiscales WHERE factura_id=?", 'lbl'=>'comprobantes_fiscales'],
  ['sql'=>"SELECT COUNT(*) c FROM recibos_transferencia WHERE factura_id=?", 'lbl'=>'recibos_transferencia'],
];
$bloqueos = [];
foreach ($refs as $r) {
  $st = $conn->prepare($r['sql']);
  $st->bind_param('i', $id);
  $st->execute();
  $c = (int)$st->get_result()->fetch_assoc()['c'];
  $st->close();
  if ($c>0) $bloqueos[] = $r['lbl'];
}
if ($bloqueos) {
  json_exit(['ok'=>false,'error'=>'FK_CONSTRAINTS','refs'=>$bloqueos], 409);
}

$stmt = $conn->prepare("DELETE FROM facturas WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

json_exit(['ok'=>true,'id'=>$id,'affected'=>$aff]);

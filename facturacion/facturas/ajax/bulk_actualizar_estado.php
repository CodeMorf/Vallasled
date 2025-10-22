<?php
// /console/facturacion/facturas/ajax/bulk_actualizar_estado.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_exit(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'], 405);
if (!csrf_ok_from_header_or_post()) json_exit(['ok'=>false,'error'=>'CSRF'], 419);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) json_exit(['ok'=>false,'error'=>'INVALID_JSON'], 400);

$ids = array_values(array_filter(array_map('intval', (array)($in['ids'] ?? [])), fn($x)=>$x>0));
$estado = trim((string)($in['estado'] ?? ''));
if (!$ids) json_exit(['ok'=>false,'error'=>'IDS_REQUIRED'], 422);
if (!in_array($estado, ['pendiente','pagado'], true)) json_exit(['ok'=>false,'error'=>'ESTADO_INVALID'], 422);

$place = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

if ($estado === 'pagado') {
  $sql = "UPDATE facturas SET estado='pagado', fecha_pago=NOW() WHERE id IN ($place)";
} else {
  $sql = "UPDATE facturas SET estado='pendiente', fecha_pago=NULL WHERE id IN ($place)";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$aff = $stmt->affected_rows;
$stmt->close();

json_exit(['ok'=>true,'affected'=>$aff,'count'=>count($ids),'estado'=>$estado]);

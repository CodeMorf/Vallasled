<?php
// /console/gestion/pagos/ajax/export_csv.php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php'; // autentica y carga $conn (mysqli)

/* ===== Validaciones de entrada ===== */
$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$estado  = isset($_GET['estado']) ? strtolower(trim((string)$_GET['estado'])) : '';
$desde   = isset($_GET['desde']) ? trim((string)$_GET['desde']) : '';
$hasta   = isset($_GET['hasta']) ? trim((string)$_GET['hasta']) : '';

$estadoMap = ['pagado','pendiente','vencido','anulado'];
if ($estado !== '' && !in_array($estado, $estadoMap, true)) {
  fail('estado inválido');
}
$desde = $desde ? date('Y-m-d', strtotime($desde)) : '';
$hasta = $hasta ? date('Y-m-d', strtotime($hasta)) : '';
if ($desde && $hasta && $hasta < $desde) {
  fail('rango de fechas inválido');
}

/* ===== Verificación de tabla ===== */
$tbl = 'pagos_facturas';
$chk = $conn->query("SHOW TABLES LIKE '{$conn->real_escape_string($tbl)}'");
if (!$chk || $chk->num_rows === 0) {
  fail("tabla {$tbl} no existe");
}

/* ===== Query con filtros ===== */
$where = '1';
$types = '';
$args  = [];

if ($q !== '') {
  $where .= " AND (numero LIKE CONCAT('%',?,'%') OR cliente_nombre LIKE CONCAT('%',?,'%') OR cliente_email LIKE CONCAT('%',?,'%'))";
  $types .= 'sss';
  $args[]  = $q; $args[] = $q; $args[] = $q;
}
if ($estado !== '') {
  $where .= " AND estado = ?";
  $types .= 's';
  $args[]  = $estado;
}
if ($desde !== '') {
  $where .= " AND fecha_emision >= ?";
  $types .= 's';
  $args[]  = $desde;
}
if ($hasta !== '') {
  $where .= " AND fecha_emision <= ?";
  $types .= 's';
  $args[]  = $hasta;
}

$sql = "
  SELECT
    id,
    numero,
    cliente_nombre,
    cliente_email,
    DATE_FORMAT(fecha_emision,'%Y-%m-%d') AS fecha_emision,
    DATE_FORMAT(fecha_pago,'%Y-%m-%d')    AS fecha_pago,
    total,
    estado
  FROM {$tbl}
  WHERE {$where}
  ORDER BY id DESC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) fail('error preparando consulta');
if ($types !== '') {
  $stmt->bind_param($types, ...$args);
}
if (!$stmt->execute()) fail('error ejecutando consulta');
$res = $stmt->get_result();

/* ===== Respuesta CSV ===== */
$filename = 'pagos_export_' . date('Ymd_His') . '.csv';
header_remove('Content-Type'); // sobreescribe JSON por defecto del _bootstrap
header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* BOM UTF-8 para Excel */
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
if ($out === false) {
  // como ya enviamos headers de CSV, devolvemos línea de error simple
  echo "Error abriendo salida";
  exit;
}

/* Encabezados */
fputcsv($out, [
  'ID',
  'Factura',
  'Cliente',
  'Email',
  'Fecha Emisión',
  'Fecha Pago',
  'Total',
  'Estado'
]);

/* Filas */
while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    (int)$row['id'],
    (string)$row['numero'],
    (string)$row['cliente_nombre'],
    (string)$row['cliente_email'],
    (string)$row['fecha_emision'],
    (string)($row['fecha_pago'] ?? ''),
    number_format((float)$row['total'], 2, '.', ''), // punto decimal
    strtoupper((string)$row['estado'])
  ]);
}

fclose($out);
exit;

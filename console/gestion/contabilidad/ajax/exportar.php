<?php
// /console/gestion/contabilidad/ajax/exportar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

// Validación CSRF por header
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrfHeader)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']);
    exit;
}

$q        = trim((string)($_GET['q'] ?? ''));
$tipo     = $_GET['tipo'] ?? '';
$cat      = $_GET['categoria'] ?? '';
$desde    = $_GET['desde'] ?? '';
$hasta    = $_GET['hasta'] ?? '';

$where = [];
$types = '';
$vals  = [];

if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $where[]="fecha >= ?"; $types.='s'; $vals[]=$desde.' 00:00:00'; }
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $where[]="fecha <= ?"; $types.='s'; $vals[]=$hasta.' 23:59:59'; }
if ($tipo === 'ingreso' || $tipo === 'egreso') { $where[]="tipo = ?"; $types.='s'; $vals[]=$tipo; }
if ($cat === 'venta_publicidad' || $cat === 'comision_vendor') { $where[]="categoria = ?"; $types.='s'; $vals[]=$cat; }
if ($q !== '') {
    $where[] = "(descripcion LIKE ? OR COALESCE(cliente_nombre,'') LIKE ? OR COALESCE(proveedor_nombre,'') LIKE ?)";
    $like = '%'.$q.'%'; $types.='sss'; array_push($vals,$like,$like,$like);
}
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$base = "
  SELECT
    f.id AS ref_id,'factura' AS ref_type,
    COALESCE(f.fecha_pago, f.fecha_generada) AS fecha,
    CONCAT('Pago Factura #', f.id) AS descripcion,
    'venta_publicidad' AS categoria,
    f.total AS monto,
    'ingreso' AS tipo,
    COALESCE(f.cliente_nombre,'') AS cliente_nombre,
    p.nombre AS proveedor_nombre
  FROM facturas f
  LEFT JOIN proveedores p ON p.id=f.proveedor_id
  WHERE f.estado='pagado' AND COALESCE(f.fecha_pago, f.fecha_generada) IS NOT NULL
  UNION ALL
  SELECT
    f.id AS ref_id,'factura' AS ref_type,
    COALESCE(f.fecha_pago, f.fecha_generada) AS fecha,
    CONCAT('Comisión Vendor por Factura #', f.id) AS descripcion,
    'comision_vendor' AS categoria,
    COALESCE(f.comision_monto,0.00) AS monto,
    'egreso' AS tipo,
    COALESCE(f.cliente_nombre,'') AS cliente_nombre,
    p.nombre AS proveedor_nombre
  FROM facturas f
  LEFT JOIN proveedores p ON p.id=f.proveedor_id
  WHERE f.estado='pagado' AND COALESCE(f.fecha_pago, f.fecha_generada) IS NOT NULL
    AND COALESCE(f.comision_monto,0.00) > 0
";

$sql = "SELECT * FROM ( $base ) t $whereSql ORDER BY fecha DESC, ref_id DESC";
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$vals); }
$stmt->execute();
$res = $stmt->get_result();

$fname = 'contabilidad_export_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Fecha','Descripción','Categoría','Tipo','Monto','Cliente','Vendor','FacturaID'], ',');

while ($r = $res->fetch_assoc()) {
    fputcsv($out, [
        substr((string)$r['fecha'],0,19),
        $r['descripcion'],
        $r['categoria']==='comision_vendor'?'Comisión de Vendor':'Venta de Publicidad',
        $r['tipo'],
        ($r['tipo']==='egreso' ? '-' : '').number_format((float)$r['monto'], 2, '.', ''),
        $r['cliente_nombre'],
        $r['proveedor_nombre'],
        (int)$r['ref_id']
    ], ',');
}
fclose($out);
exit;

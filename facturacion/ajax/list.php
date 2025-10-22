<?php
// /console/facturacion/facturas/ajax/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
}

function table_exists(mysqli $c, string $t): bool{
  $t=$c->real_escape_string($t);
  $q=$c->query("SHOW TABLES LIKE '$t'");
  return $q && $q->num_rows>0;
}
function col_exists(mysqli $c, string $t, string $col): bool{
  $t=$c->real_escape_string($t); $col=$c->real_escape_string($col);
  $q=$c->query("SHOW COLUMNS FROM `$t` LIKE '$col'");
  return $q && $q->num_rows>0;
}

$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
$fd = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fh = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$has_created = col_exists($conn,'facturas','created_at');
$created_col = $has_created ? 'f.created_at' : 'DATE(NOW())';

$sql ="SELECT f.id, f.usuario_id, f.valla_id, f.monto, IFNULL(f.descuento,0) descuento, f.estado, f.metodo_pago,
              f.comision_pct, f.comision_monto, $created_col AS created_at,
              v.nombre AS valla_nombre, v.proveedor_id
       FROM facturas f
       LEFT JOIN vallas v ON v.id=f.valla_id
       WHERE 1=1";

$bind=[]; $types='';

if ($estado !== '') { $sql.=" AND f.estado=?"; $bind[]=$estado; $types.='s'; }
if ($proveedor_id>0) { $sql.=" AND COALESCE(v.proveedor_id,0)=?"; $bind[]=$proveedor_id; $types.='i'; }
if ($fd!=='' && preg_match('~^\d{4}-\d{2}-\d{2}$~',$fd)) { $sql.=" AND DATE($created_col)>=?"; $bind[]=$fd; $types.='s'; }
if ($fh!=='' && preg_match('~^\d{4}-\d{2}-\d{2}$~',$fh)) { $sql.=" AND DATE($created_col)<=?"; $bind[]=$fh; $types.='s'; }

if ($search!=='') {
  $like = '%'.$conn->real_escape_string($search).'%';
  // intento por cliente si existen tablas
  if (table_exists($conn,'clientes') && col_exists($conn,'facturas','cliente_id')) {
    $sql.=" AND ( EXISTS(SELECT 1 FROM clientes c WHERE c.id=f.cliente_id AND (c.nombre LIKE ? OR c.correo LIKE ?))";
    $bind[]=$like; $bind[]=$like; $types.='ss';
    if (table_exists($conn,'crm_clientes') && col_exists($conn,'facturas','crm_cliente_id')) {
      $sql.=" OR EXISTS(SELECT 1 FROM crm_clientes cc WHERE cc.id=f.crm_cliente_id AND (cc.nombre LIKE ? OR cc.email LIKE ?))";
      $bind[]=$like; $bind[]=$like; $types.='ss';
    }
    $sql.=" )";
  } else {
    $sql.=" AND CAST(f.id AS CHAR) LIKE ?";
    $bind[]=$like; $types.='s';
  }
}

$sql.=" ORDER BY $created_col DESC LIMIT 500";

$stmt = $conn->prepare($sql);
if ($bind) $stmt->bind_param($types, ...$bind);
$stmt->execute();
$res = $stmt->get_result();
$rows=[];
while($r=$res->fetch_assoc()){
  $r['monto'] = (float)$r['monto'];
  $r['descuento'] = (float)$r['descuento'];
  $r['comision_pct'] = isset($r['comision_pct']) ? (float)$r['comision_pct'] : null;
  $r['comision_monto'] = isset($r['comision_monto']) ? (float)$r['comision_monto'] : null;
  $rows[]=$r;
}
$stmt->close();

// Totales
$tot=['pendiente'=>0.0,'pagado'=>0.0];
$tt = $conn->query("SELECT estado, SUM(monto-IFNULL(descuento,0)) t FROM facturas GROUP BY estado");
if($tt) while($t=$tt->fetch_assoc()){ $tot[$t['estado']] = (float)$t['t']; }

echo json_encode(['ok'=>true,'rows'=>$rows,'totals'=>['pendiente'=>$tot['pendiente']??0,'pagado'=>$tot['pagado']??0]], JSON_UNESCAPED_UNICODE);

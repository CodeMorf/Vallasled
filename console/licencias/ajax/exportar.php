<?php
// /console/licencias/ajax/exportar.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

/* CSRF para descarga */
$tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_GET['csrf'] ?? ''));
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$tok)) {
  if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); http_response_code(403); }
  echo json_encode(['ok'=>false,'error'=>'CSRF_INVALID'], JSON_UNESCAPED_UNICODE); exit;
}

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  $rs=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t' LIMIT 1");
  return (bool)($rs && $rs->fetch_row());
}

if (!table_exists($conn,'crm_licencias')) {
  if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); http_response_code(404); }
  echo json_encode(['ok'=>false,'error'=>'TABLE_MISSING','table'=>'crm_licencias'], JSON_UNESCAPED_UNICODE); exit;
}

/* Filtros */
$q            = trim((string)($_GET['q'] ?? ''));
$estado       = trim((string)($_GET['estado'] ?? ''));
$desde        = trim((string)($_GET['desde'] ?? ''));
$hasta        = trim((string)($_GET['hasta'] ?? ''));
$proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
$limit        = max(1, min(200000, (int)($_GET['limit'] ?? 50000)));

$sql = "
  SELECT
    l.id, COALESCE(l.titulo, CONCAT('Licencia #',l.id)) AS titulo,
    l.estado, l.periodicidad,
    l.proveedor_id, p.nombre AS proveedor,
    l.valla_id, v.nombre AS valla,
    l.cliente_id, c.nombre AS cliente,
    l.ciudad, l.entidad, l.tipo_licencia,
    l.fecha_emision, l.fecha_vencimiento,
    l.reminder_days, l.costo
  FROM crm_licencias l
  LEFT JOIN proveedores  p ON p.id = l.proveedor_id
  LEFT JOIN vallas       v ON v.id = l.valla_id
  LEFT JOIN crm_clientes c ON c.id = l.cliente_id
  WHERE 1=1";
$types = ''; $bind=[];

if ($q !== '') {
  $sql .= " AND (
    COALESCE(l.titulo,'') LIKE ? OR
    COALESCE(l.ciudad,'') LIKE ? OR
    COALESCE(l.entidad,'') LIKE ? OR
    COALESCE(l.tipo_licencia,'') LIKE ? OR
    COALESCE(v.nombre,'') LIKE ? OR
    COALESCE(p.nombre,'') LIKE ? OR
    COALESCE(c.nombre,'') LIKE ?
  )";
  $types .= 'sssssss'; $like = '%'.str_replace(['%','_'],['\\%','\\_'],$q).'%';
  array_push($bind,$like,$like,$like,$like,$like,$like,$like);
}
if ($estado !== '') { $sql .= " AND l.estado=?"; $types.='s'; $bind[]=$estado; }
if ($proveedor_id>0) { $sql .= " AND l.proveedor_id=?"; $types.='i'; $bind[]=$proveedor_id; }
if ($desde !== '') { $sql .= " AND COALESCE(l.fecha_emision,l.fecha_solicitud) >= ?"; $types.='s'; $bind[]=$desde; }
if ($hasta !== '') { $sql .= " AND COALESCE(l.fecha_vencimiento,l.fecha_emision) <= ?"; $types.='s'; $bind[]=$hasta; }

$sql .= " ORDER BY COALESCE(l.fecha_vencimiento,l.fecha_emision) DESC, l.id DESC LIMIT ?";
$types.='i'; $bind[]=$limit;

$stmt = $conn->prepare($sql);
if ($types!=='') $stmt->bind_param($types, ...$bind);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

/* CSV */
$fname = 'licencias_'.date('Ymd_His').'.csv';
if (!headers_sent()) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: no-store');
}
echo "\xEF\xBB\xBF";
$out = fopen('php://output','w');
fputcsv($out, ['ID','Título','Estado','Periodicidad','Proveedor','Valla','Cliente','Ciudad','Entidad','Tipo','Emisión','Vencimiento','Recordatorio','Costo']);
foreach ($rows as $r) {
  fputcsv($out, [
    $r['id'],
    $r['titulo'],
    $r['estado'],
    $r['periodicidad'],
    $r['proveedor'] ?? $r['proveedor_id'],
    $r['valla'] ?? $r['valla_id'],
    $r['cliente'] ?? $r['cliente_id'],
    $r['ciudad'],
    $r['entidad'],
    $r['tipo_licencia'],
    $r['fecha_emision'] ? substr((string)$r['fecha_emision'],0,10) : '',
    $r['fecha_vencimiento'] ? substr((string)$r['fecha_vencimiento'],0,10) : '',
    $r['reminder_days'],
    number_format((float)($r['costo'] ?? 0),2,'.','')
  ]);
}
fclose($out); exit;

<?php
// /console/licencias/ajax/listar.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php'; // <- correcto
start_session_safe();
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');

/* ===== Helpers ===== */
function getv(string $k, $def=null) {
  return isset($_GET[$k]) ? (is_string($_GET[$k]) ? trim($_GET[$k]) : $_GET[$k])
       : (isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $def);
}
function valid_date(?string $s): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s); }
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='$t' LIMIT 1";
  $rs = $db->query($sql);
  return (bool)($rs && $rs->fetch_row());
}
function today(): string { return (new DateTime('today'))->format('Y-m-d'); }

/* ===== Params ===== */
$q       = (string)getv('q', '');
$estado  = (string)getv('estado', ''); // aprobada|por_vencer|vencida|enviada|borrador|rechazada
$desde   = (string)getv('desde', '');
$hasta   = (string)getv('hasta', '');
$page    = max(1, (int)getv('page', 1));
$limit   = min(100, max(1, (int)getv('limit', 20)));
$offset  = ($page - 1) * $limit;

$orderby = (string)getv('orderby', 'vencimiento');
$dir     = strtolower((string)getv('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

$ORDER_MAP_L = [
  'vencimiento' => 'vencimiento',
  'emision'     => 'emision',
  'codigo'      => 'codigo',
  'id'          => 'id'
];
$ORDER_MAP_V = [
  'vencimiento' => 'v.fecha_vencimiento',
  'emision'     => 'v.fecha_creacion',
  'codigo'      => 'v.numero_licencia',
  'id'          => 'v.id'
];

/* ===== Schema detection ===== */
$HAS_LIC = table_exists($conn, 'licencias');
$HAS_V   = table_exists($conn, 'vallas');
$HAS_P   = table_exists($conn, 'proveedores');
$HAS_C   = table_exists($conn, 'clientes');

try {
  if (!$HAS_LIC && !$HAS_V) json_exit(['ok'=>true,'total'=>0,'page'=>$page,'limit'=>$limit,'items'=>[]]);

  $w = [];
  if ($q !== '') {
    $qEsc = '%'.$conn->real_escape_string($q).'%';
    if ($HAS_LIC) {
      $w[] = "(l.codigo LIKE '$qEsc' OR v.nombre LIKE '$qEsc' OR COALESCE(p.nombre,'') LIKE '$qEsc' OR COALESCE(cli.nombre,'') LIKE '$qEsc' OR v.zona LIKE '$qEsc' OR v.ubicacion LIKE '$qEsc' OR COALESCE(l.entidad,'') LIKE '$qEsc')";
    } else {
      $w[] = "(COALESCE(v.numero_licencia,'') LIKE '$qEsc' OR v.nombre LIKE '$qEsc' OR v.zona LIKE '$qEsc' OR v.ubicacion LIKE '$qEsc')";
    }
  }
  if ($desde !== '' && valid_date($desde)) {
    $d = $conn->real_escape_string($desde);
    if ($HAS_LIC) $w[] = "(l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= '$d')";
    else          $w[] = "(v.fecha_vencimiento IS NULL OR v.fecha_vencimiento >= '$d')";
  }
  if ($hasta !== '' && valid_date($hasta)) {
    $h = $conn->real_escape_string($hasta);
    if ($HAS_LIC) $w[] = "(l.fecha_emision IS NULL OR l.fecha_emision <= '$h')";
    else          $w[] = "(v.fecha_creacion IS NULL OR v.fecha_creacion <= '$h')";
  }
  $whereSql = $w ? ('WHERE '.implode(' AND ', $w)) : '';

  if ($HAS_LIC) {
    $orderCol = $ORDER_MAP_L[$orderby] ?? 'vencimiento';
    $orderSql = "ORDER BY $orderCol $dir";
    $joinP = $HAS_P ? "LEFT JOIN proveedores p ON p.id = COALESCE(l.proveedor_id, v.proveedor_id)" : "LEFT JOIN (SELECT 0 id, '' nombre) p ON 1=1";
    $joinC = $HAS_C ? "LEFT JOIN clientes cli ON cli.id = l.cliente_id" : "LEFT JOIN (SELECT 0 id, '' nombre) cli ON 1=1";
    $estadoSql = ($estado !== '') ? " AND l.estado = '".$conn->real_escape_string($estado)."'" : "";

    $countSql = "
      SELECT COUNT(*)
      FROM licencias l
      LEFT JOIN vallas v ON v.id = l.valla_id
      $joinP
      $joinC
      $whereSql $estadoSql
    ";
    $sql = "
      SELECT
        l.id,
        l.codigo,
        l.valla_id,
        v.nombre AS valla_nombre,
        v.zona, v.ubicacion,
        COALESCE(p.nombre,'')   AS proveedor_nombre,
        COALESCE(cli.nombre,'') AS cliente_nombre,
        COALESCE(l.entidad,'')  AS entidad,
        DATE(l.fecha_emision)     AS emision,
        DATE(l.fecha_vencimiento) AS vencimiento,
        COALESCE(l.estado,'')     AS estado
      FROM licencias l
      LEFT JOIN vallas v ON v.id = l.valla_id
      $joinP
      $joinC
      $whereSql $estadoSql
      $orderSql
      LIMIT $limit OFFSET $offset
    ";
  } else {
    $orderCol = $ORDER_MAP_V[$orderby] ?? 'v.fecha_vencimiento';
    $orderSql = "ORDER BY $orderCol $dir";
    $joinP = $HAS_P ? "LEFT JOIN proveedores p ON p.id = v.proveedor_id" : "LEFT JOIN (SELECT 0 id, '' nombre) p ON 1=1";
    $joinC = $HAS_C ? "LEFT JOIN clientes cli ON cli.id = v.cliente_id" : "LEFT JOIN (SELECT 0 id, '' nombre) cli ON 1=1";

    $countSql = "
      SELECT COUNT(*)
      FROM vallas v
      $joinP
      $joinC
      $whereSql
    ";
    $sql = "
      SELECT
        v.id,
        COALESCE(v.numero_licencia, CONCAT('VL-', v.id)) AS codigo,
        v.id AS valla_id,
        v.nombre AS valla_nombre,
        v.zona, v.ubicacion,
        COALESCE(p.nombre,'')   AS proveedor_nombre,
        COALESCE(cli.nombre,'') AS cliente_nombre,
        COALESCE(v.tipo_licencia,'') AS entidad,
        DATE(v.fecha_creacion)    AS emision,
        DATE(v.fecha_vencimiento) AS vencimiento,
        NULL AS estado
      FROM vallas v
      $joinP
      $joinC
      $whereSql
      $orderSql
      LIMIT $limit OFFSET $offset
    ";
  }

  // total
  $rs = $conn->query($countSql);
  $row = $rs ? $rs->fetch_row() : [0];
  $total = (int)$row[0];

  // page data
  $items = [];
  $rs2 = $conn->query($sql);
  $today = today();

  while ($r = $rs2->fetch_assoc()) {
    $emision = $r['emision'] ?? null;
    $venci   = $r['vencimiento'] ?? null;
    $est     = (string)($r['estado'] ?? '');

    if (!$HAS_LIC) {
      if ($venci && $venci < $today) $est = 'vencida';
      elseif ($venci && (new DateTime($venci) <= (new DateTime($today))->modify('+30 days'))) $est = 'por_vencer';
      else $est = 'aprobada';
    }

    if (!$HAS_LIC && $estado !== '' && $estado !== $est) continue;

    $items[] = [
      'id'         => (int)$r['id'],
      'codigo'     => (string)$r['codigo'],
      'valla_id'   => (int)$r['valla_id'],
      'valla'      => (string)($r['valla_nombre'] ?? ''),
      'proveedor'  => (string)($r['proveedor_nombre'] ?? ''),
      'cliente'    => (string)($r['cliente_nombre'] ?? ''),
      'ciudad'     => (string)($r['zona'] ?? ''),
      'entidad'    => (string)($r['entidad'] ?? ''),
      'emision'    => $emision,
      'vencimiento'=> $venci,
      'estado'     => $est
    ];
  }

  if (!$HAS_LIC && $estado !== '') {
    $total = count($items);
    $items = array_slice($items, 0, $limit);
  }

  json_exit(['ok'=>true,'total'=>$total,'page'=>$page,'limit'=>$limit,'items'=>$items]);
} catch (Throwable $e) {
  json_exit(['ok'=>false,'error'=>'db_error','message'=>$e->getMessage()], 500);
}

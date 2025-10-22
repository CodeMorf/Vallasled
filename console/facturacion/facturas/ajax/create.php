<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();
require_auth(['admin','staff']);
header('Content-Type: application/json; charset=UTF-8');

function fail(int $code, string $msg){ http_response_code($code); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
function post_str(string $k): ?string { if(!isset($_POST[$k])) return null; $v=trim((string)$_POST[$k]); return $v===''?null:$v; }
function post_int(string $k): ?int { if(!isset($_POST[$k]) || $_POST[$k]==='') return null; return is_numeric($_POST[$k])?(int)$_POST[$k]:null; }
function post_float(string $k): float { return isset($_POST[$k]) && is_numeric($_POST[$k]) ? (float)$_POST[$k] : 0.0; }

function lookup_proveedor_id(mysqli $conn, ?int $valla_id): ?int {
  if (!$valla_id) return null;
  $sql = "SELECT proveedor_id FROM vallas WHERE id = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  if(!$stmt) return null;
  $stmt->bind_param('i', $valla_id);
  $stmt->execute();
  $stmt->bind_result($pid);
  $ok = $stmt->fetch();
  $stmt->close();
  return $ok ? (int)$pid : null;
}
function cliente_base(mysqli $conn, int $id): array {
  $sql="SELECT COALESCE(nombre, email, CONCAT('Cliente #',id)) AS nombre, email FROM clientes_base WHERE id=? LIMIT 1";
  $stmt=$conn->prepare($sql); $stmt->bind_param('i',$id); $stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc()?:[]; $stmt->close(); return $row;
}
function cliente_crm(mysqli $conn, int $id): array {
  $sql="SELECT COALESCE(empresa, nombre, CONCAT('CRM #',id)) AS nombre, email FROM crm_clientes WHERE id=? LIMIT 1";
  $stmt=$conn->prepare($sql); $stmt->bind_param('i',$id); $stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc()?:[]; $stmt->close(); return $row;
}

/* input */
$cliente_id     = post_int('cliente_id');
$crm_cliente_id = post_int('crm_cliente_id');
$cliente_nombre = post_str('cliente_nombre');
$cliente_email  = post_str('cliente_email');

$valla_id       = post_int('valla_id');
$proveedor_id   = post_int('proveedor_id'); // opcional si no hay valla
$monto          = post_float('monto');
$descuento      = post_float('descuento');
$metodo_pago    = post_str('metodo_pago') ?: 'transferencia';
$notas          = post_str('notas');

if ($monto <= 0) fail(422,'Monto inválido');
if (!$cliente_id && !$crm_cliente_id && !$cliente_nombre) fail(422,'Cliente requerido');

if (!$cliente_nombre || !$cliente_email) {
  if ($cliente_id) {
    $c = cliente_base($conn, $cliente_id);
    $cliente_nombre = $cliente_nombre ?: ($c['nombre'] ?? null);
    $cliente_email  = $cliente_email  ?: ($c['email']  ?? null);
  } elseif ($crm_cliente_id) {
    $c = cliente_crm($conn, $crm_cliente_id);
    $cliente_nombre = $cliente_nombre ?: ($c['nombre'] ?? null);
    $cliente_email  = $cliente_email  ?: ($c['email']  ?? null);
  }
}

$pid = $proveedor_id ?: lookup_proveedor_id($conn, $valla_id);
if (!$pid && isset($_SESSION['proveedor_id']) && $_SESSION['proveedor_id']) {
  $pid = (int)$_SESSION['proveedor_id'];
}
if (!$pid) fail(422,'Proveedor requerido');

$total = max($monto - $descuento, 0.0);
$uid   = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;

/* INSERT: tipos y número coinciden */
$sql = "INSERT INTO facturas
 (proveedor_id, cliente_id, crm_cliente_id, cliente_nombre, cliente_email, valla_id,
  monto, descuento, total, metodo_pago, notas, usuario_id, creado)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())";

$stmt = $conn->prepare($sql);
if(!$stmt){ fail(500,'Prep error'); }

/* tipos: i i i s s i d d d s s i  => 12 vars */
$stmt->bind_param(
  'iiissidddssi',
  $pid,
  $cliente_id,
  $crm_cliente_id,
  $cliente_nombre,
  $cliente_email,
  $valla_id,
  $monto,
  $descuento,
  $total,
  $metodo_pago,
  $notas,
  $uid
);

if(!$stmt->execute()){
  $err = $stmt->error ?: 'DB error';
  $stmt->close();
  fail(500,$err);
}
$id = $stmt->insert_id;
$stmt->close();

echo json_encode([
  'ok'=>true,
  'id'=>$id,
  'share_url'=>"/console/facturacion/facturas/ver.php?id=".$id
]);

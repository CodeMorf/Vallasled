<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();
require_auth(['admin','staff']);
header('Content-Type: application/json; charset=UTF-8');

function fail(int $code, string $msg){ http_response_code($code); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

/* helpers */
function post_str(string $k): ?string {
  if (!isset($_POST[$k])) return null;
  $v = trim((string)$_POST[$k]);
  return $v === '' ? null : $v;
}
function post_int(string $k): ?int {
  if (!isset($_POST[$k]) || $_POST[$k]==='') return null;
  return is_numeric($_POST[$k]) ? (int)$_POST[$k] : null;
}
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

/* datos */
$empresa     = post_str('empresa');
$responsable = post_str('responsable');
$email       = post_str('email');
$telefono    = post_str('telefono');
$valla_id    = post_int('valla_id');
$proveedorId = post_int('proveedor_id'); // opcional directo

if (!$proveedorId) {
  $proveedorId = lookup_proveedor_id($conn, $valla_id);
}
if (!$proveedorId && isset($_SESSION['proveedor_id']) && $_SESSION['proveedor_id']) {
  $proveedorId = (int)$_SESSION['proveedor_id'];
}
if (!$proveedorId) {
  fail(422, 'Proveedor requerido');
}

$nombre = $empresa ?: ($responsable ?: ($email ?: 'Cliente'));
$uid    = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;

/* INSERT seguro: tipos y variables coinciden */
$sql = "INSERT INTO crm_clientes
        (proveedor_id, nombre, email, telefono, empresa, usuario_id, creado)
        VALUES (?,?,?,?,?,?, NOW())";
$stmt = $conn->prepare($sql);
if(!$stmt){ fail(500, 'Prep error'); }

/* tipos: i s s s s i  => 6 vars */
$stmt->bind_param('issssi',
  $proveedorId,
  $nombre,
  $email,      // puede ser null, mysqli lo envía como NULL correctamente
  $telefono,   // idem
  $empresa,    // idem
  $uid         // puede ser null
);

if(!$stmt->execute()){
  $err = $stmt->error ?: 'DB error';
  $stmt->close();
  fail(500, $err);
}
$id = $stmt->insert_id;
$stmt->close();

echo json_encode([
  'ok'=>true,
  'id'=>$id,
  'nombre'=>$nombre,
  'email'=>$email,
]);

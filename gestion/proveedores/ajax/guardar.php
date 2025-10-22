<?php
// /console/gestion/proveedores/ajax/guardar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = strtolower($_SERVER['REQUEST_METHOD'] ?? '');
if ($method !== 'post') { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit; }
if (!wants_json()) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Solo JSON']); exit; }
if (!csrf_ok_from_header_or_post()) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']); exit; }

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true); if (!is_array($in)) $in = [];

// normaliza
$id        = isset($in['id']) && $in['id'] !== '' ? (int)$in['id'] : null;
$nombre    = isset($in['nombre'])    ? trim((string)$in['nombre'])    : null;
$contacto  = isset($in['contacto'])  ? trim((string)$in['contacto'])  : null;
$email     = isset($in['email'])     ? trim((string)$in['email'])     : null;
$telefono  = isset($in['telefono'])  ? trim((string)$in['telefono'])  : null;
$direccion = isset($in['direccion']) ? trim((string)$in['direccion']) : null;
$estado    = array_key_exists('estado', $in) ? (int)$in['estado'] : null;
$plan_id   = array_key_exists('plan_id', $in) && $in['plan_id']!=='' ? (int)$in['plan_id'] : null;

// validación mínima
if ($id === null && ($nombre === null || $nombre === '')) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit; }
if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Email inválido']); exit; }

// helper: ¿columna existe?
function col_exists(mysqli $c, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $c->prepare($sql);
  $st->bind_param('ss', $table, $col);
  $st->execute();
  $r = $st->get_result();
  return (bool)$r->fetch_row();
}

mysqli_begin_transaction($conn);
try {
  // CREATE
  if ($id === null) {
    $has_url_pdf = col_exists($conn, 'proveedores', 'url_pdf');

    if ($has_url_pdf) {
      // usa '' para cubrir NOT NULL sin default
      $stmt = $conn->prepare(
        "INSERT INTO proveedores (nombre, contacto, email, telefono, direccion, url_pdf, estado)
         VALUES (?, ?, ?, ?, ?, '', 1)"
      );
      $stmt->bind_param('sssss', $nombre, $contacto, $email, $telefono, $direccion);
    } else {
      $stmt = $conn->prepare(
        "INSERT INTO proveedores (nombre, contacto, email, telefono, direccion, estado)
         VALUES (?, ?, ?, ?, ?, 1)"
      );
      $stmt->bind_param('sssss', $nombre, $contacto, $email, $telefono, $direccion);
    }
    $stmt->execute();
    $id = (int)$conn->insert_id;

  } else {
    // UPDATE parcial
    $sets = []; $types = ''; $vals = [];
    if ($nombre !== null)    { $sets[]='nombre=?';    $types.='s'; $vals[]=$nombre; }
    if ($contacto !== null)  { $sets[]='contacto=?';  $types.='s'; $vals[]=$contacto; }
    if ($email !== null)     { $sets[]='email=?';     $types.='s'; $vals[]=$email; }
    if ($telefono !== null)  { $sets[]='telefono=?';  $types.='s'; $vals[]=$telefono; }
    if ($direccion !== null) { $sets[]='direccion=?'; $types.='s'; $vals[]=$direccion; }
    if ($estado !== null)    { $sets[]='estado=?';    $types.='i'; $vals[]=$estado; }

    if ($sets) {
      $types .= 'i'; $vals[] = $id;
      $sql = "UPDATE proveedores SET ".implode(',', $sets)." WHERE id=?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param($types, ...$vals);
      $stmt->execute();
    }
  }

  // membresía (plan) opcional
  if (array_key_exists('plan_id', $in)) {
    if ($plan_id === null) {
      $stmt = $conn->prepare("DELETE FROM vendor_membresias WHERE proveedor_id=?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
    } else {
      $stmt = $conn->prepare("
        INSERT INTO vendor_membresias (proveedor_id, plan_id, fecha_inicio, estado)
        VALUES (?, ?, CURDATE(), 'pendiente')
        ON DUPLICATE KEY UPDATE plan_id=VALUES(plan_id)
      ");
      $stmt->bind_param('ii', $id, $plan_id);
      $stmt->execute();
    }
  }

  mysqli_commit($conn);
  echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  mysqli_rollback($conn);
  error_log('guardar_proveedor: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al guardar','error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}

<?php
// /console/gestion/planes/ajax/comision_guardar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED']); exit;
}

/* CSRF */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$sessionCsrf = $_SESSION['csrf'] ?? '';
$hdrCsrf = $_SERVER['HTTP_X_CSRF'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_CSRF_TOKEN'] ?? ''));
if (!$sessionCsrf || !$hdrCsrf || !hash_equals($sessionCsrf, $hdrCsrf)) {
  http_response_code(419);
  echo json_encode(['ok'=>false,'error'=>'CSRF_INVALID']); exit;
}
@session_write_close();

/* Body */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in) || !$in) { $in = $_POST; }
if (!is_array($in)) { $in = []; }

/* Normalizadores */
$norm_pct = function($v): float {
  $s = trim((string)($v ?? ''));
  if ($s === '') return NAN;
  if (preg_match('/^\d{1,3}([.,]\d{3})+([.,]\d+)?$/', $s)) {
    $s = preg_replace('/[.,](?=\d{3}\b)/', '', $s);
  }
  $s = str_replace(',', '.', $s);
  return (float)$s;
};
$norm_date = function($v): ?string {
  $s = trim((string)($v ?? ''));
  if ($s === '') return null;
  // acepta yyyy-mm-dd o dd/mm/yyyy
  if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s)) {
    $d = DateTime::createFromFormat('Y-m-d', $s);
  } elseif (preg_match('~^\d{2}/\d{2}/\d{4}$~', $s)) {
    $d = DateTime::createFromFormat('d/m/Y', $s);
  } else {
    return null;
  }
  return $d && $d->format('Y-m-d') ? $d->format('Y-m-d') : null;
};

/* Campos */
$id          = isset($in['id']) ? (int)$in['id'] : 0;
$rule_type   = ($in['rule_type'] ?? $in['scope'] ?? 'proveedor') === 'valla' ? 'valla' : 'proveedor';
$proveedor_id= (int)($in['proveedor_id'] ?? 0);
$valla_id_in = $rule_type === 'valla' ? (int)($in['valla_id'] ?? 0) : 0;
$comision_pct= $norm_pct($in['comision_pct'] ?? null);
$desde       = $norm_date($in['desde'] ?? null);
$hasta       = $norm_date($in['hasta'] ?? null);

/* Validaciones */
$errors = [];
if ($proveedor_id <= 0) { $errors['proveedor_id'] = 'Requerido'; }
if ($rule_type === 'valla' && $valla_id_in <= 0) { $errors['valla_id'] = 'Requerido'; }
if (!is_finite($comision_pct) || $comision_pct < 0 || $comision_pct > 100) { $errors['comision_pct'] = '0..100'; }
if (!$desde) { $errors['desde'] = 'Requerido'; }
if ($hasta && $desde && $hasta < $desde) { $errors['hasta'] = 'Rango inválido'; }

if ($errors) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>$errors]); exit;
}

/* Reglas de integridad opcionales: existencia y pertenencia */
$okProv = false;
if ($rs = mysqli_prepare($conn, "SELECT 1 FROM proveedores WHERE id=? LIMIT 1")) {
  mysqli_stmt_bind_param($rs, 'i', $proveedor_id);
  mysqli_stmt_execute($rs); mysqli_stmt_store_result($rs);
  $okProv = mysqli_stmt_num_rows($rs) === 1;
  mysqli_stmt_close($rs);
}
if (!$okProv) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>['proveedor_id'=>'No existe']]); exit; }

if ($rule_type === 'valla') {
  $okValla = false;
  if ($rs = mysqli_prepare($conn, "SELECT 1 FROM vallas WHERE id=? AND proveedor_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($rs, 'ii', $valla_id_in, $proveedor_id);
    mysqli_stmt_execute($rs); mysqli_stmt_store_result($rs);
    $okValla = mysqli_stmt_num_rows($rs) === 1;
    mysqli_stmt_close($rs);
  }
  if (!$okValla) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>['valla_id'=>'No existe o no pertenece al proveedor']]); exit; }
}

/* Persistencia */
mysqli_begin_transaction($conn);
try {
  $valla_id = ($rule_type === 'valla') ? $valla_id_in : null; // usar NULL real
  $vigente_desde = $desde;
  $vigente_hasta = $hasta; // puede ser null
  $pct = $comision_pct;    // variable separada para bind por referencia

  if ($id > 0) {
    $sql = "UPDATE vendor_commissions
               SET proveedor_id=?, valla_id=?, comision_pct=?, vigente_desde=?, vigente_hasta=?
             WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    // tipos: i i d s s i
    mysqli_stmt_bind_param($stmt, 'iidssi',
      $proveedor_id,
      $valla_id,        // puede ser NULL -> OK
      $pct,
      $vigente_desde,
      $vigente_hasta,   // puede ser NULL -> OK
      $id
    );
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('UPDATE_COMMISSION_FAILED'); }
    mysqli_stmt_close($stmt);
    $cid = $id;
  } else {
    $sql = "INSERT INTO vendor_commissions
              (proveedor_id, valla_id, comision_pct, vigente_desde, vigente_hasta)
            VALUES (?,?,?,?,?)";
    $stmt = mysqli_prepare($conn, $sql);
    // tipos: i i d s s
    mysqli_stmt_bind_param($stmt, 'iidss',
      $proveedor_id,
      $valla_id,       // NULL si aplica
      $pct,
      $vigente_desde,
      $vigente_hasta   // NULL si aplica
    );
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('INSERT_COMMISSION_FAILED'); }
    $cid = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
  }

  mysqli_commit($conn);
  echo json_encode(['ok'=>true,'id'=>$cid,'msg'=>'COMISION_GUARDADA']); exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

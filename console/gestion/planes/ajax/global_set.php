<?php
// /console/gestion/planes/ajax/global_set.php
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

/* Body JSON */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in) || !$in) { $in = $_POST; }
if (!is_array($in)) { $in = []; }

/* Normaliza número: soporta "10,5" y "10.5" */
$val = (string)($in['comision_pct'] ?? '');
$val = trim(str_replace(' ', '', $val));
if (preg_match('/^\d{1,3}([.,]\d{3})+([.,]\d+)?$/', $val)) { $val = preg_replace('/[.,](?=\d{3}\b)/', '', $val); }
$val = str_replace(',', '.', $val);
$pct = (float)$val;

/* Validación */
if (!is_numeric($val) || $pct < 0 || $pct > 100) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>['comision_pct'=>'0..100']]); exit;
}

/* Upsert en config_global.vendor_comision_pct */
mysqli_begin_transaction($conn);
try {
  // verifica si hay fila
  $id = null;
  $q = mysqli_query($conn, "SELECT id FROM config_global ORDER BY id ASC LIMIT 1");
  if ($q && mysqli_num_rows($q) === 1) {
    $row = mysqli_fetch_assoc($q);
    $id = (int)$row['id'];
  }
  if ($id) {
    // intenta también actualizar updated_at si existe
    $hasUpdatedAt = false;
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM config_global LIKE 'updated_at'");
    if ($chk && mysqli_num_rows($chk) === 1) { $hasUpdatedAt = true; }
    if ($hasUpdatedAt) {
      $stmt = mysqli_prepare($conn, "UPDATE config_global SET vendor_comision_pct=?, updated_at=NOW() WHERE id=?");
    } else {
      $stmt = mysqli_prepare($conn, "UPDATE config_global SET vendor_comision_pct=? WHERE id=?");
    }
    mysqli_stmt_bind_param($stmt, 'di', $pct, $id);
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('UPDATE_GLOBAL_FAILED'); }
    mysqli_stmt_close($stmt);
  } else {
    // inserta con timestamps si existen
    $hasCreatedAt = false; $hasUpdatedAt = false;
    $chk1 = mysqli_query($conn, "SHOW COLUMNS FROM config_global LIKE 'created_at'");
    if ($chk1 && mysqli_num_rows($chk1) === 1) { $hasCreatedAt = true; }
    $chk2 = mysqli_query($conn, "SHOW COLUMNS FROM config_global LIKE 'updated_at'");
    if ($chk2 && mysqli_num_rows($chk2) === 1) { $hasUpdatedAt = true; }

    if ($hasCreatedAt && $hasUpdatedAt) {
      $stmt = mysqli_prepare($conn, "INSERT INTO config_global (vendor_comision_pct, created_at, updated_at) VALUES (?, NOW(), NOW())");
      mysqli_stmt_bind_param($stmt, 'd', $pct);
    } else {
      $stmt = mysqli_prepare($conn, "INSERT INTO config_global (vendor_comision_pct) VALUES (?)");
      mysqli_stmt_bind_param($stmt, 'd', $pct);
    }
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('INSERT_GLOBAL_FAILED'); }
    mysqli_stmt_close($stmt);
  }

  mysqli_commit($conn);
  echo json_encode(['ok'=>true,'msg'=>'GLOBAL_UPDATED','data'=>['vendor_comision_pct'=>round($pct,2)]]); exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

<?php
// /console/gestion/planes/ajax/plan_guardar.php
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

/* Input */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in) || !$in) { $in = $_POST; }
if (!is_array($in)) { $in = []; }

/* Soporta payload plano o features{} */
$Fx = (isset($in['features']) && is_array($in['features'])) ? $in['features'] : $in;

/* Normalización */
$id            = isset($in['id']) ? (int)$in['id'] : 0;
$nombre        = trim((string)($in['nombre'] ?? ''));
$tipo          = strtolower(trim((string)($in['tipo'] ?? '')));
$precio        = (float)($in['precio'] ?? 0);
$limite        = (int)($in['limite_vallas'] ?? 0);
$prueba_dias   = (int)($in['prueba_dias'] ?? ($in['dias_prueba'] ?? 0)); // nombre real en DB
$descripcion   = trim((string)($in['descripcion'] ?? ''));
$activo        = !empty($in['activo']) ? 1 : 0;

/* Features (claves reales en DB) */
$f_access_crm    = !empty($Fx['access_crm']) ? 1 : 0;
$f_access_fact   = !empty($Fx['access_facturacion']) ? 1 : 0;
$f_access_mapa   = !empty($Fx['access_mapa']) ? 1 : 0;
$f_access_export = !empty($Fx['access_export'] ?? $Fx['exportar_datos'] ?? 0) ? 1 : 0;
$f_soporte_ncf   = !empty($Fx['soporte_ncf']) ? 1 : 0;
$f_factura_auto  = !empty($Fx['factura_auto']) ? 1 : 0;

$com_model = in_array(($Fx['comision_model'] ?? 'none'), ['none','pct','flat'], true) ? $Fx['comision_model'] : 'none';
$com_pct   = (float)($Fx['comision_pct'] ?? 0);
$com_flat  = (float)($Fx['comision_flat'] ?? 0);

/* Validaciones */
$err = [];
if ($nombre === '') { $err['nombre'] = 'Requerido'; }
if (!in_array($tipo, ['gratis','mensual','trimestral','anual','comision'], true)) { $err['tipo'] = 'Tipo inválido'; }
if (in_array($tipo, ['mensual','trimestral','anual'], true) && $precio <= 0) { $err['precio'] = 'Precio > 0 requerido'; }
if ($precio < 0) { $err['precio'] = 'No negativo'; }
if ($limite < 0) { $err['limite_vallas'] = 'No negativo'; }
if ($prueba_dias < 0) { $err['prueba_dias'] = 'No negativo'; }

if ($com_model === 'pct') {
  if ($com_pct < 0 || $com_pct > 100) { $err['comision_pct'] = '0..100'; }
  $com_flat = 0.0;
} elseif ($com_model === 'flat') {
  if ($com_flat < 0) { $err['comision_flat'] = '>=0'; }
  $com_pct = 0.0;
} else { $com_pct = 0.0; $com_flat = 0.0; }

if ($err) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'VALIDATION','fields'=>$err]); exit; }

/*
vendor_planes: id, nombre, tipo_facturacion, precio, limite_vallas, prueba_dias, descripcion, estado
vendor_plan_features: plan_id, access_crm, access_facturacion, access_mapa, access_export, soporte_ncf,
                      comision_model, comision_pct, comision_flat, factura_auto
*/

mysqli_begin_transaction($conn);
try {
  /* upsert vendor_planes */
  if ($id > 0) {
    $sql = "UPDATE vendor_planes
              SET nombre=?, tipo_facturacion=?, precio=?, limite_vallas=?, prueba_dias=?, descripcion=?, estado=?
            WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssdiisii',
      $nombre, $tipo, $precio, $limite, $prueba_dias, $descripcion, $activo, $id
    );
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 0) {
      throw new Exception('UPDATE_PLAN_FAILED');
    }
    mysqli_stmt_close($stmt);
    $planId = $id;
  } else {
    $sql = "INSERT INTO vendor_planes
              (nombre, tipo_facturacion, precio, limite_vallas, prueba_dias, descripcion, estado)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssdiisi',
      $nombre, $tipo, $precio, $limite, $prueba_dias, $descripcion, $activo
    );
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('INSERT_PLAN_FAILED'); }
    $planId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
  }

  /* upsert vendor_plan_features: delete + insert */
  $del = mysqli_prepare($conn, "DELETE FROM vendor_plan_features WHERE plan_id=?");
  mysqli_stmt_bind_param($del, 'i', $planId);
  if (!mysqli_stmt_execute($del)) { throw new Exception('DEL_FEATURES_FAILED'); }
  mysqli_stmt_close($del);

  $ins = mysqli_prepare($conn,
    "INSERT INTO vendor_plan_features
     (plan_id, access_crm, access_facturacion, access_mapa, access_export, soporte_ncf,
      comision_model, comision_pct, comision_flat, factura_auto)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
  );
  // tipos correctos: i i i i i i s d d i  => 'iiiiiisddi'
  mysqli_stmt_bind_param(
    $ins, 'iiiiiisddi',
    $planId,
    $f_access_crm,
    $f_access_fact,
    $f_access_mapa,
    $f_access_export,
    $f_soporte_ncf,
    $com_model,
    $com_pct,
    $com_flat,
    $f_factura_auto
  );
  if (!mysqli_stmt_execute($ins)) { throw new Exception('INS_FEATURES_FAILED'); }
  mysqli_stmt_close($ins);

  mysqli_commit($conn);
  echo json_encode(['ok'=>true,'id'=>$planId,'msg'=>'PLAN_GUARDADO']); exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
}

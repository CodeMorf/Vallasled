<?php
// /console/facturacion/clientes/ajax/importar.php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

start_session_safe();
$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$hdr)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'CSRF'], JSON_UNESCAPED_UNICODE); exit;
}

$raw = file_get_contents('php://input') ?: '{}';
$body = json_decode($raw, true);
if (!is_array($body)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_JSON']); exit; }

$proveedor_id = isset($body['proveedor_id']) && $body['proveedor_id'] !== '' ? (int)$body['proveedor_id'] : null;
$mode = in_array(($body['mode'] ?? 'append'), ['append','ignore-duplicates','upsert-email'], true) ? $body['mode'] : 'append';
$rows = is_array($body['rows'] ?? null) ? $body['rows'] : [];

if (count($rows) === 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'NO_ROWS']); exit; }
if (count($rows) > 2000) { http_response_code(413); echo json_encode(['ok'=>false,'error'=>'TOO_MANY']); exit; }

$EMAIL_RX = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/i';

$ins = $conn->prepare("INSERT INTO crm_clientes (proveedor_id, nombre, email, telefono, empresa) VALUES (?,?,?,?,?)");
$upd = $conn->prepare("UPDATE crm_clientes SET telefono=COALESCE(?,telefono), empresa=COALESCE(?,empresa) WHERE email=? AND proveedor_id " . ($proveedor_id === null ? "IS NULL" : "= ?"));
$sel = $conn->prepare("SELECT id FROM crm_clientes WHERE email=? AND proveedor_id " . ($proveedor_id === null ? "IS NULL" : "= ?") . " LIMIT 1");

$inserted=0; $updated=0; $skipped=0; $errors=[]; $line=0;

$conn->begin_transaction();
try{
  foreach ($rows as $r) {
    $line++;
    $nombre = trim((string)($r['nombre'] ?? ''));
    $email  = trim((string)($r['email'] ?? ''));
    $tel    = trim((string)($r['telefono'] ?? ''));
    $emp    = trim((string)($r['empresa'] ?? ''));

    if ($nombre === '') { $errors[]=['line'=>$line,'error'=>'nombre requerido']; continue; }
    if ($email !== '' && !preg_match($EMAIL_RX, $email)) { $errors[]=['line'=>$line,'error'=>'email inválido']; continue; }

    if ($mode !== 'append' && $email !== '') {
      if ($proveedor_id === null) { $sel->bind_param('s', $email); }
      else { $sel->bind_param('si', $email, $proveedor_id); }
      $sel->execute(); $sr = $sel->get_result(); $exists = (bool)$sr->fetch_row(); $sr->free();

      if ($exists) {
        if ($mode === 'ignore-duplicates') { $skipped++; continue; }
        // upsert-email
        if ($proveedor_id === null) { $upd->bind_param('sss', $tel ?: null, $emp ?: null, $email); }
        else { $upd->bind_param('sssi', $tel ?: null, $emp ?: null, $email, $proveedor_id); }
        $upd->execute(); $updated++; continue;
      }
    }

    // insert
    $pid = $proveedor_id;
    $eml = ($email !== '') ? $email : null;
    $tel = ($tel   !== '') ? $tel   : null;
    $emp = ($emp   !== '') ? $emp   : null;

    if ($pid === null) $ins->bind_param('issss', $pid, $nombre, $eml, $tel, $emp); // null ok por mysqli si va como int? sí, usa null
    else               $ins->bind_param('issss', $pid, $nombre, $eml, $tel, $emp);

    $ins->execute(); $inserted++;
  }

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER_ERROR']); exit;
}

http_response_code(207);
echo json_encode([
  'ok'=>true,
  'inserted'=>$inserted,
  'updated'=>$updated,
  'skipped'=>$skipped,
  'errors'=>$errors
], JSON_UNESCAPED_UNICODE);

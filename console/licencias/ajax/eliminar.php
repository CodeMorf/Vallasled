<?php
// /console/licencias/ajax/eliminar.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

/* CSRF */
$tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? ($_GET['csrf'] ?? '')));
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$tok)) {
  json_exit(['ok'=>false,'error'=>'CSRF_INVALID'], 403);
}

/* Helpers */
function as_int($v): int { return (isset($v) && $v!=='' && is_numeric($v)) ? max(0,(int)$v) : 0; }
function table_exists(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  $rs=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t' LIMIT 1");
  return (bool)($rs && $rs->fetch_row());
}

if (!table_exists($conn,'crm_licencias')) json_exit(['ok'=>false,'error'=>'TABLE_MISSING','table'=>'crm_licencias'],500);

$id = as_int($_POST['id'] ?? $_GET['id'] ?? null);
if ($id < 1) json_exit(['ok'=>false,'error'=>'BAD_ID'],422);

/* Existe */
$st = $conn->prepare("SELECT id FROM crm_licencias WHERE id=? LIMIT 1");
$st->bind_param('i',$id); $st->execute();
if (!$st->get_result()->fetch_row()) json_exit(['ok'=>false,'error'=>'NOT_FOUND'],404);
$st->close();

/* Delete duro. Archivos se borran por FK CASCADE si existe crm_licencias_files */
$del = $conn->prepare("DELETE FROM crm_licencias WHERE id=? LIMIT 1");
$del->bind_param('i',$id); $del->execute();

json_exit(['ok'=>true,'id'=>$id,'mode'=>'hard']);

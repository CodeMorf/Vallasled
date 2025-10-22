<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/plan_eliminar.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

function out($ok,$msg=''){ echo json_encode(['ok'=>$ok,'msg'=>$msg]); exit; }

if (function_exists('csrf_verify_header')) { csrf_verify_header(); }
else {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $h = $_SERVER['HTTP_X_CSRF'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $h)) { http_response_code(419); out(false,'CSRF'); }
}

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (int)$payload['id'] : 0;
if ($id<=0) { http_response_code(400); out(false,'ID inválido'); }

$isPdo = $conn instanceof PDO;

try {
  if ($isPdo) { $conn->beginTransaction(); } else { $conn->begin_transaction(); }

  if ($isPdo) {
    $conn->prepare("DELETE FROM vendor_plan_features WHERE plan_id=?")->execute([$id]);
    $conn->prepare("DELETE FROM vendor_planes WHERE id=?")->execute([$id]);
  } else {
    $st=$conn->prepare("DELETE FROM vendor_plan_features WHERE plan_id=?"); $st->bind_param('i',$id); $st->execute(); $st->close();
    $st=$conn->prepare("DELETE FROM vendor_planes WHERE id=?"); $st->bind_param('i',$id); $st->execute(); $st->close();
  }

  if ($isPdo) { $conn->commit(); } else { $conn->commit(); }
  out(true,'Eliminado');
} catch (Exception $e){
  if ($isPdo) { $conn->rollBack(); } else { $conn->rollback(); }
  http_response_code(500); out(false,'Error eliminando');
}

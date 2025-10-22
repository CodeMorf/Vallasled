<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/comision_eliminar.php
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

$in = json_decode(file_get_contents('php://input'), true);
$id = isset($in['id']) ? (int)$in['id'] : 0;
if ($id<=0){ http_response_code(400); out(false,'ID inválido'); }

try {
  if ($conn instanceof PDO) {
    $conn->prepare("DELETE FROM vendor_commissions WHERE id=?")->execute([$id]);
  } else {
    $st=$conn->prepare("DELETE FROM vendor_commissions WHERE id=?"); $st->bind_param('i',$id); $st->execute(); $st->close();
  }
  out(true,'Eliminado');
} catch(Exception $e){ http_response_code(500); out(false,'Error eliminando'); }

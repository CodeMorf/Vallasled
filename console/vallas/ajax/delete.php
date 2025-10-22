<?php
// /console/vallas/ajax/delete.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
// if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'id']); exit; }

try {
  // soft delete si existe 'activo'
  $rc=$conn->query("SHOW COLUMNS FROM vallas LIKE 'activo'");
  if ($rc && $rc->num_rows>0) {
    $stmt=$conn->prepare("UPDATE vallas SET activo=0 WHERE id=?");
    $stmt->bind_param('i',$id); $stmt->execute();
    echo json_encode(['ok'=>true]); exit;
  }
  // hard delete
  $stmt=$conn->prepare("DELETE FROM vallas WHERE id=?");
  $stmt->bind_param('i',$id); $stmt->execute();
  echo json_encode(['ok'=>true]);
} catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>'db']);
}

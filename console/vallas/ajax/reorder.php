<?php
// /console/vallas/ajax/reorder.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Bad']); exit; }
// if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }

$order = trim((string)($_POST['order'] ?? ''));
if ($order==='') { echo json_encode(['ok'=>true]); exit; }

try {
  // Verifica columna orden
  $has = false; $rc=$conn->query("SHOW COLUMNS FROM vallas LIKE 'orden'");
  if ($rc && $rc->num_rows>0) $has=true;

  if (!$has) { echo json_encode(['ok'=>true,'msg'=>'sin-columna-orden']); exit; }

  $ids = array_values(array_filter(array_map('intval', explode(',', $order))));
  if (!$ids) { echo json_encode(['ok'=>true]); exit; }

  $sql = "UPDATE vallas SET orden = CASE id ";
  $i=1; foreach ($ids as $id) { $sql .= "WHEN $id THEN $i "; $i++; }
  $sql .= "END WHERE id IN (".implode(',', $ids).")";
  $conn->query($sql);
  echo json_encode(['ok'=>true]);
} catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>'no-orden']);
}

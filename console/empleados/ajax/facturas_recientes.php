<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || !in_array($_SESSION['tipo'] ?? '', ['staff','admin'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

$items=[];
$sql = "SELECT id, cliente_nombre, total, estado, creado
        FROM facturas
        ORDER BY id DESC
        LIMIT 10";
try {
  $q = $conn->query($sql);
  while ($row=$q->fetch_assoc()) $items[]=$row;
} catch(Throwable $e) {}

echo json_encode(['ok'=>true,'items'=>$items]);

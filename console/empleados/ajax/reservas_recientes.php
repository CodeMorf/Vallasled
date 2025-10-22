<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || !in_array($_SESSION['tipo'] ?? '', ['staff','admin'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

$items=[];
$sql = "SELECT r.id, r.valla_id, r.nombre_cliente, r.fecha_inicio, r.fecha_fin, r.estado,
               v.nombre AS valla_nombre
        FROM reservas r
        LEFT JOIN vallas v ON v.id=r.valla_id
        ORDER BY r.id DESC
        LIMIT 10";
try {
  $q = $conn->query($sql);
  while ($row=$q->fetch_assoc()) $items[]=$row;
} catch(Throwable $e) {}

echo json_encode(['ok'=>true,'items'=>$items]);

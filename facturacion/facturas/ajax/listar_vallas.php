<?php
// /console/facturacion/facturas/ajax/listar_vallas.php
declare(strict_types=1);
require_once __DIR__.'/../../../../config/db.php';
start_session_safe(); require_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$proveedorId = (int)($_SESSION['proveedor_id'] ?? 0);

$sql = "SELECT id, nombre, precio, proveedor_id FROM vallas WHERE 1";
$params = [];
$types = '';

if ($q !== '') { $sql .= " AND (nombre LIKE CONCAT('%',?,'%'))"; $params[]=$q; $types.='s'; }
if ($proveedorId > 0) { $sql .= " AND proveedor_id=?"; $params[]=$proveedorId; $types.='i'; }
$sql .= " ORDER BY id DESC LIMIT ?"; $params[]=$limit; $types.='i';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$items=[];
while($row=$res->fetch_assoc()){
  $items[]=[
    'id'=>(int)$row['id'],
    'nombre'=>$row['nombre'],
    'precio'=>(float)($row['precio'] ?? 0),
    'proveedor_id'=>(int)($row['proveedor_id'] ?? 0),
  ];
}
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);

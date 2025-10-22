<?php
// /console/facturacion/facturas/ajax/buscar_clientes_base.php
declare(strict_types=1);
require_once __DIR__.'/../../../../config/db.php';
start_session_safe(); require_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

$sql = "SELECT id, nombre, correo FROM clientes WHERE 1";
$params=[]; $types='';
if ($q !== '') { $sql.=" AND (nombre LIKE CONCAT('%',?,'%') OR correo LIKE CONCAT('%',?,'%'))"; $params[]=$q; $params[]=$q; $types.='ss'; }
$sql.=" ORDER BY id DESC LIMIT ?"; $params[]=$limit; $types.='i';

$stmt=$conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$r=$stmt->get_result();

$items=[];
while($row=$r->fetch_assoc()){
  $items[]=[
    'id'=>(int)$row['id'],
    'nombre'=>$row['nombre'],
    'email'=>$row['correo'] ?? null,
    'rnc'=>null
  ];
}
echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);

<?php
// /console/facturacion/crm/ajax/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'forbidden']); exit;
}

$q = trim($_GET['q'] ?? '');

$sql = "SELECT c.id, c.proveedor_id, c.nombre, c.email, c.telefono, c.empresa,
               COALESCE(p.nombre,'') proveedor
        FROM crm_clientes c
        LEFT JOIN proveedores p ON p.id=c.proveedor_id";
$w = [];
if ($q!=='') {
  $qEsc = '%' . $conn->real_escape_string($q) . '%';
  $w[] = "(c.nombre LIKE '$qEsc' OR c.email LIKE '$qEsc' OR c.empresa LIKE '$qEsc' OR c.telefono LIKE '$qEsc')";
}
if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
$sql .= " ORDER BY c.nombre LIMIT 1000";

$res = $conn->query($sql);
$out = ['rows'=>[]];
if ($res) {
  while($r=$res->fetch_assoc()){
    $out['rows'][] = [
      'id'=>(int)$r['id'],
      'proveedor_id'=> isset($r['proveedor_id'])?(int)$r['proveedor_id']:null,
      'nombre'=>$r['nombre']??'',
      'email'=>$r['email']??'',
      'telefono'=>$r['telefono']??'',
      'empresa'=>$r['empresa']??'',
      'proveedor'=>$r['proveedor']??'',
    ];
  }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);

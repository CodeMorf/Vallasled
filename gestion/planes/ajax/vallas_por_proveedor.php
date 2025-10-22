<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/vallas_por_proveedor.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

$pid = (int)($_GET['proveedor_id'] ?? 0);
if ($pid<=0){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'proveedor_id requerido']); exit; }

try {
  $data=[];
  if ($conn instanceof PDO) {
    $st=$conn->prepare("SELECT id,nombre FROM vallas WHERE proveedor_id=? AND estado=1 ORDER BY nombre ASC");
    $st->execute([$pid]);
    while($r=$st->fetch(PDO::FETCH_ASSOC)){ $data[]=['id'=>(int)$r['id'],'nombre'=>$r['nombre']]; }
  } else {
    $st=$conn->prepare("SELECT id,nombre FROM vallas WHERE proveedor_id=? AND estado=1 ORDER BY nombre ASC");
    $st->bind_param('i',$pid); $st->execute(); $res=$st->get_result();
    while($row=$res->fetch_assoc()){ $data[]=['id'=>(int)$row['id'],'nombre'=>$row['nombre']]; }
    $st->close();
  }
  echo json_encode(['ok'=>true,'data'=>$data]);
} catch(Exception $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error cargando vallas']);
}

<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/proveedores.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

try {
  $data=[];
  if ($conn instanceof PDO) {
    $st=$conn->query("SELECT id,nombre FROM proveedores WHERE estado=1 ORDER BY nombre ASC");
    while($r=$st->fetch(PDO::FETCH_ASSOC)){ $data[]=['id'=>(int)$r['id'],'nombre'=>$r['nombre']]; }
  } else {
    $r=$conn->query("SELECT id,nombre FROM proveedores WHERE estado=1 ORDER BY nombre ASC");
    while($row=$r->fetch_assoc()){ $data[]=['id'=>(int)$row['id'],'nombre'=>$row['nombre']]; }
  }
  echo json_encode(['ok'=>true,'data'=>$data]);
} catch(Exception $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error cargando proveedores']);
}

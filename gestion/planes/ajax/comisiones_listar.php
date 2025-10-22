<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/comisiones_listar.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

function http500($m){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$m]); exit; }

$page = max(1,(int)($_GET['page'] ?? 1));
$limit= min(100,max(1,(int)($_GET['limit'] ?? 20)));
$offset = ($page-1)*$limit;
$isPdo = $conn instanceof PDO;

try {
  // total
  $sqlC="SELECT COUNT(*) c FROM vendor_commissions";
  if ($isPdo) { $total=(int)$conn->query($sqlC)->fetch(PDO::FETCH_ASSOC)['c']; }
  else { $r=$conn->query($sqlC); $total=(int)$r->fetch_assoc()['c']; }

  $sql="SELECT vc.id, vc.proveedor_id, vc.valla_id, vc.comision_pct,
               vc.vigente_desde, vc.vigente_hasta,
               COALESCE(v.nombre, pr.nombre) AS nombre
        FROM vendor_commissions vc
        LEFT JOIN vallas v ON v.id=vc.valla_id
        LEFT JOIN proveedores pr ON pr.id=vc.proveedor_id
        ORDER BY vc.vigente_desde DESC, vc.id DESC
        LIMIT $limit OFFSET $offset";
  $data=[];
  if ($isPdo) {
    $st=$conn->query($sql);
    while($row=$st->fetch(PDO::FETCH_ASSOC)){
      $data[]=[
        'id'=>(int)$row['id'],
        'proveedor_id'=>(int)$row['proveedor_id'],
        'valla_id'=>$row['valla_id']!==null? (int)$row['valla_id'] : null,
        'comision_pct'=>(float)$row['comision_pct'],
        'desde'=>$row['vigente_desde'],
        'hasta'=>$row['vigente_hasta'],
        'nombre'=>$row['nombre'],
      ];
    }
  } else {
    $r=$conn->query($sql);
    while($row=$r->fetch_assoc()){
      $data[]=[
        'id'=>(int)$row['id'],
        'proveedor_id'=>(int)$row['proveedor_id'],
        'valla_id'=>$row['valla_id']!==null? (int)$row['valla_id'] : null,
        'comision_pct'=>(float)$row['comision_pct'],
        'desde'=>$row['vigente_desde'],
        'hasta'=>$row['vigente_hasta'],
        'nombre'=>$row['nombre'],
      ];
    }
  }
  echo json_encode(['ok'=>true,'data'=>$data,'total'=>$total,'page'=>$page,'limit'=>$limit]);
} catch(Exception $e){ http500('Error listando'); }

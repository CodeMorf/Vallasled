<?php
// /console/facturacion/facturas/ajax/preview_comision.php
declare(strict_types=1);
require_once __DIR__.'/../../../../config/db.php';
start_session_safe(); require_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$vallaId = (int)($_GET['valla_id'] ?? 0);
$pct = 0.10;

if ($vallaId>0){
  // intenta leer % de comisión del proveedor
  if ($st=$conn->prepare("SELECT v.proveedor_id, vf.comision_model, vf.comision_pct FROM vallas v LEFT JOIN vw_vendor_features vf ON vf.proveedor_id=v.proveedor_id WHERE v.id=? LIMIT 1")){
    $st->bind_param('i',$vallaId); $st->execute(); $st->bind_result($pid,$model,$cpct);
    if($st->fetch() && $model==='pct' && $cpct!==null){ $pct = max(0, min(1, ((float)$cpct)/100.0)); }
    $st->close();
  }
}
echo json_encode(['ok'=>true,'pct'=>$pct], JSON_UNESCAPED_UNICODE);

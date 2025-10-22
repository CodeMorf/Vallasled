<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../../config/db.php';
start_session_safe();
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

$months = max(3,min(12,(int)($_GET['months']??6)));
$start = new DateTime('first day of this month'); $start->modify('-'.($months-1).' months');
$from = $start->format('Y-m-d');

$sql="SELECT DATE_FORMAT(COALESCE(fecha_pago,fecha_generacion),'%Y-%m') ym,
             COALESCE(SUM(COALESCE(precio_personalizado,monto)-COALESCE(descuento,0)),0) total
        FROM facturas
       WHERE estado='pagado' AND COALESCE(fecha_pago,fecha_generacion)>=?
    GROUP BY ym";
$st=$conn->prepare($sql); $st->bind_param('s',$from); $st->execute(); $res=$st->get_result();
$map=[]; while($row=$res->fetch_assoc()) $map[$row['ym']]=(float)$row['total'];

$mes=['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$labels=[];$series=[];$c=clone $start;
for($i=0;$i<$months;$i++){ $ym=$c->format('Y-m'); $labels[]=$mes[(int)$c->format('n')-1]; $series[]=$map[$ym]??0.0; $c->modify('+1 month'); }
echo json_encode(['ok'=>true,'data'=>['labels'=>$labels,'series'=>$series]]);

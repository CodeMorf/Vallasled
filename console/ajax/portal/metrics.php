<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

function one(mysqli $c,string $sql){ $r=$c->query($sql); if(!$r) return null; $row=$r->fetch_row(); return $row?$row[0]:null; }
function hasCol(mysqli $c,string $t,string $col):bool{
  $t=preg_replace('/[^a-zA-Z0-9_]/','',$t); $col=preg_replace('/[^a-zA-Z0-9_]/','',$col);
  $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '$col'"); return $r && $r->num_rows>0;
}
function i0($v){ return $v===null?0:(int)$v; }
function f0($v){ return $v===null?0.0:(float)$v; }

try{
  $mon = one($conn,"SELECT valor FROM config_global WHERE clave='stripe_currency' AND activo=1 ORDER BY id DESC LIMIT 1");
  $mon = strtoupper($mon ?: 'USD');

  $ing = f0(one($conn,"SELECT COALESCE(SUM(COALESCE(precio_personalizado,monto)-COALESCE(descuento,0)),0)
                       FROM facturas
                      WHERE estado='pagado'
                        AND DATE_FORMAT(COALESCE(fecha_pago,fecha_generacion),'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')"));

  $v_pub = i0(one($conn,"SELECT COUNT(*) FROM vallas WHERE COALESCE(visible_publico,1)=1 AND COALESCE(estado_valla,'activa')='activa'"));
  $res   = i0(one($conn,"SELECT COUNT(*) FROM reservas WHERE CURDATE() BETWEEN COALESCE(fecha_inicio,CURDATE()) AND COALESCE(fecha_fin,CURDATE()) AND estado IN ('activa','confirmada')"));

  $lic = 0;
  if ($conn->query("SHOW TABLES LIKE 'vallas_licencias'")->num_rows) {
    $lic = i0(one($conn,"SELECT COUNT(*) FROM vallas_licencias WHERE estado='activo' AND fecha_vencimiento IS NOT NULL AND DATE(fecha_vencimiento) BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)"));
  }

  $tot = i0(one($conn,"SELECT COUNT(*) FROM vallas"));

  $tot_dest = 0;
  $conds=[]; if(hasCol($conn,'vallas','destacado'))$conds[]="destacado=1";
  if(hasCol($conn,'vallas','es_destacado'))$conds[]="es_destacado=1";
  if(hasCol($conn,'vallas','featured'))$conds[]="featured=1";
  if(hasCol($conn,'vallas','destacado_orden'))$conds[]="COALESCE(destacado_orden,0)>0";
  if($conds) $tot_dest = i0(one($conn,"SELECT COUNT(*) FROM vallas WHERE ".implode(' OR ',$conds)));

  $tot_led=$tot_imp=0;
  if (hasCol($conn,'vallas','tipo')) {
    $tot_led = i0(one($conn,"SELECT COUNT(*) FROM vallas WHERE LOWER(tipo) IN ('led','digital','lcd','pantalla')"));
    $tot_imp = i0(one($conn,"SELECT COUNT(*) FROM vallas WHERE LOWER(tipo) IN ('imprenta','impresa','estatica','estática','vinil','vinilo','lona')"));
  }

  echo json_encode(['ok'=>true,'data'=>[
    'moneda'=>$mon,
    'ingresos_mes'=>$ing,
    'vallas_publicadas'=>$v_pub,
    'reservas_activas'=>$res,
    'licencias_por_vencer'=>$lic,
    'total_vallas'=>$tot,
    'total_destacadas'=>$tot_dest,
    'total_led'=>$tot_led,
    'total_imprenta'=>$tot_imp,
  ]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  echo json_encode(['ok'=>true,'data'=>[
    'moneda'=>'USD','ingresos_mes'=>0,'vallas_publicadas'=>0,'reservas_activas'=>0,'licencias_por_vencer'=>0,
    'total_vallas'=>0,'total_destacadas'=>0,'total_led'=>0,'total_imprenta'=>0
  ]]);
}

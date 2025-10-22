<?php
require __DIR__.'/_bootstrap.php';

$sumPagado    = 0.0;
$sumPendiente = 0.0;
$vencidas     = 0;
$sum30d       = 0.0;

$q = $conn->query("SELECT 
  SUM(CASE WHEN estado='pagado' THEN total ELSE 0 END) AS pagado,
  SUM(CASE WHEN estado='pendiente' THEN total ELSE 0 END) AS pendiente,
  SUM(CASE WHEN estado='pendiente' AND DATE(fecha_generacion)<CURDATE() THEN 1 ELSE 0 END) AS vencidas,
  SUM(CASE WHEN DATE(fecha_generacion)>=DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total ELSE 0 END) AS last30
  FROM facturas");
if($q){ $row=$q->fetch_assoc(); $sumPagado=(float)$row['pagado']; $sumPendiente=(float)$row['pendiente']; $vencidas=(int)$row['vencidas']; $sum30d=(float)$row['last30']; }

ok(['total_pagado'=>$sumPagado,'total_pendiente'=>$sumPendiente,'vencidas'=>$vencidas,'facturado_30d'=>$sum30d]);

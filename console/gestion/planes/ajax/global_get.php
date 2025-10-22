<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/global_get.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

try {
  $pct = null;
  if ($conn instanceof PDO) {
    $st=$conn->query("SELECT CAST(valor AS DECIMAL(5,2)) AS v FROM config_global WHERE clave='vendor_comision_pct' AND activo=1 ORDER BY id DESC LIMIT 1");
    $row = $st->fetch(PDO::FETCH_ASSOC); if ($row) $pct = (float)$row['v'];
  } else {
    $r=$conn->query("SELECT CAST(valor AS DECIMAL(5,2)) AS v FROM config_global WHERE clave='vendor_comision_pct' AND activo=1 ORDER BY id DESC LIMIT 1");
    $row = $r->fetch_assoc(); if ($row) $pct = (float)$row['v'];
  }
  if ($pct===null) $pct=10.00; // fallback como en función SQL
  echo json_encode(['ok'=>true,'data'=>['vendor_comision_pct'=>$pct]]);
} catch(Exception $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Error leyendo configuración']);
}

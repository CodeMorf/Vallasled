<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$tab = ($_GET['tab'] ?? 'emitidos')==='secuencias'?'secuencias':'emitidos';
$limit = max(1,min(100,(int)($_GET['per_page']??20)));
$offset = max(0,(int)($_GET['offset']??0));

if ($tab==='secuencias'){
  $conn->query("CREATE TABLE IF NOT EXISTS ncf_secuencias (
    id INT PRIMARY KEY AUTO_INCREMENT,tipo VARCHAR(5),serie CHAR(1) DEFAULT 'B',
    desde INT,hasta INT,vence DATE,activo TINYINT(1) DEFAULT 1,creado TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $q=$conn->query("SELECT SQL_CALC_FOUND_ROWS id,tipo,serie,desde,hasta,vence,activo
                   FROM ncf_secuencias ORDER BY tipo,vence DESC LIMIT $limit OFFSET $offset");
  $data=[]; while($r=$q->fetch_assoc()) $data[]=$r;
  $tot=$conn->query("SELECT FOUND_ROWS() x")->fetch_assoc()['x']??0;
  echo json_encode(['ok'=>true,'data'=>$data,'total'=>(int)$tot]); exit;
}

/* Emitidos: trabaja con `comprobantes` si existe, si no intenta `comprobantes_fiscales` */
$have = function(string $t) use ($conn){ $r=$conn->query("SHOW TABLES LIKE '$t'"); return $r && $r->num_rows>0; };
$tbl = $have('comprobantes')?'comprobantes':($have('comprobantes_fiscales')?'comprobantes_fiscales':null);
if(!$tbl){ echo json_encode(['ok'=>true,'data'=>[],'total'=>0]); exit; }

$q = $conn->query("SELECT SQL_CALC_FOUND_ROWS ncf, factura_id AS factura, cliente_nombre AS cliente, rnc_cliente AS rnc,
                          monto, DATE(fecha_emision) fecha, estado
                   FROM $tbl ORDER BY fecha_emision DESC LIMIT $limit OFFSET $offset");
$data=[]; while($r=$q->fetch_assoc()) $data[]=$r;
$tot=$conn->query("SELECT FOUND_ROWS() x")->fetch_assoc()['x']??0;
echo json_encode(['ok'=>true,'data'=>$data,'total'=>(int)$tot]);

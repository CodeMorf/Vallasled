<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

function jerr($c,$m='',$h=400){ http_response_code($h); echo json_encode(['ok'=>false,'code'=>$c,'msg'=>$m]); exit; }
function jout($p){ echo json_encode($p); exit; }
function body(){ $j=json_decode(file_get_contents('php://input'),true); return is_array($j)?$j:[]; }

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrf)) jerr('CSRF');

$itbisRate = 0.18; // RD

// Helpers
$have = function(string $t) use ($conn){ $r=$conn->query("SHOW TABLES LIKE '$t'"); return $r && $r->num_rows>0; };
$tbl = $have('comprobantes')?'comprobantes':($have('comprobantes_fiscales')?'comprobantes_fiscales':null);
if(!$tbl){
  // crea mínima si no existe nada
  $conn->query("CREATE TABLE comprobantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ncf VARCHAR(16) UNIQUE, tipo_ncf VARCHAR(5), cliente_id INT NULL,
    cliente_nombre VARCHAR(128) NULL, rnc_cliente VARCHAR(32) NULL,
    monto DECIMAL(12,2) NOT NULL, aplica_itbis TINYINT(1) DEFAULT 0,
    factura_id INT NULL, estado ENUM('generado','anulado') DEFAULT 'generado',
    fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $tbl='comprobantes';
}

// aseguro secuencias
$conn->query("CREATE TABLE IF NOT EXISTS ncf_secuencias (
  id INT PRIMARY KEY AUTO_INCREMENT,tipo VARCHAR(5),serie CHAR(1) DEFAULT 'B',
  desde INT,hasta INT,vence DATE,activo TINYINT(1) DEFAULT 1,creado TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$in = body();
$act = $in['action'] ?? '';

if ($act==='emitir') {
  $tipo = strtoupper(trim((string)($in['tipo_ncf'] ?? 'B01')));
  if(!preg_match('/^B0[12]$/',$tipo)) jerr('VALIDACION','tipo_ncf');
  $monto = (float)($in['monto'] ?? 0);
  if($monto<=0) jerr('VALIDACION','monto');
  $aplica = (int)!!($in['aplica_itbis'] ?? 0);
  $monto_total = $aplica ? round($monto*(1+$itbisRate),2) : round($monto,2);

  // buscar secuencia válida
  $rs=$conn->query("SELECT id,desde,hasta,serie FROM ncf_secuencias
                    WHERE tipo='{$conn->real_escape_string($tipo)}'
                      AND activo=1 AND vence>=CURDATE()
                    ORDER BY vence ASC, id ASC LIMIT 1");
  if(!$rs || !$rs->num_rows) jerr('NCF_AGOTADO','No hay secuencia activa');

  $seq=$rs->fetch_assoc();
  $pref=$tipo; // formato B01########
  $maxQ = $conn->query("SELECT MAX(CAST(SUBSTRING(ncf,4) AS UNSIGNED)) m FROM $tbl WHERE ncf LIKE '{$conn->real_escape_string($pref)}%'");
  $max = (int)($maxQ->fetch_assoc()['m'] ?? 0);
  $next = max($seq['desde'], $max+1);
  if ($next > (int)$seq['hasta']) jerr('NCF_AGOTADO','Rango agotado');

  $ncf = $pref . str_pad((string)$next, 8, '0', STR_PAD_LEFT);

  // insert
  $st=$conn->prepare("INSERT INTO $tbl (ncf,tipo_ncf,cliente_id,cliente_nombre,rnc_cliente,monto,aplica_itbis,factura_id,estado)
                      VALUES (?,?,?,?,?,?,?, ?, 'generado')");
  $cid = $in['cliente_id']??null; $cname = $in['cliente_nombre']??null; $rnc=$in['rnc']??null; $fid=$in['factura_id']??null;
  $st->bind_param('ssissdii',$ncf,$tipo,$cid,$cname,$rnc,$monto_total,$aplica,$fid);
  if(!$st->execute()) jerr('DB_ERROR',$st->error,500);

  jout(['ok'=>true,'ncf'=>$ncf,'id'=>$conn->insert_id,'monto_total'=>$monto_total]);
}

if ($act==='anular') {
  $ncf = trim((string)($in['ncf'] ?? '')); if($ncf==='') jerr('VALIDACION','ncf');
  $st=$conn->prepare("UPDATE $tbl SET estado='anulado' WHERE ncf=?");
  $st->bind_param('s',$ncf);
  if(!$st->execute()) jerr('DB_ERROR',$st->error,500);
  jout(['ok'=>true]);
}

jerr('ACTION');

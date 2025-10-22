<?php
// CRUD Secuencias NCF
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

function jerr(string $code, string $msg='', int $http=400){ http_response_code($http); echo json_encode(['ok'=>false,'code'=>$code,'msg'=>$msg]); exit; }
function jout(array $p){ echo json_encode($p); exit; }
function body(){ $j=json_decode(file_get_contents('php://input'),true); return is_array($j)?$j:[]; }

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrf)) jerr('CSRF');

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = $conn; // de config/db.php

// autocreate table if missing
$mysqli->query("
CREATE TABLE IF NOT EXISTS ncf_secuencias (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tipo VARCHAR(5) NOT NULL,      -- B01, B02
  serie CHAR(1) NOT NULL DEFAULT 'B',
  desde INT NOT NULL,
  hasta INT NOT NULL,
  vence DATE NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$method = $_SERVER['REQUEST_METHOD'];

if ($method==='GET') {
  $q = $mysqli->query("SELECT id,tipo,serie,desde,hasta,vence,activo FROM ncf_secuencias ORDER BY tipo,vence DESC, id DESC");
  $rows=[]; while($r=$q->fetch_assoc()) $rows[]=$r;
  jout(['ok'=>true,'data'=>$rows]);
}

if ($method!=='POST') jerr('METHOD');

$in = body();
$act = $in['action'] ?? '';

if ($act==='create') {
  foreach (['tipo','serie','desde','hasta','vence'] as $k) if (!isset($in[$k])) jerr('VALIDACION',"Falta $k");
  $tipo  = trim($in['tipo']);
  $serie = substr(trim($in['serie']?:'B'),0,1);
  $desde = max(1,(int)$in['desde']);
  $hasta = max($desde,(int)$in['hasta']);
  $vence = preg_replace('/[^0-9\-]/','', (string)$in['vence']);
  $activo= isset($in['activo'])?(int)!!$in['activo']:1;

  $st=$mysqli->prepare("INSERT INTO ncf_secuencias(tipo,serie,desde,hasta,vence,activo) VALUES (?,?,?,?,?,?)");
  if(!$st) jerr('DB_ERROR',$mysqli->error,500);
  $st->bind_param('ssii si', $tipo,$serie,$desde,$hasta,$vence,$activo); // espacio evita parse issues
  $ok=$st->execute();
  if(!$ok) jerr('DB_ERROR',$st->error,500);
  jout(['ok'=>true,'id'=>$mysqli->insert_id]);
}

if ($act==='update') {
  foreach (['id','tipo','serie','desde','hasta','vence','activo'] as $k) if (!isset($in[$k])) jerr('VALIDACION',"Falta $k");
  $id=(int)$in['id']; if($id<1) jerr('VALIDACION','id');
  $tipo=trim($in['tipo']); $serie=substr(trim($in['serie']?:'B'),0,1);
  $desde=max(1,(int)$in['desde']); $hasta=max($desde,(int)$in['hasta']);
  $vence=preg_replace('/[^0-9\-]/','',(string)$in['vence']); $activo=(int)!!$in['activo'];

  $st=$mysqli->prepare("UPDATE ncf_secuencias SET tipo=?,serie=?,desde=?,hasta=?,vence=?,activo=? WHERE id=?");
  if(!$st) jerr('DB_ERROR',$mysqli->error,500);
  $st->bind_param('ssii sii',$tipo,$serie,$desde,$hasta,$vence,$activo,$id);
  if(!$st->execute()) jerr('DB_ERROR',$st->error,500);
  jout(['ok'=>true]);
}

if ($act==='delete') {
  $id=(int)($in['id']??0); if($id<1) jerr('VALIDACION','id');
  $st=$mysqli->prepare("DELETE FROM ncf_secuencias WHERE id=?");
  if(!$st) jerr('DB_ERROR',$mysqli->error,500);
  $st->bind_param('i',$id);
  if(!$st->execute()) jerr('DB_ERROR',$st->error,500);
  jout(['ok'=>true]);
}

jerr('ACTION');

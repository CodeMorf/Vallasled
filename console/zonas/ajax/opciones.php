<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ob_start();
set_error_handler(function($no,$str,$file,$line){ throw new ErrorException($str, 0, $no, $file, $line); });
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();

function out(int $code, array $p){ http_response_code($code); while(ob_get_level()) ob_end_clean(); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function ok(array $p=[]){ out(200, ['ok'=>true]+$p); }
function err(int $c,string $m,$e=null){ $p=['ok'=>false,'msg'=>$m]; if($e!==null)$p['err']=$e; out($c,$p); }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) err(401,'Auth requerida');
if (!isset($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) err(403,'CSRF inválido');
if (!isset($conn) || !($conn instanceof mysqli)) err(500,'DB no disponible');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$op = (string)($body['op'] ?? '');

if ($op === 'crear') {
  $nombre = trim((string)($body['nombre'] ?? ''));
  if ($nombre === '') err(422,'Nombre requerido');
  try {
    $st=$conn->prepare("INSERT INTO zonas (nombre, activo) VALUES (?, 1)");
    $st->bind_param('s',$nombre); $st->execute(); $id=$conn->insert_id; $st->close();
    ok(['id'=>$id]);
  } catch (Throwable $e) {
    err(409,'Ya existe o inválida');
  }
}

if ($op === 'zonas') {
  $res = $conn->query("SELECT id, nombre FROM zonas WHERE activo=1 ORDER BY nombre");
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  ok(['zonas'=>$out]);
}

$ns="TRIM(REGEXP_REPLACE(REGEXP_REPLACE(LOWER(nombre), '^[^[:alpha:]]+', ''), '[[:space:]]+', ' '))";
$dups=[]; $res=$conn->query("SELECT $ns AS nn, COUNT(*) c FROM zonas GROUP BY nn HAVING c>1 ORDER BY c DESC");
while($r=$res->fetch_assoc()) $dups[]=$r;
ok(['dups'=>$dups]);

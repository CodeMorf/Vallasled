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
$valla_id = (int)($body['valla_id'] ?? 0);
$zona_id  = (int)($body['zona_id'] ?? 0);
if ($valla_id<=0 || $zona_id<=0) err(422,'Parámetros inválidos');

$st=$conn->prepare("SELECT 1 FROM vallas WHERE id=?"); $st->bind_param('i',$valla_id); $st->execute();
if(!$st->get_result()->fetch_row()){ $st->close(); err(404,'Valla no existe'); } $st->close();

$st=$conn->prepare("SELECT 1 FROM zonas WHERE id=?"); $st->bind_param('i',$zona_id); $st->execute();
if(!$st->get_result()->fetch_row()){ $st->close(); err(404,'Zona no existe'); } $st->close();

$st=$conn->prepare("REPLACE INTO vallas_zonas (valla_id, zona_id) VALUES (?, ?)");
$st->bind_param('ii', $valla_id, $zona_id); $st->execute(); $st->close();

ok(['msg'=>'OK']);

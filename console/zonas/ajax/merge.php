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
$keep_id = (int)($body['keep_id'] ?? 0);
$dup_ids = array_values(array_filter(array_map('intval', (array)($body['dup_ids'] ?? [])), fn($v)=>$v>0));
if ($keep_id<=0 || !$dup_ids || in_array($keep_id,$dup_ids,true)) err(422,'Parámetros inválidos');

$st=$conn->prepare("SELECT 1 FROM zonas WHERE id=?"); $st->bind_param('i',$keep_id); $st->execute();
if(!$st->get_result()->fetch_row()){ $st->close(); err(404,'Zona canónica no existe'); } $st->close();

$conn->begin_transaction();
try {
  // Silencia triggers que respeten el flag
  $conn->query("SET @skip_vz_trg := 1");

  // Reasigna pivote
  $in = implode(',', array_fill(0, count($dup_ids), '?'));
  $stmt = $conn->prepare("UPDATE vallas_zonas SET zona_id=? WHERE zona_id IN ($in)");
  $types = 'i'.str_repeat('i', count($dup_ids));
  $stmt->bind_param($types, $keep_id, ...$dup_ids);
  $stmt->execute(); $stmt->close();

  // Borra duplicadas
  $stmt = $conn->prepare("DELETE FROM zonas WHERE id IN ($in)");
  $stmt->bind_param(str_repeat('i', count($dup_ids)), ...$dup_ids);
  $stmt->execute(); $stmt->close();

  // Refleja texto en vallas sin depender de triggers
  $conn->query("UPDATE vallas v
                JOIN vallas_zonas vz ON vz.valla_id=v.id
                JOIN zonas z ON z.id=vz.zona_id
                SET v.zona = z.nombre");

  $conn->commit();
  $conn->query("SET @skip_vz_trg := 0");
  ok(['moved'=>count($dup_ids)]);
} catch (Throwable $e) {
  $conn->rollback();
  $conn->query("SET @skip_vz_trg := 0");
  err(500,'No se pudo unificar',$e->getMessage());
}

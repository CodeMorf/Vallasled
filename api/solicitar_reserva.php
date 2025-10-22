<?php declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__.'/../config/db.php';
function i($v,$d=0){ $n=filter_var($v,FILTER_VALIDATE_INT); return $n===false?$d:$n; }
function s($v){ return trim((string)$v); }

$id    = i($_POST['id'] ?? 0);
$desde = s($_POST['desde'] ?? '');
$hasta = s($_POST['hasta'] ?? '');

if ($id<=0 || !$desde || !$hasta) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }

try{
  $pdo = db();
  // Inserta en tabla solicitudes_reserva (ajusta a tu esquema)
  $st = $pdo->prepare("INSERT INTO solicitudes_reserva(valla_id, fecha_inicio, fecha_fin, estado, creado_en)
                       VALUES(:id, :d, :h, 'pendiente', NOW())");
  $st->execute([':id'=>$id, ':d'=>$desde, ':h'=>$hasta]);
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false]);
}

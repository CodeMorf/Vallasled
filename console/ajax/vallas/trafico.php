<?php
// /console/ajax/vallas/trafico.php
declare(strict_types=1);

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Guard */
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

/* Input */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

/* Helpers */
function tableExists(mysqli $conn, string $table): bool {
  $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
  $st  = $conn->prepare($sql); $st->bind_param('s', $table); $st->execute();
  return (bool)$st->get_result()->fetch_row();
}
function oneVal(mysqli $conn, string $sql, string $types = '', array $params = []) {
  $st = $conn->prepare($sql);
  if ($types!==''){ $refs=[]; $refs[]=&$types; foreach($params as &$v) $refs[]=&$v; call_user_func_array([$st,'bind_param'],$refs); }
  $st->execute(); $res=$st->get_result(); $row=$res->fetch_row(); return $row? $row[0] : null;
}

try{
  $labels = [];
  for($h=0;$h<24;$h++){ $labels[] = sprintf('%02d:00', $h); }

  $fuente = 'estimado';
  $data   = array_fill(0,24,0);

  if (tableExists($conn,'vallas_trafico_horas')){
    $st = $conn->prepare("SELECT hora, valor FROM vallas_trafico_horas WHERE valla_id=? ORDER BY hora");
    $st->bind_param('i',$id); $st->execute(); $res=$st->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    if ($rows){
      foreach($rows as $r){ $h = max(0,min(23,(int)$r['hora'])); $data[$h] = (int)$r['valor']; }
      $fuente = 'mediciones';
    }
  }

  if ($fuente==='estimado'){
    $prom = oneVal($conn,"SELECT trafico_promedio FROM vallas WHERE id=?",'i',[$id]);
    $prom = $prom!==null ? (int)$prom : 800;
    for($h=0;$h<24;$h++){
      $factor = 0.3;
      if ($h>=6 && $h<=9)  $factor = 1.0;
      if ($h>=10 && $h<=15) $factor = 0.6;
      if ($h>=16 && $h<=19) $factor = 1.1;
      if ($h>=20 && $h<=22) $factor = 0.5;
      $data[$h] = (int)round($prom * $factor);
    }
  }

  echo json_encode(['ok'=>true,'labels'=>$labels,'data'=>$data,'fuente'=>$fuente], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

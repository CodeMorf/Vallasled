<?php
// /console/ajax/vallas/disponibilidad.php
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
$valla_id = filter_input(INPUT_GET, 'valla_id', FILTER_VALIDATE_INT);
if (!$valla_id || $valla_id < 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

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
  $bloques = [];

  // Reservas activas
  if (tableExists($conn,'reservas')){
    $st = $conn->prepare("
      SELECT DATE(COALESCE(fecha_inicio, CURDATE())) AS desde,
             DATE(COALESCE(fecha_fin, CURDATE()))     AS hasta
        FROM reservas
       WHERE valla_id=?
         AND estado IN ('activa','confirmada')
         AND COALESCE(fecha_fin, fecha_inicio, CURDATE()) >= CURDATE()
       ORDER BY 1
    ");
    $st->bind_param('i',$valla_id); $st->execute(); $res=$st->get_result();
    while($r=$res->fetch_assoc()){
      $bloques[] = ['desde'=>$r['desde'], 'hasta'=>$r['hasta'], 'motivo'=>'reserva'];
    }
  }

  // Periodos no disponibles
  if (tableExists($conn,'periodos_no_disponibles')){
    $st = $conn->prepare("
      SELECT DATE(desde) AS desde, DATE(hasta) AS hasta, COALESCE(motivo,'mantenimiento') AS motivo
        FROM periodos_no_disponibles
       WHERE valla_id=?
         AND COALESCE(hasta, desde) >= CURDATE()
       ORDER BY 1
    ");
    $st->bind_param('i',$valla_id); $st->execute(); $res=$st->get_result();
    while($r=$res->fetch_assoc()){
      $bloques[] = ['desde'=>$r['desde'], 'hasta'=>$r['hasta'] ?: $r['desde'], 'motivo'=>$r['motivo']];
    }
  }

  // Hoy disponible?
  $hoy = oneVal($conn,"
    SELECT CASE
      WHEN EXISTS(
        SELECT 1 FROM reservas r
         WHERE r.valla_id=? AND r.estado IN ('activa','confirmada')
           AND CURDATE() BETWEEN DATE(COALESCE(r.fecha_inicio, CURDATE())) AND DATE(COALESCE(r.fecha_fin, CURDATE()))
      ) THEN 0
      WHEN EXISTS(
        SELECT 1 FROM periodos_no_disponibles p
         WHERE p.valla_id=?
           AND CURDATE() BETWEEN DATE(p.desde) AND DATE(COALESCE(p.hasta, p.desde))
      ) THEN 0
      ELSE 1
    END
  ",'ii',[$valla_id,$valla_id]);
  $dispHoy = is_null($hoy) ? 1 : (int)$hoy;

  echo json_encode(['ok'=>true,'bloques'=>$bloques,'disponible_hoy'=>$dispHoy], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

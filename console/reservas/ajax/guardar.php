<?php
// /console/reservas/ajax/guardar.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit;
}

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

/* -------- utilidades -------- */
function respond(array $payload, int $code=200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function hheaders(): array {
  if (function_exists('getallheaders')) return getallheaders();
  $out=[]; foreach ($_SERVER as $k=>$v) {
    if (substr($k,0,5)==='HTTP_') {
      $name=str_replace(' ','-', ucwords(strtolower(str_replace('_',' ',substr($k,5)))));
      $out[$name]=$v;
    }
  }
  return $out;
}
function stmt_first(mysqli $c, string $sql, string $types='', array $args=[]): ?array {
  if ($types) {
    $st=$c->prepare($sql); if(!$st) return null;
    $st->bind_param($types, ...$args);
    if(!$st->execute()){ $st->close(); return null; }
    $rs=$st->get_result(); $row=$rs? $rs->fetch_assoc():null; $st->close(); return $row?:null;
  } else {
    $rs=$c->query($sql); return $rs? $rs->fetch_assoc():null;
  }
}
function stmt_all(mysqli $c, string $sql, string $types='', array $args=[]): array {
  $out=[]; if ($types) { $st=$c->prepare($sql); if(!$st) return $out;
    $st->bind_param($types, ...$args); if(!$st->execute()){ $st->close(); return $out; }
    $rs=$st->get_result(); while($rs && ($r=$rs->fetch_assoc())) $out[]=$r; $st->close();
  } else { $rs=$c->query($sql); while($rs && ($r=$rs->fetch_assoc())) $out[]=$r; }
  return $out;
}

/* -------- entrada -------- */
$headers = hheaders();
$csrf_hdr = $headers['X-Csrf-Token'] ?? $headers['X-CSRF-Token'] ?? '';
$raw = file_get_contents('php://input') ?: '';
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST; // fallback

$csrf = trim((string)($in['csrf'] ?? ''));
if (!$csrf && $csrf_hdr) $csrf = trim((string)$csrf_hdr);

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  http_response_code(419);
  respond(['ok'=>false,'msg'=>'CSRF inválido o ausente']);
}

$id            = isset($in['id']) ? (int)$in['id'] : 0;
$valla_id      = isset($in['valla_id']) ? (int)$in['valla_id'] : 0;
$nombre_cliente= isset($in['nombre_cliente']) ? trim((string)$in['nombre_cliente']) : '';
$fecha_inicio  = isset($in['fecha_inicio']) ? trim((string)$in['fecha_inicio']) : '';
$fecha_fin     = isset($in['fecha_fin']) ? trim((string)$in['fecha_fin']) : '';
$estado        = isset($in['estado']) ? trim((string)$in['estado']) : '';
$motivo        = isset($in['motivo']) ? trim((string)$in['motivo']) : '';

$ESTADOS_RESERVA = ['pendiente','confirmada','activa','cancelada','finalizada'];
$ES_BLOQUEO = ($estado === 'bloqueo');

/* -------- validaciones -------- */
$errs=[];

if ($valla_id <= 0) $errs['valla_id']='valla_id requerido';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) $errs['fecha_inicio']='formato YYYY-MM-DD';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) $errs['fecha_fin']='formato YYYY-MM-DD';

if (!$ES_BLOQUEO) {
  if ($nombre_cliente === '') $errs['nombre_cliente']='requerido';
  if (!in_array($estado, $ESTADOS_RESERVA, true)) $errs['estado']='inválido';
} else {
  if ($motivo === '') $motivo = 'Bloqueo';
}

if (!$errs) {
  if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
    $errs['rango']='fecha_fin debe ser ≥ fecha_inicio';
  }
}

if ($errs) respond(['ok'=>false,'msg'=>'Errores de validación','errors'=>$errs], 422);

/* valla existe */
$exV = stmt_first($conn, "SELECT id FROM vallas WHERE id=?", 'i', [$valla_id]);
if (!$exV) respond(['ok'=>false,'msg'=>'Valla no existe'], 422);

/* -------- reglas de solape: opción A estricta --------
   No se permiten solapes con:
   - reservas en estado pendiente|confirmada|activa
   - cualquier periodo_no_disponible
*/
$exclude_res_id = $ES_BLOQUEO ? 0 : $id;
$exclude_blk_id = $ES_BLOQUEO ? $id : 0;

$conf_res = stmt_all($conn,
  "SELECT id, fecha_inicio, fecha_fin, estado
     FROM reservas
    WHERE valla_id = ?
      AND (? = 0 OR id <> ?)
      AND estado IN ('pendiente','confirmada','activa')
      AND NOT (fecha_fin < ? OR fecha_inicio > ?)",
  'iiiss',
  [$valla_id, $exclude_res_id, $exclude_res_id, $fecha_inicio, $fecha_fin]
);

$conf_blk = stmt_all($conn,
  "SELECT id, fecha_inicio, fecha_fin
     FROM periodos_no_disponibles
    WHERE valla_id = ?
      AND (? = 0 OR id <> ?)
      AND NOT (fecha_fin < ? OR fecha_inicio > ?)",
  'iiiss',
  [$valla_id, $exclude_blk_id, $exclude_blk_id, $fecha_inicio, $fecha_fin]
);

if ($conf_res || $conf_blk) {
  respond([
    'ok'=>false,
    'msg'=>'Conflicto de fechas',
    'conflictos'=>[
      'reservas'=>$conf_res,
      'bloqueos'=>$conf_blk
    ]
  ], 409);
}

/* -------- escribir -------- */
$conn->begin_transaction();
try {
  if ($ES_BLOQUEO) {
    if ($id > 0) {
      /* actualizar si existe, si no insertar */
      $existsBlk = stmt_first($conn, "SELECT id FROM periodos_no_disponibles WHERE id=? AND valla_id=?", 'ii', [$id,$valla_id]);
      if ($existsBlk) {
        $st=$conn->prepare("UPDATE periodos_no_disponibles SET motivo=?, fecha_inicio=?, fecha_fin=? WHERE id=? AND valla_id=?");
        $st->bind_param('sssii', $motivo, $fecha_inicio, $fecha_fin, $id, $valla_id);
        if (!$st->execute()) throw new Exception('No se pudo actualizar bloqueo');
        $st->close();
        $saved_id = $id;
      } else {
        $st=$conn->prepare("INSERT INTO periodos_no_disponibles (valla_id, motivo, fecha_inicio, fecha_fin) VALUES (?,?,?,?)");
        $st->bind_param('isss', $valla_id, $motivo, $fecha_inicio, $fecha_fin);
        if (!$st->execute()) throw new Exception('No se pudo crear bloqueo');
        $saved_id = $st->insert_id; $st->close();
      }
      $tipo='bloqueo';
    } else {
      $st=$conn->prepare("INSERT INTO periodos_no_disponibles (valla_id, motivo, fecha_inicio, fecha_fin) VALUES (?,?,?,?)");
      $st->bind_param('isss', $valla_id, $motivo, $fecha_inicio, $fecha_fin);
      if (!$st->execute()) throw new Exception('No se pudo crear bloqueo');
      $saved_id = $st->insert_id; $st->close();
      $tipo='bloqueo';
    }
  } else {
    if ($id > 0) {
      $existsRes = stmt_first($conn, "SELECT id FROM reservas WHERE id=? AND valla_id=?", 'ii', [$id,$valla_id]);
      if ($existsRes) {
        $st=$conn->prepare("UPDATE reservas SET nombre_cliente=?, fecha_inicio=?, fecha_fin=?, estado=? WHERE id=? AND valla_id=?");
        $st->bind_param('ssssii', $nombre_cliente, $fecha_inicio, $fecha_fin, $estado, $id, $valla_id);
        if (!$st->execute()) throw new Exception('No se pudo actualizar reserva');
        $st->close();
        $saved_id = $id;
      } else {
        $st=$conn->prepare("INSERT INTO reservas (valla_id, nombre_cliente, fecha_inicio, fecha_fin, estado) VALUES (?,?,?,?,?)");
        $st->bind_param('issss', $valla_id, $nombre_cliente, $fecha_inicio, $fecha_fin, $estado);
        if (!$st->execute()) throw new Exception('No se pudo crear reserva');
        $saved_id = $st->insert_id; $st->close();
      }
      $tipo='reserva';
    } else {
      $st=$conn->prepare("INSERT INTO reservas (valla_id, nombre_cliente, fecha_inicio, fecha_fin, estado) VALUES (?,?,?,?,?)");
      $st->bind_param('issss', $valla_id, $nombre_cliente, $fecha_inicio, $fecha_fin, $estado);
      if (!$st->execute()) throw new Exception('No se pudo crear reserva');
      $saved_id = $st->insert_id; $st->close();
      $tipo='reserva';
    }
  }

  $conn->commit();
  respond([
    'ok'=>true,
    'msg'=>'Guardado',
    'data'=>[
      'id'=>(int)$saved_id,
      'tipo'=>$tipo,
      'valla_id'=>$valla_id,
      'title'=>$ES_BLOQUEO ? $motivo : $nombre_cliente,
      'start'=>$fecha_inicio,
      'end'=>$fecha_fin,
      'estado'=>$ES_BLOQUEO ? 'bloqueo' : $estado
    ]
  ]);
} catch (\Throwable $e) {
  $conn->rollback();
  respond(['ok'=>false,'msg'=>'Error de servidor','error'=>$e->getMessage()], 500);
}

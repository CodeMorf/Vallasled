<?php
// /console/reservas/ajax/listar.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit;
}

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

$ESTADOS_RESERVA = ['pendiente','confirmada','activa','cancelada','finalizada'];
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
$valla_id = isset($_GET['valla_id']) ? (int)$_GET['valla_id'] : 0;

function respond(array $payload, int $code=200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

/** Util: ejecutar consulta preparada opcionalmente */
function stmt_all(mysqli $conn, string $sql, string $types='', array $args=[]): array {
  $out=[];
  if ($types) {
    $st = $conn->prepare($sql);
    if (!$st) return $out;
    $st->bind_param($types, ...$args);
    if (!$st->execute()) { $st->close(); return $out; }
    $rs = $st->get_result();
    while ($rs && ($r=$rs->fetch_assoc())) $out[]=$r;
    $st->close();
  } else {
    $rs = $conn->query($sql);
    if (!$rs) return $out;
    while ($r=$rs->fetch_assoc()) $out[]=$r;
  }
  return $out;
}

/** 1) Vallas para filtros */
$vallas = stmt_all($conn, "SELECT id, nombre FROM vallas ORDER BY nombre ASC");

/** 2) Markers: usar vista si existe, si no fallback a tabla */
$markers = [];
try {
  $hasView = false;
  $chk = $conn->query("SHOW FULL TABLES WHERE Table_type='VIEW' AND Tables_in_{$conn->real_escape_string($conn->query('SELECT DATABASE()')->fetch_row()[0])}='vw_vallas_geo'");
  if ($chk && $chk->num_rows) $hasView = true;

  if ($hasView) {
    $markers = stmt_all($conn, "SELECT id, nombre, lat, lng, estado_valla, disponible FROM vw_vallas_geo");
  } else {
    $markers = stmt_all($conn, "SELECT id, nombre, lat, lng, estado_valla, disponible FROM vallas");
  }
} catch (\Throwable $e) {
  // Fallback duro
  $markers = stmt_all($conn, "SELECT id, nombre, lat, lng, estado_valla, disponible FROM vallas");
}

/** 3) Eventos calendario */
$eventos = [];

/* 3a) Bloqueos (periodos_no_disponibles) */
$bloqueo_sql = "SELECT id, valla_id, motivo, fecha_inicio, fecha_fin FROM periodos_no_disponibles";
$bloqueo_types = '';
$bloqueo_args = [];
$bloqueo_where = [];

if ($valla_id > 0) { $bloqueo_where[] = "valla_id = ?"; $bloqueo_types .= 'i'; $bloqueo_args[] = $valla_id; }

if ($bloqueo_where) $bloqueo_sql .= " WHERE ".implode(' AND ', $bloqueo_where);
$bloqueos = stmt_all($conn, $bloqueo_sql, $bloqueo_types, $bloqueo_args);
foreach ($bloqueos as $b) {
  $eventos[] = [
    'id'       => (int)$b['id'],
    'tipo'     => 'bloqueo',
    'valla_id' => (int)$b['valla_id'],
    'title'    => $b['motivo'] !== null && $b['motivo'] !== '' ? $b['motivo'] : 'Bloqueo',
    'start'    => $b['fecha_inicio'],
    'end'      => $b['fecha_fin'],
    'estado'   => 'bloqueo'
  ];
}

/* 3b) Reservas normales */
$res_sql = "SELECT id, valla_id, nombre_cliente, fecha_inicio, fecha_fin, estado FROM reservas";
$res_types = '';
$res_args = [];
$res_where = [];

if ($valla_id > 0) { $res_where[] = "valla_id = ?"; $res_types .= 'i'; $res_args[] = $valla_id; }

if ($estado !== '') {
  if ($estado === 'bloqueo') {
    // ya incluidos en $bloqueos; no añadir reservas
    $res_where[] = "1=0";
  } elseif (in_array($estado, $ESTADOS_RESERVA, true)) {
    $res_where[] = "estado = ?";
    $res_types .= 's'; $res_args[] = $estado;
  }
}
// Opcional: excluir canceladas/finalizadas por defecto en vista general
// if ($estado==='') { $res_where[] = "estado NOT IN ('cancelada','finalizada')"; }

if ($res_where) $res_sql .= " WHERE ".implode(' AND ', $res_where);

$reservas = stmt_all($conn, $res_sql, $res_types, $res_args);
foreach ($reservas as $r) {
  $eventos[] = [
    'id'       => (int)$r['id'],
    'tipo'     => 'reserva',
    'valla_id' => (int)$r['valla_id'],
    'title'    => (string)$r['nombre_cliente'],
    'start'    => $r['fecha_inicio'],
    'end'      => $r['fecha_fin'],
    'estado'   => $r['estado']
  ];
}

respond([
  'ok' => true,
  'vallas' => array_map(fn($v)=>['id'=>(int)$v['id'],'nombre'=>$v['nombre']], $vallas),
  'markers' => array_map(function($m){
    return [
      'id' => (int)$m['id'],
      'nombre' => $m['nombre'],
      'lat' => isset($m['lat']) ? (float)$m['lat'] : null,
      'lng' => isset($m['lng']) ? (float)$m['lng'] : null,
      'estado_valla' => $m['estado_valla'] ?? null,
      'disponible' => isset($m['disponible']) ? (int)$m['disponible'] : null
    ];
  }, $markers),
  'eventos' => $eventos,
  'estados' => array_merge($ESTADOS_RESERVA, ['bloqueo'])
]);

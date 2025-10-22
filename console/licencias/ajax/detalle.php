<?php
// /console/licencias/ajax/detalle.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

/* CSRF */
$tok = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? ($_GET['csrf'] ?? ''));
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$tok)) {
  json_exit(['ok'=>false,'error'=>'CSRF_INVALID'], 403);
}

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $sql = "SELECT 1 FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name='$t' LIMIT 1";
  $rs  = $db->query($sql);
  return (bool)($rs && $rs->fetch_row());
}
function as_int($v): int { return (isset($v) && is_numeric($v)) ? max(0,(int)$v) : 0; }

/* Input */
$id = as_int($_GET['id'] ?? $_POST['id'] ?? null);
if ($id < 1) json_exit(['ok'=>false,'error'=>'ID_INVALID'], 422);

/* Ramas por esquema */
$HAS_CRM = table_exists($conn, 'crm_licencias');
$HAS_LIC = table_exists($conn, 'licencias'); // por compat
$HAS_VAL = table_exists($conn, 'vallas');

if ($HAS_CRM) {
  $sql = "
    SELECT
      l.id, l.titulo, l.proveedor_id, l.valla_id, l.cliente_id,
      l.ciudad, l.entidad, l.tipo_licencia, l.direccion, l.reminder_days,
      l.fecha_emision, l.fecha_vencimiento, l.estado, l.notas, l.costo,
      p.nombre AS proveedor_nombre,
      c.nombre AS cliente_nombre,
      v.nombre AS valla_nombre,
      COALESCE(v.numero_licencia, CONCAT('VL-', v.id)) AS valla_codigo
    FROM crm_licencias l
    LEFT JOIN proveedores  p ON p.id = l.proveedor_id
    LEFT JOIN crm_clientes c ON c.id = l.cliente_id
    LEFT JOIN vallas       v ON v.id = l.valla_id
    WHERE l.id = ?
    LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) json_exit(['ok'=>false,'error'=>'NOT_FOUND'], 404);

  // Archivos
  $files = [];
  if (table_exists($conn, 'crm_licencias_files')) {
    $fs = $conn->prepare("SELECT ruta, nombre_original AS nombre FROM crm_licencias_files WHERE licencia_id=? ORDER BY id ASC");
    $fs->bind_param('i', $id);
    $fs->execute();
    $rfs = $fs->get_result();
    while ($f = $rfs->fetch_assoc()) { $files[] = ['ruta'=>$f['ruta'], 'nombre'=>$f['nombre'] ?? basename($f['ruta'])]; }
  }

  $data = [
    'id'                => (int)$row['id'],
    'titulo'            => $row['titulo'],
    'proveedor'         => ['id'=>$row['proveedor_id'] ? (int)$row['proveedor_id'] : null, 'nombre'=>$row['proveedor_nombre'] ?? null],
    'cliente'           => ['id'=>$row['cliente_id'] ? (int)$row['cliente_id'] : null,   'nombre'=>$row['cliente_nombre'] ?? null],
    'valla'             => [
                            'id'    => $row['valla_id'] ? (int)$row['valla_id'] : null,
                            'codigo'=> $row['valla_codigo'] ?? null,
                            'nombre'=> $row['valla_nombre'] ?? null
                          ],
    'ciudad'            => $row['ciudad'],
    'entidad'           => $row['entidad'],
    'tipo_licencia'     => $row['tipo_licencia'],
    'direccion'         => $row['direccion'],
    'reminder_days'     => isset($row['reminder_days']) ? (int)$row['reminder_days'] : null,
    'fecha_emision'     => $row['fecha_emision'] ? substr($row['fecha_emision'],0,10) : null,
    'fecha_vencimiento' => $row['fecha_vencimiento'] ? substr($row['fecha_vencimiento'],0,10) : null,
    'estado'            => $row['estado'],
    'notas'             => $row['notas'],
    'costo'             => $row['costo'],
    'files'             => $files
  ];
  json_exit(['ok'=>true,'data'=>$data]);
}

/* Compat: tabla licencias antigua */
if ($HAS_LIC) {
  $stmt = $conn->prepare("SELECT * FROM licencias WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) json_exit(['ok'=>false,'error'=>'NOT_FOUND'], 404);

  $data = [
    'id'                => (int)$row['id'],
    'titulo'            => $row['titulo'] ?? null,
    'proveedor'         => ['id'=>$row['proveedor_id'] ?? null, 'nombre'=>null],
    'cliente'           => ['id'=>$row['cliente_id'] ?? null,   'nombre'=>null],
    'valla'             => ['id'=>$row['valla_id'] ?? null, 'codigo'=>null],
    'ciudad'            => $row['ciudad'] ?? null,
    'entidad'           => $row['entidad'] ?? null,
    'tipo_licencia'     => $row['tipo_licencia'] ?? null,
    'direccion'         => $row['direccion'] ?? null,
    'reminder_days'     => $row['reminder_days'] ?? null,
    'fecha_emision'     => $row['fecha_emision'] ? substr($row['fecha_emision'],0,10) : null,
    'fecha_vencimiento' => $row['fecha_vencimiento'] ? substr($row['fecha_vencimiento'],0,10) : null,
    'estado'            => $row['estado'] ?? null,
    'notas'             => $row['notas'] ?? null,
    'files'             => []
  ];
  json_exit(['ok'=>true,'data'=>$data]);
}

/* Fallback a vallas */
if ($HAS_VAL) {
  $stmt = $conn->prepare("
    SELECT v.id, v.proveedor_id, v.zona AS ciudad, v.fecha_creacion AS fecha_emision,
           v.fecha_vencimiento,
           COALESCE(v.numero_licencia, CONCAT('VL-', v.id)) AS valla_codigo,
           v.nombre AS valla_nombre
    FROM vallas v WHERE v.id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  if (!$row) json_exit(['ok'=>false,'error'=>'NOT_FOUND'], 404);

  $estado = 'aprobada';
  if ($row['fecha_vencimiento'] && substr($row['fecha_vencimiento'],0,10) < date('Y-m-d')) $estado = 'vencida';

  $data = [
    'id'                => (int)$row['id'],
    'titulo'            => $row['valla_codigo'],
    'proveedor'         => ['id'=>$row['proveedor_id'] ? (int)$row['proveedor_id'] : null, 'nombre'=>null],
    'cliente'           => ['id'=>null,'nombre'=>null],
    'valla'             => ['id'=>(int)$row['id'], 'codigo'=>$row['valla_codigo'], 'nombre'=>$row['valla_nombre']],
    'ciudad'            => $row['ciudad'] ?? null,
    'entidad'           => null,
    'tipo_licencia'     => null,
    'direccion'         => null,
    'reminder_days'     => null,
    'fecha_emision'     => $row['fecha_emision'] ? substr($row['fecha_emision'],0,10) : null,
    'fecha_vencimiento' => $row['fecha_vencimiento'] ? substr($row['fecha_vencimiento'],0,10) : null,
    'estado'            => $estado,
    'notas'             => null,
    'files'             => []
  ];
  json_exit(['ok'=>true,'data'=>$data]);
}

json_exit(['ok'=>false,'error'=>'NO_TABLES'], 500);

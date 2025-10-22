<?php
// /console/licencias/ajax/guardar.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'NO_AUTH']); exit;
}
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_CSRF']); exit;
}

function nvl($s){ $s=trim((string)$s); return $s===''?null:$s; }

try {
  $conn->set_charset('utf8mb4');

  // === Diagnóstico de DB y tabla ===
  $activeDb = '';
  if ($rs = $conn->query('SELECT DATABASE()')) { $r = $rs->fetch_row(); $activeDb = (string)($r[0] ?? ''); $rs->close(); }

  $tbl = 'crm_licencias';
  $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
  if (!$stmt) { throw new RuntimeException('SQL_PREP_CHECK'); }
  $stmt->bind_param('s', $tbl);
  $stmt->execute();
  $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();

  if (empty($cnt)) {
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>'TABLE_MISSING',
      'table'=>$tbl,
      'database'=>$activeDb
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // === Datos ===
  $titulo        = nvl($_POST['titulo'] ?? '');
  $estado        = nvl($_POST['estado'] ?? 'borrador');
  $periodicidad  = nvl($_POST['periodicidad'] ?? null);
  $proveedor_id  = (int)($_POST['proveedor_id'] ?? 0);
  $valla_id      = (int)($_POST['valla_id'] ?? 0);
  $cliente_id    = (int)($_POST['cliente_id'] ?? 0);
  $ciudad        = nvl($_POST['ciudad'] ?? '');
  $entidad       = nvl($_POST['entidad'] ?? '');
  $tipo_lic      = nvl($_POST['tipo_licencia'] ?? null);
  $emi           = nvl($_POST['fecha_emision'] ?? '');
  $venc          = nvl($_POST['fecha_venc'] ?? '');
  $reminder      = (int)($_POST['reminder_days'] ?? 30);
  $costo         = (string)($_POST['costo'] ?? '0');
  $notas         = nvl($_POST['notas'] ?? null);

  if ($proveedor_id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'FALTA_PROVEEDOR']); exit; }
  if (!$titulo || !$ciudad || !$entidad || !$emi || !$venc) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'FALTAN_CAMPOS']); exit; }

  $sql = "INSERT INTO crm_licencias
    (titulo,estado,periodicidad,proveedor_id,valla_id,cliente_id,ciudad,entidad,tipo_licencia,fecha_emision,fecha_vencimiento,reminder_days,costo,notas)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new RuntimeException('SQL_PREP');

  $valla_id   = $valla_id ?: null;
  $cliente_id = $cliente_id ?: null;

  // tipos: s s s i i i s s s s s i d s
  $stmt->bind_param(
    'sssiiisssssids',
    $titulo,$estado,$periodicidad,$proveedor_id,$valla_id,$cliente_id,$ciudad,$entidad,$tipo_lic,$emi,$venc,$reminder,$costo,$notas
  );
  $stmt->execute();
  $id = (int)$conn->insert_id;
  $stmt->close();

  echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[licencias/guardar] '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER_ERROR'], JSON_UNESCAPED_UNICODE);
}

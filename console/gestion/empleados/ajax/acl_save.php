<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../config/db.php';
start_session_safe();

/* Preflight local (si el navegador manda OPTIONS) */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(204); exit; }

/* Sólo admin */
if (empty($_SESSION['uid']) || ($_SESSION['tipo']??'') !== 'admin') {
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

/* Carga segura de payload: JSON o x-www-form-urlencoded */
$ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
$in = [];
if (strpos($ct,'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $in = json_decode($raw, true) ?: [];
} else {
  $in = $_POST;
}

/* CSRF */
$csrf = (string)($in['csrf'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'csrf_invalid']); exit;
}

/* Método */
if ($method !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
}

/* Inputs */
$empleadoId = (int)($in['empleado_id'] ?? 0);
$urlsIn     = $in['urls'] ?? [];
if ($empleadoId <= 0 || !is_array($urlsIn)) {
  echo json_encode(['ok'=>false,'error'=>'bad_request']); exit;
}

/* Normaliza y filtra URLs */
$urls = [];
foreach ($urlsIn as $p) {
  $p = trim((string)$p);
  if ($p === '') continue;
  if ($p[0] !== '/') continue;
  if (strpos($p, '/console/') !== 0) continue;
  if (substr($p,-1) !== '/') $p .= '/';
  $urls['url:' . $p] = true; // set
}
$urls = array_keys($urls);

/* Persiste en usuarios_permisos: borra url:% y re-inserta */
try {
  $conn->begin_transaction();

  // asegúrate de que existe la tabla (id autoincrement + índice)
  $conn->query("
    CREATE TABLE IF NOT EXISTS usuarios_permisos (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      usuario_id INT NOT NULL,
      permiso VARCHAR(50) NOT NULL,
      valor TINYINT(1) NOT NULL DEFAULT 1,
      KEY idx_usr_perm (usuario_id, permiso)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  // borra permisos url: previos del usuario
  $stmtDel = $conn->prepare("DELETE FROM usuarios_permisos WHERE usuario_id=? AND permiso LIKE 'url:%'");
  $stmtDel->bind_param('i', $empleadoId);
  $stmtDel->execute();
  $stmtDel->close();

  // inserta nuevos
  if (!empty($urls)) {
    $stmtIns = $conn->prepare("INSERT INTO usuarios_permisos (usuario_id, permiso, valor) VALUES (?,?,1)");
    foreach ($urls as $perm) {
      $stmtIns->bind_param('is', $empleadoId, $perm);
      $stmtIns->execute();
    }
    $stmtIns->close();
  }

  // fuerza tipo staff si no es admin
  $conn->query("UPDATE usuarios SET tipo=IF(tipo='admin','admin','staff') WHERE id=".$empleadoId);

  $conn->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($conn->errno) { try{$conn->rollback();}catch(Throwable $e2){} }
  echo json_encode(['ok'=>false,'error'=>'db_error']); // no exponemos detalles en prod
}

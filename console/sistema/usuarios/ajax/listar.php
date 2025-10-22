<?php
// /console/sistema/usuarios/ajax/listar.php
declare(strict_types=1);
@header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

try {
  if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF inválido']); exit;
  }

  $limit  = isset($_POST['limit']) ? max(1, min(100, (int)$_POST['limit'])) : 50;
  $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;

  $q      = trim((string)($_POST['q'] ?? ''));
  $tipo   = trim((string)($_POST['tipo'] ?? ''));
  $rol    = trim((string)($_POST['rol']  ?? ''));
  $estado = trim((string)($_POST['estado'] ?? ''));

  $where = [];
  $types = '';
  $vals  = [];

  if ($q !== '') {
    $like = "%$q%";
    $where[] = "(usuario LIKE ? OR email LIKE ? OR responsable LIKE ? OR nombre_empresa LIKE ?)";
    $types  .= 'ssss';
    array_push($vals, $like, $like, $like, $like);
  }

  if ($tipo !== '' && in_array($tipo, ['admin','staff','cliente'], true)) {
    $where[] = "tipo = ?";
    $types  .= 's';
    $vals[]  = $tipo;
  }

  if ($rol !== '' && in_array($rol, ['operador','staff_basico','staff_operativo'], true)) {
    $where[] = "rol = ?";
    $types  .= 's';
    $vals[]  = $rol;
  }

  if ($estado !== '' && in_array($estado, ['0','1'], true)) {
    $where[] = "activo = ?";
    $types  .= 'i';
    $vals[]  = (int)$estado;
  }

  $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

  // total
  $sqlCount = "SELECT COUNT(1) FROM usuarios" . $whereSql;
  $stmtC = $conn->prepare($sqlCount);
  if ($types !== '') { $stmtC->bind_param($types, ...$vals); }
  $stmtC->execute();
  $stmtC->bind_result($total);
  $stmtC->fetch();
  $stmtC->close();

  // rows
  $sql = "SELECT id, usuario, responsable, tipo, rol, activo, nombre_empresa
          FROM usuarios" . $whereSql . " ORDER BY id DESC LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);
  $types2 = $types . 'ii';
  $vals2  = $vals;
  $vals2[] = $limit;
  $vals2[] = $offset;
  if ($types !== '') $stmt->bind_param($types2, ...$vals2);
  else               $stmt->bind_param('ii', $limit, $offset);

  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      'id'             => (int)$r['id'],
      'usuario'        => (string)($r['usuario'] ?? ''),
      'responsable'    => (string)($r['responsable'] ?? ''),
      'tipo'           => (string)($r['tipo'] ?? ''),
      'rol'            => (string)($r['rol'] ?? ''),
      'activo'         => (int)($r['activo'] ?? 0),
      'nombre_empresa' => (string)($r['nombre_empresa'] ?? '')
    ];
  }
  $stmt->close();

  echo json_encode(['ok'=>true, 'rows'=>$rows, 'total'=>(int)$total], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error interno']); // no filtrar detalles
}

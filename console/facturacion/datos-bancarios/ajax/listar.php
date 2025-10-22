<?php
// /console/facturacion/datos-bancarios/ajax/listar.php
declare(strict_types=1);
require_once __DIR__ . '/../../../../config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

$q       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$estado  = isset($_GET['estado']) && $_GET['estado'] !== '' ? (int)$_GET['estado'] : null;
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = (int)($_GET['limit'] ?? 20);
$limit   = $limit < 10 ? 10 : ($limit > 100 ? 100 : $limit);
$offset  = ($page - 1) * $limit;

$where = [];
$params = [];
$types = '';

if ($q !== '') {
  $where[] = '(banco LIKE ? OR titular LIKE ? OR numero_cuenta LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}
if ($estado !== null) {
  $where[] = 'activo = ?';
  $params[] = $estado; $types .= 'i';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
  // total
  $sqlCount = "SELECT COUNT(*) AS c FROM datos_bancarios {$whereSql}";
  $stmt = $conn->prepare($sqlCount);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  // rows
  $sql = "SELECT id,banco,numero_cuenta,tipo_cuenta,titular,activo
          FROM datos_bancarios {$whereSql}
          ORDER BY id DESC LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);
  $types2 = $types . 'ii';
  $params2 = $params; $params2[] = $limit; $params2[] = $offset;
  $stmt->bind_param($types2, ...$params2);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      'id'=>(int)$r['id'],
      'banco'=>$r['banco'],
      'numero_cuenta'=>$r['numero_cuenta'],
      'tipo_cuenta'=>$r['tipo_cuenta'],
      'titular'=>$r['titular'],
      'activo'=>(int)$r['activo']
    ];
  }
  $stmt->close();

  echo json_encode(['ok'=>true,'rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>($total ? (int)ceil($total/$limit) : 1)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'SERVER','msg'=>'Error al listar'], JSON_UNESCAPED_UNICODE);
}

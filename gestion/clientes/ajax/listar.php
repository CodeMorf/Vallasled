<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
  $q     = trim((string)($_GET['q'] ?? ''));
  $prov  = (int)($_GET['proveedor_id'] ?? 0);

  $where = [];
  $args  = [];
  $types = '';

  if ($q !== '') {
    $where[] = "(LOWER(nombre) LIKE ? OR LOWER(empresa) LIKE ? OR LOWER(email) LIKE ? OR LOWER(telefono) LIKE ?)";
    $needle = '%' . mb_strtolower($q, 'UTF-8') . '%';
    array_push($args, $needle, $needle, $needle, $needle);
    $types .= 'ssss';
  }
  if ($prov > 0) {
    $where[] = "proveedor_id = ?";
    $args[] = $prov;
    $types .= 'i';
  }
  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // total
  $sqlCount = "SELECT COUNT(*) AS c FROM crm_clientes {$whereSql}";
  $stmt = $conn->prepare($sqlCount);
  if ($types) $stmt->bind_param($types, ...$args);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  $pages = max(1, (int)ceil($total / $limit));
  $page  = min($page, $pages);
  $off   = ($page - 1) * $limit;

  // datos
  $sql = "SELECT c.id, c.nombre, c.empresa, c.email, c.telefono, c.proveedor_id,
                 IFNULL(p.nombre,'—') AS proveedor_nombre
          FROM crm_clientes c
          LEFT JOIN proveedores p ON p.id=c.proveedor_id
          {$whereSql}
          ORDER BY c.id DESC
          LIMIT ? OFFSET ?";
  $stmt = $conn->prepare($sql);

  if ($types) {
    $types2 = $types . 'ii';
    $args2 = array_merge($args, [$limit, $off]);
    $stmt->bind_param($types2, ...$args2);
  } else {
    $stmt->bind_param('ii', $limit, $off);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  $items = [];
  while ($row = $res->fetch_assoc()) $items[] = $row;
  $stmt->close();

  echo json_encode(['ok'=>true, 'data'=>['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'Error al listar']);
}

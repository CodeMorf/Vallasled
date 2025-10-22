<?php
// /console/gestion/planes/ajax/planes_listar.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');

try {
  // Params
  $q        = trim((string)($_GET['q'] ?? ''));
  $tipo     = trim((string)($_GET['tipo'] ?? ''));           // '', gratis|mensual|trimestral|anual|comision
  $estado   = $_GET['estado'] ?? '';                         // '', '1', '0'
  $sort     = trim((string)($_GET['sort'] ?? 'recientes'));  // recientes|nombre|precio|limite|comision|tipo
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $perPage  = (int)($_GET['per_page'] ?? 50);
  $simple   = (int)($_GET['simple'] ?? 0);                   // 1 = id/nombre/tipo
  if ($perPage < 5)   $perPage = 5;
  if ($perPage > 200) $perPage = 200;
  $offset   = ($page - 1) * $perPage;

  // WHERE dinámico
  $where = [];
  $types = '';
  $vals  = [];

  if ($q !== '') {
    $where[] = '(vp.nombre LIKE ? OR vp.descripcion LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ss';
    array_push($vals, $like, $like);
  }
  if ($tipo !== '') {
    $where[] = 'vp.tipo_facturacion = ?';
    $types  .= 's';
    $vals[]  = $tipo;
  }
  if ($estado === '0' || $estado === '1') {
    $where[] = 'vp.estado = ?';
    $types  .= 'i';
    $vals[]  = (int)$estado;
  }
  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

  // Orden
  switch ($sort) {
    case 'nombre':   $orderBy = 'vp.nombre ASC'; break;
    case 'precio':   $orderBy = 'vp.precio ASC, vp.nombre ASC'; break;
    case 'limite':   $orderBy = 'vp.limite_vallas DESC, vp.nombre ASC'; break;
    case 'comision': $orderBy = 'vp.comision DESC, vp.nombre ASC'; break;
    case 'tipo':     $orderBy = "FIELD(vp.tipo_facturacion,'gratis','mensual','trimestral','anual','comision'), vp.nombre ASC"; break;
    default:         $orderBy = 'vp.created_at DESC, vp.id DESC';
  }

  // Count
  $sqlCount = "SELECT COUNT(*) AS c FROM vendor_planes vp $whereSql";
  $stmt = $conn->prepare($sqlCount);
  if ($types !== '') { $stmt->bind_param($types, ...$vals); }
  $stmt->execute();
  $total = (int)$stmt->get_result()->fetch_assoc()['c'];
  $stmt->close();

  // Datos
  $sql = "
    SELECT
      vp.id,
      vp.nombre,
      vp.descripcion,
      vp.limite_vallas,
      vp.tipo_facturacion,
      vp.precio,
      vp.comision,
      vp.estado,
      vp.created_at,
      vp.prueba_dias
    FROM vendor_planes vp
    $whereSql
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
  ";
  $stmt = $conn->prepare($sql);
  $types2 = $types . 'ii';
  $vals2  = $vals;
  $vals2[] = $perPage;
  $vals2[] = $offset;
  if ($types2 !== '') { $stmt->bind_param($types2, ...$vals2); }
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    // Casts
    foreach (['id','limite_vallas','estado','prueba_dias'] as $k) {
      if (isset($r[$k])) $r[$k] = (int)$r[$k];
    }
    foreach (['precio','comision'] as $k) {
      if (isset($r[$k])) $r[$k] = (float)$r[$k];
    }

    if ($simple === 1) {
      $rows[] = [
        'id'   => $r['id'],
        'nombre' => $r['nombre'],
        'tipo' => $r['tipo_facturacion'],
      ];
    } else {
      $rows[] = $r;
    }
  }
  $stmt->close();

  json_exit(['ok'=>true,'total'=>$total,'rows'=>$rows,'page'=>$page,'per_page'=>$perPage]);
} catch (Throwable $e) {
  json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}

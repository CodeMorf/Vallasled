<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

start_session_safe();
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
}

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$csrf)) {
  http_response_code(419); echo json_encode(['error'=>'csrf']); exit;
}

$tipo   = $_POST['tipo']   ?? 'facturas';
$desde  = $_POST['desde']  ?? '';
$hasta  = $_POST['hasta']  ?? '';
$limit  = max(1, min(1000, (int)($_POST['limit'] ?? 200)));
$offset = max(0, (int)($_POST['offset'] ?? 0));

$columnsMap = [
  'facturas' => [
    ['key'=>'id','title'=>'ID'],
    ['key'=>'valla_id','title'=>'ID Valla'],
    ['key'=>'cliente_nombre','title'=>'Cliente'],
    ['key'=>'monto','title'=>'Monto'],
    ['key'=>'comision_monto','title'=>'Comisión'],
    ['key'=>'estado','title'=>'Estado'],
    ['key'=>'fecha_generacion','title'=>'Fecha'],
  ],
  'vallas' => [
    ['key'=>'id','title'=>'ID Valla'],
    ['key'=>'nombre','title'=>'Nombre'],
    ['key'=>'tipo','title'=>'Tipo'],
    ['key'=>'provincia_id','title'=>'ID Provincia'],
    ['key'=>'proveedor_id','title'=>'ID Proveedor'],
    ['key'=>'precio','title'=>'Precio'],
    ['key'=>'estado_valla','title'=>'Estado'],
    ['key'=>'fecha_creacion','title'=>'Creada'],
  ],
  'licencias' => [
    ['key'=>'id','title'=>'ID'],
    ['key'=>'titulo','title'=>'Título'],
    ['key'=>'proveedor_id','title'=>'ID Proveedor'],
    ['key'=>'valla_id','title'=>'ID Valla'],
    ['key'=>'estado','title'=>'Estado'],
    ['key'=>'periodicidad','title'=>'Periodicidad'],
    ['key'=>'fecha_emision','title'=>'Emisión'],
    ['key'=>'fecha_vencimiento','title'=>'Vencimiento'],
  ],
  'clientes' => [
    ['key'=>'id','title'=>'ID'],
    ['key'=>'nombre','title'=>'Nombre'],
    ['key'=>'empresa','title'=>'Empresa'],
    ['key'=>'proveedor_id','title'=>'ID Proveedor'],
    ['key'=>'creado','title'=>'Creado'],
  ],
  'proveedores' => [
    ['key'=>'id','title'=>'ID'],
    ['key'=>'nombre','title'=>'Nombre'],
    ['key'=>'contacto','title'=>'Contacto'],
    ['key'=>'estado','title'=>'Estado'],
    ['key'=>'creado','title'=>'Creado'],
  ],
];

function whereFechas(string $campo, string $desde, string $hasta, array &$types, array &$params): string {
  $w = [];
  if ($desde !== '') { $w[] = "DATE($campo) >= ?"; $types[]='s'; $params[] = $desde; }
  if ($hasta !== '') { $w[] = "DATE($campo) <= ?"; $types[]='s'; $params[] = $hasta; }
  return $w ? (' WHERE '.implode(' AND ', $w)) : '';
}

function db_all(mysqli $conn, string $sql, string $types='', array $params=[]) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) return ['err'=>$conn->error];
  if ($types && $params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}
function db_one(mysqli $conn, string $sql, string $types='', array $params=[]) {
  $rows = db_all($conn, $sql, $types, $params);
  return $rows[0] ?? null;
}

$types=[]; $params=[];
$select = ''; $from = ''; $order=''; $dateField='';
switch ($tipo) {
  case 'vallas':
    $select = "SELECT id,nombre,tipo,provincia_id,proveedor_id,precio,estado_valla,fecha_creacion";
    $from = "FROM vallas";
    $dateField = 'fecha_creacion';
    $order = " ORDER BY fecha_creacion DESC, id DESC";
    break;
  case 'licencias':
    $select = "SELECT id,titulo,proveedor_id,valla_id,estado,periodicidad,fecha_emision,fecha_vencimiento";
    $from = "FROM crm_licencias";
    $dateField = 'fecha_emision';
    $order = " ORDER BY fecha_emision DESC, id DESC";
    break;
  case 'clientes':
    $select = "SELECT id,nombre,empresa,proveedor_id,creado";
    $from = "FROM crm_clientes";
    $dateField = 'creado';
    $order = " ORDER BY creado DESC, id DESC";
    break;
  case 'proveedores':
    $select = "SELECT id,nombre,contacto,estado,creado";
    $from = "FROM proveedores";
    $dateField = 'creado';
    $order = " ORDER BY creado DESC, id DESC";
    break;
  case 'facturas':
  default:
    $tipo = 'facturas';
    $select = "SELECT id,valla_id,cliente_nombre,monto,comision_monto,estado,fecha_generacion";
    $from = "FROM facturas";
    $dateField = 'fecha_generacion';
    $order = " ORDER BY fecha_generacion DESC, id DESC";
    break;
}

$where = whereFechas($dateField, $desde, $hasta, $types, $params);
$countRow = db_one($conn, "SELECT COUNT(1) AS c $from $where", implode('', $types), $params) ?: ['c'=>0];

$sql = "$select $from $where $order LIMIT ? OFFSET ?";
$types2 = implode('', $types) . 'ii';
$params2 = array_merge($params, [ $limit, $offset ]);
$rows = db_all($conn, $sql, $types2, $params2);
if (isset($rows['err'])) {
  http_response_code(500); echo json_encode(['error'=>$rows['err']]); exit;
}

echo json_encode([
  'columns' => $columnsMap[$tipo],
  'rows'    => $rows,
  'total'   => (int)$countRow['c']
], JSON_UNESCAPED_UNICODE);

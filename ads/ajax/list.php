<?php
// /console/vallas/ajax/list.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json');

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Bad request']); exit; }
// if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }

const UPLOAD_BASE = 'https://auth.vallasled.com/uploads/';

$q      = trim((string)($_GET['q'] ?? ''));
$prov   = trim((string)($_GET['prov'] ?? ''));
$disp   = ($_GET['disp'] ?? '') !== '' ? (int)$_GET['disp'] : null;
$pub    = ($_GET['publico'] ?? '') !== '' ? (int)$_GET['publico'] : null;
$ads    = ($_GET['ads'] ?? '') !== '' ? (int)$_GET['ads'] : null;
$all    = (int)($_GET['all'] ?? 0) === 1;
$page   = max(1, (int)($_GET['page'] ?? 1));
$size   = max(1, min(1000, (int)($_GET['size'] ?? 12)));
$off    = ($page-1)*$size;

function build_image_url(array $row): string {
  // 1) Busca una columna con extensión de imagen
  foreach ($row as $v) {
    if (!is_string($v)) continue;
    $v = trim($v);
    if ($v !== '' && preg_match('~\.(jpe?g|png|gif|webp|avif)$~i', $v)) {
      $cand = preg_split('/[,|;]/', $v)[0];
      if (str_starts_with($cand,'http://') || str_starts_with($cand,'https://')) return $cand;
      if ($cand[0] === '/') return $cand;
      return UPLOAD_BASE . $cand;
    }
  }
  // 2) Columnas comunes
  foreach (['imagen_url','imagen','foto','img','image','thumb','archivo','path'] as $k) {
    if (!empty($row[$k]) && is_string($row[$k])) {
      $cand = preg_split('/[,|;]/', trim((string)$row[$k]))[0];
      if ($cand === '') continue;
      if (str_starts_with($cand,'http://') || str_starts_with($cand,'https://')) return $cand;
      if ($cand[0] === '/') return $cand;
      return UPLOAD_BASE . $cand;
    }
  }
  return '';
}

$items = []; $total = 0; $proveedores = [];

try {
  // Columnas disponibles
  $cols = [];
  $resCols = $conn->query("SHOW COLUMNS FROM vallas");
  while ($c = $resCols->fetch_assoc()) $cols[$c['Field']] = true;

  $where = []; $bind = []; $types = '';

  if ($q !== '') {
    $wq = [];
    if (!empty($cols['nombre']))    { $wq[]="v.nombre LIKE ?";    $bind[]="%$q%"; $types.='s'; }
    if (!empty($cols['ubicacion'])) { $wq[]="v.ubicacion LIKE ?"; $bind[]="%$q%"; $types.='s'; }
    if ($wq) $where[] = '('.implode(' OR ',$wq).')';
  }
  if ($prov !== '') {
    if (!empty($cols['proveedor_id'])) { $where[]="v.proveedor_id=?"; $bind[]=$prov; $types.='s'; }
    elseif (!empty($cols['proveedor'])){ $where[]="v.proveedor=?";    $bind[]=$prov; $types.='s'; }
  }
  if ($disp !== null && isset($cols['disponible'])) { $where[]="v.disponible=?"; $bind[]=$disp; $types.='i'; }
  if ($pub  !== null && isset($cols['publico']))    { $where[]="v.publico=?";    $bind[]=$pub;  $types.='i'; }
  if ($ads  !== null && isset($cols['ads']))        { $where[]="v.ads=?";        $bind[]=$ads;  $types.='i'; }

  $W = $where ? ('WHERE '.implode(' AND ',$where)) : '';

  // Total
  $stmt = $conn->prepare("SELECT COUNT(*) c FROM vallas v $W");
  if ($types) $stmt->bind_param($types, ...$bind);
  $stmt->execute();
  $total = (int)$stmt->get_result()->fetch_assoc()['c'];

  // Datos
  $order = isset($cols['orden']) ? "ORDER BY v.orden ASC" : "ORDER BY v.id DESC";
  $limit = $all ? "" : "LIMIT ?,?";
  $sql = "SELECT * FROM vallas v $W $order $limit";
  $stmt = $conn->prepare($sql);
  if ($all) {
    if ($types) $stmt->bind_param($types, ...$bind);
  } else {
    $types2 = $types.'ii'; $bind2 = $bind; $bind2[]=$off; $bind2[]=$size;
    if ($types2) $stmt->bind_param($types2, ...$bind2);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $img = build_image_url($row);
    $items[] = [
      'id'        => (int)($row['id'] ?? 0),
      'nombre'    => $row['nombre'] ?? ($row['titulo'] ?? ($row['name'] ?? '')),
      'tipo'      => $row['tipo'] ?? ($row['categoria'] ?? ''),
      'proveedor' => $row['proveedor'] ?? '',
      'precio'    => (float)($row['precio_publico'] ?? $row['precio'] ?? $row['precio_mes'] ?? 0),
      'publico'   => (int)($row['publico'] ?? 0),
      'activo'    => (int)($row['activo'] ?? $row['estado'] ?? 1),
      'disponible'=> (int)($row['disponible'] ?? 1),
      'ads'       => (int)($row['ads'] ?? 0),
      'imagen'    => $img !== '' ? $img : 'https://placehold.co/400x300/e2e8f0/718096?text=Valla',
      // sin descripción
      // 'ubicacion' intencionalmente omitida si no la quieres mostrar
    ];
  }

  // Filtro proveedores opcional
  try {
    $rp = $conn->query("SELECT id, nombre FROM proveedores ORDER BY nombre LIMIT 200");
    while ($p = $rp->fetch_assoc()) $proveedores[] = ['id'=> (string)$p['id'], 'nombre'=>$p['nombre']];
  } catch(Throwable $e){}

} catch (Throwable $e) {
  // Fallback demo
  $total = 2;
  $items = [
    ['id'=>1,'nombre'=>'PANTALLA HIGÜEY','tipo'=>'LED','proveedor'=>'Fox Publicidad','precio'=>53100,'publico'=>1,'activo'=>1,'disponible'=>1,'ads'=>1,'imagen'=>UPLOAD_BASE.'1759813884_68e4a0fc53012_IMG-20250814-WA0043.jpg'],
    ['id'=>2,'nombre'=>'Valla Padre Castellanos','tipo'=>'Impresa','proveedor'=>'VALLAS PAUL','precio'=>35000,'publico'=>0,'activo'=>0,'disponible'=>0,'ads'=>0,'imagen'=>UPLOAD_BASE.'demo2.jpg'],
  ];
}

echo json_encode([
  'ok'=>true,
  'items'=>$items,
  'meta'=>[
    'total'=>$total,
    'page'=>$page,
    'size'=>$all ? $total : $size,
    'proveedores'=>$proveedores
  ],
], JSON_UNESCAPED_UNICODE);

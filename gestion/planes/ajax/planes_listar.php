<?php
declare(strict_types=1);

// /console/gestion/planes/ajax/planes_listar.php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);

function http400($m){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>$m]); exit; }
function http500($m){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$m]); exit; }

// CSRF header simple (si tienes helper propio, úsalo)
if (function_exists('csrf_verify_header')) { csrf_verify_header(); }
else {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $h = $_SERVER['HTTP_X_CSRF'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $h)) {
    http_response_code(419); echo json_encode(['ok'=>false,'msg'=>'CSRF inválido']); exit;
  }
}

$q    = trim((string)($_GET['q'] ?? ''));
$tipo = trim((string)($_GET['tipo'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit= min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page-1)*$limit;

// Detecta driver
$isPdo = $conn instanceof PDO;

// Build WHERE
$where = [];
$args  = [];
if ($q !== '') {
  $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ?)";
  $args[] = "%$q%"; $args[] = "%$q%";
}
if ($tipo !== '') {
  $where[] = "p.tipo_facturacion = ?";
  $args[] = $tipo;
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Count
$sqlCount = "SELECT COUNT(*) AS c FROM vendor_planes p $whereSql";
try {
  if ($isPdo) {
    $st = $conn->prepare($sqlCount); $st->execute($args); $total = (int)$st->fetch(PDO::FETCH_ASSOC)['c'];
  } else {
    $stmt = $conn->prepare($sqlCount);
    if ($args){ $types=str_repeat('s', count($args)); $stmt->bind_param($types, ...$args); }
    $stmt->execute(); $r=$stmt->get_result(); $row=$r->fetch_assoc(); $total=(int)$row['c']; $stmt->close();
  }
} catch(Exception $e){ http500('Error contando'); }

// Data
$sql = "SELECT p.id,p.nombre,p.descripcion,p.limite_vallas,p.tipo_facturacion,p.precio,p.comision,
               p.estado,p.prueba_dias,
               f.access_crm,f.access_facturacion,f.access_mapa,f.access_export,f.soporte_ncf,
               f.comision_model,f.comision_pct,f.comision_flat,f.factura_auto
        FROM vendor_planes p
        LEFT JOIN vendor_plan_features f ON f.plan_id=p.id
        $whereSql
        ORDER BY p.id DESC
        LIMIT $limit OFFSET $offset";
$data = [];
try {
  if ($isPdo) {
    $st = $conn->prepare($sql); $st->execute($args);
    while($row=$st->fetch(PDO::FETCH_ASSOC)){
      $data[] = [
        'id'=>(int)$row['id'],
        'nombre'=>$row['nombre'],
        'descripcion'=>$row['descripcion'],
        'limite_vallas'=>(int)$row['limite_vallas'],
        'tipo'=>$row['tipo_facturacion'],
        'precio'=>(float)$row['precio'],
        'activo'=>(int)$row['estado']===1,
        'dias_prueba'=>(int)$row['prueba_dias'],
        'features'=>[
          'access_crm'=>(int)$row['access_crm']===1,
          'access_facturacion'=>(int)$row['access_facturacion']===1,
          'access_mapa'=>(int)$row['access_mapa']===1,
          'exportar_datos'=>(int)$row['access_export']===1,
          'soporte_ncf'=>(int)$row['soporte_ncf']===1,
          'factura_auto'=>(int)$row['factura_auto']===1,
          'comision_model'=>$row['comision_model'] ?: 'none',
          'comision_pct'=>(float)$row['comision_pct'],
          'comision_flat'=>(float)$row['comision_flat'],
        ],
      ];
    }
  } else {
    $stmt=$conn->prepare($sql);
    if ($args){ $types=str_repeat('s', count($args)); $stmt->bind_param($types, ...$args); }
    $stmt->execute(); $r=$stmt->get_result();
    while($row=$r->fetch_assoc()){
      $data[] = [
        'id'=>(int)$row['id'],
        'nombre'=>$row['nombre'],
        'descripcion'=>$row['descripcion'],
        'limite_vallas'=>(int)$row['limite_vallas'],
        'tipo'=>$row['tipo_facturacion'],
        'precio'=>(float)$row['precio'],
        'activo'=>(int)$row['estado']===1,
        'dias_prueba'=>(int)$row['prueba_dias'],
        'features'=>[
          'access_crm'=>(int)$row['access_crm']===1,
          'access_facturacion'=>(int)$row['access_facturacion']===1,
          'access_mapa'=>(int)$row['access_mapa']===1,
          'exportar_datos'=>(int)$row['access_export']===1,
          'soporte_ncf'=>(int)$row['soporte_ncf']===1,
          'factura_auto'=>(int)$row['factura_auto']===1,
          'comision_model'=>$row['comision_model'] ?: 'none',
          'comision_pct'=>(float)$row['comision_pct'],
          'comision_flat'=>(float)$row['comision_flat'],
        ],
      ];
    }
    $stmt->close();
  }
} catch(Exception $e){ http500('Error listando'); }

echo json_encode(['ok'=>true,'data'=>$data,'total'=>$total,'page'=>$page,'limit'=>$limit]);

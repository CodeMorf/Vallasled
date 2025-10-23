<?php declare(strict_types=1);
/**
 * /api/destacados/api.php
 * Lista pagos de vallas destacadas (JSON) usando mysqli ($conn de /config/db.php).
 * Filtros: include_valla, q, valla_id, proveedor_id, cliente_id, estado, sort/dir, paginación.
 */

@header('Content-Type: application/json; charset=utf-8');

/* Bootstrap */
$root      = dirname(__DIR__, 2);
$confDb    = $root . '/config/db.php';
$confMedia = $root . '/config/media.php';
if (!is_file($confDb)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'MISSING_DB']); exit; }
require_once $confDb;
if (!isset($conn) || !($conn instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'MYSQLI_CONN_MISSING']); exit; }
if (is_file($confMedia)) { require_once $confMedia; }

/* CORS mínimo */
if (isset($_SERVER['HTTP_ORIGIN'])) { header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']); header('Vary: Origin'); }
else { header('Access-Control-Allow-Origin: *'); }
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS'){ http_response_code(204); exit; }

/* Helpers */
function out(array $payload, int $code=200): void {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function i($v, int $def=0): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n===false? $def : $n; }
function s($v): string { return trim((string)$v); }

/* Normaliza media a auth.vallasled.com */
function media_abs_auth(string $u): string {
  $u = trim($u);
  if ($u === '') return '';
  if (preg_match('~^https?://~i', $u)) {
    if (stripos($u, 'auth.vallasled.com') !== false) return $u;
    $p = parse_url($u);
    if (!empty($p['path']) && strpos($p['path'], '/uploads/') !== false) {
      return 'https://auth.vallasled.com' . $p['path'];
    }
    return $u;
  }
  if ($u[0] !== '/') $u = '/'.$u;
  if (strpos($u, '/uploads/') !== false) return 'https://auth.vallasled.com'.$u;
  return 'https://auth.vallasled.com/uploads/'.ltrim($u, '/');
}

/* mysqli helpers */
function bind_all(mysqli_stmt $stmt, string $types, array $vals): void {
  if ($types === '') return; // no params
  $stmt->bind_param($types, ...$vals);
}

/* TZ RD */
$tz      = new DateTimeZone('America/Santo_Domingo');
$nowRD   = new DateTime('now', $tz);
$todayRD = $nowRD->format('Y-m-d');

/* Entrada */
$page         = max(1, i($_GET['page'] ?? 1));
$perPage      = i($_GET['per_page'] ?? 100); if($perPage<1)$perPage=1; if($perPage>1000)$perPage=1000;
$includeValla = i($_GET['include_valla'] ?? 1) === 1;

$q            = s($_GET['q'] ?? '');
$valla_id     = isset($_GET['valla_id']) ? i($_GET['valla_id']) : null;
$proveedor_id = isset($_GET['proveedor_id']) ? i($_GET['proveedor_id']) : null;
$cliente_id   = isset($_GET['cliente_id']) ? i($_GET['cliente_id']) : null;

/* Orden */
$sort = strtolower((string)($_GET['sort'] ?? 'orden'));
$dir  = strtolower((string)($_GET['dir']  ?? ($sort==='orden'?'asc':'desc')));
$dir  = ($dir === 'asc') ? 'ASC' : 'DESC';
$sortMap = ['orden'=>'p.orden','id'=>'p.id','inicio'=>'p.fecha_inicio','fin'=>'p.fecha_fin'];
$orderBy = $sortMap[$sort] ?? $sortMap['orden'];

/* Filtro de vigencia */
$estadoParam = strtolower(s($_GET['estado'] ?? 'active')); // active|upcoming|expired|all
if (!in_array($estadoParam, ['active','upcoming','expired','all'], true)) $estadoParam = 'active';

/* WHERE y params */
$where = [];
$types = '';
$vals  = [];

if ($valla_id !== null)     { $where[] = 'p.valla_id=?';     $types.='i'; $vals[] = $valla_id; }
if ($proveedor_id !== null) { $where[] = 'p.proveedor_id=?'; $types.='i'; $vals[] = $proveedor_id; }
if ($cliente_id !== null)   { $where[] = 'p.cliente_id=?';   $types.='i'; $vals[] = $cliente_id; }

switch ($estadoParam) {
  case 'all':
    // sin filtro de fechas
    break;
  case 'upcoming':
    $where[] = "COALESCE(p.fecha_inicio,'2099-12-31') > ?";
    $types  .= 's'; $vals[] = $todayRD;
    break;
  case 'expired':
    $where[] = "COALESCE(p.fecha_fin,'1970-01-01') < ?";
    $types  .= 's'; $vals[] = $todayRD;
    break;
  case 'active':
  default:
    $where[] = "(COALESCE(p.fecha_inicio,'1970-01-01') <= ? AND COALESCE(p.fecha_fin,'2099-12-31') >= ?)";
    $types  .= 'ss'; $vals[] = $todayRD; $vals[] = $todayRD;
    break;
}

/* JOIN + q */
$join = '';
if ($includeValla) {
  $join = "LEFT JOIN vallas v ON v.id=p.valla_id
           LEFT JOIN provincias pr ON pr.id=v.provincia_id";
  if ($q !== '') { $where[]='(v.nombre LIKE ? OR v.ubicacion LIKE ?)'; $types.='ss'; $like = '%'.$q.'%'; $vals[]=$like; $vals[]=$like; }
}

$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

/* COUNT */
$sqlCount = "SELECT COUNT(*) AS c FROM vallas_destacadas_pagos p $join $whereSql";

/* SELECT */
$selectCore = "
  p.id, p.valla_id, p.proveedor_id, p.cliente_id,
  p.fecha_inicio, p.fecha_fin, p.monto_pagado, p.fecha_pago,
  COALESCE(p.observacion,'') AS observacion, p.orden";

$selectValla = $includeValla ? ",
  v.tipo AS v_tipo, v.nombre AS v_nombre, v.ubicacion AS v_ubicacion, v.zona AS v_zona,
  v.provincia_id AS v_provincia_id, pr.nombre AS v_provincia_nombre,
  v.proveedor_id AS v_proveedor_id_orig,
  v.lat, v.lng, v.mostrar_precio_cliente, v.precio,
  v.estado_valla, v.visible_publico, v.disponible,
  v.imagen, v.imagen1, v.imagen2, v.imagen_previa, v.imagen_tercera, v.imagen_cuarta,
  v.medida, v.descripcion, v.url_stream_pantalla, v.url_stream_trafico" : "";

$sqlSel = "SELECT $selectCore $selectValla
           FROM vallas_destacadas_pagos p
           $join
           $whereSql
           ORDER BY $orderBy $dir, p.id DESC
           LIMIT ? OFFSET ?";

try {
  /* COUNT exec */
  $stc = $conn->prepare($sqlCount);
  bind_all($stc, $types, $vals);
  $stc->execute();
  $resC = $stc->get_result();
  $rowC = $resC->fetch_assoc();
  $total = (int)($rowC['c'] ?? 0);
  $stc->close();

  /* SELECT exec */
  $typesSel = $types.'ii';
  $valsSel  = array_merge($vals, [$perPage, ($page-1)*$perPage]);

  $st = $conn->prepare($sqlSel);
  bind_all($st, $typesSel, $valsSel);
  $st->execute();
  $res = $st->get_result();

  $items = [];
  while ($r = $res->fetch_assoc()) {
    $fi = !empty($r['fecha_inicio']) ? new DateTime($r['fecha_inicio'].' 00:00:00', $tz) : null;
    $ff = !empty($r['fecha_fin'])    ? new DateTime($r['fecha_fin'].' 23:59:59', $tz) : null;

    $estado = null; $empiezaEn = null; $restan = null;
    if ($fi && $ff) {
      $dHoy = new DateTime($todayRD.' 12:00:00', $tz);
      if ($dHoy < $fi)        { $estado='upcoming'; $empiezaEn=(int)$dHoy->diff($fi)->format('%a'); $restan=(int)$dHoy->diff($ff)->format('%a'); }
      elseif ($dHoy <= $ff)   { $estado='active';   $empiezaEn=0; $restan=(int)$dHoy->diff($ff)->format('%a'); }
      else                    { $estado='expired';  $empiezaEn=0; $restan=0; }
    }

    $item = [
      'id'               => (int)$r['id'],
      'valla_id'         => (int)$r['valla_id'],
      'proveedor_id'     => isset($r['proveedor_id']) ? (int)$r['proveedor_id'] : null,
      'cliente_id'       => isset($r['cliente_id'])   ? (int)$r['cliente_id']   : null,
      'publica_desde'    => $fi ? $fi->format('Y-m-d\T00:00:00P') : null,
      'publica_hasta'    => $ff ? $ff->format('Y-m-d\T23:59:59P') : null,
      'estado'           => $estado,
      'empieza_en_dias'  => $empiezaEn,
      'dias_restantes'   => $restan,
      'monto_pagado'     => is_null($r['monto_pagado']) ? null : (float)$r['monto_pagado'],
      'fecha_pago'       => $r['fecha_pago'],
      'observacion'      => $r['observacion'],
      'orden'            => (int)$r['orden'],
    ];

    if ($includeValla) {
      $media = [];
      $cols = ['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'];
      foreach ($cols as $c) {
        $raw = (string)($r[$c] ?? '');
        if ($raw === '') continue;
        $u = function_exists('media_norm') ? media_norm($raw) : $raw;
        $u = media_abs_auth($u);
        if ($u === '') continue;
        $tipo = function_exists('is_vid')
          ? (is_vid($u) ? 'video' : 'foto')
          : (preg_match('~\.(mp4|webm|mov)(\?.*)?$~i',$u) ? 'video' : 'foto');
        $media[] = ['tipo'=>$tipo, 'url'=>$u];
        if (!empty($media) && $media[0]['tipo'] === 'foto') break;
      }
      if (!$media) $media[] = ['tipo'=>'foto','url'=>'https://placehold.co/600x340/e2e8f0/475569?text=Sin+imagen'];

      $item['valla'] = [
        'tipo'                  => $r['v_tipo'] ?? null,
        'nombre'                => $r['v_nombre'] ?? null,
        'ubicacion'             => $r['v_ubicacion'] ?? null,
        'zona'                  => $r['v_zona'] ?? null,
        'provincia_id'          => isset($r['v_provincia_id']) ? (int)$r['v_provincia_id'] : null,
        'provincia'             => $r['v_provincia_nombre'] ?? '',
        'proveedor_id'          => isset($r['v_proveedor_id_orig']) ? (int)$r['v_proveedor_id_orig'] : null,
        'coords'                => [
          'lat' => isset($r['lat']) ? (float)$r['lat'] : null,
          'lng' => isset($r['lng']) ? (float)$r['lng'] : null,
        ],
        'precio'                => isset($r['precio']) ? (float)$r['precio'] : null,
        'mostrar_precio_cliente'=> isset($r['mostrar_precio_cliente']) ? (int)$r['mostrar_precio_cliente'] : null,
        'estado_valla'          => $r['estado_valla'] ?? null,
        'visible_publico'       => isset($r['visible_publico']) ? (int)$r['visible_publico'] : null,
        'disponible'            => isset($r['disponible']) ? (int)$r['disponible'] : null,
        'medida'                => $r['medida'] ?? null,
        'descripcion'           => $r['descripcion'] ?? null,
        'url_stream_pantalla'   => $r['url_stream_pantalla'] ?? '',
        'url_stream_trafico'    => $r['url_stream_trafico'] ?? '',
        'media'                 => $media,
      ];
    }

    $items[] = $item;
  }
  $st->close();

  /* ETag y cache */
  $etag = 'W/"'.sha1(json_encode([
    $total,$page,$perPage,$includeValla,$sort,$dir,$q,$todayRD,$estadoParam,$valla_id,$proveedor_id,$cliente_id
  ], JSON_UNESCAPED_UNICODE)).'"';
  header('ETag: '.$etag);
  header('Cache-Control: public, max-age=60');
  if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }

  out([
    'ok'      => true,
    'tz'      => 'America/Santo_Domingo',
    'now_rd'  => $nowRD->format('Y-m-d\TH:i:sP'),
    'page'    => $page,
    'per_page'=> $perPage,
    'total'   => $total,
    'count'   => count($items),
    'items'   => $items,
  ], 200);

} catch (\Throwable $e) {
  out(['ok'=>false,'error'=>'DB_ERROR','message'=>$e->getMessage()], 500);
}

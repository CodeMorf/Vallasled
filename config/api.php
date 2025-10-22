<?php declare(strict_types=1);
/**
 * /api/destacados/api.php
 * Lista pagos de vallas destacadas (JSON)
 */

@header('Content-Type: application/json; charset=utf-8');

/* Bootstrap */
$root = dirname(__DIR__, 2);
$confDb    = $root . '/config/db.php';
$confMedia = $root . '/config/media.php';
if (!is_file($confDb)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'MISSING_DB']); exit; }
require_once $confDb;
if (!function_exists('db')) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB_FN_MISSING']); exit; }
if (is_file($confMedia)) { require_once $confMedia; }

/* CORS */
if (isset($_SERVER['HTTP_ORIGIN'])) { header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']); header('Vary: Origin'); }
else { header('Access-Control-Allow-Origin: *'); }
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS'){ http_response_code(204); exit; }

/* Helpers */
function out(array $payload, int $code=200): void {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
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
$dir  = strtolower((string)($_GET['dir']  ?? ($sort==='orden'?'asc':'desc'))); $dir = $dir==='asc'?'ASC':'DESC';
$sortMap = ['orden'=>'p.orden','id'=>'p.id','inicio'=>'p.fecha_inicio','fin'=>'p.fecha_fin'];
$orderBy = $sortMap[$sort] ?? $sortMap['orden'];

/* Filtro de vigencia con placeholders únicos */
$estadoParam = strtolower(s($_GET['estado'] ?? 'active')); // active|upcoming|expired|all

$where  = [];
$params = [];

if($valla_id!==null){ $where[]='p.valla_id=:valla_id'; $params[':valla_id']=$valla_id; }
if($proveedor_id!==null){ $where[]='p.proveedor_id=:proveedor_id'; $params[':proveedor_id']=$proveedor_id; }
if($cliente_id!==null){ $where[]='p.cliente_id=:cliente_id'; $params[':cliente_id']=$cliente_id; }

switch ($estadoParam) {
  case 'all':
    break;
  case 'upcoming':
    $where[] = "COALESCE(p.fecha_inicio,'2099-12-31') > :today_up";
    $params[':today_up'] = $todayRD;
    break;
  case 'expired':
    $where[] = "COALESCE(p.fecha_fin,'1970-01-01') < :today_exp";
    $params[':today_exp'] = $todayRD;
    break;
  case 'active':
  default:
    $where[] = "(COALESCE(p.fecha_inicio,'1970-01-01') <= :today_le
                 AND COALESCE(p.fecha_fin,'2099-12-31') >= :today_ge)";
    $params[':today_le'] = $todayRD;
    $params[':today_ge'] = $todayRD;
    break;
}

/* JOIN + q */
$join='';
if($includeValla){
  $join="LEFT JOIN vallas v ON v.id=p.valla_id
         LEFT JOIN provincias pr ON pr.id=v.provincia_id";
  if($q!==''){ $where[]='(v.nombre LIKE :q OR v.ubicacion LIKE :q)'; $params[':q']='%'.$q.'%'; }
}

/* COUNT */
$sqlCount="SELECT COUNT(*) FROM vallas_destacadas_pagos p $join ".($where?'WHERE '.implode(' AND ',$where):'');

/* SELECT */
$selectCore="
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

$sql="SELECT $selectCore $selectValla
      FROM vallas_destacadas_pagos p
      $join
      ".($where?'WHERE '.implode(' AND ',$where):'')."
      ORDER BY $orderBy $dir, p.id DESC
      LIMIT :limit OFFSET :offset";

/* Run */
try{
  $pdo=db();

  $stc=$pdo->prepare($sqlCount);
  foreach($params as $k=>$v){ $stc->bindValue($k,$v); }
  $stc->execute();
  $total=(int)$stc->fetchColumn();

  $st=$pdo->prepare($sql);
  foreach($params as $k=>$v){ $st->bindValue($k,$v); }
  $st->bindValue(':limit',$perPage,\PDO::PARAM_INT);
  $st->bindValue(':offset',($page-1)*$perPage,\PDO::PARAM_INT);
  $st->execute();

  $items=[];
  while($r=$st->fetch(\PDO::FETCH_ASSOC)){
    $fi = !empty($r['fecha_inicio']) ? new DateTime($r['fecha_inicio'].' 00:00:00', $tz) : null;
    $ff = !empty($r['fecha_fin'])    ? new DateTime($r['fecha_fin'].' 23:59:59', $tz) : null;

    $estado = null; $empiezaEn = null; $restan = null;
    if ($fi && $ff) {
      $dHoy = new DateTime($todayRD.' 12:00:00', $tz);
      if ($dHoy < $fi)        { $estado='upcoming'; $empiezaEn=(int)$dHoy->diff($fi)->format('%a'); $restan=(int)$dHoy->diff($ff)->format('%a'); }
      elseif ($dHoy <= $ff)   { $estado='active';   $empiezaEn=0; $restan=(int)$dHoy->diff($ff)->format('%a'); }
      else                    { $estado='expired';  $empiezaEn=0; $restan=0; }
    }

    $item=[
      'id'=>(int)$r['id'],
      'valla_id'=> (int)$r['valla_id'],
      'proveedor_id'=> array_key_exists('proveedor_id',$r)? (int)$r['proveedor_id'] : null,
      'cliente_id'=>   array_key_exists('cliente_id',$r)?   (int)$r['cliente_id']   : null,
      'publica_desde'=> $fi ? $fi->format('Y-m-d\T00:00:00P') : null,
      'publica_hasta'=> $ff ? $ff->format('Y-m-d\T23:59:59P') : null,
      'estado'=>$estado,
      'empieza_en_dias'=>$empiezaEn,
      'dias_restantes'=>$restan,
      'monto_pagado'=> isset($r['monto_pagado'])? (float)$r['monto_pagado'] : null,
      'fecha_pago'=>$r['fecha_pago'] ?? null,
      'observacion'=>$r['observacion'] ?? '',
      'orden'=>(int)$r['orden'],
    ];

    if($includeValla){
      $media = [];
      $cols = ['imagen_previa','imagen','imagen1','imagen2','imagen_tercera','imagen_cuarta'];
      foreach ($cols as $c) {
        $raw = (string)($r[$c] ?? '');
        if ($raw === '') continue;
        $u = function_exists('media_norm') ? media_norm($raw) : $raw;
        $u = media_abs_auth($u);
        if ($u === '') continue;
        $tipo = function_exists('is_vid') ? (is_vid($u) ? 'video' : 'foto') : (preg_match('~\.(mp4|webm|mov)(\?.*)?$~i',$u) ? 'video' : 'foto');
        $media[] = ['tipo' => $tipo, 'url' => $u];
        if (!empty($media) && $media[0]['tipo'] === 'foto') break;
      }
      if (!$media) $media[] = ['tipo'=>'foto','url'=>'https://placehold.co/600x340/e2e8f0/475569?text=Sin+imagen'];

      $item['valla']=[
        'tipo'=>$r['v_tipo']??null,
        'nombre'=>$r['v_nombre']??null,
        'ubicacion'=>$r['v_ubicacion']??null,
        'zona'=>$r['v_zona']??null,
        'provincia_id'=> isset($r['v_provincia_id'])?(int)$r['v_provincia_id']:null,
        'provincia'=> $r['v_provincia_nombre'] ?? '',
        'proveedor_id'=> isset($r['v_proveedor_id_orig'])?(int)$r['v_proveedor_id_orig']:null,
        'coords'=>[
          'lat'=> isset($r['lat'])?(float)$r['lat']:null,
          'lng'=> isset($r['lng'])?(float)$r['lng']:null,
        ],
        'precio'=> isset($r['precio'])?(float)$r['precio']:null,
        'mostrar_precio_cliente'=> isset($r['mostrar_precio_cliente'])?(int)$r['mostrar_precio_cliente']:null,
        'estado_valla'=>$r['estado_valla']??null,
        'visible_publico'=> isset($r['visible_publico'])?(int)$r['visible_publico']:null,
        'disponible'=> isset($r['disponible'])?(int)$r['disponible']:null,
        'medida'=>$r['medida']??null,
        'descripcion'=>$r['descripcion']??null,
        'url_stream_pantalla'=> $r['url_stream_pantalla'] ?? '',
        'url_stream_trafico'=>  $r['url_stream_trafico'] ?? '',
        'media'=>$media,
      ];
    }

    $items[]=$item;
  }

  out([
    'ok'=>true,
    'tz'=>'America/Santo_Domingo',
    'now_rd'=>$nowRD->format('Y-m-d\TH:i:sP'),
    'page'=>$page,
    'per_page'=>$perPage,
    'total'=>$total,
    'count'=>count($items),
    'items'=>$items,
  ],200);

}catch(\Throwable $e){
  out(['ok'=>false,'error'=>'DB_ERROR','message'=>$e->getMessage()],500);
}

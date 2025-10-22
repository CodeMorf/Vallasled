<?php declare(strict_types=1);

/**
 * /api/destacados/api.php
 * Salida: JSON únicamente.
 * Lista TODOS los pagos de vallas destacadas sin filtrar por fechas.
 * Zona horaria fija: America/Santo_Domingo.
 *
 * GET:
 *  - include_valla=0|1      (defecto: 1)
 *  - q=texto                (sobre v.nombre/ubicacion si include_valla=1)
 *  - valla_id, proveedor_id, cliente_id
 *  - page=1..n, per_page=1..1000  (defecto: 1, 100)
 *  - sort=orden|id|inicio|fin     (defecto: orden)
 *  - dir=asc|desc                 (defecto: asc cuando sort=orden, si no desc)
 */

@header('Content-Type: text/html; charset=utf-8');

/* Bootstrap desde /config/db.php subiendo dos niveles */
$root = dirname(__DIR__, 2);
$conf = $root . '/config/db.php';
if (!is_file($conf)) { http_response_code(500); echo 'Falta /config/db.php'; exit; }
require_once $conf;
if (!function_exists('db')) { http_response_code(500); echo 'db() no disponible'; exit; }

/* CORS mínimo */
if (isset($_SERVER['HTTP_ORIGIN'])) { header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']); header('Vary: Origin'); }
else { header('Access-Control-Allow-Origin: *'); }
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }

/* Solo JSON */
function out(array $payload, int $code=200): void {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

/* TZ RD */
$tz = new DateTimeZone('America/Santo_Domingo');
$nowRD = new DateTime('now', $tz);
$todayRD = $nowRD->format('Y-m-d');

/* Entrada */
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 100); if($perPage<1)$perPage=1; if($perPage>1000)$perPage=1000;

$includeValla = (int)($_GET['include_valla'] ?? 1) === 1;

$q            = trim((string)($_GET['q'] ?? ''));
$valla_id     = isset($_GET['valla_id']) ? (int)$_GET['valla_id'] : null;
$proveedor_id = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : null;
$cliente_id   = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;

$sort = strtolower((string)($_GET['sort'] ?? 'orden'));
$dir  = strtolower((string)($_GET['dir']  ?? ($sort==='orden'?'asc':'desc'))); $dir = $dir==='asc'?'ASC':'DESC';
$sortMap = ['orden'=>'p.orden','id'=>'p.id','inicio'=>'p.fecha_inicio','fin'=>'p.fecha_fin'];
$orderBy = $sortMap[$sort] ?? $sortMap['orden'];

/* SQL sin filtro por fecha */
$where=[]; $params=[];

if($valla_id!==null){ $where[]='p.valla_id=:valla_id'; $params[':valla_id']=$valla_id; }
if($proveedor_id!==null){ $where[]='p.proveedor_id=:proveedor_id'; $params[':proveedor_id']=$proveedor_id; }
if($cliente_id!==null){ $where[]='p.cliente_id=:cliente_id'; $params[':cliente_id']=$cliente_id; }

$join='';
if($includeValla){
  $join="LEFT JOIN vallas v ON v.id=p.valla_id";
  if($q!==''){ $where[]='(v.nombre LIKE :q OR v.ubicacion LIKE :q)'; $params[':q']='%'.$q.'%'; }
}

/* COUNT */
$sqlCount="SELECT COUNT(*) FROM vallas_destacadas_pagos p $join ".($where?'WHERE '.implode(' AND ',$where):'');

/* SELECT: computa estado relativo a RD, pero NO filtra por él */
$selectCore="
  p.id, p.valla_id, p.proveedor_id, p.cliente_id,
  p.fecha_inicio, p.fecha_fin, p.monto_pagado, p.fecha_pago,
  COALESCE(p.observacion,'') AS observacion, p.orden";

$selectValla = $includeValla ? ",
  v.tipo AS v_tipo, v.nombre AS v_nombre, v.ubicacion AS v_ubicacion, v.zona AS v_zona,
  v.provincia_id AS v_provincia_id, v.proveedor_id AS v_proveedor_id_orig,
  v.lat, v.lng, v.mostrar_precio_cliente, v.precio,
  v.estado_valla, v.visible_publico, v.disponible,
  v.imagen, v.imagen1, v.imagen2, v.imagen_previa,
  v.medida, v.descripcion" : "";

$sql="SELECT $selectCore $selectValla
      FROM vallas_destacadas_pagos p
      $join
      ".($where?'WHERE '.implode(' AND ',$where):'')."
      ORDER BY $orderBy $dir, p.id DESC
      LIMIT :limit OFFSET :offset";

/* Run */
try{
  $pdo=db();

  $stc=$pdo->prepare($sqlCount); foreach($params as $k=>$v)$stc->bindValue($k,$v); $stc->execute();
  $total=(int)$stc->fetchColumn();

  $st=$pdo->prepare($sql);
  foreach($params as $k=>$v)$st->bindValue($k,$v);
  $st->bindValue(':limit',$perPage,\PDO::PARAM_INT);
  $st->bindValue(':offset',($page-1)*$perPage,\PDO::PARAM_INT);
  $st->execute();

  $items=[];
  while($r=$st->fetch(\PDO::FETCH_ASSOC)){
    /* Normalización fechas a RD con hora: si fecha_inicio/fin son DATE, asumimos 00:00:00 y 23:59:59 */
    $fi = $r['fecha_inicio'] ? new DateTime($r['fecha_inicio'].' 00:00:00', $tz) : null;
    $ff = $r['fecha_fin']    ? new DateTime($r['fecha_fin'].' 23:59:59', $tz) : null;

    $estado = null; $empiezaEn = null; $restan = null;
    if ($fi && $ff) {
      $dHoy = new DateTime($todayRD.' 12:00:00', $tz); // corte del día en RD
      if ($dHoy < $fi)        { $estado='upcoming'; $empiezaEn=(int)$dHoy->diff($fi)->format('%a'); $restan=($ff<$dHoy)?0:(int)$dHoy->diff($ff)->format('%a'); }
      elseif ($dHoy <= $ff)   { $estado='active';   $empiezaEn=0; $restan=(int)$dHoy->diff($ff)->format('%a'); }
      else                    { $estado='expired';  $empiezaEn=0; $restan=0; }
    }

    $item=[
      'id'=>(int)$r['id'],
      'valla_id'=>(int)$r['valla_id'],
      'proveedor_id'=> isset($r['proveedor_id'])?(int)$r['proveedor_id']:null,
      'cliente_id'=>   isset($r['cliente_id'])?(int)$r['cliente_id']:null,
      'publica_desde'=> $fi ? $fi->format('Y-m-d\TH:i:sP') : null,
      'publica_hasta'=> $ff ? $ff->format('Y-m-d\TH:i:sP') : null,
      'estado'=>$estado,                 // informativo, no filtra
      'empieza_en_dias'=>$empiezaEn,
      'dias_restantes'=>$restan,
      'monto_pagado'=> is_null($r['monto_pagado'])?null:(float)$r['monto_pagado'],
      'fecha_pago'=>$r['fecha_pago'],
      'observacion'=>$r['observacion'],
      'orden'=>(int)$r['orden'],
    ];

    if($includeValla){
      $imgs=[];
      foreach(['imagen','imagen1','imagen2','imagen_previa'] as $kimg){
        if(!empty($r[$kimg])){
          $imgs[] = function_exists('abs_url') ? abs_url((string)$r[$kimg]) : (string)$r[$kimg];
        }
      }
      $item['valla']=[
        'tipo'=>$r['v_tipo']??null,
        'nombre'=>$r['v_nombre']??null,
        'ubicacion'=>$r['v_ubicacion']??null,
        'zona'=>$r['v_zona']??null,
        'provincia_id'=> isset($r['v_provincia_id'])?(int)$r['v_provincia_id']:null,
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
        'imagenes'=>$imgs,
      ];
    }

    $items[]=$item;
  }

  $etag='W/"'.sha1(json_encode([$total,$page,$perPage,$includeValla,$sort,$dir,$q,$todayRD],JSON_UNESCAPED_UNICODE)).'"';
  header('ETag: '.$etag);
  header('Cache-Control: public, max-age=60');
  if(($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag){ http_response_code(304); exit; }

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
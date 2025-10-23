<?php
// /api/vallas/index.php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
only_methods(['GET','POST']);

$T = 'vallas';
if (!table_exists($conn,$T)) json_exit(['ok'=>false,'error'=>'TABLE_MISSING:vallas'],500);
$C = columns_of($conn,$T);

/* === GET: LISTADO === */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $q    = trim((string)($_GET['q'] ?? ''));
    $prov = (string)($_GET['provincia_id'] ?? '');
    $provdr = (string)($_GET['proveedor_id'] ?? '');
    $disp = (string)($_GET['disp'] ?? '');
    $pub  = (string)($_GET['publico'] ?? '');
    $ads  = (string)($_GET['ads'] ?? '');
    $page = max(1,(int)($_GET['page'] ?? 1));
    $per  = (int)($_GET['per_page'] ?? 24); if ($per<1 || $per>100) $per=24;

    $where=[]; $types=''; $bind=[];

    if ($q!=='') {
        $like='%'.$q.'%';
        if (in_array('ubicacion',$C,true)) { $where[]="(nombre LIKE ? OR ubicacion LIKE ?)"; $types.='ss'; $bind[]=$like; $bind[]=$like; }
        else { $where[]="(nombre LIKE ?)"; $types.='s'; $bind[]=$like; }
    }
    if ($prov!=='' && ctype_digit($prov) && in_array('provincia_id',$C,true)) { $where[]="provincia_id=?"; $types.='i'; $bind[]=(int)$prov; }
    if ($provdr!=='' && ctype_digit($provdr) && in_array('proveedor_id',$C,true)) { $where[]="proveedor_id=?"; $types.='i'; $bind[]=(int)$provdr; }
    if ($disp!=='' && in_array('disponible',$C,true)) { $where[]="disponible=?"; $types.='i'; $bind[]=(int)$disp; }
    if ($pub!==''  && in_array('visible_publico',$C,true)) { $where[]="visible_publico=?"; $types.='i'; $bind[]=(int)$pub; }
    if ($ads!==''  && in_array('destacado_orden',$C,true)) {
        if ($ads==='1') $where[]="destacado_orden IS NOT NULL";
        if ($ads==='0') $where[]="destacado_orden IS NULL";
    }

    $wsql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
    $order = in_array('destacado_orden',$C,true)
      ? "ORDER BY (destacado_orden IS NULL), destacado_orden ASC, id DESC"
      : "ORDER BY id DESC";

    // total
    $sqlCount="SELECT COUNT(*) c FROM `$T` $wsql";
    $st=$conn->prepare($sqlCount); if ($types) $st->bind_param($types,...$bind); $st->execute();
    $total=(int)($st->get_result()->fetch_assoc()['c'] ?? 0);

    // page
    $off = ($page-1)*$per;
    $fields = array_values(array_intersect([
      'id','nombre','tipo','proveedor_id','provincia_id','ubicacion','lat','lng','medida','precio','zona',
      'descripcion','audiencia_mensual','spot_time_seg','url_stream_pantalla','url_stream_trafico',
      'mostrar_precio_cliente','estado_valla','visible_publico','disponible','numero_licencia','fecha_vencimiento',
      'destacado_orden','created_at','updated_at'
    ], $C));
    if (!$fields) $fields=['id','nombre'];

    $sql="SELECT ".implode(',',array_map(fn($f)=>"`$f`",$fields))." FROM `$T` $wsql $order LIMIT ?,?";
    $st2=$conn->prepare($sql);
    $types2=$types.'ii'; $bind2=$bind; $bind2[]=$off; $bind2[]=$per;
    $st2->bind_param($types2, ...$bind2);
    $st2->execute(); $res=$st2->get_result();

    $items=[];
    while($r=$res->fetch_assoc()){
        foreach($r as $k=>$v){
            if (in_array($k,['id','proveedor_id','provincia_id','audiencia_mensual','spot_time_seg','mostrar_precio_cliente','visible_publico','disponible','destacado_orden'],true)) {
                $r[$k]= is_null($v)?null:(int)$v;
            } elseif ($k==='precio') {
                $r[$k]= is_null($v)?null:(float)$v;
            } elseif (in_array($k,['lat','lng'],true)) {
                $r[$k]= is_null($v)?null:(float)$v;
            }
        }
        $items[]=$r;
    }

    json_exit(['ok'=>true,'total'=>$total,'page'=>$page,'per_page'=>$per,'items'=>$items]);
}

/* === POST: CREAR === */
need_csrf_for_write();

$in = function(string $k, $def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def; };

$payload = [
  'tipo' => in_array($in('tipo','impresa'),['led','impresa'],true) ? $in('tipo','impresa') : 'impresa',
  'nombre' => $in('nombre'),
  'provincia_id' => $in('provincia_id'),
  'ubicacion' => $in('ubicacion'),
  'lat' => $in('lat'),
  'lng' => $in('lng'),
  'medida' => $in('medida'),
  'precio' => $in('precio'),
  'zona' => $in('zona'),
  'descripcion' => $in('descripcion'),
  'audiencia_mensual' => $in('audiencia_mensual','0'),
  'spot_time_seg' => $in('spot_time_seg','10'),
  'url_stream_pantalla' => $in('url_stream_pantalla'),
  'url_stream_trafico' => $in('url_stream_trafico'),
  'mostrar_precio_cliente' => $in('mostrar_precio_cliente','1'),
  'estado_valla' => $in('estado_valla','1'),
  'visible_publico' => $in('visible_publico','1'),
  'disponible' => $in('disponible','1'),
  'numero_licencia' => $in('numero_licencia'),
  'fecha_vencimiento' => $in('fecha_vencimiento'),
  'created_at' => date('Y-m-d H:i:s'),
  'updated_at' => date('Y-m-d H:i:s'),
];

$errs=[];
if (strlen($payload['nombre'])<2) $errs['nombre']='Muy corto';
if (!is_numeric($payload['provincia_id'])) $errs['provincia_id']='Inválido';
if (!is_numeric($payload['lat']) || $payload['lat']<-90 || $payload['lat']>90) $errs['lat']='Inválida';
if (!is_numeric($payload['lng']) || $payload['lng']<-180 || $payload['lng']>180) $errs['lng']='Inválida';
if (!is_numeric($payload['precio']) || (float)$payload['precio']<0) $errs['precio']='Inválido';
if ($payload['fecha_vencimiento'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$payload['fecha_vencimiento'])) $errs['fecha_vencimiento']='Formato';
if ($errs) json_exit(['ok'=>false,'error'=>'VALIDATION','fields'=>$errs],422);

$fields = array_values(array_intersect(array_keys($payload), $C));
if (!$fields) json_exit(['ok'=>false,'error'=>'NO_MATCHING_COLUMNS'],500);

$place = implode(',', array_fill(0, count($fields), '?'));
$sql = "INSERT INTO `$T` (".implode(',',array_map(fn($f)=>"`$f`",$fields)).") VALUES ($place)";
$stmt=$conn->prepare($sql);
$types=''; $vals=[];
foreach($fields as $f){
    $v=$payload[$f];
    if (is_numeric($v)) { $isFloat = strpos((string)$v,'.')!==false; $types.=($isFloat?'d':'i'); $vals[]=$isFloat?(float)$v:(int)$v; }
    else { $types.='s'; $vals[]=$v; }
}
$stmt->bind_param($types, ...$vals);
$stmt->execute();
json_exit(['ok'=>true,'valla_id'=>(int)$conn->insert_id]);

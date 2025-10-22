<?php declare(strict_types=1);
/* /api/whatsapp/api.php */
require_once __DIR__ . '/../../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== utils ===== */
function digits(string $s): string { return preg_replace('/\D+/', '', $s); }
function phone_norm(string $raw): string {
  $d = digits($raw);
  if ($d === '') return '';
  // RD: si 10 dígitos 809/829/849, antepone 1
  if (strlen($d) === 10 && preg_match('~^(809|829|849)~', $d)) return '1'.$d;
  // Si 11 y empieza en 1, usar tal cual
  if (strlen($d) === 11 && $d[0] === '1') return $d;
  return $d;
}
function render_tmpl(string $tpl, array $vars): string {
  $repl=[]; foreach($vars as $k=>$v){ $repl['{{'.$k.'}}']=(string)$v; } return strtr($tpl,$repl);
}
function jexit(array $o, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($o, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

/* ===== inputs ===== */
$act      = $_GET['a']   ?? 'chat';
$ids_str  = (string)($_GET['ids'] ?? '');
$ids      = array_values(array_unique(array_filter(array_map('intval', preg_split('~[, ]+~',$ids_str,-1,PREG_SPLIT_NO_EMPTY)))));
$force_t  = $_GET['t']   ?? '';           // 'valla' fuerza template valla
$to_param = phone_norm((string)($_GET['to'] ?? ''));
$override_msg = trim((string)($_GET['msg'] ?? ''));
$tpl_kind = $_GET['tpl'] ?? '';           // 'personal' | 'valla' | 'cart'

/* si no hay ids, intenta carrito */
if(!$ids && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) $ids = array_keys($_SESSION['cart']);

/* ===== BD ===== */
try { $pdo = db(); }
catch(\Throwable $e){ jexit(['ok'=>false,'error'=>'DB_CONN','msg'=>'No se pudo conectar a la BD'],500); }

/* ===== web_setting: lectura exacta ===== */
function web_setting(PDO $pdo, string $key, ?string $default=null): ?string {
  try{
    $st=$pdo->prepare("SELECT valor FROM web_setting WHERE clave=:k LIMIT 1");
    $st->execute([':k'=>$key]);
    $val=$st->fetchColumn();
    if($val===false || $val===null || $val==='') return $default;
    return (string)$val;
  }catch(\Throwable $e){ return $default; }
}

/* ===== datos de vallas ===== */
function fetch_vallas(PDO $pdo, array $ids): array {
  if(!$ids) return [];
  $in = implode(',', array_fill(0,count($ids),'?'));
  $sql = "SELECT id,nombre,ubicacion,medida,precio,mostrar_precio_cliente
          FROM vallas WHERE id IN ($in)";
  $st=$pdo->prepare($sql); $st->execute($ids);
  $out=[];
  while($r=$st->fetch(PDO::FETCH_ASSOC)){
    $show = (int)($r['mostrar_precio_cliente'] ?? 0) === 1;
    $out[(int)$r['id']] = [
      'id'=>(int)$r['id'],
      'nombre'=>(string)($r['nombre'] ?? ''),
      'ubicacion'=>(string)($r['ubicacion'] ?? ''),
      'medida'=>(string)($r['medida'] ?? ''),
      'precio'=>$show && isset($r['precio']) ? (float)$r['precio'] : 0.0,
    ];
  }
  return $out;
}

$map = fetch_vallas($pdo, $ids);

/* cantidades del carrito */
$qty=[]; if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])){ foreach($_SESSION['cart'] as $cid=>$q){ $qty[(int)$cid]=(int)$q; } }
foreach($ids as $id){ if(!isset($qty[$id])) $qty[$id]=1; }

/* ===== templates ===== */
$tpl_valla = (string)(web_setting($pdo,'wa_tpl_valla',"Interesado en esta valla:\nID {{id}} x{{qty}}\n{{nombre}}\n{{ubicacion}}\n{{medida}}\nPrecio: RD$ {{precio}}") ?? '');
$tpl_item  = (string)(web_setting($pdo,'wa_tpl_item', "- ID {{id}} x{{qty}} · {{nombre}}") ?? '');
$tpl_cart  = (string)(web_setting($pdo,'wa_tpl_cart', "Interesado en vallas:\n{{items}}\nTotal: RD$ {{total}}") ?? '');
$tpl_sep   = (string)(web_setting($pdo,'wa_tpl_sep',  "\n") ?? "");
$tpl_personal = (string)(web_setting($pdo,'wa_tpl_personal',
  "Hola {{nombre}}, soy de {{empresa}}.\nVi su interés en publicidad exterior en {{ciudad}}.\n¿Le comparto opciones y precios? {{asunto}}"
) ?? '');

/* ===== mensaje ===== */
$msg='';
if($override_msg!==''){
  $msg=$override_msg;
}else{
  $use_personal = (!$ids) && ($tpl_kind==='' || $tpl_kind==='personal') && $force_t!=='valla';
  if($use_personal){
    // placeholders por GET
    $vars = [
      'nombre'  => trim((string)($_GET['nombre']  ?? '')),
      'empresa' => trim((string)($_GET['empresa'] ?? '')),
      'ciudad'  => trim((string)($_GET['ciudad']  ?? '')),
      'asunto'  => trim((string)($_GET['asunto']  ?? '')),
    ];
    $msg = render_tmpl($tpl_personal, $vars);
  } else if (count($ids)===1 || $force_t==='valla' || $tpl_kind==='valla') {
    $id=$ids[0] ?? 0; $v=$map[$id] ?? ['id'=>$id,'nombre'=>'','ubicacion'=>'','medida'=>'','precio'=>0.0];
    $msg = render_tmpl($tpl_valla, [
      'id'=>$v['id'],'nombre'=>$v['nombre'],'ubicacion'=>$v['ubicacion'],'medida'=>$v['medida'],
      'precio'=> $v['precio']>0 ? number_format((float)$v['precio'],0,'.',',') : '—',
      'qty'=>$qty[$id] ?? 1,
    ]);
  } else {
    $lines=[]; $total=0.0; $any=false;
    foreach($ids as $id){
      $v=$map[$id] ?? ['id'=>$id,'nombre'=>'','ubicacion'=>'','medida'=>'','precio'=>0.0];
      $q=$qty[$id] ?? 1;
      $line = render_tmpl($tpl_item, ['id'=>$v['id'],'nombre'=>$v['nombre'],'ubicacion'=>$v['ubicacion'],'medida'=>$v['medida'],'qty'=>$q]);
      if($v['precio']>0){ $any=true; $total += $v['precio']*$q; $line .= ' · RD$ '.number_format((float)$v['precio'],0,'.',','); }
      $lines[]=$line;
    }
    $msg = render_tmpl($tpl_cart, ['items'=>implode($tpl_sep,$lines),'total'=>$any?number_format($total,0,'.',','):'—']);
  }
}

/* ===== número destino ===== */
/* Prioridad: ?to → support_whatsapp (web_setting) → fallback fijo */
$to = $to_param;
if ($to === '') {
  $to = phone_norm((string)(web_setting($pdo,'support_whatsapp','') ?? ''));
}
if ($to === '') {
  $to = '18090000000'; // último recurso
}

/* ===== link ===== */
$link = 'https://api.whatsapp.com/send?phone='.rawurlencode($to).'&text='.rawurlencode($msg);

/* ===== salidas ===== */
switch ($act) {
  case 'who':     jexit(['ok'=>true,'to'=>$to]); break;
  case 'tpl':     // devuelve templates disponibles y placeholders
    jexit([
      'ok'=>true,
      'templates'=>[
        'personal'=>$tpl_personal,
        'valla'=>$tpl_valla,
        'item'=>$tpl_item,
        'cart'=>$tpl_cart,
        'sep'=>$tpl_sep
      ],
      'placeholders_personal'=>['{{nombre}}','{{empresa}}','{{ciudad}}','{{asunto}}']
    ]);
    break;
  case 'link':
  case 'preview': jexit(['ok'=>true,'to'=>$to,'ids'=>$ids,'msg'=>$msg,'link'=>$link]); break;
  case 'chat':
    header('Cache-Control: no-store');
    header('Location: '.$link, true, 302);
    exit;
  default:        jexit(['ok'=>false,'error'=>'INVALID_ACTION','hint'=>'a=chat|link|who|preview|tpl'],400);
}

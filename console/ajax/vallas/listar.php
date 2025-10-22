<?php
// /console/ajax/vallas/listar.php
declare(strict_types=1);

@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

/* Guard */
if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

function val($key,$def=''){ return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $def; }
function i($key,$def=0){ $v = isset($_GET[$key])?(int)$_GET[$key]:$def; return max(0,$v); }

$page = max(1, i('page',1));
$per  = min(100, max(1, i('per_page',24)));
$off  = ($page-1)*$per;

$q          = val('q');
$proveedor  = val('proveedor');
$estado     = val('estado');
$publico    = val('publico','');
$ads        = val('ads','');
$tipo       = val('tipo');

$uploadsBase = 'https://auth.vallasled.com/uploads/';

$where = ['1=1'];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = "(v.titulo LIKE CONCAT('%',?,'%') OR v.nombre LIKE CONCAT('%',?,'%') OR v.ubicacion LIKE CONCAT('%',?,'%') OR v.zona LIKE CONCAT('%',?,'%'))";
  $params = array_merge($params, [$q,$q,$q,$q]); $types .= 'ssss';
}
if ($proveedor !== '') { $where[] = "p.nombre = ?"; $params[]=$proveedor; $types.='s'; }
if ($estado !== '')    { $where[] = "v.estado_valla = ?"; $params[]=$estado; $types.='s'; }
if ($publico !== '')   { $where[] = "v.visible_publico = ?"; $params[]=(int)$publico; $types.='i'; }
if ($tipo !== '')      { $where[] = "UPPER(v.tipo) = UPPER(?)"; $params[]=$tipo; $types.='s'; }
if ($ads === '1')      { $where[] = "d.valla_id IS NOT NULL"; }

$wsql = implode(' AND ', $where);

/* total */
$sqlCnt = "SELECT COUNT(DISTINCT v.id)
            FROM vallas v
            LEFT JOIN proveedores p ON p.id=v.proveedor_id
            LEFT JOIN vallas_destacadas_pagos d
              ON d.valla_id=v.id AND CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin
           WHERE $wsql";
$st = $conn->prepare($sqlCnt);
if ($types!==''){ $refs=[&$types]; foreach($params as &$v) $refs[]=&$v; call_user_func_array([$st,'bind_param'],$refs); }
$st->execute(); $total = (int)$st->get_result()->fetch_row()[0]; $st->close();

$sql = "SELECT DISTINCT
          v.id,
          COALESCE(v.titulo, v.nombre) AS titulo,
          v.zona,
          v.tipo,
          p.nombre AS proveedor,
          v.estado_valla,
          v.visible_publico,
          v.precio_mes,
          v.imagen, v.imagen1, v.imagen2, v.imagen_previa,
          (d.valla_id IS NOT NULL) AS destacado
        FROM vallas v
        LEFT JOIN proveedores p ON p.id=v.proveedor_id
        LEFT JOIN vallas_destacadas_pagos d
          ON d.valla_id=v.id AND CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin
        WHERE $wsql
        ORDER BY destacado DESC, v.id DESC
        LIMIT ? OFFSET ?";

$types2 = $types.'ii';
$params2 = $params; $params2[] = $per; $params2[] = $off;

$st = $conn->prepare($sql);
$refs=[&$types2]; foreach($params2 as &$v) $refs[]=&$v; call_user_func_array([$st,'bind_param'],$refs);
$st->execute();
$res = $st->get_result();

$items = [];
while($r = $res->fetch_assoc()){
  $img = $r['imagen'] ?: ($r['imagen_previa'] ?: ($r['imagen1'] ?: $r['imagen2']));
  if ($img) {
    if (!preg_match('~^https?://~i', $img)) $img = $uploadsBase . ltrim($img,'/');
  } else {
    $img = 'https://placehold.co/640x400/e2e8f0/718096?text=Valla';
  }
  $items[] = [
    'id'               => (int)$r['id'],
    'titulo'           => $r['titulo'] ?: '—',
    'zona'             => $r['zona'] ?: '',
    'tipo'             => $r['tipo'] ?: '',
    'proveedor'        => $r['proveedor'] ?: '',
    'estado_valla'     => $r['estado_valla'] ?: '',
    'visible_publico'  => is_null($r['visible_publico']) ? null : (int)$r['visible_publico'],
    'precio_mes'       => isset($r['precio_mes']) ? (float)$r['precio_mes'] : null,
    'img'              => $img,
    'destacado'        => (int)$r['destacado'],
  ];
}
$st->close();

$pages = $per ? (int)ceil($total/$per) : 1;

echo json_encode([
  'ok'=>true,
  'items'=>$items,
  'pagination'=>[
    'page'=>$page,'per_page'=>$per,'total'=>$total,'pages'=>$pages
  ]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

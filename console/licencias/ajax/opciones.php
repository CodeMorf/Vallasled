<?php
// /console/licencias/ajax/opciones.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

/* CSRF */
$tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ($_GET['csrf'] ?? ''));
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$tok)) {
  json_exit(['ok'=>false,'error'=>'CSRF_INVALID'], 403);
}

/* Helpers */
function likeq(string $q): string { return '%'.str_replace(['%','_'],['\\%','\\_'],$q).'%'; }
function read_pairs(mysqli $db, string $sql, string $types='', array $params=[]): array {
  $stmt = $db->prepare($sql);
  if ($types!=='') $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $out=[];
  while ($r=$res->fetch_assoc()) $out[]=['id'=>(int)$r['id'],'nombre'=>$r['nombre']];
  $stmt->close();
  return $out;
}

$q = trim((string)($_GET['q'] ?? ''));

/* Estados */
$estados = [
  ['id'=>'aprobada','nombre'=>'Aprobada'],
  ['id'=>'enviada','nombre'=>'Enviada'],
  ['id'=>'borrador','nombre'=>'Borrador'],
  ['id'=>'rechazada','nombre'=>'Rechazada'],
  ['id'=>'vencida','nombre'=>'Vencida'],
  ['id'=>'por_vencer','nombre'=>'Por Vencer'],
];

/* Proveedores */
$provSql = "SELECT id, nombre FROM proveedores WHERE ".($q!==''?"nombre LIKE ? AND ":"")."COALESCE(estado,1)=1 ORDER BY nombre ASC LIMIT 500";
$proveedores = ($q!=='') ? read_pairs($conn,"SELECT id, nombre FROM proveedores WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 500",'s',[likeq($q)])
                         : read_pairs($conn,"SELECT id, nombre FROM proveedores WHERE COALESCE(estado,1)=1 ORDER BY nombre ASC LIMIT 500");

/* Clientes (CRM) */
$clientes = ($q!=='') ? read_pairs($conn,"SELECT id, COALESCE(nombre,empresa) AS nombre FROM crm_clientes WHERE COALESCE(nombre,empresa) LIKE ? ORDER BY nombre ASC LIMIT 500",'s',[likeq($q)])
                      : read_pairs($conn,"SELECT id, COALESCE(nombre,empresa) AS nombre FROM crm_clientes ORDER BY nombre ASC LIMIT 500");

/* Vallas */
$vallas = ($q!=='') ? read_pairs($conn,"SELECT id, COALESCE(nombre, CONCAT('Valla #',id)) AS nombre FROM vallas WHERE COALESCE(nombre,'') LIKE ? OR COALESCE(zona,'') LIKE ? ORDER BY nombre ASC LIMIT 500",'ss',[likeq($q), likeq($q)])
                    : read_pairs($conn,"SELECT id, COALESCE(nombre, CONCAT('Valla #',id)) AS nombre FROM vallas ORDER BY nombre ASC LIMIT 500");

/* Sugerencias de ciudades/entidades desde licencias existentes */
$ciudades = [];
$entidades = [];
if ($q!=='') {
  $stmt = $conn->prepare("SELECT DISTINCT ciudad AS nombre, MIN(id) AS id FROM crm_licencias WHERE COALESCE(ciudad,'')<>'' AND ciudad LIKE ? GROUP BY ciudad ORDER BY ciudad ASC LIMIT 200");
  $like = likeq($q); $stmt->bind_param('s',$like); $stmt->execute();
  $r=$stmt->get_result(); while($x=$r->fetch_assoc()) $ciudades[]=['id'=>(int)$x['id'],'nombre'=>$x['nombre']]; $stmt->close();

  $stmt = $conn->prepare("SELECT DISTINCT entidad AS nombre, MIN(id) AS id FROM crm_licencias WHERE COALESCE(entidad,'')<>'' AND entidad LIKE ? GROUP BY entidad ORDER BY entidad ASC LIMIT 200");
  $stmt->bind_param('s',$like); $stmt->execute();
  $r=$stmt->get_result(); while($x=$r->fetch_assoc()) $entidades[]=['id'=>(int)$x['id'],'nombre'=>$x['nombre']]; $stmt->close();
} else {
  $rs = $conn->query("SELECT ciudad AS nombre, MIN(id) AS id FROM crm_licencias WHERE COALESCE(ciudad,'')<>'' GROUP BY ciudad ORDER BY nombre ASC LIMIT 200");
  while($x=$rs->fetch_assoc()) $ciudades[]=['id'=>(int)$x['id'],'nombre'=>$x['nombre']];
  $rs = $conn->query("SELECT entidad AS nombre, MIN(id) AS id FROM crm_licencias WHERE COALESCE(entidad,'')<>'' GROUP BY entidad ORDER BY nombre ASC LIMIT 200");
  while($x=$rs->fetch_assoc()) $entidades[]=['id'=>(int)$x['id'],'nombre'=>$x['nombre']];
}

/* Respuesta */
json_exit([
  'ok'=>true,
  'estados'=>$estados,
  'proveedores'=>$proveedores,
  'clientes'=>$clientes,
  'vallas'=>$vallas,
  'ciudades'=>$ciudades,
  'entidades'=>$entidades
]);

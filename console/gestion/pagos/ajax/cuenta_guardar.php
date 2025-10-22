<?php
require __DIR__.'/_bootstrap.php';
$in = jbody();
$id     = val_int($in['id'] ?? 0, 0);
$banco  = val_str($conn, $in['banco'] ?? '', 100);
$tit    = val_str($conn, $in['titular'] ?? '', 100);
$tipo   = $in['tipo_cuenta'] ?? '';
$num    = val_str($conn, $in['numero_cuenta'] ?? '', 50);
$activo = (int)($in['activo'] ?? 0);

if($banco==='' || $tit==='') fail('Banco y Titular requeridos',422);
if(!in_array($tipo,['Ahorros','Corriente'],true)) fail('Tipo inválido',422);
if(!preg_match('/^[0-9A-Za-z\- ]{3,50}$/', $num)) fail('Número de cuenta inválido',422);

if($id>0){
  $stmt=$conn->prepare("UPDATE datos_bancarios SET banco=?, numero_cuenta=?, tipo_cuenta=?, titular=?, activo=? WHERE id=?");
  $stmt->bind_param('ssssii',$banco,$num,$tipo,$tit,$activo,$id);
}else{
  $stmt=$conn->prepare("INSERT INTO datos_bancarios (banco,numero_cuenta,tipo_cuenta,titular,activo) VALUES (?,?,?,?,?)");
  $stmt->bind_param('ssssi',$banco,$num,$tipo,$tit,$activo);
}
if(!$stmt->execute()) fail('No se pudo guardar',500);
ok(['id'=>$id?:$stmt->insert_id]);

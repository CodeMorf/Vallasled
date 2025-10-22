<?php
require __DIR__.'/_bootstrap.php';
$in=jbody(); $id=val_int($in['id']??0,0);
if($id<=0) fail('ID inválido',422);
$stmt=$conn->prepare("DELETE FROM datos_bancarios WHERE id=?");
$stmt->bind_param('i',$id);
if(!$stmt->execute()) fail('No se pudo eliminar',500);
ok();

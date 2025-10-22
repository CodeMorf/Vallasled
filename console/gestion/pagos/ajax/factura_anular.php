<?php
require __DIR__.'/_bootstrap.php';
$in = jbody();
$id = val_int($in['id'] ?? 0, 0);
if($id<=0) fail('ID inválido',422);

/* Sin estado 'anulado' en la tabla; volvemos a 'pendiente' y limpiamos fecha_pago */
$stmt = $conn->prepare("UPDATE facturas SET estado='pendiente', fecha_pago=NULL WHERE id=?");
$stmt->bind_param('i',$id);
if(!$stmt->execute()) fail('No se pudo anular',500);
ok();

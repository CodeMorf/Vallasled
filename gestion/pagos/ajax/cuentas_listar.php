<?php
require __DIR__.'/_bootstrap.php';
$res = $conn->query("SELECT id,banco,numero_cuenta,tipo_cuenta,titular,activo FROM datos_bancarios ORDER BY id DESC");
$data=[]; if($res) while($r=$res->fetch_assoc()) $data[]=$r;
ok($data);

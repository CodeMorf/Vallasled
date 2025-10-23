<?php
// /api/provincias/index.php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
only_methods(['GET']);

$T = 'provincias';
if (!table_exists($conn,$T)) json_exit(['ok'=>false,'error'=>'TABLE_MISSING:provincias'],500);

$stmt = $conn->prepare("SELECT id,nombre FROM provincias ORDER BY nombre ASC");
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) $items[] = ['id'=>(int)$r['id'], 'nombre'=>$r['nombre']];

json_exit(['ok'=>true,'items'=>$items]);

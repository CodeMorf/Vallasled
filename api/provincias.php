<?php declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/debug.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  $st = db()->query("SELECT id, nombre FROM provincias ORDER BY nombre ASC");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // normaliza para múltiples frontends
  $out = array_map(static function(array $r): array {
    $id = (int)($r['id'] ?? 0);
    $name = (string)($r['nombre'] ?? '');
    return [
      'id'    => $id,
      'nombre'=> $name,
      'name'  => $name,   // alias
      'value' => $id,     // alias
    ];
  }, $rows);

  json_response([
    'ok'          => true,
    'count'       => count($out),
    'data'        => $out,       // lo que usa tu JS V2
    'provincias'  => $out,       // compatibilidad vieja
  ]);
} catch (Throwable $e) {
  json_response(['ok' => false, 'error' => 'DB_ERROR'], 500);
}

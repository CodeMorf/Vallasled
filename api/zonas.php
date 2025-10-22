<?php declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/debug.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$clean = static function(?string $z): string {
  $z = trim((string)$z);
  if ($z === '') return '';
  $z = preg_replace('/^[\p{So}\p{Sk}\p{Sm}\p{Cf}\s\-\•\|\.\,]+/u','',$z);
  $z = preg_replace('/\s+/u',' ',$z);
  if (function_exists('mb_convert_case')) $z = mb_convert_case($z, MB_CASE_TITLE, 'UTF-8');
  return trim($z);
};

try {
  $pid = isset($_GET['provincia']) ? (int)$_GET['provincia'] : 0;
  $pnm = isset($_GET['provincia_nombre']) ? trim((string)$_GET['provincia_nombre']) : '';

  $set = [];

  // 1) Intentar tabla zonas(nombre[, provincia_id])
  try {
    if ($pid > 0) {
      $st = db()->prepare("SELECT DISTINCT TRIM(nombre) AS nombre FROM zonas WHERE provincia_id = :pid ORDER BY nombre ASC");
      $st->execute([':pid'=>$pid]);
    } else {
      $st = db()->query("SELECT DISTINCT TRIM(nombre) AS nombre FROM zonas ORDER BY nombre ASC");
    }
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $lab = $clean($r['nombre'] ?? '');
      if ($lab !== '') $set[$lab] = true;
    }
  } catch (Throwable $e) {
    // 2) Fallback: columna zona en vallas
    if ($pid > 0) {
      $sql = "SELECT DISTINCT TRIM(zona) AS nombre
              FROM vallas
              WHERE zona IS NOT NULL AND zona <> ''
                AND visible_publico = 1
                AND estado_valla = 'activa'
                AND (provincia_id = :pid OR provincia = :pnm)
              ORDER BY zona ASC";
      $st = db()->prepare($sql);
      $st->execute([':pid'=>$pid, ':pnm'=>$pnm]);
    } else {
      $st = db()->query("SELECT DISTINCT TRIM(zona) AS nombre
                         FROM vallas
                         WHERE zona IS NOT NULL AND zona <> ''
                           AND visible_publico = 1
                           AND estado_valla = 'activa'
                         ORDER BY zona ASC");
    }
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $lab = $clean($r['nombre'] ?? '');
      if ($lab !== '') $set[$lab] = true;
    }
  }

  $zones = array_keys($set);
  sort($zones, SORT_NATURAL | SORT_FLAG_CASE);

  // Normaliza para el frontend
  $data = array_map(static function(string $name): array {
    return ['nombre'=>$name, 'name'=>$name, 'value'=>$name];
  }, $zones);

  json_response([
    'ok'    => true,
    'count' => count($data),
    'data'  => $data,   // lo que usa tu JS V2
    'zonas' => $data,   // compatibilidad
    'meta'  => ['generated_at'=>gmdate('c')]
  ]);
} catch (Throwable $e) {
  json_response(['ok'=>false,'error'=>'INTERNAL'],500);
}

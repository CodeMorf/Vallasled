<?php
// /console/gestion/pagos/ajax/_bootstrap.php
declare(strict_types=1);

/* Resolver db.php de forma robusta */
$candidates = [
  dirname(__DIR__, 3) . '/config/db.php', // /console/config/db.php
  dirname(__DIR__, 4) . '/config/db.php', // /config/db.php  (raíz del sitio)
  dirname(__DIR__, 2) . '/config/db.php',
  dirname(__DIR__)    . '/config/db.php',
];
$dbPath = null;
foreach ($candidates as $p) { if (is_file($p)) { $dbPath = $p; break; } }
if (!$dbPath) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'config/db.php no encontrado','tried'=>$candidates], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once $dbPath;                  // ahora sí carga db.php correcto
require_console_auth(['admin','staff']); // misma política que en Planes

/* Respuestas JSON por defecto (export_csv lo sobreescribe) */
header('Content-Type: application/json; charset=utf-8');

/* Helpers comunes */
function jbody(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function ok($data=[], $extra=[]){ echo json_encode(['ok'=>true,'data'=>$data]+$extra, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg='ERROR', $code=400){
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg,'msg'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function val_str(mysqli $c, ?string $s, int $max): string { $s=trim((string)$s); if($s==='') return ''; $s=mb_substr($s,0,$max); return $c->real_escape_string($s); }
function val_int($v, $d=0){ return filter_var($v, FILTER_VALIDATE_INT)!==false ? (int)$v : (int)$d; }
function val_date(?string $s): ?string { if(!$s) return null; $t=strtotime($s); return $t?date('Y-m-d',$t):null; }

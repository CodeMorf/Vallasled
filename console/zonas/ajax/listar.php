<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ob_start();
set_error_handler(function($no,$str,$file,$line){ throw new ErrorException($str, 0, $no, $file, $line); });
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();

function out(int $code, array $p){ http_response_code($code); while(ob_get_level()) ob_end_clean(); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function ok(array $p=[]){ out(200, ['ok'=>true]+$p); }
function err(int $c,string $m,$e=null){ $p=['ok'=>false,'msg'=>$m]; if($e!==null)$p['err']=$e; out($c,$p); }

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) err(401,'Auth requerida');
if (!isset($_SESSION['csrf']) || ($_SERVER['HTTP_X_CSRF'] ?? '') !== $_SESSION['csrf']) err(403,'CSRF inválido');
if (!isset($conn) || !($conn instanceof mysqli)) err(500,'DB no disponible');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$q        = trim((string)($body['q'] ?? ''));
$dupOnly  = (string)($body['dup_only'] ?? '') === '1';
$page     = max(1, (int)($body['page'] ?? 1));
$limit    = min(200, max(1, (int)($body['limit'] ?? 100)));
$offset   = ($page - 1) * $limit;

$ns = "TRIM(REGEXP_REPLACE(REGEXP_REPLACE(LOWER(z.nombre), '^[^[:alpha:]]+', ''), '[[:space:]]+', ' '))";
$where = '1'; $params=[]; $types='';

if ($q !== '') {
  $where .= " AND (($ns LIKE CONCAT('%', ?, '%')) OR (z.nombre LIKE CONCAT('%', ?, '%')))";
  $params[]=$q; $params[]=$q; $types.='ss';
}

$sql = "
SELECT z.id, z.nombre, $ns AS nombre_norm, COALESCE(cnt.vallas_count,0) AS vallas_count
FROM zonas z
LEFT JOIN (SELECT zona_id, COUNT(*) AS vallas_count FROM vallas_zonas GROUP BY zona_id) cnt ON cnt.zona_id=z.id
WHERE $where
ORDER BY z.nombre ASC
LIMIT ? OFFSET ?";

$params[]=$limit; $params[]=$offset; $types.='ii';

$stmt=$conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute(); $res=$stmt->get_result();
$items=[]; while($r=$res->fetch_assoc()) $items[]=$r; $stmt->close();

$dupeGroups=[];
$dupRes=$conn->query("SELECT $ns AS nn, COUNT(*) c FROM zonas z GROUP BY nn HAVING c>1");
while($d=$dupRes->fetch_assoc()) $dupeGroups[$d['nn']]=(int)$d['c'];

if ($dupOnly) $items = array_values(array_filter($items, fn($it)=> ($dupeGroups[$it['nombre_norm']] ?? 0) > 1));
$items = array_map(function($it) use($dupeGroups){ $it['dupe']= (($dupeGroups[$it['nombre_norm']] ?? 0) > 1)?1:0; return $it; }, $items);

$kpis = [
  'zonas' => (int)($conn->query("SELECT COUNT(*) c FROM zonas")->fetch_assoc()['c'] ?? 0),
  'grupos_duplicados' => (int)($conn->query("SELECT COUNT(*) c FROM (SELECT $ns nn, COUNT(*) c FROM zonas z GROUP BY nn HAVING c>1) x")->fetch_assoc()['c'] ?? 0),
  'vallas_asignadas' => (int)($conn->query("SELECT COUNT(*) c FROM vallas_zonas")->fetch_assoc()['c'] ?? 0),
  'vallas_sin_asignar' => (int)($conn->query("SELECT COUNT(*) c FROM vallas v LEFT JOIN vallas_zonas vz ON vz.valla_id=v.id WHERE vz.valla_id IS NULL")->fetch_assoc()['c'] ?? 0),
];

ok(['items'=>$items,'kpis'=>$kpis]);

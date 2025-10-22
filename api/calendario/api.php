<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

function ok(array $p){ echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function bad(string $msg, int $code=400){ http_response_code($code); ok(['ok'=>false,'error'=>$msg]); }

$vallaId = (int)($_GET['valla_id'] ?? 0);
if ($vallaId <= 0) bad('MISSING_VALLA_ID');

$tz = new DateTimeZone('America/Santo_Domingo');
$today = new DateTime('today', $tz);

$start = isset($_GET['start']) ? DateTime::createFromFormat('Y-m-d', $_GET['start'], $tz) : null;
$end   = isset($_GET['end'])   ? DateTime::createFromFormat('Y-m-d', $_GET['end'], $tz)   : null;

if (!$start || !$end) {
  // rango por defecto: mes actual +/- 15 días
  $start = (clone $today)->modify('first day of this month')->modify('-15 days');
  $end   = (clone $today)->modify('last day of this month')->modify('+15 days');
}
if ($end < $start) bad('INVALID_RANGE');

$pdo = db();

/* Cargar reservas confirmadas/activas para la valla */
$stR = $pdo->prepare("
  SELECT fecha_inicio, fecha_fin
  FROM reservas
  WHERE valla_id = ? AND estado IN ('confirmada','activa')
    AND fecha_fin >= ? AND fecha_inicio <= ?
  ORDER BY fecha_inicio ASC
");
$stR->execute([$vallaId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
$reservados = $stR->fetchAll(PDO::FETCH_ASSOC);

/* Cargar periodos bloqueados para la valla */
$stB = $pdo->prepare("
  SELECT fecha_inicio, fecha_fin, motivo
  FROM periodos_no_disponibles
  WHERE valla_id = ?
    AND fecha_fin >= ? AND fecha_inicio <= ?
  ORDER BY fecha_inicio ASC
");
$stB->execute([$vallaId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
$bloqueos = $stB->fetchAll(PDO::FETCH_ASSOC);

/* Fusionar intervalos NO disponibles */
function normalizeIntervals(array $arr): array {
  $out = [];
  foreach ($arr as $it) {
    $a = $it['fecha_inicio']; $b = $it['fecha_fin'];
    if (!$a || !$b) continue;
    if (empty($out)) { $out[] = ['ini'=>$a,'fin'=>$b]; continue; }
    $last = &$out[count($out)-1];
    if ($a <= $last['fin']) { // solapa o contiguo
      if ($b > $last['fin']) $last['fin'] = $b;
    } else {
      $out[] = ['ini'=>$a,'fin'=>$b];
    }
    unset($last);
  }
  // asegurar orden por fecha
  usort($out, fn($x,$y)=>strcmp($x['ini'],$y['ini']));
  return $out;
}

$merged = [];
foreach ($reservados as $r) { $merged[] = ['fecha_inicio'=>$r['fecha_inicio'],'fecha_fin'=>$r['fecha_fin']]; }
foreach ($bloqueos as $b)   { $merged[] = ['fecha_inicio'=>$b['fecha_inicio'],'fecha_fin'=>$b['fecha_fin']]; }
usort($merged, fn($x,$y)=>strcmp($x['fecha_inicio'],$y['fecha_inicio']));
$noDisp = normalizeIntervals(array_map(fn($x)=>['fecha_inicio'=>$x['fecha_inicio'],'fecha_fin'=>$x['fecha_fin']], $merged));

/* Construir calendario día a día */
$cursor = clone $start;
$days = [];
while ($cursor <= $end) {
  $d = $cursor->format('Y-m-d');
  $busy = false;
  foreach ($noDisp as $blk) {
    if ($d >= $blk['ini'] && $d <= $blk['fin']) { $busy = true; break; }
  }
  $days[] = ['date'=>$d, 'available'=> !$busy];
  $cursor->modify('+1 day');
}

/* Próximo rango disponible continuo de al menos 1 día desde hoy */
function nextAvailableRange(array $days, DateTime $from, int $minDays = 1): array {
  $start = null; $len = 0;
  foreach ($days as $d) {
    if ($d['date'] < $from->format('Y-m-d')) continue;
    if ($d['available']) {
      if ($start === null) { $start = $d['date']; $len = 1; }
      else { $len++; }
    } else {
      if ($start !== null && $len >= $minDays) return ['from'=>$start,'to'=>date('Y-m-d', strtotime($start." +".($len-1)." day"))];
      $start = null; $len = 0;
    }
  }
  if ($start !== null && $len >= $minDays)
    return ['from'=>$start,'to'=>date('Y-m-d', strtotime($start." +".($len-1)." day"))];
  return ['from'=>null,'to'=>null];
}

$next = nextAvailableRange($days, $today, 1);

/* Opcional: listar intervalos ocupados para tooltips */
$ocupados = $noDisp;

/* Also echo raw reservas y bloqueos con motivo */
ok([
  'ok' => true,
  'valla_id' => $vallaId,
  'range' => ['start'=>$start->format('Y-m-d'), 'end'=>$end->format('Y-m-d')],
  'days' => $days,
  'next_available' => $next,
  'reservas' => $reservados,
  'bloqueos' => $bloqueos
]);

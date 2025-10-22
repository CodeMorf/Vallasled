<?php declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/debug.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: max-age=120, public');

/* Sanitiza fechas YYYY-MM-DD */
$sf = static function(?string $s): string {
  $s = (string)$s;
  $s = preg_replace('/[^0-9\-]/', '', $s ?? '');
  return preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $s ?? '') ? $s : '';
};

$id   = (int)($_GET['id'] ?? 0);
$from = $sf($_GET['from'] ?? '') ?: date('Y-m-01');
$to   = $sf($_GET['to']   ?? '') ?: date('Y-m-t');
$sum  = (int)($_GET['summary'] ?? 0);

if (strtotime($from) !== false && strtotime($to) !== false && $from > $to) {
  [$from, $to] = [$to, $from];
}
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'MISSING_ID'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $pdo = db();

  /* 
   * 1) Trae rangos ocupados y los CLAMPEA al intervalo solicitado.
   * 2) Ordena y calcula running max para detectar “islas” (merge overlaps en SQL).
   * 3) Devuelve rangos ocupados no solapados dentro de [from,to].
   */
  $sql = "
    WITH raw AS (
      SELECT GREATEST(r.fecha_inicio, :from) AS s,
             LEAST(r.fecha_fin,    :to  ) AS e
      FROM reservas r
      WHERE r.valla_id = :id
        AND r.estado IN ('confirmada','activa')
        AND r.fecha_fin >= :from AND r.fecha_inicio <= :to
      UNION ALL
      SELECT GREATEST(p.fecha_inicio, :from) AS s,
             LEAST(p.fecha_fin,    :to  ) AS e
      FROM periodos_no_disponibles p
      WHERE p.valla_id = :id
        AND p.fecha_fin >= :from AND p.fecha_inicio <= :to
    ),
    norm AS (
      SELECT DATE(s) AS s, DATE(e) AS e
      FROM raw
      WHERE s <= e
    ),
    step1 AS (
      SELECT s, e,
             MAX(e) OVER (ORDER BY s, e ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS run_max_e
      FROM norm
    ),
    step2 AS (
      SELECT s, e, run_max_e,
             CASE WHEN s > LAG(run_max_e) OVER (ORDER BY s, e) THEN 1 ELSE 0 END AS is_new
      FROM step1
    ),
    step3 AS (
      SELECT s, e,
             SUM(COALESCE(is_new,1)) OVER (ORDER BY s, e ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS grp
      FROM step2
    )
    SELECT MIN(s) AS start, MAX(e) AS end
    FROM step3
    GROUP BY grp
    ORDER BY start
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id'=>$id, ':from'=>$from, ':to'=>$to]);

  $busy = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $s = substr((string)$r['start'], 0, 10);
    $e = substr((string)$r['end'],   0, 10);
    if ($s === '' || $e === '') continue;
    if ($e < $s) { [$s,$e] = [$e,$s]; }
    $busy[] = ['start'=>$s, 'end'=>$e, 'tipo'=>'ocupado'];
  }

  $is_available   = empty($busy);
  $next_free_from = null;
  $next_busy_from = null;

  if ($sum === 1) {
    // Días totales en rango
    $total_days = (int)((new DateTime($from))->diff(new DateTime($to))->format('%a')) + 1;

    // Suma días ocupados (rango cerrado) a partir de los rangos ya fusionados
    $busy_days = 0;
    foreach ($busy as $b) {
      $busy_days += (int)((new DateTime($b['start']))->diff(new DateTime($b['end']))->format('%a')) + 1;
    }
    $free_days = max(0, $total_days - $busy_days);

    // Próximo inicio ocupado después de "to"
    $sqlNext = "
      SELECT MIN(fecha_inicio) AS next_busy
      FROM (
        SELECT fecha_inicio FROM reservas WHERE valla_id=:id AND estado IN('confirmada','activa') AND fecha_inicio > :to
        UNION ALL
        SELECT fecha_inicio FROM periodos_no_disponibles WHERE valla_id=:id AND fecha_inicio > :to
      ) t
    ";
    $stNext = $pdo->prepare($sqlNext);
    $stNext->execute([':id'=>$id, ':to'=>$to]);
    $rowN = $stNext->fetch(PDO::FETCH_ASSOC);
    $next_busy_from = $rowN['next_busy'] ?? null;

    // Si no hay ocupación en el intervalo actual, libre desde from, si no, desde el día siguiente a "to"
    $next_free_from = $is_available ? $from : date('Y-m-d', strtotime($to.' +1 day'));
  }

  echo json_encode([
    'ok'              => true,
    'id'              => $id,
    'from'            => $from,
    'to'              => $to,
    'busy'            => $busy,
    'is_available'    => $is_available,
    'next_free_from'  => $next_free_from,
    'next_busy_from'  => $next_busy_from,
  ] + (
    $sum === 1 ? [
      'summary' => [
        'total_days' => $total_days ?? null,
        'busy_days'  => $busy_days ?? null,
        'free_days'  => $free_days ?? null,
        'occupancy'  => isset($busy_days,$total_days) && $total_days>0 ? round(($busy_days/$total_days)*100,2) : null
      ]
    ] : []
  ), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

<?php
// /console/licencias/ajax/kpis.php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
start_session_safe();
require_console_auth(['admin','staff']);

header('Content-Type: application/json; charset=utf-8');

$today = (new DateTime('today'))->format('Y-m-d');
$soon  = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');

$sql = "
  SELECT
    SUM(CASE WHEN (l.fecha_vencimiento < ?) OR l.estado='vencida' THEN 1 ELSE 0 END) AS vencidas,
    SUM(CASE WHEN (l.fecha_vencimiento >= ? AND l.fecha_vencimiento <= ?) AND COALESCE(l.estado,'') <> 'vencida' THEN 1 ELSE 0 END) AS por_vencer,
    SUM(CASE WHEN l.estado='borrador'  THEN 1 ELSE 0 END) AS borrador,
    SUM(CASE WHEN l.estado='enviada'   THEN 1 ELSE 0 END) AS enviada,
    SUM(CASE WHEN l.estado='rechazada' THEN 1 ELSE 0 END) AS rechazada,
    SUM(
      CASE WHEN
        ( (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento > ?) ) AND
        COALESCE(l.estado,'') NOT IN ('borrador','rechazada','vencida')
      THEN 1 ELSE 0 END
    ) AS aprobadas
  FROM crm_licencias l";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss',$today,$today,$soon,$soon);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: [];

json_exit([
  'ok'=>true,
  'today'=>$today,
  'soon'=>$soon,
  'data'=>[
    'aprobadas'  => (int)($row['aprobadas']  ?? 0),
    'por_vencer' => (int)($row['por_vencer'] ?? 0),
    'vencidas'   => (int)($row['vencidas']   ?? 0),
    'borrador'   => (int)($row['borrador']   ?? 0),
    'enviada'    => (int)($row['enviada']    ?? 0),
    'rechazada'  => (int)($row['rechazada']  ?? 0),
  ]
]);

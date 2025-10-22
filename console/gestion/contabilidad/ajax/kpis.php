<?php
// /console/gestion/contabilidad/ajax/kpis.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    $desde = $_GET['desde'] ?? '';
    $hasta = $_GET['hasta'] ?? '';

    $where = [];
    $types = '';
    $vals  = [];

    // fecha en pagos
    if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $where[] = "COALESCE(f.fecha_pago, f.fecha_generada) >= ?";
        $types  .= 's';
        $vals[]  = $desde . ' 00:00:00';
    }
    if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $where[] = "COALESCE(f.fecha_pago, f.fecha_generada) <= ?";
        $types  .= 's';
        $vals[]  = $hasta . ' 23:59:59';
    }
    $where[] = "f.estado='pagado'";
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sql = "
      SELECT
        COALESCE(SUM(f.total),0.00)                        AS ingresos_total,
        COALESCE(SUM(CASE WHEN COALESCE(f.comision_monto,0)>0 THEN f.comision_monto ELSE 0 END),0.00) AS comisiones_pagadas
      FROM facturas f
      $whereSql
    ";
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$vals); }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['ingresos_total'=>0,'comisiones_pagadas'=>0];
    $stmt->close();

    $ing = (float)$row['ingresos_total'];
    $com = (float)$row['comisiones_pagadas'];
    $egr = $com; // por ahora solo egresos de comisión

    json_exit([
        'ok'=>true,
        'ingresos_total'=>$ing,
        'egresos_total'=>$egr,
        'comisiones_pagadas'=>$com,
        'balance'=>$ing-$egr
    ]);
} catch (Throwable $e) {
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}

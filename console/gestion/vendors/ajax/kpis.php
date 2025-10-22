<?php
// /console/gestion/vendors/ajax/kpis.php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/config/db.php';
require_console_auth(['admin','staff']);
header('Content-Type: application/json; charset=utf-8');

try {
    // KPIs básicos
    $total_vendors = 0; $planes_activos = 0; $vallas_activas = 0; $monto_pendiente = 0.0;

    $q1 = $conn->query("SELECT COUNT(*) c FROM proveedores"); // incluye activos e inactivos
    $total_vendors = (int)($q1->fetch_assoc()['c'] ?? 0); $q1->close();

    $q2 = $conn->query("
      SELECT COUNT(*) c
      FROM vendor_membresias vm
      WHERE vm.plan_id IS NOT NULL
        AND (vm.estado='activa' OR (vm.fecha_fin IS NULL OR vm.fecha_fin>=CURDATE()))
    ");
    $planes_activos = (int)($q2->fetch_assoc()['c'] ?? 0); $q2->close();

    $q3 = $conn->query("SELECT SUM(v.estado_valla='activa') s FROM vallas v");
    $vallas_activas = (int)($q3->fetch_assoc()['s'] ?? 0); $q3->close();

    $q4 = $conn->query("
      SELECT COALESCE(SUM(f.monto),0) s
      FROM facturas f
      WHERE f.estado='pendiente'
    ");
    $monto_pendiente = (float)($q4->fetch_assoc()['s'] ?? 0); $q4->close();

    json_exit([
      'ok'=>true,
      'total_vendors'=>$total_vendors,
      'planes_activos'=>$planes_activos,
      'vallas_activas'=>$vallas_activas,
      'monto_pendiente'=>$monto_pendiente
    ]);
} catch (Throwable $e) {
    json_exit(['ok'=>false,'error'=>'DB_ERROR','msg'=>$e->getMessage()], 500);
}

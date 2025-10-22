<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

if (empty($_SESSION['uid']) || !in_array($_SESSION['tipo'] ?? '', ['staff','admin'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
}

$stats = [
  'vallas_total'=>0,'vallas_activas'=>0,'vallas_inactivas'=>0,
  'reservas_total'=>0,'reservas_activas'=>0,
  'fact_pagadas'=>0.0,'fact_pendientes'=>0.0,
];

try {
  // vallas
  $q1 = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(estado_valla='activa') AS act,
    SUM(estado_valla='inactiva') AS inact
    FROM vallas");
  if ($r=$q1->fetch_assoc()){
    $stats['vallas_total']     = (int)$r['total'];
    $stats['vallas_activas']   = (int)$r['act'];
    $stats['vallas_inactivas'] = (int)$r['inact'];
  }

  // reservas
  $q2 = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CURDATE() BETWEEN fecha_inicio AND fecha_fin) AS activas
    FROM reservas");
  if ($r=$q2->fetch_assoc()){
    $stats['reservas_total']   = (int)$r['total'];
    $stats['reservas_activas'] = (int)$r['activas'];
  }

  // facturación
  $q3 = $conn->query("SELECT 
    SUM(CASE WHEN estado='pagado' THEN total ELSE 0 END) AS pag,
    SUM(CASE WHEN estado='pendiente' THEN total ELSE 0 END) AS pen
    FROM facturas");
  if ($r=$q3->fetch_assoc()){
    $stats['fact_pagadas']    = (float)$r['pag'];
    $stats['fact_pendientes'] = (float)$r['pen'];
  }
} catch(Throwable $e) {}

echo json_encode(['ok'=>true,'stats'=>$stats]);

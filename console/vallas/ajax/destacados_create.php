<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_csrf']); exit;
}

$valla_id = (int)($_POST['valla_id'] ?? 0);
$f1 = trim((string)($_POST['fecha_inicio'] ?? ''));
$f2 = trim((string)($_POST['fecha_fin'] ?? ''));
if ($valla_id<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$f1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$f2)) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>'faltan_campos']); exit;
}

try{
  $stmt = $conn->prepare("INSERT INTO vallas_destacadas_pagos (valla_id, fecha_inicio, fecha_fin, monto_pagado, observacion) VALUES (?,?,?,?,?)");
  $cero = 0.0; $obs='ADS';
  $stmt->bind_param('issds', $valla_id, $f1, $f2, $cero, $obs);
  $stmt->execute();
  echo json_encode(['ok'=>true,'id'=>$stmt->insert_id]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']); }

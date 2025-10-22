<?php
// /console/vallas/ajax/gmaps-key.php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
start_session_safe();

header('Content-Type: application/json; charset=utf-8');

// Auth + AJAX
if (empty($_SESSION['uid']) || empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin','staff'], true)) {
  http_response_code(401); echo json_encode(['ok'=>false,'error'=>'UNAUTHORIZED']); exit;
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'XHR_ONLY']); exit;
}

// ENV primero
$key = getenv('GMAPS_API_KEY') ?: getenv('GOOGLE_MAPS_KEY') ?: '';

// DB fallback
if ($key === '') {
  try {
    $stmt = $conn->prepare("
      SELECT valor FROM config_global
      WHERE activo=1 AND clave IN ('gmaps_api_key','google_maps_key','google_maps_api_key','maps_api_key','gmaps_key')
      ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $key = trim((string)$row['valor']);
  } catch(Throwable $e) {}
}

if ($key === '') { echo json_encode(['ok'=>false,'error'=>'MISSING_KEY']); exit; }

echo json_encode(['ok'=>true,'key'=>$key]);
